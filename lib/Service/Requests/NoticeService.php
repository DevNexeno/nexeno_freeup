<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Requests;

use DateTime;
use OCA\NexenoFreeUp\Service\Settings\FormatSettingsService;
use OCA\NexenoFreeUp\Util\LocaleFormatter;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Never throws: a failed channel is reported in the result so the workflow can carry on.
 */
class NoticeService {
	public function __construct(
		private IMailer $mailer,
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
		private FormatSettingsService $formats,
		private INotificationManager $notificationManager,
		private Defaults $defaults,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}
	 */
	public function sendArchiveNotice(IUser $user, DateTime $on, int $requestId): array {
		return $this->deliver($user, $on, $requestId, 'archive_notice', [], function ($l, string $name, string $date, string $site): array {
			return [
				'subject' => $l->t('Your %s account will be disabled on %s', [$site, $date]),
				'heading' => $l->t('Your account will be disabled soon'),
				'paragraphs' => [
					$l->t('Hello %s,', [$name]),
					$l->t('You have not used your %s account for a while, so it is going to be disabled on %s.', [$site, $date]),
					$l->t('After that you can no longer log in, but your files are kept safely and the account can be brought back by an administrator.'),
					$l->t('If you still need your account, just log in before %s and nothing will change.', [$date]),
				],
				'button' => $l->t('Log in to keep my account'),
			];
		});
	}

	/**
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}
	 */
	public function sendCleanupNotice(IUser $user, string $fileName, DateTime $on, int $requestId): array {
		return $this->deliver($user, $on, $requestId, 'cleanup_notice', ['file' => $fileName], function ($l, string $name, string $date, string $site) use ($fileName): array {
			return [
				'subject' => $l->t('Your copy of %s will be replaced on %s', [$fileName, $date]),
				'heading' => $l->t('A duplicate file in your account will be replaced'),
				'paragraphs' => [
					$l->t('Hello %s,', [$name]),
					$l->t('The file "%s" in your account is an exact copy of a file that other users on %s also have. To free up storage, on %s your copy will be moved to your trash and you will see one shared copy in your files, read-only.', [$fileName, $site, $date]),
					$l->t('Your copy is moved to your trash bin first, so you can restore it from there if you need it. You do not have to do anything. If you delete your copy yourself before then, that is fine too.'),
					$l->t('A file that has been changed in the meantime is no longer an exact copy and is left alone.'),
				],
				'button' => $l->t('Open my files'),
			];
		});
	}

	/**
	 * Email only: the person can no longer log in, so a notification would never be seen.
	 *
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}
	 */
	public function sendArchivedMessage(IUser $user, int $requestId): array {
		$this->dismissArchiveNotice($user->getUID(), $requestId);
		return $this->deliver($user, new DateTime(), $requestId, 'archive_done', [], function ($l, string $name, string $date, string $site): array {
			return [
				'subject' => $l->t('Your %s account has been disabled', [$site]),
				'heading' => $l->t('Your account has been disabled'),
				'paragraphs' => [
					$l->t('Hello %s,', [$name]),
					$l->t('Your %s account was disabled on %s because it had not been used for a while. You can no longer log in.', [$site, $date]),
					$l->t('Your files have not been deleted. They are kept safely, and an administrator can bring the account back at any time.'),
					$l->t('If you need your account or your files again, please contact your administrator.'),
				],
				'button' => '',
			];
		}, false);
	}

	/**
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}
	 */
	public function sendRestoredMessage(IUser $user, int $requestId): array {
		return $this->deliver($user, new DateTime(), $requestId, 'restore_done', [], function ($l, string $name, string $date, string $site): array {
			return [
				'subject' => $l->t('Your %s account has been restored', [$site]),
				'heading' => $l->t('Your account is active again'),
				'paragraphs' => [
					$l->t('Hello %s,', [$name]),
					$l->t('Good news: an administrator restored your %s account on %s. You can log in again with your usual password.', [$site, $date]),
					$l->t('All your files are exactly as you left them.'),
				],
				'button' => $l->t('Log in'),
			];
		});
	}

