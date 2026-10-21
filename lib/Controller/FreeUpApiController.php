<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Controller;

use OCA\NexenoFreeUp\Service\Accounts\AccountReportService;
use OCA\NexenoFreeUp\Service\Accounts\OverviewService;
use OCA\NexenoFreeUp\Service\AuditTrailService;
use OCA\NexenoFreeUp\Service\Automation\CatchUpService;
use OCA\NexenoFreeUp\Service\Duplicates\CleanupProposalService;
use OCA\NexenoFreeUp\Service\Duplicates\DuplicateScanService;
use OCA\NexenoFreeUp\Service\Duplicates\ScanThrottle;
use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCA\NexenoFreeUp\Service\Licences\LicenceReportService;
use OCA\NexenoFreeUp\Service\Requests\ApprovalBoardService;
use OCA\NexenoFreeUp\Service\Requests\NoticePeriodService;
use OCA\NexenoFreeUp\Service\Requests\RequestWorkflowService;
use OCA\NexenoFreeUp\Service\Settings\ApproverService;
use OCA\NexenoFreeUp\Service\Settings\FormatSettingsService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCA\NexenoFreeUp\Util\LocaleFormatter;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FreeUpApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ApproverService $approvers,
		private OverviewService $overview,
		private LicenceHistoryService $licenceHistory,
		private AccountReportService $accountReport,
		private ApprovalBoardService $approvalBoard,
		private RequestWorkflowService $workflow,
		private CleanupProposalService $cleanupProposals,
		private NoticePeriodService $noticePeriod,
		private DuplicateScanService $duplicateScanner,
		private ScanThrottle $scanThrottle,
		private AuditTrailService $auditTrail,
		private SettingsService $settings,
		private LicenceReportService $reportService,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
		private FormatSettingsService $formats,
		private CatchUpService $catchUp,
		private IL10N $l,
		private ?string $userId = null,
	) {
		parent::__construct($appName, $request);
	}

	private function getCurrentUserId(): ?string {
		if ($this->userId !== null && $this->userId !== '') {
			return $this->userId;
		}
		return $this->userSession->getUser()?->getUID();
	}

	private function isAdmin(): bool {
		$uid = $this->getCurrentUserId();
		return $uid !== null && $this->groupManager->isAdmin($uid);
	}

	private function isApprover(): bool {
		$uid = $this->getCurrentUserId();
		return $uid !== null && $this->approvers->isUserApprover($uid);
	}

	private function formatTimestamp(?string $timestampStr, string $locale, string $dateStyle, string $clock): string {
		if (empty($timestampStr)) {
			return '--';
		}
		$ts = is_numeric($timestampStr) ? (int)$timestampStr : strtotime($timestampStr);
		if ($ts <= 0) {
			return '--';
		}
		return LocaleFormatter::date($ts, $locale, $dateStyle) . ', ' . LocaleFormatter::time($ts, $locale, $clock);
	}

	private function checkAdminOrApprover(): ?JSONResponse {
		$uid = $this->getCurrentUserId();
		if ($uid === null || $uid === '') {
			return $this->jsonNoCache(['error' => $this->l->t('Authentication required.')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->isAdmin() && !$this->isApprover()) {
			return $this->jsonNoCache(['error' => $this->l->t('Access denied. Administrator or Governance Approver privileges required.')], Http::STATUS_FORBIDDEN);
		}
		return null;
	}

	private function checkAdminOnly(): ?JSONResponse {
		$uid = $this->getCurrentUserId();
		if ($uid === null || $uid === '') {
			return $this->jsonNoCache(['error' => $this->l->t('Authentication required.')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->isAdmin()) {
			return $this->jsonNoCache(['error' => $this->l->t('Access denied. System Administrator privileges required.')], Http::STATUS_FORBIDDEN);
		}
		return null;
	}

	private function jsonNoCache(array $data, int $statusCode = Http::STATUS_OK): JSONResponse {
		$response = new JSONResponse($data, $statusCode);
		$response->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
		$response->addHeader('Pragma', 'no-cache');
		return $response;
	}

	/** Explains broken rules (InvalidArgumentException); anything else gets a generic message so internals are not exposed. */
	private function failure(\Throwable $e, string $generic): JSONResponse {
		if ($e instanceof \InvalidArgumentException) {
			return $this->jsonNoCache(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return $this->jsonNoCache(['error' => $generic], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getOverview(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$refresh = $this->request->getParam('refresh', 'false') === 'true';
			$overview = $this->overview->getDashboardOverview($refresh);
			$history = $this->licenceHistory->getLicenceHistory($refresh);
			return $this->jsonNoCache([
				'overview' => $overview,
				'history' => $history,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error fetching overview: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to fetch overview.'));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAccounts(
		string $search = '',
		string $status = 'all',
		string $inactivity = 'all',
		string $storage = 'all',
	): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$refresh = $this->request->getParam('refresh', 'false') === 'true';
			$filters = [
				'search' => trim($search),
				'status' => $status,
				'inactivity' => $inactivity,
				'storage' => $storage,
			];
			$accounts = $this->accountReport->getAccountsReport($filters, $refresh);
			return $this->jsonNoCache(['accounts' => $accounts]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error fetching accounts: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to fetch accounts.'));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getApprovals(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$board = $this->approvalBoard->getApprovalBoard($this->getCurrentUserId());
			return $this->jsonNoCache([
				'approvals' => $board['proposed'],
				'queued' => $board['queued'],
				'completed' => $board['completed'],
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error fetching approvals: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to fetch approvals.'));
		}
	}

	#[NoAdminRequired]
	public function proposeArchival(string $userId = '', string $reason = '', bool $reasonIsSuggestion = false): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$userId = trim($userId);
			if (empty($userId)) {
				return $this->jsonNoCache(['error' => $this->l->t('User ID is required.')], Http::STATUS_BAD_REQUEST);
			}

			$currentUserId = $this->getCurrentUserId() ?? 'system';
			// A missing or unchanged suggested reason only repeats the facts, so Recent activity omits it.
			$reasonIsSuggestion = $reasonIsSuggestion || trim($reason) === '';
			$reason = trim($reason) ?: 'Identified as inactive account by administrator';

			$req = $this->workflow->proposeArchival($userId, $reason, $currentUserId, $reasonIsSuggestion);
			return $this->jsonNoCache([
				'success' => true,
				'request' => $req->jsonSerialize(),
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error proposing archival: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	public function proposeCleanup(string $type = '', string $id = '', string $reason = '', bool $reasonIsSuggestion = false): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$currentUserId = $this->getCurrentUserId() ?? 'system';
			$reasonIsSuggestion = $reasonIsSuggestion || trim($reason) === '';
			$reason = trim($reason) ?: 'Duplicate content found in several places; keep one shared copy.';
			$req = $this->cleanupProposals->proposeCleanup(trim($type), trim($id), $reason, $currentUserId, null, true, $reasonIsSuggestion);
			return $this->jsonNoCache(['success' => true, 'request' => $req->jsonSerialize()]);
		} catch (\InvalidArgumentException $e) {
			return $this->jsonNoCache(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error proposing clean-up: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 600)]
	public function decideMany(array $requestIds = [], string $decision = '', string $notes = ''): JSONResponse {
		if (!in_array($decision, ['approve', 'reject'], true) || empty($requestIds) || count($requestIds) > 500) {
			return $this->jsonNoCache(['error' => $this->l->t('Choose between 1 and 500 requests and approve or reject.')], Http::STATUS_BAD_REQUEST);
		}
		try {
			$uid = $this->getCurrentUserId() ?? 'manager';
			$result = $this->workflow->decideMany(
				$requestIds,
				$decision,
				trim($notes),
				$uid,
				fn (int $id): bool => $this->approvers->canDecideRequest($uid, $id)
			);
			return $this->jsonNoCache(['success' => true] + $result);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error deciding requests: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 600)]
	public function proposeCleanupBulk(array $ids = [], string $reason = '', bool $reasonIsSuggestion = false): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		if (empty($ids) || count($ids) > 500) {
			return $this->jsonNoCache(['error' => $this->l->t('Choose between 1 and 500 files.')], Http::STATUS_BAD_REQUEST);
		}
		try {
			$currentUserId = $this->getCurrentUserId() ?? 'system';
			$reasonIsSuggestion = $reasonIsSuggestion || trim($reason) === '';
			$reason = trim($reason) ?: 'Duplicate content found in several places; keep one shared copy.';
			return $this->jsonNoCache(['success' => true] + $this->cleanupProposals->proposeCleanupBulk($ids, $reason, $currentUserId, $reasonIsSuggestion));
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error proposing clean-ups: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 600)]
	public function resendNotice(int $requestId = 0): JSONResponse {
		$uid = $this->getCurrentUserId();
		if ($requestId <= 0 || !$this->approvers->canDecideRequest($uid, $requestId)) {
			return $this->jsonNoCache(['error' => $this->approvers->denyMessageForRequest($requestId)], Http::STATUS_FORBIDDEN);
		}
		try {
			$result = $this->noticePeriod->resendNotice($requestId, $uid ?? 'system');
			return $this->jsonNoCache(['success' => true] + $result);
		} catch (\InvalidArgumentException $e) {
			return $this->jsonNoCache(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error resending notice: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 600)]
	public function runArchive(array $requestIds = []): JSONResponse {
		if (!$this->isApprover() && !$this->isAdmin()) {
			return $this->jsonNoCache(['error' => $this->l->t('You are not allowed to process approved requests.')], Http::STATUS_FORBIDDEN);
		}
		try {
			$uid = $this->getCurrentUserId() ?? 'system';
			$ids = null;
			if (!empty($requestIds)) {
				$ids = array_values(array_filter(array_map('intval', $requestIds), fn ($id) => $this->approvers->canDecideRequest($uid, $id)));
			}
			$result = $this->workflow->runApprovedArchives($uid, $ids);
			return $this->jsonNoCache(['success' => true] + $result);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error running archive: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	public function approveArchival(int $requestId = 0, string $notes = ''): JSONResponse {
		try {
			if ($requestId <= 0) {
				return $this->jsonNoCache(['error' => $this->l->t('Valid request ID is required.')], Http::STATUS_BAD_REQUEST);
			}

			$currentUserId = $this->getCurrentUserId();
			if (!$this->approvers->canDecideRequest($currentUserId, $requestId)) {
				return $this->jsonNoCache(['error' => $this->approvers->denyMessageForRequest($requestId)], Http::STATUS_FORBIDDEN);
			}

			$req = $this->workflow->approveArchival($requestId, trim($notes), $currentUserId ?? 'manager');

			return $this->jsonNoCache([
				'success' => true,
				'request' => $req->jsonSerialize(),
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error approving archival: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	public function rejectProposal(int $requestId = 0, string $notes = ''): JSONResponse {
		try {
			if ($requestId <= 0) {
				return $this->jsonNoCache(['error' => $this->l->t('Valid request ID is required.')], Http::STATUS_BAD_REQUEST);
			}

			$currentUserId = $this->getCurrentUserId();
			if (!$this->approvers->canDecideRequest($currentUserId, $requestId)) {
				return $this->jsonNoCache(['error' => $this->approvers->denyMessageForRequest($requestId)], Http::STATUS_FORBIDDEN);
			}

			$req = $this->workflow->rejectProposal($requestId, trim($notes), $currentUserId ?? 'manager');

			return $this->jsonNoCache([
				'success' => true,
				'request' => $req->jsonSerialize(),
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error rejecting proposal: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	public function restoreUser(string $userId = ''): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		try {
			$userId = trim($userId);
			if (empty($userId)) {
				return $this->jsonNoCache(['error' => $this->l->t('User ID is required.')], Http::STATUS_BAD_REQUEST);
			}

			$currentUserId = $this->getCurrentUserId() ?? 'admin';
			$told = $this->workflow->restoreUser($userId, $currentUserId);

			return $this->jsonNoCache([
				'success' => true,
				'emailed' => (bool)($told['emailed'] ?? false),
				'emailProblem' => $told['emailProblem'] ?? null,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error restoring user: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Something went wrong. Please try again.'));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDuplicates(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$duplicates = $this->duplicateScanner->getDuplicateFiles();
			$lastScan = $this->duplicateScanner->getLastDuplicateScan();
			return $this->jsonNoCache([
				'duplicates' => $duplicates,
				'cleanupStatus' => $this->cleanupProposals->getCleanupStatusMap(),
				'lastScan' => $lastScan,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error fetching duplicates: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to fetch duplicates.'));
		}
	}

	#[NoAdminRequired]
	public function scanDuplicates(): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		// Our own limit instead of #[UserRateLimit], so the refusal can say when the next scan is allowed.
		$uid = (string)$this->userId;
		$retryAt = $this->scanThrottle->blockedUntil($uid);
		if ($retryAt !== null) {
			return $this->jsonNoCache([
				'error' => $this->l->t('Too many scans in a short time.'),
				'retryAt' => $retryAt,
			], Http::STATUS_TOO_MANY_REQUESTS);
		}
		try {
			$minSizeKb = max(0, min(1048576, (int)$this->request->getParam('minSizeKb', 0)));
			$minBytes = ($minSizeKb <= 0) ? 1 : ($minSizeKb * 1024);
			return $this->jsonNoCache([
				'success' => true,
				'scan' => $this->recordScan($uid, $this->duplicateScanner->queueDuplicateScan($minBytes, $uid)),
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error queueing duplicate scan: ' . $e->getMessage(), ['exception' => $e]);
			return $this->jsonNoCache(['error' => $this->l->t('The scan could not be started.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/** Counts a scan against the limit only when this request actually queued one. */
	private function recordScan(string $uid, array $scan): array {
		if (($scan['state'] ?? '') === 'queued' && (int)($scan['startedAt'] ?? 0) >= time() - 5) {
			$this->scanThrottle->record($uid);
		}
		return $scan;
	}

	#[NoAdminRequired]
	// Backstop against abuse; scanDuplicates queued the scan and carries the real limit.
	#[UserRateLimit(limit: 20, period: 600)]
	public function scanRunNow(): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		// The scan keeps going even if the browser stops waiting; progress is read from the status call.
		ignore_user_abort(true);
		@set_time_limit(0);
		try {
			return $this->jsonNoCache(['success' => true, 'scan' => $this->duplicateScanner->startQueuedScanNow()]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error running duplicate scan: ' . $e->getMessage(), ['exception' => $e]);
			return $this->jsonNoCache(['error' => $this->l->t('The scan could not be started.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function scanStatus(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		return $this->jsonNoCache([
			'scan' => $this->duplicateScanner->getDuplicateScanStatus(),
			'lastScan' => $this->duplicateScanner->getLastDuplicateScan(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getLicenceHistory(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$refresh = $this->request->getParam('refresh', 'false') === 'true';
			$history = $this->licenceHistory->getLicenceHistory($refresh);
			return $this->jsonNoCache(['history' => $history]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error fetching licence history: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to fetch licence history.'));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAuditLog(): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		try {
			$limit = (int)$this->request->getParam('limit', 500);
			if ($limit <= 0 || $limit > 2000) {
				$limit = 500;
			}
			$audit = $this->auditTrail->getAuditLog($limit);
			return $this->jsonNoCache(['audit' => $audit]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error fetching audit log: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to fetch audit log.'));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSettings(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		$currentUserId = $this->getCurrentUserId();
		return $this->jsonNoCache([
			'settings' => [
				'inactivityMonths' => $this->settings->getInactivityThresholdMonths(),
				'exemptGroups' => $this->settings->getExemptGroups(),
				'approverGroups' => $this->settings->getApproverGroups(),
				'cleanupApproverGroups' => $this->settings->getCleanupApproverGroups(),
				'exemptUsers' => $this->settings->getExemptUsers(),
				'approverUsers' => $this->settings->getApproverUsers(),
				'cleanupApproverUsers' => $this->settings->getCleanupApproverUsers(),
				'userNames' => (object)$this->settings->getSettingsUserNames(),
				'auditRetentionMonths' => $this->settings->getAuditRetentionMonths(),
				'noticeEnabled' => $this->settings->isNoticeEnabled(),
				'noticeDays' => $this->settings->getNoticeDays(),
				'limits' => $this->settings->getSettingLimits(),
				'financeReportEnabled' => $this->settings->isFinanceReportEnabled(),
				'financeReportEmail' => $this->settings->getFinanceReportEmail(),
				'financeReportLastMonth' => $this->settings->getFinanceReportLastSent(),
				'format' => $this->formats->toArray($this->isAdmin()),
				'mailConfigured' => $this->settings->isMailConfigured(),
				'isAdmin' => $this->isAdmin(),
				'isApprover' => $this->isApprover(),
				'currentUserId' => $currentUserId,
			]
		]);
	}

	#[NoAdminRequired]
	public function saveSettings(int $inactivityMonths = 6, array $exemptGroups = [], array $approverGroups = [], array $cleanupApproverGroups = [], int $auditRetentionMonths = 12, bool $noticeEnabled = true, int $noticeDays = 14, array $exemptUsers = [], array $approverUsers = [], array $cleanupApproverUsers = [], bool $financeReportEnabled = false, string $financeReportEmail = '', ?string $formatMode = null, ?string $formatLocale = null, ?string $formatDateStyle = null, ?string $formatClock = null): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		try {
			// Checked first, so a wrong value stops the whole save instead of saving half of it.
			$this->settings->setFinanceReport($financeReportEnabled, $financeReportEmail);
			$this->formats->save($formatMode, $formatLocale, $formatDateStyle, $formatClock);
			$this->settings->setInactivityThresholdMonths($inactivityMonths);
			$this->settings->setExemptGroups(array_slice(array_map('strval', $exemptGroups), 0, 200));
			$this->settings->setExemptUsers($exemptUsers);
			$this->settings->setApproverUsers($approverUsers);
			$this->settings->setCleanupApproverUsers($cleanupApproverUsers);
			if (!empty($approverGroups)) {
				$this->settings->setApproverGroups($approverGroups);
			}
			$this->settings->setCleanupApproverGroups($cleanupApproverGroups);
			$this->settings->setAuditRetentionMonths($auditRetentionMonths);
			$this->settings->setNotice($noticeEnabled, $noticeDays);

			return $this->jsonNoCache([
				'success' => true,
				'settings' => [
					'inactivityMonths' => $this->settings->getInactivityThresholdMonths(),
					'exemptGroups' => $this->settings->getExemptGroups(),
					'approverGroups' => $this->settings->getApproverGroups(),
					'cleanupApproverGroups' => $this->settings->getCleanupApproverGroups(),
					'exemptUsers' => $this->settings->getExemptUsers(),
					'approverUsers' => $this->settings->getApproverUsers(),
					'cleanupApproverUsers' => $this->settings->getCleanupApproverUsers(),
					'userNames' => (object)$this->settings->getSettingsUserNames(),
					'auditRetentionMonths' => $this->settings->getAuditRetentionMonths(),
					'noticeEnabled' => $this->settings->isNoticeEnabled(),
					'noticeDays' => $this->settings->getNoticeDays(),
					'limits' => $this->settings->getSettingLimits(),
					'financeReportEnabled' => $this->settings->isFinanceReportEnabled(),
					'financeReportEmail' => $this->settings->getFinanceReportEmail(),
					'financeReportLastMonth' => $this->settings->getFinanceReportLastSent(),
					'format' => $this->formats->toArray(true),
				]
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error saving settings: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Failed to save settings.'));
		}
	}

	/**
	 * Called when an approver has FreeUp open: does overdue work if a background job is late.
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 600)]
	public function runCatchUp(): JSONResponse {
		if ($err = $this->checkAdminOrApprover()) {
			return $err;
		}
		try {
			$done = $this->catchUp->catchUp();
			return $this->jsonNoCache($done);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Catch-up failed: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('Automatic processing could not be checked.'));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getFormatLocales(): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		return $this->jsonNoCache(['locales' => $this->formats->listLocales()]);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 5, period: 600)]
	public function sendReportNow(): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		try {
			$user = $this->userSession->getUser();
			$result = $this->reportService->sendNow((string)$this->userId, $user?->getDisplayName() ?: (string)$this->userId);
			if ($result['sent']) {
				return $this->jsonNoCache(['success' => true, 'to' => $result['to'], 'lastSent' => $this->settings->getFinanceReportLastSent()]);
			}
			$message = match ($result['problem']) {
				'no_address' => $this->l->t('Save a valid finance contact email address first.'),
				'no_mail_server' => $this->l->t('This Nextcloud has no email server set up, so the report cannot be sent.'),
				'not_delivered' => $this->l->t('The mail server did not accept the report. Check the address and the server settings.'),
				default => $this->l->t('The report could not be sent. Details are in the Nextcloud log.'),
			};
			return $this->jsonNoCache(['error' => $message], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error sending the licence report: ' . $e->getMessage(), ['exception' => $e]);
			return $this->failure($e, $this->l->t('The report could not be sent.'));
		}
	}

	/** Stops Excel/Sheets from running values that start with = + - @ as formulas. */
	private function csvCell(mixed $v): string|int|float {
		if (is_int($v) || is_float($v)) {
			return $v;
		}
		$v = (string)($v ?? '');
		return $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
	}

	private function auditEventLabel(string $action, bool $isFile): string {
		$labels = [
			'propose_archival' => [$this->l->t('Removal proposed')], 'approve_archival' => [$this->l->t('Removal approved'), $this->l->t('Clean-up approved')],
			'reject_proposal' => [$this->l->t('Removal dismissed'), $this->l->t('Clean-up dismissed')], 'notice_sent' => [$this->l->t('User notified'), $this->l->t('Owners notified')],
			'request_obsolete' => [$this->l->t('Request closed'), $this->l->t('Clean-up closed')],
			'notice_skipped' => [$this->l->t('Disabled before the waiting period ended'), $this->l->t('Clean-up run before the waiting period ended')],
			'archive_cancelled' => [$this->l->t('Disabling cancelled')], 'archive_started' => [$this->l->t('Disabling started')],
			'archive_completed' => [$this->l->t('Account disabled')], 'restore_user' => [$this->l->t('Account restored')],
			'propose_cleanup' => [$this->l->t('Clean-up proposed')], 'cleanup_completed' => [$this->l->t('Clean-up done')],
			'scan_duplicates' => [$this->l->t('Scan completed')], 'licence_report_sent' => [$this->l->t('Licence report sent')],
		];
		$names = $labels[$action] ?? [ucfirst(str_replace('_', ' ', $action))];
		return $isFile && isset($names[1]) ? $names[1] : $names[0];
	}

	private function csvRow($out, array $cells): void {
		fputcsv($out, array_map(fn ($c) => $this->csvCell($c), $cells), ',', '"', '\\');
	}

	private function csvStatus(string $status): string {
		return match ($status) {
			'candidate' => $this->l->t('Inactive'),
			'proposed' => $this->l->t('Pending approval'),
			'approved' => $this->l->t('Approved'),
			'archived' => $this->l->t('Disabled'),
			'disabled' => $this->l->t('Disabled'),
			'exempt' => $this->l->t('Protected'),
			'rejected' => $this->l->t('Kept'),
			'active' => $this->l->t('Active'),
			default => ucfirst(str_replace('_', ' ', $status)),
		};
	}

	private function csvPath(string $p): string {
		return str_starts_with($p, 'files/') ? substr($p, 6) : $p;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 200, period: 600)]
	public function searchPrincipals(string $search = ''): JSONResponse {
		if ($err = $this->checkAdminOnly()) {
			return $err;
		}
		return $this->jsonNoCache(['results' => $this->settings->searchPrincipals(mb_substr($search, 0, 100))]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 20, period: 600)]
	public function exportReport(string $type = 'accounts'): DataDisplayResponse {
		if (!$this->isAdmin() && !$this->isApprover()) {
			return new DataDisplayResponse('Access denied. Administrator or Governance Approver privileges required.', Http::STATUS_FORBIDDEN);
		}
		if ($type === 'audit' && !$this->isAdmin()) {
			return new DataDisplayResponse('Access denied. Only administrators can export audit logs.', Http::STATUS_FORBIDDEN);
		}
		if (!in_array($type, ['accounts', 'approvals', 'duplicates', 'licences', 'audit'], true)) {
			return new DataDisplayResponse('Unknown report.', Http::STATUS_BAD_REQUEST);
		}

		// Downloader's Nextcloud locale (Personal info, or the server's).
		$locale = $this->formats->getLocaleFor($this->userSession->getUser());
		$dateStyle = $this->formats->getDateStyle();
		$clock = $this->formats->getClock();
		$filename = 'nexeno-freeup-' . $type . '-' . date('Y-m-d') . '.csv';
		$out = fopen('php://temp', 'r+');
		$row = fn (array $cells) => $this->csvRow($out, $cells);

		if ($type === 'accounts') {
			$row([$this->l->t('Account'), $this->l->t('Name'), $this->l->t('Email'), $this->l->t('Created at'), $this->l->t('Groups'), $this->l->t('Last login'), $this->l->t('Months inactive'), $this->l->t('Storage used'), $this->l->t('Quota'), $this->l->t('Files'), $this->l->t('Status')]);
			foreach ($this->accountReport->getAccountsReport([], true) as $a) {
				$row([
					$a['userId'],
					$a['displayName'],
					$a['email'] ?? '',
					($a['createdAt'] ?? 0) > 0 ? LocaleFormatter::date((int)$a['createdAt'], $locale, $dateStyle) : '',
					implode(', ', $a['groups'] ?? []),
					($a['lastLogin'] ?? 0) > 0 ? LocaleFormatter::date((int)$a['lastLogin'], $locale, $dateStyle) . ', ' . LocaleFormatter::time((int)$a['lastLogin'], $locale, $clock) : '',
					(int)round((float)($a['inactiveMonths'] ?? 0)),
					LocaleFormatter::bytes((int)($a['storageBytes'] ?? 0), $locale),
					((int)($a['quotaBytes'] ?? -1)) < 0 ? $this->l->t('No storage limit') : LocaleFormatter::bytes((int)$a['quotaBytes'], $locale),
					(int)($a['fileCount'] ?? 0),
					// Same as the Storage by account page: an account stays inactive until it is actually disabled.
					$this->csvStatus(in_array($a['status'] ?? '', ['proposed', 'approved', 'archiving'], true) ? 'candidate' : (string)($a['status'] ?? '')),
				]);
			}
		} elseif ($type === 'approvals') {
			$row([$this->l->t('Request'), $this->l->t('Account'), $this->l->t('Name'), $this->l->t('Email'), $this->l->t('Storage used'), $this->l->t('Proposed by'), $this->l->t('Proposed on'), $this->l->t('Reason'), $this->l->t('Status')]);
			foreach ($this->approvalBoard->getPendingApprovals() as $ap) {
				$row([
					$ap['id'],
					$ap['userId'],
					$ap['displayName'],
					$ap['email'] ?? '',
					LocaleFormatter::bytes((int)($ap['storageBytes'] ?? 0), $locale),
					$ap['proposedBy'] ?? '',
					$this->formatTimestamp($ap['proposedAt'] ?? null, $locale, $dateStyle, $clock),
					$ap['proposalReason'] ?? '',
					$this->csvStatus((string)($ap['status'] ?? '')),
				]);
			}
		} elseif ($type === 'duplicates') {
			$row([$this->l->t('File name'), $this->l->t('Copies'), $this->l->t('Size of each copy'), $this->l->t('Space that could be freed'), $this->l->t('Found in')]);
			foreach ($this->duplicateScanner->getDuplicateFiles() as $d) {
				$where = implode('; ', array_map(
					fn ($i) => ($i['userId'] ?? '?') . ': ' . $this->csvPath($i['path'] ?? ($i['rawPath'] ?? '')),
					$d['instances'] ?? []
				));
				$row([
					$d['fileName'] ?? '',
					$d['occurrences'] ?? 2,
					LocaleFormatter::bytes((int)($d['size'] ?? 0), $locale),
					LocaleFormatter::bytes((int)($d['wastedBytes'] ?? 0), $locale),
					$where,
				]);
			}
		} elseif ($type === 'licences') {
			$row([$this->l->t('Month'), $this->l->t('Licences in use'), $this->l->t('Logged in during the month'), $this->l->t('No login')]);
			foreach (array_reverse($this->licenceHistory->getLicenceHistory()) as $h) {
				$row([LocaleFormatter::monthYear(strtotime(($h['month'] ?? date('Y-m')) . '-01'), $locale), $h['licencesInUse'] ?? 0, $h['loggedIn'] ?? '', $h['notUsed'] ?? '']);
			}
		} else {
			$row([$this->l->t('Date'), $this->l->t('Event'), $this->l->t('Account'), $this->l->t('File name'), $this->l->t('Done by'), $this->l->t('Details')]);
			foreach ($this->auditTrail->getAuditLog(500) as $e) {
				$row([
					$this->formatTimestamp($e['createdAt'] ?? null, $locale, $dateStyle, $clock),
					$this->auditEventLabel((string)($e['action'] ?? ''), !empty($e['targetName'])),
					$e['targetUser'] ?? '',
					$e['targetName'] ?? '',
					$e['actorName'] ?? ($e['actorId'] ?? ''),
					$e['details'] ?? '',
				]);
			}
		}

		rewind($out);
		// UTF-8 byte-order mark so Excel opens accents and non-Latin names correctly.
		$csvData = "\xEF\xBB\xBF" . stream_get_contents($out);
		fclose($out);

		return new DataDisplayResponse($csvData, Http::STATUS_OK, [
			'Content-Type' => 'text/csv; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
		]);
	}
}
