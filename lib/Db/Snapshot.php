<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getSnapshotMonth()
 * @method void setSnapshotMonth(string $snapshotMonth)
 * @method int getTotalAccounts()
 * @method void setTotalAccounts(int $totalAccounts)
 * @method int getActiveAccounts()
 * @method void setActiveAccounts(int $activeAccounts)
 * @method int getInactiveAccounts()
 * @method void setInactiveAccounts(int $inactiveAccounts)
 * @method int getArchivedAccounts()
 * @method void setArchivedAccounts(int $archivedAccounts)
 * @method int getTotalStorageBytes()
 * @method void setTotalStorageBytes(int $totalStorageBytes)
 * @method int getInactiveStorageBytes()
 * @method void setInactiveStorageBytes(int $inactiveStorageBytes)
 * @method int getReclaimedStorageBytes()
 * @method void setReclaimedStorageBytes(int $reclaimedStorageBytes)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method int|null getLicencesInUse()
 * @method void setLicencesInUse(?int $licencesInUse)
 * @method int|null getLoggedInAccounts()
 * @method void setLoggedInAccounts(?int $loggedInAccounts)
 */
class Snapshot extends Entity implements \JsonSerializable {
	protected ?string $snapshotMonth = null;
	protected int $totalAccounts = 0;
	protected int $activeAccounts = 0;
	protected int $inactiveAccounts = 0;
	protected int $archivedAccounts = 0;
	protected int $totalStorageBytes = 0;
	protected int $inactiveStorageBytes = 0;
	protected int $reclaimedStorageBytes = 0;
	protected ?int $licencesInUse = null;
	protected ?int $loggedInAccounts = null;
	protected ?DateTime $createdAt = null;

	public function __construct() {
		$this->addType('snapshotMonth', 'string');
		$this->addType('totalAccounts', 'integer');
		$this->addType('activeAccounts', 'integer');
		$this->addType('inactiveAccounts', 'integer');
		$this->addType('archivedAccounts', 'integer');
		$this->addType('totalStorageBytes', 'integer');
		$this->addType('inactiveStorageBytes', 'integer');
		$this->addType('reclaimedStorageBytes', 'integer');
		$this->addType('licencesInUse', 'integer');
		$this->addType('loggedInAccounts', 'integer');
		$this->addType('createdAt', 'datetime');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'snapshotMonth' => $this->snapshotMonth,
			'totalAccounts' => $this->totalAccounts,
			'activeAccounts' => $this->activeAccounts,
			'inactiveAccounts' => $this->inactiveAccounts,
			'archivedAccounts' => $this->archivedAccounts,
			'totalStorageBytes' => $this->totalStorageBytes,
			'inactiveStorageBytes' => $this->inactiveStorageBytes,
			'reclaimedStorageBytes' => $this->reclaimedStorageBytes,
			'licencesInUse' => $this->licencesInUse,
			'loggedInAccounts' => $this->loggedInAccounts,
			'createdAt' => $this->createdAt?->format(DateTime::ATOM),
		];
	}
}