	/**
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}
	 */
	public function sendCleanupDoneMessage(IUser $user, string $fileName, int $requestId): array {
		$this->dismissNotice($user->getUID(), 'cleanup_notice', $requestId);
		return $this->deliver($user, new DateTime(), $requestId, 'cleanup_done', ['file' => $fileName], function ($l, string $name, string $date, string $site) use ($fileName): array {
			return [
				'subject' => $l->t('Your copy of %s has been replaced by a shared copy', [$fileName]),
				'heading' => $l->t('A duplicate file in your account was replaced'),
				'paragraphs' => [
					$l->t('Hello %s,', [$name]),
					$l->t('Your copy of "%s" was an exact copy of a file other users on %s also have. To free up storage, on %s your copy was moved to your trash bin.', [$fileName, $site, $date]),
					$l->t('In its place you now have one shared copy of the same file, read-only, in the same folder.'),
					$l->t('Need your own copy back? Restore it from your trash bin before the trash is emptied.'),
				],
				'button' => $l->t('Open my files'),
			];
		});
	}

	public function dismissNotice(string $userId, string $objectType, int $requestId): void {
		try {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp('nexeno_freeup')
				->setUser($userId)
				->setObject($objectType, (string)$requestId);
			$this->notificationManager->markProcessed($notification);
		} catch (\Throwable $e) {
			// non-critical
		}
	}

	public function dismissArchiveNotice(string $userId, int $requestId): void {
		$this->dismissNotice($userId, 'archive_notice', $requestId);
	}

	/**
	 * @param array<string,string> $extraParams extra notification parameters (e.g. file)
	 * @param callable(object,string,string,string):array{subject:string,heading:string,paragraphs:string[],button:string} $content
	 *                                                                                                                              an empty button text means no button
	 * @param bool $notify false = email only (e.g. the account is already disabled and cannot see notifications)
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}
	 */
	private function deliver(IUser $user, DateTime $on, int $requestId, string $type, array $extraParams, callable $content, bool $notify = true): array {
		$result = ['emailed' => false, 'notified' => false, 'emailProblem' => null];

		// Words follow the person's language; the date follows the email locale setting.
		$locale = $this->formats->getEmailLocale($user);
		$l = $this->l10nFactory->get('nexeno_freeup', $this->l10nFactory->getUserLanguage($user), $locale);
		$date = LocaleFormatter::date($on, $locale, 'long');
		$name = $user->getDisplayName() ?: $user->getUID();
		$text = $content($l, $name, $date, $this->defaults->getName());

		// A resend replaces the earlier notification instead of piling up.
		if ($notify) {
			$this->dismissNotice($user->getUID(), $type, $requestId);
			try {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp('nexeno_freeup')
					->setUser($user->getUID())
					->setDateTime(new DateTime())
					->setObject($type, (string)$requestId)
					->setSubject($type, ['date' => $date] + $extraParams);
				$this->notificationManager->notify($notification);
				$result['notified'] = true;
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Could not create notice notification for ' . $user->getUID() . ': ' . $e->getMessage());
			}
		}

		$email = (string)$user->getEMailAddress();
		if ($email === '' || !$this->mailer->validateMailAddress($email)) {
			$result['emailProblem'] = 'no_address';
			return $result;
		}

		try {
			$template = $this->mailer->createEMailTemplate('nexeno_freeup.' . $type, [
				'userId' => $user->getUID(),
				'date' => $date,
			]);
			$template->setSubject($text['subject']);
			$template->addHeader();
			$template->addHeading($text['heading']);
			foreach ($text['paragraphs'] as $paragraph) {
				$template->addBodyText($paragraph);
			}
			if ($text['button'] !== '') {
				$template->addBodyButton($text['button'], $this->urlGenerator->getAbsoluteURL('/'));
			}
			$template->addFooter();

			$message = $this->mailer->createMessage();
			$message->setTo([$email => $name]);
			$message->useTemplate($template);
			$failed = $this->mailer->send($message);

			if (empty($failed)) {
				$result['emailed'] = true;
			} else {
				$result['emailProblem'] = 'not_delivered';
			}
		} catch (\Throwable $e) {
			$result['emailProblem'] = 'send_failed';
			$this->logger->warning('NexenoFreeUp: Could not email notice to ' . $user->getUID() . ': ' . $e->getMessage());
		}

		return $result;
	}
}
