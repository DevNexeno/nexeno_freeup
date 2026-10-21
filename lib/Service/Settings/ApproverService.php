<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Settings;

use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;

/** Administrators always count as approvers. */
class ApproverService {
	public function __construct(
		private IL10N $l,
		private SettingsService $settings,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private FreeUpRequestMapper $requestMapper,
	) {
	}

	public function isUserApprover(?string $userId): bool {
		return $this->userInGroups(
			$userId,
			array_merge($this->settings->getApproverGroups(), $this->settings->getCleanupApproverGroups()),
			array_merge($this->settings->getApproverUsers(), $this->settings->getCleanupApproverUsers())
		);
	}

	public function canApprove(?string $userId, string $requestType): bool {
		$cleanup = $requestType === 'cleanup';
		return $this->userInGroups(
			$userId,
			$cleanup ? $this->settings->getCleanupApproverGroups() : $this->settings->getApproverGroups(),
			$cleanup ? $this->settings->getCleanupApproverUsers() : $this->settings->getApproverUsers()
		);
	}

	public function denyMessage(string $requestType): string {
		return $requestType === 'cleanup'
			? $this->l->t('Only authorized approvers can approve duplicate clean-ups. An administrator can change who approves in Settings.')
			: $this->l->t('Only authorized approvers can approve account removals. An administrator can change who approves in Settings.');
	}

	public function denyMessageForRequest(int $requestId): string {
		$req = $this->requestMapper->findById($requestId);
		return $this->denyMessage($req !== null ? $req->getRequestType() : 'account');
	}

	public function canDecideRequest(?string $userId, int $requestId): bool {
		$req = $this->requestMapper->findById($requestId);
		return $req !== null && $this->canApprove($userId, $req->getRequestType());
	}

	private function userInGroups(?string $userId, array $groups, array $users = []): bool {
		if ($userId === null || $userId === '') {
			return false;
		}
		$user = $this->userManager->get($userId);
		if (!$user) {
			return false;
		}
		if (in_array($userId, $users, true)) {
			return true;
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		return in_array('admin', $userGroups, true) || !empty(array_intersect($userGroups, $groups));
	}

	public function getApproverUserIds(string $requestType = 'account'): array {
		$groups = $requestType === 'cleanup' ? $this->settings->getCleanupApproverGroups() : $this->settings->getApproverGroups();
		$userIds = [];
		foreach ($requestType === 'cleanup' ? $this->settings->getCleanupApproverUsers() : $this->settings->getApproverUsers() as $uid) {
			if ($this->userManager->userExists($uid)) {
				$userIds[$uid] = true;
			}
		}
		foreach ($groups as $gid) {
			$group = $this->groupManager->get($gid);
			if ($group !== null) {
				foreach ($group->getUsers() as $user) {
					$userIds[$user->getUID()] = true;
				}
			}
		}
		return array_keys($userIds);
	}

	public function getApproverLabel(string $requestType = 'account'): string {
		$cleanup = $requestType === 'cleanup';
		$people = array_map(
			fn (string $uid) => $this->userManager->get($uid)?->getDisplayName() ?: $uid,
			$cleanup ? $this->settings->getCleanupApproverUsers() : $this->settings->getApproverUsers()
		);
		return implode(', ', array_merge($cleanup ? $this->settings->getCleanupApproverGroups() : $this->settings->getApproverGroups(), $people));
	}
}
