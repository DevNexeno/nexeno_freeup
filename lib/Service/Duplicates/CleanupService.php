<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Duplicates;

use DateTime;
use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Util\AuditText;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Executes approved clean-ups. For each extra copy: verify the SHA-256, share the kept copy
 * read-only with the owner, then move the extra copy to the owner's trash.
 * If any step fails the extra copy is left untouched.
 */
class CleanupService {
	public function __construct(
		private IL10N $l,
		private FreeUpRequestMapper $requestMapper,
		private DuplicateFileMapper $duplicateMapper,
		private AuditMapper $auditMapper,
		private IRootFolder $rootFolder,
		private IShareManager $shareManager,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array{type:string,name:string,ref:string,checksums:string[],users:string[],copies:int,wastedBytes:int,sizeBytes:int,primaryUser:string,keeper?:string|null,keeperReason?:string|null} $target
	 */
	public function propose(array $target, string $reason, string $proposedBy, bool $reasonIsSuggestion = false): FreeUpRequest {
		$existing = $this->requestMapper->findLatestCleanupsByTarget()[$target['ref']] ?? null;
		if ($existing !== null && in_array($existing->getStatus(), ['proposed', 'approved'], true)) {
			throw new \InvalidArgumentException($this->l->t('A clean-up for this item is already waiting in the approval queue.'));
		}

		$req = new FreeUpRequest();
		$req->setRequestType('cleanup');
		$req->setUserId($target['primaryUser'] !== '' ? $target['primaryUser'] : $proposedBy);
		$req->setTargetRef($target['ref']);
		$req->setTargetName($target['name']);
		$req->setTargetMeta((string)json_encode([
			'kind' => $target['type'],
			'checksums' => $target['checksums'],
			'users' => $target['users'],
			'copies' => $target['copies'],
			'sizeBytes' => $target['sizeBytes'],
			'wastedBytes' => $target['wastedBytes'],
			'keeper' => $target['keeper'] ?? null,
			'keeperReason' => $target['keeperReason'] ?? null,
		]));
		$req->setStorageBytes($target['wastedBytes']);
		$req->setStatus('proposed');
		$req->setProposedBy($proposedBy);
		$req->setProposedAt(new DateTime());
		$req->setProposalReason($reason);
		$req = $this->requestMapper->insert($req);

		$this->auditMapper->log(
			'propose_cleanup',
			null,
			$proposedBy,
			$proposedBy,
			AuditText::join(AuditText::count((int)$target['copies'], 'copy', 'copies'), $reasonIsSuggestion ? '' : AuditText::reason($reason)),
			(string)$target['name']
		);

		return $req;
	}

	/**
	 * Idempotent: only runs for status 'approved'.
	 */
	public function execute(FreeUpRequest $req, string $actorId): FreeUpRequest {
		if ($req->getStatus() !== 'approved') {
			return $req;
		}

		$req->setStatus('archiving');
		$this->requestMapper->update($req);

		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$freed = 0;
		$removed = 0;
		$skipped = 0;

		$gone = 0;
		// [userId => fileName] of owners to notify afterwards.
		$replacedOwners = [];

		foreach (($meta['checksums'] ?? []) as $checksum) {
			$dup = $this->duplicateMapper->findByChecksum((string)$checksum);
			if ($dup === null) {
				continue;
			}
			$instances = json_decode((string)$dup->getInstancesJson(), true) ?: [];

			// Copies deleted during the notice period count as gone; changed files are skipped.
			$present = [];
			foreach ($instances as $inst) {
				$node = $this->resolveVerified($inst, (string)$checksum);
				if ($node === null) {
					$this->fileStillExists($inst) ? $skipped++ : $gone++;
					continue;
				}
				$present[] = $inst;
			}
			if (count($present) < 2) {
				$this->duplicateMapper->delete($dup);
				continue;
			}

			$pick = $this->pickKeeper($present);
			$keeperInst = $present[$pick['index']];
			$keeper = $this->resolveVerified($keeperInst, (string)$checksum);
			if ($keeper === null) {
				$skipped += count($present) - 1;
				continue;
			}

			$remainingInstances = [$keeperInst];
			foreach ($present as $i => $inst) {
				if ($i === $pick['index']) {
					continue;
				}
				$owner = (string)$inst['userId'];
				try {
					// The trash bin only intercepts deletes by a logged-in user, so act as the owner.
					$size = $this->actAs($owner, function () use ($inst, $checksum, $keeper, $keeperInst, $owner): int {
						$extra = $this->resolveVerified($inst, (string)$checksum);
						if ($extra === null) {
							throw new \RuntimeException('file changed or missing since the scan');
						}
						// Never delete permanently.
						if (!$extra->getStorage()->instanceOfStorage('OCA\\Files_Trashbin\\Storage')) {
							throw new \RuntimeException('Trash bin is not active; refusing to delete permanently.');
						}
						$size = (int)$extra->getSize();
						$keeperOwner = (string)$keeperInst['userId'];
						$share = null;
						if ($owner !== $keeperOwner) {
							// Share listeners (e.g. Activity) resolve the path for the current user.
							$share = $this->actAs($keeperOwner, fn () => $this->shareReadOnly($keeper, $keeperOwner, $owner));
						}
						$name = $extra->getName();
						$extra->delete();
						if (!$this->isInTrash($owner, $name)) {
							$this->logger->error("NexenoFreeUp: {$name} of {$owner} was deleted but is not in the trash bin.");
							throw new \RuntimeException('deleted but not found in trash');
						}
						// Mounted while the copy still existed, so it got a "name (2)" target.
						if ($share !== null) {
							$this->moveShareTo($share, $owner, '/' . ltrim((string)$inst['path'], '/'));
						}
						return $size;
					});
					$removed++;
					$freed += $size;
					$replacedOwners[$owner] ??= (string)($inst['name'] ?? $dup->getFileName());
				} catch (\Throwable $e) {
					$skipped++;
					$remainingInstances[] = $inst;
					$this->logger->warning("NexenoFreeUp: Clean-up skipped {$inst['rawPath']} of {$owner}: " . $e->getMessage());
				}
			}

			if (count($remainingInstances) < 2) {
				$this->duplicateMapper->delete($dup);
			} else {
				$dup->setInstancesJson((string)json_encode($remainingInstances));
				$dup->setOccurrences(count($remainingInstances));
				$dup->setWastedBytes($dup->getSize() * (count($remainingInstances) - 1));
				$this->duplicateMapper->update($dup);
			}
		}

		$meta['removedCopies'] = $removed;
		$meta['skippedCopies'] = $skipped;
		$meta['goneCopies'] = $gone;
		$meta['freedBytes'] = $freed;
		$meta['replacedOwners'] = $replacedOwners;
		$req->setTargetMeta((string)json_encode($meta));

		if ($removed === 0 && $skipped === 0) {
			$req->setStatus('rejected');
			$req->setReviewNotes('Nothing to do: the copies no longer exist.');
			$req = $this->requestMapper->update($req);
			$this->auditMapper->log('request_obsolete', null, $actorId, $actorId, 'The copies no longer exist', (string)$req->getTargetName());
			return $req;
		}

		$req->setStorageBytes($freed);
		$req->setStatus('archived');
		$req->setArchivedAt(new DateTime());
		$req = $this->requestMapper->update($req);

		$this->auditMapper->log(
			'cleanup_completed',
			null,
			$actorId,
			$actorId,
			$this->completedText($removed, $gone, $skipped),
			(string)$req->getTargetName()
		);

		return $req;
	}

	/**
	 * Keeps the oldest copy owned by an enabled account; ties and unknown ages fall back to the lowest file id.
	 *
	 * @param array<int,array> $instances copies with `userId`, `fileId` and (optional) `mtime`
	 * @return array{index:int,userId:string,reason:string} reason: oldest_active | active | oldest | first
	 */
	public function pickKeeper(array $instances): array {
		$active = [];
		foreach ($instances as $i => $inst) {
			$user = $this->userManager->get((string)($inst['userId'] ?? ''));
			if ($user !== null && $user->isEnabled()) {
				$active[] = $i;
			}
		}
		$activeOnly = count($active) > 0 && count($active) < count($instances);
		$candidates = count($active) > 0 ? $active : array_keys($instances);

		$byAge = count($candidates) > 1 && count(array_filter($candidates, fn ($i) => (int)($instances[$i]['mtime'] ?? 0) > 0)) === count($candidates);

		usort($candidates, function ($a, $b) use ($instances) {
			$ma = (int)($instances[$a]['mtime'] ?? 0) ?: PHP_INT_MAX;
			$mb = (int)($instances[$b]['mtime'] ?? 0) ?: PHP_INT_MAX;
			return [$ma, (int)($instances[$a]['fileId'] ?? 0)] <=> [$mb, (int)($instances[$b]['fileId'] ?? 0)];
		});
		$index = $candidates[0];

		$reason = $activeOnly && $byAge ? 'oldest_active' : ($activeOnly ? 'active' : ($byAge ? 'oldest' : 'first'));
		return ['index' => $index, 'userId' => (string)($instances[$index]['userId'] ?? ''), 'reason' => $reason];
	}

	/**
	 * @return array<string,string>
	 */
	public function affectedOwners(FreeUpRequest $req): array {
		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$owners = [];
		foreach (($meta['checksums'] ?? []) as $checksum) {
			$dup = $this->duplicateMapper->findByChecksum((string)$checksum);
			$instances = $dup ? (json_decode((string)$dup->getInstancesJson(), true) ?: []) : [];
			if (count($instances) < 2) {
				continue;
			}
			$keep = $this->pickKeeper($instances)['index'];
			foreach ($instances as $i => $inst) {
				if ($i !== $keep && !isset($owners[(string)$inst['userId']])) {
					$owners[(string)$inst['userId']] = (string)($inst['name'] ?? $dup->getFileName());
				}
			}
		}
		return $owners;
	}

	private function fileStillExists(array $inst): bool {
		try {
			$this->rootFolder->getUserFolder((string)$inst['userId'])->get((string)$inst['path']);
			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function actAs(string $userId, callable $fn): mixed {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new \RuntimeException('That owner no longer exists.');
		}
		$previous = $this->userSession->getUser();
		$this->userSession->setVolatileActiveUser($user);
		// The legacy trash backend uses the global filesystem, which must be the owner's.
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($userId);
		try {
			return $fn();
		} finally {
			\OC_Util::tearDownFS();
			$this->userSession->setVolatileActiveUser($previous);
			// Restore the caller's filesystem so actAs can be nested.
			if ($previous !== null) {
				\OC_Util::setupFS($previous->getUID());
			}
		}
	}

	private function isInTrash(string $ownerId, string $name): bool {
		try {
			$trash = $this->rootFolder->get('/' . $ownerId . '/files_trashbin/files');
			if (!$trash instanceof Folder) {
				return false;
			}
			foreach ($trash->getDirectoryListing() as $entry) {
				if (str_starts_with($entry->getName(), $name . '.d')) {
					return true;
				}
			}
		} catch (\Throwable $e) {
			// non-critical
		}
		return false;
	}

	private function resolveVerified(array $inst, string $checksum): ?File {
		try {
			$node = $this->rootFolder->getUserFolder((string)$inst['userId'])->get((string)$inst['path']);
			if (!$node instanceof File) {
				return null;
			}
			return hash_equals($checksum, (string)$node->hash('sha256')) ? $node : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * @return IShare|null the new share, or null when the recipient already had one (it stays where they put it)
	 */
	private function shareReadOnly(File $node, string $ownerId, string $recipientId): ?IShare {
		foreach ($this->shareManager->getSharesBy($ownerId, IShare::TYPE_USER, $node, true, -1) as $existing) {
			if ($existing->getSharedWith() === $recipientId) {
				$this->acceptFor($existing, $recipientId);
				return null;
			}
		}
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($recipientId)
			->setSharedBy($ownerId)
			->setShareOwner($ownerId)
			->setPermissions(Constants::PERMISSION_READ);
		$share = $this->shareManager->createShare($share);
		$this->acceptFor($share, $recipientId);
		return $share;
	}

	private function moveShareTo(IShare $share, string $recipientId, string $target): void {
		try {
			$share = $this->shareManager->getShareById($share->getFullId(), $recipientId);
			if ($share->getTarget() !== $target) {
				$share->setTarget($target);
				$this->shareManager->moveShare($share, $recipientId);
			}
		} catch (\Throwable $e) {
			// Still shared, only under another name.
			$this->logger->warning("NexenoFreeUp: Could not move the shared copy to {$target} for {$recipientId}: " . $e->getMessage());
		}
	}

	private function completedText(int $removed, int $gone, int $skipped): string {
		return AuditText::join(
			AuditText::count($removed, 'copy', 'copies') . ' replaced by a shared copy',
			$gone > 0 ? $gone . ' already deleted by ' . ($gone === 1 ? 'its owner' : 'their owners') : '',
			$skipped > 0 ? $skipped . ' left unchanged (changed since the scan)' : ''
		);
	}

	/**
	 * Accepts on the recipient's behalf, otherwise the share stays pending and the file looks lost.
	 */
	private function acceptFor(IShare $share, string $recipientId): void {
		try {
			$this->shareManager->acceptShare($share, $recipientId);
		} catch (\Throwable $e) {
			$this->logger->warning("NexenoFreeUp: Could not auto-accept share for {$recipientId}: " . $e->getMessage());
		}
	}
}
