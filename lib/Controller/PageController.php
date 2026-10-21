<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Controller;

use OCA\NexenoFreeUp\AppInfo\Application;
use OCA\NexenoFreeUp\Service\Accounts\OverviewService;
use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCA\NexenoFreeUp\Service\Settings\ApproverService;
use OCA\NexenoFreeUp\Service\Settings\FormatSettingsService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ApproverService $approvers,
		private OverviewService $overview,
		private LicenceHistoryService $licenceHistory,
		private SettingsService $settings,
		private FormatSettingsService $formats,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IInitialState $initialState,
		private LoggerInterface $logger,
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

	private function renderForbidden(string $message = 'Access to Nexeno FreeUp is restricted to system administrators and designated governance approvers.'): TemplateResponse {
		$response = new TemplateResponse('core', '403', [
			'message' => $message,
		], TemplateResponse::RENDER_AS_ERROR);
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function overview(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function inactiveAccounts(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function inactive_accounts(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function accounts(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function approvals(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function duplicates(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function duplicateDetail(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function licences(): TemplateResponse {
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function audit(): TemplateResponse {
		if (!$this->isAdmin()) {
			return $this->renderForbidden('Access to Audit Trail is restricted to system administrators.');
		}
		return $this->renderApp();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function settings(): TemplateResponse {
		if (!$this->isAdmin()) {
			return $this->renderForbidden('Access to Settings is restricted to system administrators.');
		}
		return $this->renderApp();
	}

	private function renderApp(): TemplateResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null || $userId === '') {
			return $this->renderForbidden('Please log in to access Nexeno FreeUp.');
		}

		$isAdmin = $this->isAdmin();
		$isApprover = $this->isApprover();

		if (!$isAdmin && !$isApprover) {
			return $this->renderForbidden();
		}

		Util::addStyle(Application::APP_ID, 'nexeno_freeup');
		Util::addScript(Application::APP_ID, 'nexeno_freeup');

		$overview = [];
		$licenceHistory = [];
		try {
			$overview = $this->overview->getDashboardOverview();
			$licenceHistory = $this->licenceHistory->getLicenceHistory();
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error preparing initial state: ' . $e->getMessage(), [
				'exception' => $e,
				'app' => 'nexeno_freeup',
			]);
		}

		$settings = [
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
			'mailConfigured' => $this->settings->isMailConfigured(),
			'format' => $this->formats->toArray($isAdmin),
			'isAdmin' => $isAdmin,
			'isApprover' => $isApprover,
			'currentUserId' => $userId,
		];

		try {
			$this->initialState->provideInitialState('overview', $overview);
			$this->initialState->provideInitialState('licenceHistory', $licenceHistory);
			$this->initialState->provideInitialState('settings', $settings);
		} catch (\Throwable $e) {
		}

		return new TemplateResponse(Application::APP_ID, 'main', [
			'initialOverview' => json_encode($overview, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
			'initialLicenceHistory' => json_encode($licenceHistory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
			'initialSettings' => json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
		]);
	}
}
