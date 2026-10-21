<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Duplicates;

use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Requests\ApproverNotifier;
use OCP\IL10N;

class CleanupProposalService {
	public function __construct(
		private IL10N $l,
		private DuplicateScanService $scanner,
		private DuplicateFileMapper $duplicateMapper,
		private FreeUpRequestMapper $requestMapper,
		private ApproverNotifier $approverNotifier,
		private StatsCache $statsCache,
		private ?CleanupService $cleanupService = null,
	) {
	}

	public function proposeCleanup(string $type, string $id, string $reason, string $proposedBy, ?array $duplicates = null, bool $notify = true, bool $reasonIsSuggestion = false): FreeUpRequest {
		if ($this->cleanupService === null) {
			throw new \RuntimeException('Clean-up service unavailable.');
		}

		$target = null;
		if ($type === 'file') {
			foreach ($duplicates ?? $this->scanner->getDuplicateFiles() as $d) {
				if ((string)$d['id'] === $id) {
					$users = array_values(array_unique(array_filter(array_column($d['instances'] ?? [], 'userId'))));
					$pick = $this->cleanupService->pickKeeper($d['instances'] ?? []);
					$target = [
						'keeper' => $pick['userId'],
						'keeperReason' => $pick['reason'],
						'type' => 'file',
						'name' => (string)$d['fileName'],
						'ref' => 'file:' . $d['checksum'],
						'checksums' => [(string)$d['checksum']],
						'users' => $users,
						'copies' => (int)$d['occurrences'],
						'wastedBytes' => (int)$d['wastedBytes'],
						'sizeBytes' => (int)$d['size'],
						'primaryUser' => (string)($users[0] ?? ''),
					];
					break;
				}
			}
		} else {
			throw new \InvalidArgumentException($this->l->t('Unknown clean-up type.'));
		}

		if ($target === null || empty($target['checksums'])) {
			throw new \InvalidArgumentException($this->l->t('This item is no longer in the latest duplicate scan. Run a new scan and try again.'));
		}

		$req = $this->cleanupService->propose($target, $reason, $proposedBy, $reasonIsSuggestion);
		if ($notify) {
			$this->approverNotifier->notifyApprovers($req, $proposedBy);
		}
		$this->statsCache->clear();
		return $req;
	}

	/**
	 * Sends one summary notification to the approvers instead of one per file.
	 *
	 * @param string[] $ids duplicate file ids from the latest scan
	 * @return array{proposed:int,failed:array<int,array{id:string,error:string}>}
	 */
	public function proposeCleanupBulk(array $ids, string $reason, string $proposedBy, bool $reasonIsSuggestion = false): array {
		$duplicates = $this->scanner->getDuplicateFiles();
		$proposed = 0;
		$failed = [];
		foreach (array_values(array_unique(array_map('strval', $ids))) as $id) {
			try {
				$this->proposeCleanup('file', $id, $reason, $proposedBy, $duplicates, false, $reasonIsSuggestion);
				$proposed++;
			} catch (\InvalidArgumentException $e) {
				$failed[] = ['id' => $id, 'error' => $e->getMessage()];
			}
		}
		if ($proposed > 0) {
			$this->approverNotifier->notifyApproversOfBatch($proposed, $proposedBy);
		}
		return ['proposed' => $proposed, 'failed' => $failed];
	}

	/**
	 * @return array<string,string> ['file:<sha>' => status]
	 */
	public function getCleanupStatusMap(): array {
		$scannedAt = [];
		foreach ($this->duplicateMapper->findAllSorted() as $dup) {
			$scannedAt['file:' . $dup->getChecksum()] = $dup->getLastScanned()?->getTimestamp() ?? 0;
		}

		$map = [];
		foreach ($this->requestMapper->findLatestCleanupsByTarget() as $ref => $r) {
			// Duplicates found again after an archived clean-up are a new case.
			if ($r->getStatus() === 'archived' && ($scannedAt[$ref] ?? 0) > ($r->getArchivedAt()?->getTimestamp() ?? PHP_INT_MAX)) {
				continue;
			}
			$map[$ref] = $r->getStatus();
		}
		return $map;
	}
}
