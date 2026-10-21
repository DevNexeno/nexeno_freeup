<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Notification;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'nexeno_freeup';
	}

	public function getName(): string {
		return $this->l10nFactory->get('nexeno_freeup')->t('Nexeno FreeUp');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'nexeno_freeup') {
			throw new UnknownNotificationException('Unknown app');
		}

		$l = $this->l10nFactory->get('nexeno_freeup', $languageCode);

		switch ($notification->getSubject()) {
			case 'archival_proposal_submitted':
				$params = $notification->getSubjectParameters();
				$displayName = (string)($params['displayName'] ?? $params['userId'] ?? 'user');
				$proposedBy = (string)($params['proposedBy'] ?? 'admin');

				$notification->setParsedSubject(
					$l->t('Request to disable %s, proposed by @%s', [$displayName, $proposedBy])
				);
				$notification->setRichSubject(
					$l->t('Request to disable {user}, proposed by @{proposer}'),
					[
						'user' => [
							'type' => 'user',
							'id' => (string)($params['userId'] ?? ''),
							'name' => $displayName,
						],
						'proposer' => [
							'type' => 'user',
							'id' => $proposedBy,
							'name' => $proposedBy,
						],
					]
				);

				$notification->setLink(
					$this->urlGenerator->linkToRouteAbsolute('nexeno_freeup.page.approvals')
				);

				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL(
						$this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg')
					)
				);

				return $notification;
			case 'archive_notice':
				$params = $notification->getSubjectParameters();
				$date = (string)($params['date'] ?? '');

				$notification->setParsedSubject(
					$l->t('Your account will be disabled on %s. Log in before then to keep it.', [$date])
				);
				$notification->setRichSubject(
					$l->t('Your account will be disabled on {date}. Log in before then to keep it.'),
					['date' => ['type' => 'highlight', 'id' => $date, 'name' => $date]]
				);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index'));
				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg'))
				);
				return $notification;
			case 'cleanup_batch_submitted':
				$params = $notification->getSubjectParameters();
				$count = (int)($params['count'] ?? 0);
				$proposedBy = (string)($params['proposedBy'] ?? 'admin');

				$notification->setParsedSubject(
					$l->n('Clean-up of %n duplicate file requested by @%s', 'Clean-up of %n duplicate files requested by @%s', $count, [$proposedBy])
				);
				$notification->setRichSubject(
					$l->n('Clean-up of {count} duplicate file requested by @{proposer}', 'Clean-up of {count} duplicate files requested by @{proposer}', $count),
					[
						'count' => ['type' => 'highlight', 'id' => (string)$count, 'name' => (string)$count],
						'proposer' => ['type' => 'user', 'id' => $proposedBy, 'name' => $proposedBy],
					]
				);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('nexeno_freeup.page.approvals'));
				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg'))
				);
				return $notification;
			case 'cleanup_notice':
				$params = $notification->getSubjectParameters();
				$file = (string)($params['file'] ?? '');
				$date = (string)($params['date'] ?? '');

				$notification->setParsedSubject(
					$l->t('Your copy of %s will be replaced by a shared copy on %s.', [$file, $date])
				);
				$notification->setRichSubject(
					$l->t('Your copy of {file} will be replaced by a shared copy on {date}.'),
					[
						'file' => ['type' => 'highlight', 'id' => $file, 'name' => $file],
						'date' => ['type' => 'highlight', 'id' => $date, 'name' => $date],
					]
				);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index'));
				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg'))
				);
				return $notification;
			case 'restore_done':
				$notification->setParsedSubject($l->t('Your account was restored. Welcome back: all your files are where you left them.'));
				$notification->setRichSubject($l->t('Your account was restored. Welcome back: all your files are where you left them.'));
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index'));
				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg'))
				);
				return $notification;
			case 'cleanup_done':
				$params = $notification->getSubjectParameters();
				$file = (string)($params['file'] ?? '');

				$notification->setParsedSubject(
					$l->t('Your copy of %s was moved to your trash. A shared, read-only copy is now in its place.', [$file])
				);
				$notification->setRichSubject(
					$l->t('Your copy of {file} was moved to your trash. A shared, read-only copy is now in its place.'),
					['file' => ['type' => 'highlight', 'id' => $file, 'name' => $file]]
				);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('files.view.index'));
				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg'))
				);
				return $notification;
			case 'cleanup_proposal_submitted':
				$params = $notification->getSubjectParameters();
				$name = (string)($params['displayName'] ?? 'duplicate files');
				$proposedBy = (string)($params['proposedBy'] ?? 'admin');

				$notification->setParsedSubject(
					$l->t('Duplicate clean-up of %s requested by @%s', [$name, $proposedBy])
				);
				$notification->setRichSubject(
					$l->t('Duplicate clean-up of {item} requested by @{proposer}'),
					[
						'item' => ['type' => 'highlight', 'id' => $name, 'name' => $name],
						'proposer' => ['type' => 'user', 'id' => $proposedBy, 'name' => $proposedBy],
					]
				);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('nexeno_freeup.page.approvals'));
				$notification->setIcon(
					$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('nexeno_freeup', 'app-dark.svg'))
				);
				return $notification;
			default:
				throw new UnknownNotificationException('Unknown subject');
		}
	}
}
