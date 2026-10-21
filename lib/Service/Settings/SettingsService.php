<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Settings;

use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Mail\IEmailValidator;

class SettingsService {
	public const DEFAULT_INACTIVITY_MONTHS = 6;

	/** Allowed range of every numeric setting; the Settings page reads these. */
	public const INACTIVITY_CHOICES = [4, 6, 8, 12, 24];
	public const LIMIT_RETENTION_MONTHS = [1, 24];
	public const LIMIT_NOTICE_DAYS = [1, 90];

	public function __construct(
		private IL10N $l,
		private IConfig $config,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private StatsCache $statsCache,
		private ?IEmailValidator $emailValidator = null,
	) {
	}

	/** Stored values outside INACTIVITY_CHOICES move to the nearest choice. */
	private function snapInactivityMonths(int $months): int {
		$best = self::INACTIVITY_CHOICES[0];
		foreach (self::INACTIVITY_CHOICES as $choice) {
			if (abs($choice - $months) <= abs($best - $months)) {
				$best = $choice;
			}
		}
		return $best;
	}

	public function getInactivityThresholdMonths(): int {
		return $this->snapInactivityMonths((int)$this->config->getAppValue('nexeno_freeup', 'inactivity_months', (string)self::DEFAULT_INACTIVITY_MONTHS));
	}

	public function setInactivityThresholdMonths(int $months): void {
		$this->config->setAppValue('nexeno_freeup', 'inactivity_months', (string)$this->snapInactivityMonths($months));
		$this->statsCache->clear();
	}

	public function getExemptGroups(): array {
		$val = $this->config->getAppValue('nexeno_freeup', 'exempt_groups', 'admin');
		return array_filter(array_map('trim', explode(',', $val)));
	}

	public function setExemptGroups(array $groups): void {
		$this->config->setAppValue('nexeno_freeup', 'exempt_groups', implode(',', array_filter(array_map('trim', $groups))));
		$this->statsCache->clear();
	}

	public function getApproverGroups(): array {
		$val = $this->config->getAppValue('nexeno_freeup', 'approver_groups', 'admin');
		$groups = array_filter(array_map('trim', explode(',', $val)));
		return !empty($groups) ? array_values($groups) : ['admin'];
	}

	public function setApproverGroups(array $groups): void {
		$clean = array_values(array_filter(array_map('trim', $groups)));
		if (empty($clean)) {
			$clean = ['admin'];
		}
		$this->config->setAppValue('nexeno_freeup', 'approver_groups', implode(',', $clean));
		$this->statsCache->clear();
	}

	/** Falls back to the account approvers until clean-up approvers are chosen. */
	public function getCleanupApproverGroups(): array {
		$val = $this->config->getAppValue('nexeno_freeup', 'cleanup_approver_groups', '');
		$groups = array_values(array_filter(array_map('trim', explode(',', $val))));
		if (!empty($groups) || !empty($this->readUserList('cleanup_approver_users'))) {
			return $groups;
		}
		return $this->getApproverGroups();
	}

	public function setCleanupApproverGroups(array $groups): void {
		$clean = array_values(array_filter(array_map('trim', $groups)));
		$this->config->setAppValue('nexeno_freeup', 'cleanup_approver_groups', implode(',', $clean));
		$this->statsCache->clear();
	}

	/** @return string[] */
	private function readUserList(string $key): array {
		$val = $this->config->getAppValue('nexeno_freeup', $key, '');
		return array_values(array_unique(array_filter(array_map('trim', explode(',', $val)))));
	}

	/**
	 * Drops accounts that no longer exist.
	 *
	 * @param string[] $userIds
	 */
	private function writeUserList(string $key, array $userIds): void {
		$clean = [];
		foreach (array_slice($userIds, 0, 200) as $uid) {
			$uid = trim((string)$uid);
			if ($uid !== '' && $this->userManager->userExists($uid)) {
				$clean[$uid] = true;
			}
		}
		$this->config->setAppValue('nexeno_freeup', $key, implode(',', array_keys($clean)));
		$this->statsCache->clear();
	}

	public function getExemptUsers(): array {
		return $this->readUserList('exempt_users');
	}

	public function setExemptUsers(array $userIds): void {
		$this->writeUserList('exempt_users', $userIds);
	}

	public function getApproverUsers(): array {
		return $this->readUserList('approver_users');
	}

	public function setApproverUsers(array $userIds): void {
		$this->writeUserList('approver_users', $userIds);
	}

	/** Account approvers while nobody is chosen for clean-ups. */
	public function getCleanupApproverUsers(): array {
		$own = $this->readUserList('cleanup_approver_users');
		if (!empty($own) || !empty(array_filter(array_map('trim', explode(',', $this->config->getAppValue('nexeno_freeup', 'cleanup_approver_groups', '')))))) {
			return $own;
		}
		return $this->getApproverUsers();
	}

	public function setCleanupApproverUsers(array $userIds): void {
		$this->writeUserList('cleanup_approver_users', $userIds);
	}

	/** @return array<string, string> account id => display name */
	public function getSettingsUserNames(): array {
		$names = [];
		foreach (array_unique(array_merge($this->getExemptUsers(), $this->getApproverUsers(), $this->getCleanupApproverUsers())) as $uid) {
			$user = $this->userManager->get($uid);
			if ($user !== null) {
				$names[$uid] = $user->getDisplayName() ?: $uid;
			}
		}
		return $names;
	}

