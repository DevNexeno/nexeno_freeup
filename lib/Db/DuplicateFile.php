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
 * @method string getChecksum()
 * @method void setChecksum(string $checksum)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method int getOccurrences()
 * @method void setOccurrences(int $occurrences)
 * @method int getWastedBytes()
 * @method void setWastedBytes(int $wastedBytes)
 * @method string|null getMimetype()
 * @method void setMimetype(?string $mimetype)
 * @method string|null getInstancesJson()
 * @method void setInstancesJson(?string $instancesJson)
 * @method DateTime getLastScanned()
 * @method void setLastScanned(DateTime $lastScanned)
 */
class DuplicateFile extends Entity implements \JsonSerializable {
	protected ?string $checksum = null;
	protected ?string $fileName = null;
	protected int $size = 0;
	protected int $occurrences = 1;
	protected int $wastedBytes = 0;
	protected ?string $mimetype = null;
	protected ?string $instancesJson = null;
	protected ?DateTime $lastScanned = null;

	public function __construct() {
		$this->addType('checksum', 'string');
		$this->addType('fileName', 'string');
		$this->addType('size', 'integer');
		$this->addType('occurrences', 'integer');
		$this->addType('wastedBytes', 'integer');
		$this->addType('mimetype', 'string');
		$this->addType('instancesJson', 'string');
		$this->addType('lastScanned', 'datetime');
	}

	public function jsonSerialize(): array {
		$instances = [];
		if (!empty($this->instancesJson)) {
			$decoded = json_decode($this->instancesJson, true);
			if (is_array($decoded)) {
				$instances = $decoded;
			}
		}

		return [
			'id' => $this->getId(),
			'checksum' => $this->checksum,
			'fileName' => $this->fileName,
			'size' => $this->size,
			'occurrences' => $this->occurrences,
			'wastedBytes' => $this->wastedBytes,
			'mimetype' => $this->mimetype ?? 'application/octet-stream',
			'instances' => $instances,
			'lastScanned' => $this->lastScanned?->format(DateTime::ATOM),
		];
	}
}
