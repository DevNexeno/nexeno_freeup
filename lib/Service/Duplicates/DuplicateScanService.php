<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Duplicates;

use DateTime;
use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\DuplicateFile;
use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Requests\ObsoleteRequestService;
use OCA\NexenoFreeUp\Util\Format;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class DuplicateScanService {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private DuplicateFileMapper $duplicateMapper,
		private IUserManager $userManager,
		private AuditMapper $auditMapper,
		private SkeletonFilter $skeletons,
		private DuplicatePruner $pruner,
		private ObsoleteRequestService $obsoleteRequests,
		private StatsCache $statsCache,
		private LoggerInterface $logger,
		private ?IJobList $jobList = null,
		private ?CleanupService $cleanupService = null,
	) {
	}

	public function scanDuplicates(int $minSize = 1, ?string $requestedBy = null): array {
		// Only home:: storages, so shared and external mounts are not counted twice.
		$qb = $this->db->getQueryBuilder();
		$qb->select('f.size', $qb->createFunction('COUNT(*) as count'))
			->from('filecache', 'f')
			->join('f', 'storages', 's', $qb->expr()->eq('f.storage', 's.numeric_id'))
			->where($qb->expr()->like('f.path', $qb->createNamedParameter('files/%')))
			->andWhere($qb->expr()->gte('f.size', $qb->createNamedParameter($minSize, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('f.mimetype', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))) // directories
			->andWhere($qb->expr()->like('s.id', $qb->createNamedParameter('home::%')))
			->groupBy('f.size')
			->having($qb->expr()->gt($qb->createFunction('COUNT(*)'), $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->orderBy('f.size', 'DESC')
			->setMaxResults(5000);

		$res = $qb->executeQuery();
		$candidateSizes = [];
		while ($row = $res->fetch()) {
			$candidateSizes[] = (int)$row['size'];
		}
		$res->closeCursor();

		$pending = [];

		$dataDir = $this->config->getSystemValue('datadirectory', '/var/www/html/data');
		$duplicateGroups = [];
		$skeletonHashes = $this->skeletons->getSkeletonFileHashes();

		foreach ($candidateSizes as $size) {
			$qb2 = $this->db->getQueryBuilder();
			$qb2->select('f.fileid', 'f.storage', 'f.path', 'f.name', 'f.mimetype', 'f.mtime', 's.id as storage_id')
				->from('filecache', 'f')
				->join('f', 'storages', 's', $qb2->expr()->eq('f.storage', 's.numeric_id'))
				->where($qb2->expr()->eq('f.size', $qb2->createNamedParameter($size)))
				->andWhere($qb2->expr()->like('f.path', $qb2->createNamedParameter('files/%')))
				->andWhere($qb2->expr()->neq('f.mimetype', $qb2->createNamedParameter(2, IQueryBuilder::PARAM_INT)))
				->andWhere($qb2->expr()->like('s.id', $qb2->createNamedParameter('home::%')));

			$res2 = $qb2->executeQuery();
			$files = [];
			while ($f = $res2->fetch()) {
				$uid = str_starts_with((string)$f['storage_id'], 'home::') ? substr((string)$f['storage_id'], 6) : (string)$f['storage_id'];
				$rawPath = (string)$f['path'];
				$cleanPath = str_starts_with($rawPath, 'files/') ? substr($rawPath, 6) : $rawPath;
				$dir = dirname($cleanPath);
				$folderPath = ($dir === '.' || $dir === '') ? '/' : $dir;
				$folderName = ($folderPath === '/') ? 'Root' : basename($folderPath);

				$files[] = [
					'fileId' => (int)$f['fileid'],
					'storage' => (int)$f['storage'],
					'path' => $cleanPath,
					'rawPath' => $rawPath,
					'name' => (string)$f['name'],
					'userId' => $uid,
					'folder' => $folderPath,
					'folderName' => $folderName,
					'mimetype' => (string)$f['mimetype'],
					'mtime' => (int)$f['mtime'],
				];
			}
			$res2->closeCursor();

			// Files that differ in their first or last 64 KB cannot be identical; skip hashing them.
			$files = $this->dropFilesWithUniqueFingerprint($files, $dataDir, $size);

			$groupedBySha256 = [];
			foreach ($files as $fileItem) {
				$sha256 = null;
				$localPath = $dataDir . '/' . $fileItem['userId'] . '/' . ($fileItem['rawPath'] ?? $fileItem['path']);

				if (file_exists($localPath) && is_readable($localPath)) {
					$sha256 = hash_file('sha256', $localPath);
				}

				// External or object storage: read through the Nextcloud file API.
				if (empty($sha256)) {
					try {
						$userFolder = $this->rootFolder->getUserFolder($fileItem['userId']);
						$relPath = str_starts_with($fileItem['path'], 'files/') ? substr($fileItem['path'], 6) : $fileItem['path'];
						if ($userFolder->nodeExists($relPath)) {
							$node = $userFolder->get($relPath);
							if ($node instanceof \OCP\Files\File) {
								$stream = $node->fopen('rb');
								if (is_resource($stream)) {
									$ctx = hash_init('sha256');
									hash_update_stream($ctx, $stream);
									$sha256 = hash_final($ctx);
									fclose($stream);
								}
							}
						}
					} catch (\Throwable $e) {
						// falls back to the name hash below
					}
				}

				// Unreadable content: group by size and name instead.
				if (empty($sha256)) {
					$sha256 = hash('sha256', $size . '_' . $fileItem['name']);
				}

				$fileItem['sha256'] = $sha256;
				$groupedBySha256[$sha256][] = $fileItem;
			}

			foreach ($groupedBySha256 as $sha256Hash => $matchedFiles) {
				if (isset($skeletonHashes[$sha256Hash]) || $this->skeletons->isSkeletonText($matchedFiles[0], (int)$size, $dataDir)) {
					continue;
				}

				// Shared aliases must not be counted twice.
				$uniqueInstances = [];
				$seenKeys = [];
				foreach ($matchedFiles as $mf) {
					$k = $mf['userId'] . '::' . ($mf['rawPath'] ?? $mf['path']);
					if (!isset($seenKeys[$k])) {
						$seenKeys[$k] = true;
						$uniqueInstances[] = $mf;
					}
				}
				$matchedFiles = $uniqueInstances;

				if (count($matchedFiles) > 1) {
					$first = $matchedFiles[0];
					$occurrences = count($matchedFiles);
					$wastedBytes = $size * ($occurrences - 1);

					$dup = new DuplicateFile();
					$dup->setChecksum($sha256Hash);
					$dup->setFileName($first['name']);
					$dup->setSize($size);
					$dup->setOccurrences($occurrences);
					$dup->setWastedBytes($wastedBytes);
					$dup->setMimetype((string)$first['mimetype']);
					$dup->setInstancesJson(json_encode($matchedFiles));
					$dup->setLastScanned(new DateTime());

					$pending[] = $dup;
				}
			}
		}

		// One transaction, so a failed scan never leaves the list empty.
		$this->db->beginTransaction();
		try {
			$this->duplicateMapper->clearAll();
			foreach ($pending as $dup) {
				$duplicateGroups[] = $this->duplicateMapper->insert($dup)->jsonSerialize();
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$this->config->setAppValue('nexeno_freeup', 'has_scanned_duplicates', 'yes');
		$this->config->setAppValue('nexeno_freeup', 'last_duplicate_scan', (string)time());

		[$actorId, $actorName] = $this->scanActor($requestedBy);
		$this->auditMapper->log(
			'scan_duplicates',
			null,
			$actorId,
			$actorName,
			$this->scanSummary($duplicateGroups)
		);

		$this->statsCache->clear();
		$this->obsoleteRequests->sweepObsoleteRequests();

		return $this->withKeepers($duplicateGroups);
	}

	/**
	 * @param array<int, array<string, mixed>> $groups
	 */
	private function scanSummary(array $groups): string {
		$files = count($groups);
		$updated = 'Storage and licence figures updated';
		if ($files === 0) {
			return $updated . ' · No duplicate files found';
		}
		$freeable = array_sum(array_map(fn (array $g) => (int)($g['wastedBytes'] ?? 0), $groups));
		return $updated . ' · Duplicates: ' . ($files === 1 ? '1 file' : $files . ' files') . ' with extra copies, '
			. Format::bytes($freeable, 1) . ' could be freed';
	}

	/**
	 * Falls back to the system when nobody started the scan or the account no longer exists.
	 *
	 * @return array{0:string,1:string} actor id and display name
	 */
	private function scanActor(?string $requestedBy): array {
		$user = $requestedBy !== null && $requestedBy !== '' ? $this->userManager->get($requestedBy) : null;
		return $user !== null ? [$user->getUID(), $user->getDisplayName() ?: $user->getUID()] : ['system', 'System Scanner'];
	}

	private function quickFingerprint(string $path, int $size): ?string {
		if (!is_file($path) || !is_readable($path)) {
			return null;
		}
		$h = @fopen($path, 'rb');
		if ($h === false) {
			return null;
		}
		$chunk = 65536;
		$data = (string)fread($h, $chunk);
		if ($size > 2 * $chunk && fseek($h, -$chunk, SEEK_END) === 0) {
			$data .= (string)fread($h, $chunk);
		}
		fclose($h);
		return sha1($data);
	}

	/**
	 * Files that cannot be read locally are kept, since they cannot be ruled out.
	 *
	 * @param array<int, array<string, mixed>> $files
	 * @return array<int, array<string, mixed>>
	 */
	private function dropFilesWithUniqueFingerprint(array $files, string $dataDir, int $size): array {
		if (count($files) < 2) {
			return $files;
		}
		$byKey = [];
		$unknown = [];
		foreach ($files as $f) {
			$key = $this->quickFingerprint($dataDir . '/' . $f['userId'] . '/' . ($f['rawPath'] ?? $f['path']), $size);
			if ($key === null) {
				$unknown[] = $f;
			} else {
				$byKey[$key][] = $f;
			}
		}
		$keep = $unknown;
		foreach ($byKey as $group) {
			if (count($group) > 1) {
				array_push($keep, ...$group);
			}
		}
		return $keep;
	}

	/**
	 * @return array{state: string, startedAt: ?int, finishedAt: ?int}
	 */
	public function queueDuplicateScan(int $minBytes, string $requestedBy): array {
		$status = $this->getDuplicateScanStatus();
		if (in_array($status['state'], ['queued', 'running'], true)) {
			return $status;
		}
		if ($this->jobList === null) {
			throw new \RuntimeException('Background jobs are not available.');
		}
		$this->config->setAppValue('nexeno_freeup', 'scan_state', 'queued');
		$this->config->setAppValue('nexeno_freeup', 'scan_started_at', (string)time());
		$this->config->setAppValue('nexeno_freeup', 'scan_finished_at', '');
		$args = ['minBytes' => max(1, $minBytes), 'by' => $requestedBy];
		$this->config->setAppValue('nexeno_freeup', 'scan_args', (string)json_encode($args));
		$this->jobList->add(\OCA\NexenoFreeUp\BackgroundJob\DuplicateScanJob::class, $args);
		return $this->getDuplicateScanStatus();
	}

	/**
	 * Only a scan still "queued" is started, so cron and "run now" never overlap.
	 */
	public function runQueuedDuplicateScan(int $minBytes, ?string $requestedBy = null): void {
		if ($this->config->getAppValue('nexeno_freeup', 'scan_state', 'idle') !== 'queued') {
			return;
		}
		$this->config->setAppValue('nexeno_freeup', 'scan_state', 'running');
		try {
			$this->scanDuplicates(max(1, $minBytes), $requestedBy);
			$this->config->setAppValue('nexeno_freeup', 'scan_state', 'done');
		} catch (\Throwable $e) {
			$this->config->setAppValue('nexeno_freeup', 'scan_state', 'failed');
			$this->logger->error('NexenoFreeUp: Duplicate scan failed: ' . $e->getMessage(), ['exception' => $e]);
		} finally {
			$this->config->setAppValue('nexeno_freeup', 'scan_finished_at', (string)time());
		}
	}

	/**
	 * Needed because background jobs in the default AJAX mode can wait for hours.
	 */
	public function startQueuedScanNow(): array {
		$status = $this->getDuplicateScanStatus();
		if ($status['state'] !== 'queued') {
			return $status;
		}
		$args = json_decode($this->config->getAppValue('nexeno_freeup', 'scan_args', ''), true);
		if (is_array($args) && $this->jobList !== null) {
			$this->jobList->remove(\OCA\NexenoFreeUp\BackgroundJob\DuplicateScanJob::class, $args);
		}
		$this->runQueuedDuplicateScan((int)($args['minBytes'] ?? 1), isset($args['by']) ? (string)$args['by'] : null);
		return $this->getDuplicateScanStatus();
	}

	/**
	 * @return array{state: string, startedAt: ?int, finishedAt: ?int} state: idle | queued | running | done | failed
	 */
	public function getDuplicateScanStatus(): array {
		$state = $this->config->getAppValue('nexeno_freeup', 'scan_state', 'idle');
		$started = (int)$this->config->getAppValue('nexeno_freeup', 'scan_started_at', '0');
		// After 3 hours a queued or running scan is treated as lost (e.g. cron restart).
		if (in_array($state, ['queued', 'running'], true) && $started > 0 && (time() - $started) > 3 * 3600) {
			$state = 'failed';
			$this->config->setAppValue('nexeno_freeup', 'scan_state', 'failed');
		}
		$finished = (int)$this->config->getAppValue('nexeno_freeup', 'scan_finished_at', '0');
		return ['state' => $state, 'startedAt' => $started ?: null, 'finishedAt' => $finished ?: null];
	}

	private function withKeepers(array $duplicates): array {
		if ($this->cleanupService === null) {
			return $duplicates;
		}
		foreach ($duplicates as &$d) {
			if (!empty($d['instances']) && is_array($d['instances'])) {
				$d['keeper'] = $this->cleanupService->pickKeeper($d['instances']);
			}
		}
		return $duplicates;
	}

	public function getDuplicateFiles(): array {
		$hasScanned = $this->config->getAppValue('nexeno_freeup', 'has_scanned_duplicates', 'no');
		if ($hasScanned === 'no') {
			return $this->scanDuplicates();
		}

		$duplicates = $this->pruner->pruneStaleDuplicates($this->duplicateMapper->findAllSorted());
		return $this->withKeepers(array_map(fn ($d) => $d->jsonSerialize(), $duplicates));
	}

	public function getLastDuplicateScan(): ?int {
		$val = $this->config->getAppValue('nexeno_freeup', 'last_duplicate_scan', '');
		return !empty($val) ? (int)$val : null;
	}
}
