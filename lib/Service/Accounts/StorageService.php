<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Accounts;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class StorageService {
	public function __construct(
		private IConfig $config,
		private IDBConnection $db,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	/** Reads the filecache size of the user root: no recursive scan, works with every storage backend. */
	public function getUserStorageBytes(string $userId): int {
		try {
			$includeExternal = $this->config->getSystemValueBool('quota_include_external_storage', false);
			if ($includeExternal && class_exists('\OC_Helper') && class_exists('\OC_Util')) {
				try {
					\OC_Util::tearDownFS();
					\OC_Util::setupFS($userId);
					$storage = \OC_Helper::getStorageInfo('/', null, true, false);
					if (isset($storage['used'])) {
						return max(0, (int)$storage['used']);
					}
				} catch (\Throwable $e) {
					// fall back below
				}
			}

			$userFolder = $this->rootFolder->getUserFolder($userId);
			$cache = $userFolder->getStorage()->getCache()->get('');
			return $cache ? max(0, (int)$cache->getSize()) : 0;
		} catch (\OCP\Files\NotFoundException $e) {
			return 0;
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Could not load native storage for ' . $userId . ': ' . $e->getMessage());
			return 0;
		}
	}

	public function getUserStorageMap(): array {
		$storageMap = [];
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('s.id', 'f.size')
				->from('storages', 's')
				->join('s', 'filecache', 'f', $qb->expr()->eq('f.storage', 's.numeric_id'))
				->where($qb->expr()->eq('f.path', $qb->createNamedParameter('files')))
				->andWhere($qb->expr()->like('s.id', $qb->createNamedParameter('home::%')));

			$res = $qb->executeQuery();
			while ($row = $res->fetch()) {
				$uid = substr((string)$row['id'], 6); // strip 'home::'
				$storageMap[$uid] = (int)$row['size'];
			}
			$res->closeCursor();
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Failed to query storage map: ' . $e->getMessage());
		}

		return $storageMap;
	}

	public function getUserFileCountMap(): array {
		$map = [];
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('s.id')
				->selectAlias($qb->func()->count('f.fileid'), 'file_count')
				->from('storages', 's')
				->join('s', 'filecache', 'f', $qb->expr()->eq('f.storage', 's.numeric_id'))
				->join('f', 'mimetypes', 'm', $qb->expr()->eq('f.mimetype', 'm.id'))
				->where($qb->expr()->like('f.path', $qb->createNamedParameter('files/%')))
				->andWhere($qb->expr()->neq('m.mimetype', $qb->createNamedParameter('httpd/unix-directory')))
				->andWhere($qb->expr()->orX(
					$qb->expr()->like('s.id', $qb->createNamedParameter('home::%')),
					$qb->expr()->like('s.id', $qb->createNamedParameter('object::user:%'))
				))
				->groupBy('s.id');

			$res = $qb->executeQuery();
			while ($row = $res->fetch()) {
				$id = (string)$row['id'];
				$uid = str_starts_with($id, 'home::') ? substr($id, 6) : substr($id, strlen('object::user:'));
				$map[$uid] = (int)$row['file_count'];
			}
			$res->closeCursor();
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Failed to query file count map: ' . $e->getMessage());
		}
		return $map;
	}

	/**
	 * The user root folder mtime in filecache stands in for the creation time.
	 *
	 * @return array<string, int>
	 */
	public function getUserCreatedAtMap(): array {
		$createdMap = [];
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('s.id', 'f.mtime')
				->from('storages', 's')
				->join('s', 'filecache', 'f', $qb->expr()->eq('f.storage', 's.numeric_id'))
				->where($qb->expr()->eq('f.path', $qb->createNamedParameter('')))
				->andWhere($qb->expr()->orX(
					$qb->expr()->like('s.id', $qb->createNamedParameter('home::%')),
					$qb->expr()->like('s.id', $qb->createNamedParameter('object::user:%'))
				));

			$res = $qb->executeQuery();
			while ($row = $res->fetch()) {
				$id = (string)$row['id'];
				$uid = str_starts_with($id, 'home::') ? substr($id, 6) : substr($id, strlen('object::user:'));
				$mtime = (int)$row['mtime'];
				if ($mtime > 0) {
					$createdMap[$uid] = $mtime;
				}
			}
			$res->closeCursor();
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Failed to query created at map: ' . $e->getMessage());
		}

		return $createdMap;
	}

	public function getStorageCapacityBytes(): int {
		$override = (int)$this->config->getAppValue('nexeno_freeup', 'storage_capacity_bytes', '0');
		if ($override > 0) {
			return $override;
		}
		$dir = (string)$this->config->getSystemValue('datadirectory', '');
		$total = $dir !== '' ? @disk_total_space($dir) : false;
		return $total === false ? 0 : (int)$total;
	}
}
