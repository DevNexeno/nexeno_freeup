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
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string|null getTargetUser()
 * @method void setTargetUser(?string $targetUser)
 * @method string|null getTargetName()
 * @method void setTargetName(?string $targetName)
 * @method string|null getActorId()
 * @method void setActorId(?string $actorId)
 * @method string|null getActorName()
 * @method void setActorName(?string $actorName)
 * @method string|null getDetails()
 * @method void setDetails(?string $details)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 */
class AuditEntry extends Entity implements \JsonSerializable {
	protected ?string $action = null;
	protected ?string $targetUser = null;
	/** What the entry is about when it is not an account, e.g. the file of a clean-up. */
	protected ?string $targetName = null;
	protected ?string $actorId = null;
	protected ?string $actorName = null;
	protected ?string $details = null;
	protected ?DateTime $createdAt = null;

	public function __construct() {
		$this->addType('action', 'string');
		$this->addType('targetUser', 'string');
		$this->addType('targetName', 'string');
		$this->addType('actorId', 'string');
		$this->addType('actorName', 'string');
		$this->addType('details', 'string');
		$this->addType('createdAt', 'datetime');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'action' => $this->action,
			'targetUser' => $this->targetUser,
			'targetName' => $this->targetName,
			'actorId' => $this->actorId,
			'actorName' => $this->actorName ?? $this->actorId,
			'details' => $this->details,
			'createdAt' => $this->createdAt?->format(DateTime::ATOM),
		];
	}
}