	/** @return array<int, array{type:string,id:string,label:string,subname:string}> */
	public function searchPrincipals(string $search): array {
		$search = trim($search);
		$out = [];
		foreach ($this->userManager->searchDisplayName($search, 10) as $user) {
			$out[] = ['type' => 'user', 'id' => $user->getUID(), 'label' => $user->getDisplayName() ?: $user->getUID(), 'subname' => (string)$user->getSystemEMailAddress()];
		}
		foreach ($this->groupManager->search($search, 10) as $group) {
			$out[] = ['type' => 'group', 'id' => $group->getGID(), 'label' => $group->getDisplayName(), 'subname' => ''];
		}
		return $out;
	}

	public function getAuditRetentionMonths(): int {
		$days = (int)$this->config->getAppValue('nexeno_freeup', 'audit_retention_days', '365');
		return max(self::LIMIT_RETENTION_MONTHS[0], min(self::LIMIT_RETENTION_MONTHS[1], (int)round($days / 30.4167)));
	}

	public function setAuditRetentionMonths(int $months): void {
		$months = max(self::LIMIT_RETENTION_MONTHS[0], min(self::LIMIT_RETENTION_MONTHS[1], $months));
		$this->config->setAppValue('nexeno_freeup', 'audit_retention_days', (string)(int)round($months * 30.4167));
	}

	public function isMailConfigured(): bool {
		return $this->config->getSystemValueString('mail_from_address', '') !== ''
			&& $this->config->getSystemValueString('mail_domain', '') !== '';
	}

	/** @return array<string, array{min:int,max:int,choices?:int[]}> */
	public function getSettingLimits(): array {
		return [
			'inactivityMonths' => ['min' => min(self::INACTIVITY_CHOICES), 'max' => max(self::INACTIVITY_CHOICES), 'choices' => self::INACTIVITY_CHOICES],
			'auditRetentionMonths' => ['min' => self::LIMIT_RETENTION_MONTHS[0], 'max' => self::LIMIT_RETENTION_MONTHS[1]],
			'noticeDays' => ['min' => self::LIMIT_NOTICE_DAYS[0], 'max' => self::LIMIT_NOTICE_DAYS[1]],
		];
	}

	public function isFinanceReportEnabled(): bool {
		return $this->config->getAppValue('nexeno_freeup', 'finance_report_enabled', 'no') === 'yes';
	}

	public function getFinanceReportEmail(): string {
		return $this->config->getAppValue('nexeno_freeup', 'finance_report_email', '');
	}

	/** Month (YYYY-MM) the job last handled; stops a second send in the same month. */
	public function getFinanceReportLastMonth(): string {
		return $this->config->getAppValue('nexeno_freeup', 'finance_report_last_month', '');
	}

	public function setFinanceReportLastMonth(string $month): void {
		$this->config->setAppValue('nexeno_freeup', 'finance_report_last_month', $month);
	}

	/** Month (YYYY-MM) of the last report actually sent, or ''. */
	public function getFinanceReportLastSent(): string {
		return $this->config->getAppValue('nexeno_freeup', 'finance_report_last_sent', '');
	}

	public function setFinanceReportLastSent(string $month): void {
		$this->config->setAppValue('nexeno_freeup', 'finance_report_last_sent', $month);
	}

	/**
	 * Empty is allowed (no report is sent).
	 *
	 * @throws \InvalidArgumentException when the address is filled in but not valid
	 */
	public function setFinanceReport(bool $enabled, string $email): void {
		$email = trim($email);
		if ($email !== '' && (strlen($email) > 254 || !$this->isValidEmail($email))) {
			throw new \InvalidArgumentException($this->l->t('Enter a valid email address for the finance contact.'));
		}
		$wasSending = $this->isFinanceReportEnabled() && $this->getFinanceReportEmail() !== '';
		if ($enabled && $email !== '' && !$wasSending) {
			// First report goes out on the 1st of next month, not last month's figures at once.
			$this->setFinanceReportLastMonth(date('Y-m'));
		}
		$this->config->setAppValue('nexeno_freeup', 'finance_report_enabled', $enabled ? 'yes' : 'no');
		$this->config->setAppValue('nexeno_freeup', 'finance_report_email', $email);
	}

	public function isValidEmail(string $email): bool {
		return $this->emailValidator !== null ? $this->emailValidator->isValid($email) : filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	/** Days of warning before an approved account is archived. */
	public function isNoticeEnabled(): bool {
		return $this->config->getAppValue('nexeno_freeup', 'notice_enabled', 'yes') === 'yes';
	}

	public function getNoticeDays(): int {
		return max(self::LIMIT_NOTICE_DAYS[0], min(self::LIMIT_NOTICE_DAYS[1], (int)$this->config->getAppValue('nexeno_freeup', 'notice_days', '14')));
	}

	/** When a background job last started ('archive', 'report' or 'daily'); 0 = never. Shown as the automation status. */
	public function recordHeartbeat(string $job): void {
		$this->config->setAppValue('nexeno_freeup', 'heartbeat_' . $job, (string)time());
	}

	public function getHeartbeat(string $job): int {
		return (int)$this->config->getAppValue('nexeno_freeup', 'heartbeat_' . $job, '0');
	}

	/** When FreeUp last caught up a task itself because its background job was late. */
	public function recordCatchUp(string $task): void {
		$this->config->setAppValue('nexeno_freeup', 'catchup_' . $task, (string)time());
	}

	public function getCatchUp(string $task): int {
		return (int)$this->config->getAppValue('nexeno_freeup', 'catchup_' . $task, '0');
	}

	public function setNotice(bool $enabled, int $days): void {
		$this->config->setAppValue('nexeno_freeup', 'notice_enabled', $enabled ? 'yes' : 'no');
		$this->config->setAppValue('nexeno_freeup', 'notice_days', (string)max(self::LIMIT_NOTICE_DAYS[0], min(self::LIMIT_NOTICE_DAYS[1], $days)));
	}
}
