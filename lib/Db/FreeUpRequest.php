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
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getDisplayName()
 * @method void setDisplayName(?string $displayName)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getGroupsList()
 * @method void setGroupsList(?string $groupsList)
 * @method int getLastLogin()
 * @method void setLastLogin(int $lastLogin)
 * @method int getStorageBytes()
 * @method void setStorageBytes(int $storageBytes)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getProposedBy()
 * @method void setProposedBy(?string $proposedBy)
 * @method DateTime|null getProposedAt()
 * @method void setProposedAt(?DateTime $proposedAt)
 * @method string|null getProposalReason()
 * @method void setProposalReason(?string $proposalReason)
 * @method string|null getReviewedBy()
 * @method void setReviewedBy(?string $reviewedBy)
 * @method DateTime|null getReviewedAt()
 * @method void setReviewedAt(?DateTime $reviewedAt)
 * @method string|null getReviewNotes()
 * @method void setReviewNotes(?string $reviewNotes)
 * @method DateTime|null getArchivedAt()
 * @method void setArchivedAt(?DateTime $archivedAt)
 * @method string getRequestType()
 * @method void setRequestType(string $requestType)
 * @method string|null getTargetRef()
 * @method void setTargetRef(?string $targetRef)
 * @method string|null getTargetName()
 * @method void setTargetName(?string $targetName)
 * @method string|null getTargetMeta()
 * @method void setTargetMeta(?string $targetMeta)
 * @method DateTime|null getNoticeSentAt()
 * @method void setNoticeSentAt(?DateTime $noticeSentAt)
 * @method DateTime|null getArchiveAfter()
 * @method void setArchiveAfter(?DateTime $archiveAfter)
 */
class FreeUpRequest extends Entity implements \JsonSerializable {
	protected ?string $userId = null;
	protected ?string $displayName = null;
	protected ?string $email = null;
	protected ?string $groupsList = null;
	protected int $lastLogin = 0;
	protected int $storageBytes = 0;
	protected string $status = 'candidate';
	protected ?string $proposedBy = null;
	protected ?DateTime $proposedAt = null;
	protected ?string $proposalReason = null;
	protected ?string $reviewedBy = null;
	protected ?DateTime $reviewedAt = null;
	protected ?string $reviewNotes = null;
	protected ?DateTime $archivedAt = null;
	protected string $requestType = 'account';
	protected ?string $targetRef = null;
	protected ?string $targetName = null;
	protected ?string $targetMeta = null;
	protected ?DateTime $noticeSentAt = null;
	protected ?DateTime $archiveAfter = null;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('displayName', 'string');
		$this->addType('email', 'string');
		$this->addType('groupsList', 'string');
		$this->addType('lastLogin', 'integer');
		$this->addType('storageBytes', 'integer');
		$this->addType('status', 'string');
		$this->addType('proposedBy', 'string');
		$this->addType('proposedAt', 'datetime');
		$this->addType('proposalReason', 'string');
		$this->addType('reviewedBy', 'string');
		$this->addType('reviewedAt', 'datetime');
		$this->addType('reviewNotes', 'string');
		$this->addType('archivedAt', 'datetime');
		$this->addType('requestType', 'string');
		$this->addType('targetRef', 'string');
		$this->addType('targetName', 'string');
		$this->addType('targetMeta', 'string');
		$this->addType('noticeSentAt', 'datetime');
		$this->addType('archiveAfter', 'datetime');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->userId,
			'displayName' => $this->displayName ?? $this->userId,
			'email' => $this->email ?? '',
			'groups' => !empty($this->groupsList) ? explode(',', $this->groupsList) : [],
			'lastLogin' => $this->lastLogin,
			'storageBytes' => $this->storageBytes,
			'status' => $this->status,
			'proposedBy' => $this->proposedBy,
			'proposedAt' => $this->proposedAt?->format(DateTime::ATOM),
			'proposalReason' => $this->proposalReason,
			'reviewedBy' => $this->reviewedBy,
			'reviewedAt' => $this->reviewedAt?->format(DateTime::ATOM),
			'reviewNotes' => $this->reviewNotes,
			'archivedAt' => $this->archivedAt?->format(DateTime::ATOM),
			'requestType' => $this->requestType,
			'targetRef' => $this->targetRef,
			'targetName' => $this->targetName,
			'noticeSentAt' => $this->noticeSentAt?->format(DateTime::ATOM),
			'archiveAfter' => $this->archiveAfter?->format(DateTime::ATOM),
			'targetMeta' => !empty($this->targetMeta) ? (json_decode($this->targetMeta, true) ?: null) : null,
		];
	}
}
