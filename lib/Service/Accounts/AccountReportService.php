<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Accounts;

use DateTime;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class AccountReportService {
	public function __construct(
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IConfig $config,
		private FreeUpRequestMapper $requestMapper,
		private SettingsService $settings,
		private StorageService $storage,
		private StatsCache $statsCache,
		private AccountDateService $dates,
	) {
	}

	/**
	 * Expensive per-account facts, cached for StatsCache::ACCOUNT_FACTS_TTL and shared by every page. Lifecycle
	 * status is added per request by getAccountsReport(), so approving or archiving never forces a full re-scan.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function getAccountFacts(bool $forceRefresh): array {
		if (!$forceRefresh) {
			$cached = $this->statsCache->getArray(StatsCache::ACCOUNT_FACTS);
			if ($cached !== null) {
				return $cached;
			}
		}

		$storageMap = $this->storage->getUserStorageMap();
		$fileCountMap = $this->storage->getUserFileCountMap();
		$createdAtMap = $this->storage->getUserCreatedAtMap();
		$recorded = $this->dates->recorded();
		$firstSeen = [];
		// One query covers home storages; per-user filesystem setup is only needed for object-store primary storage or external quota.
		$perUserStorage = $storageMap === [] || $this->config->getSystemValueBool('quota_include_external_storage', false);
		$defaultQuota = $this->config->getAppValue('files', 'default_quota', 'none');
		$now = time();

		$facts = [];
		$this->userManager->callForAllUsers(function (IUser $user) use (&$facts, &$firstSeen, $recorded, $storageMap, $fileCountMap, $createdAtMap, $perUserStorage, $defaultQuota, $now) {
			$uid = $user->getUID();
			$lastLogin = (int)$user->getLastLogin();

			if ($perUserStorage) {
				$storageBytes = $this->storage->getUserStorageBytes($uid);
				if ($storageBytes === 0 && isset($storageMap[$uid])) {
					$storageBytes = $storageMap[$uid];
				}
			} else {
				$storageBytes = $storageMap[$uid] ?? 0;
			}

			$rawQuota = $user->getQuota();
			if ($rawQuota === 'default' || empty($rawQuota)) {
				$rawQuota = (!empty($defaultQuota) && $defaultQuota !== 'none') ? $defaultQuota : 'none';
			}
			$quotaBytes = ($rawQuota === 'none') ? -1 : \OCP\Util::computerFileSize($rawQuota);
			if ($quotaBytes === false) {
				$quotaBytes = -1;
			}

			// An account that never logged in is as old as its creation date, so FreeUp needs one for it.
			$createdAt = $recorded['created'][$uid] ?? $createdAtMap[$uid] ?? 0;
			if ($createdAt === 0 && $lastLogin <= 0) {
				$createdAt = $recorded['seen'][$uid] ?? 0;
				if ($createdAt === 0) {
					$firstSeen[] = $uid;
					$createdAt = $now;
				}
			}

			$facts[] = [
				'userId' => $uid,
				'displayName' => $user->getDisplayName() ?: $uid,
				'email' => (string)$user->getSystemEMailAddress(),
				'groups' => $this->groupManager->getUserGroupIds($user),
				'createdAt' => $createdAt,
				'lastLogin' => $lastLogin,
				'enabled' => $user->isEnabled(),
				'storageBytes' => $storageBytes,
				'fileCount' => $fileCountMap[$uid] ?? 0,
				'quotaBytes' => (int)$quotaBytes,
			];
		});

		$this->dates->recordFirstSeen($firstSeen, $now);
		$this->statsCache->storeAccountFacts($facts);
		return $facts;
	}

	public function getAccountsReport(array $filters = [], bool $forceRefresh = false): array {
		$now = time();
		$thresholdMonths = $this->settings->getInactivityThresholdMonths();
		$thresholdSeconds = $thresholdMonths * 30 * 86400;
		$exemptGroups = $this->settings->getExemptGroups();
		$exemptUsers = $this->settings->getExemptUsers();

		$requestMap = [];
		foreach ($this->requestMapper->findAllRequests() as $req) {
			if (!isset($requestMap[$req->getUserId()])) {
				$requestMap[$req->getUserId()] = $req;
			}
		}

		$nowMidnight = strtotime(date('Y-m-d 00:00:00', $now));
		$recentLogins = $this->statsCache->readRecentLogins();
		$allUsers = [];
		foreach ($this->getAccountFacts($forceRefresh) as $f) {
			$uid = $f['userId'];
			if (isset($recentLogins[$uid]) && $recentLogins[$uid] > $f['lastLogin']) {
				$f['lastLogin'] = $recentLogins[$uid];
			}
			$groups = $f['groups'];
			$lastLogin = $f['lastLogin'];
			$storageBytes = $f['storageBytes'];
			$quotaBytes = $f['quotaBytes'];
			$enabled = $f['enabled'];
			$isNever = ($lastLogin <= 0);
			// Inactive since the last login or, for an account that never logged in, since it was created.
			$since = $isNever ? (int)($f['createdAt'] ?? 0) : $lastLogin;
			if ($since <= 0 || $since > $now) {
				$since = $now;
			}
			$inactiveSeconds = $now - $since;
			$inactiveDays = max(0, (int)round(($nowMidnight - strtotime(date('Y-m-d 00:00:00', $since))) / 86400));
			$inactiveMonths = round($inactiveDays / 30.416, 1);
			$isExempt = !empty(array_intersect($groups, $exemptGroups)) || in_array($uid, $exemptUsers, true);

			$req = $requestMap[$uid] ?? null;
			if ($storageBytes === 0 && $req !== null && $req->getStorageBytes() > 0) {
				$storageBytes = $req->getStorageBytes();
			}
			if (!$enabled) {
				$status = ($req !== null && in_array($req->getStatus(), ['archived', 'archiving'], true)) ? 'archived' : 'disabled';
			} elseif ($isExempt) {
				$status = 'exempt';
			} elseif ($req !== null && in_array($req->getStatus(), ['proposed', 'approved', 'archived', 'archiving'], true)) {
				$status = $req->getStatus();
			} elseif ($inactiveSeconds >= $thresholdSeconds) {
				// An account that never logged in is judged by its age, like any other: inactive once old enough, active before.
				$status = 'candidate';
			} else {
				$status = 'active';
			}

			$type = 'User';
			if (in_array('students', $groups, true) || in_array('student', $groups, true)) {
				$type = 'Student';
			} elseif (in_array('staff', $groups, true) || in_array('staffs', $groups, true)) {
				$type = 'Staff';
			} elseif ($isExempt || in_array('admin', $groups, true)) {
				$type = 'Admin';
			} elseif (!empty($groups)) {
				$type = ucfirst($groups[0]);
			}

			$allUsers[] = [
				'userId' => $uid,
				'displayName' => $f['displayName'],
				'email' => $f['email'],
				'groups' => $groups,
				'type' => $type,
				'createdAt' => $f['createdAt'] ?? 0,
				'lastLogin' => $lastLogin,
				'isNeverLoggedIn' => $isNever,
				'inactiveDays' => $inactiveDays,
				'inactiveMonths' => $inactiveMonths,
				'storageBytes' => $storageBytes,
				'fileCount' => $f['fileCount'],
				'quotaBytes' => $quotaBytes,
				'enabled' => $enabled,
				'isExempt' => $isExempt,
				'status' => $status,
				'requestId' => $req?->getId(),
				'proposedBy' => $req?->getProposedBy(),
				'proposedAt' => $req?->getProposedAt()?->format(DateTime::ATOM),
				'proposalReason' => $req?->getProposalReason(),
			];
		}

		$filtered = array_filter($allUsers, function ($item) use ($filters, $thresholdMonths) {
			if (!empty($filters['search'])) {
				$q = strtolower($filters['search']);
				$match = str_contains(strtolower($item['userId']), $q)
					|| str_contains(strtolower($item['displayName']), $q)
					|| str_contains(strtolower($item['email']), $q);
				if (!$match) {
					return false;
				}
			}

			if (!empty($filters['status']) && $filters['status'] !== 'all') {
				if ($filters['status'] === 'candidate') {
					if ($item['status'] !== 'candidate') {
						return false;
					}
				} elseif ($filters['status'] === 'never_logged_in') {
					if (!$item['isNeverLoggedIn']) {
						return false;
					}
				} elseif ($filters['status'] === 'disabled') {
					if ($item['status'] !== 'disabled') {
						return false;
					}
				} elseif ($item['status'] !== $filters['status']) {
					return false;
				}
			}

			if (!empty($filters['inactivity']) && $filters['inactivity'] !== 'all') {
				if ($filters['inactivity'] === '6m' && $item['inactiveMonths'] < 6) {
					return false;
				}
				if ($filters['inactivity'] === '12m' && $item['inactiveMonths'] < 12) {
					return false;
				}
				if ($filters['inactivity'] === 'never' && !$item['isNeverLoggedIn']) {
					return false;
				}
				if ($filters['inactivity'] === 'active' && $item['inactiveMonths'] >= $thresholdMonths) {
					return false;
				}
			}

			if (!empty($filters['storage']) && $filters['storage'] !== 'all') {
				$minBytes = match ($filters['storage']) {
					'100mb' => 100 * 1024 * 1024,
					'1gb' => 1024 * 1024 * 1024,
					'10gb' => 10 * 1024 * 1024 * 1024,
					'50gb' => 50 * 1024 * 1024 * 1024,
					'100gb' => 100 * 1024 * 1024 * 1024,
					default => 0,
				};
				if ($item['storageBytes'] < $minBytes) {
					return false;
				}
			}

			return true;
		});

		return array_values($filtered);
	}
}
