<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Licences;

use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Service\Accounts\OverviewService;
use OCA\NexenoFreeUp\Service\Settings\FormatSettingsService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCA\NexenoFreeUp\Util\AuditText;
use OCA\NexenoFreeUp\Util\LocaleFormatter;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

/** Monthly report for the finance contact. Counts only, no personal data (GDPR data minimisation). */
class LicenceReportService {
	public function __construct(
		private SettingsService $settings,
		private OverviewService $overview,
		private LicenceHistoryService $licenceHistory,
		private IMailer $mailer,
		private IFactory $l10nFactory,
		private FormatSettingsService $formats,
		private IURLGenerator $urlGenerator,
		private Defaults $defaults,
		private AuditMapper $auditMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Daily entry point: sends once per month, on the first run of a new month, for the month that ended.
	 * Switching the report on mid-month sends nothing at once. A failed send is retried on the next run.
	 */
	public function sendIfDue(): void {
		if (!$this->settings->isFinanceReportEnabled() || $this->settings->getFinanceReportEmail() === '') {
			return;
		}
		$thisMonth = date('Y-m');
		$last = $this->settings->getFinanceReportLastMonth();
		if ($last === '') {
			// Switched on before this check existed, or never sent: first report goes out on the 1st of next month.
			$this->settings->setFinanceReportLastMonth($thisMonth);
			return;
		}
		if ($last >= $thisMonth) {
			return;
		}
		$result = $this->send(date('Y-m', strtotime('first day of last month')), 'system', 'System');
		if ($result['sent']) {
			$this->settings->setFinanceReportLastMonth($thisMonth);
		}
	}

	/**
	 * Sends now (the "Send now" button), using the current month so far.
	 *
	 * @return array{sent:bool,to:string,problem:?string}
	 */
	public function sendNow(string $actorId, string $actorName): array {
		return $this->send(date('Y-m'), $actorId, $actorName);
	}

	/**
	 * @return array{sent:bool,to:string,problem:?string} problem: no_address | no_mail_server | not_delivered | send_failed
	 */
	private function send(string $month, string $actorId, string $actorName): array {
		$to = $this->settings->getFinanceReportEmail();
		$result = ['sent' => false, 'to' => $to, 'problem' => null];
		if ($to === '' || !$this->settings->isValidEmail($to)) {
			$result['problem'] = 'no_address';
			return $result;
		}
		if (!$this->settings->isMailConfigured()) {
			$result['problem'] = 'no_mail_server';
			return $result;
		}

		try {
			// The finance contact has no Nextcloud account, so there is no personal locale to follow.
			$locale = $this->formats->getEmailLocale(null);
			$l = $this->l10nFactory->get('nexeno_freeup', $this->l10nFactory->findLanguage('nexeno_freeup'), $locale);
			$monthName = LocaleFormatter::monthYear(strtotime($month . '-01'), $locale);
			$report = $this->figures($month, $monthName, $l, $locale);
			$site = $this->defaults->getName();

			$template = $this->mailer->createEMailTemplate('nexeno_freeup.licence_report', ['month' => $month]);
			$template->setSubject($l->t('%s: licence report for %s', [$site, $monthName]));
			$template->addHeader();
			$template->addHeading($l->t('Licence report for %s', [$monthName]));
			$template->addBodyText($l->t('Here are the licence figures for %s from %s. They are counts only and contain no personal data. The figures for the last 12 months are attached as a CSV file.', [$monthName, $site]));
			$template->addBodyText($this->figuresHtml($report['sections']), $this->figuresText($report['sections']));
			$template->addBodyButton($l->t('Open the licence overview'), $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('nexeno_freeup.page.licences')));
			$template->addFooter();

			$message = $this->mailer->createMessage();
			$message->setTo([$to]);
			$message->useTemplate($template);
			$message->attach($this->mailer->createAttachment($report['csv'], 'licences-' . $month . '.csv', 'text/csv'));
			$failed = $this->mailer->send($message);

			if (!empty($failed)) {
				$result['problem'] = 'not_delivered';
				return $result;
			}
			$result['sent'] = true;
			$this->settings->setFinanceReportLastSent($month);
			$this->auditMapper->log('licence_report_sent', null, $actorId, $actorName, AuditText::join($month, 'Sent to ' . $to));
		} catch (\Throwable $e) {
			$result['problem'] = 'send_failed';
			$this->logger->warning('NexenoFreeUp: Could not send the licence report: ' . $e->getMessage());
		}
		return $result;
	}

	/**
	 * Two-column table per section. Nextcloud's body text sits inside a <p>, so it is closed before the table and
	 * reopened after it. The template uses vsprintf, so every % is doubled.
	 *
	 * @param array<int, array{title:string, rows:array<int, array{0:string,1:string,2?:bool}>}> $sections
	 */
	private function figuresHtml(array $sections): string {
		$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$cell = 'padding:10px 16px;font-size:15px;line-height:20px;border-top:1px solid #ededed;';
		$html = '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
			. 'style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #dadada;border-radius:8px;margin:8px 0 24px;text-align:left;">';
		foreach ($sections as $i => $section) {
			$html .= '<tr><td colspan="2" style="padding:12px 16px 8px;font-size:13px;font-weight:bold;letter-spacing:0.3px;text-transform:uppercase;color:#6b6b6b;background:#f5f5f5;'
				. ($i > 0 ? 'border-top:1px solid #dadada;' : 'border-radius:8px 8px 0 0;') . '">'
				. $e($section['title']) . '</td></tr>';
			foreach ($section['rows'] as $row) {
				$highlight = !empty($row[2]);
				$html .= '<tr>'
					. '<td style="' . $cell . 'color:#545454;text-align:left;">' . $e($row[0]) . '</td>'
					. '<td style="' . $cell . 'text-align:right;white-space:nowrap;font-weight:bold;color:' . ($highlight ? '#b45309' : '#222222') . ';">'
					. $e($row[1]) . '</td>'
					. '</tr>';
			}
		}
		return str_replace('%', '%%', $html . '</table><p style="margin:0;">');
	}

	/**
	 * @param array<int, array{title:string, rows:array<int, array{0:string,1:string,2?:bool}>}> $sections
	 */
	private function figuresText(array $sections): string {
		$lines = [];
		foreach ($sections as $section) {
			$lines[] = '';
			$lines[] = mb_strtoupper($section['title']);
			foreach ($section['rows'] as $row) {
				$lines[] = $row[0] . ': ' . $row[1];
			}
		}
		return implode("\n", $lines);
	}

	/**
	 * @return array{sections: array<int, array{title:string, rows:array<int, array{0:string,1:string,2?:bool}>}>, csv: string}
	 */
	private function figures(string $month, string $monthName, IL10N $l, string $locale): array {
		$overview = $this->overview->getDashboardOverview(true);
		$history = $this->licenceHistory->getLicenceHistory();
		$row = null;
		foreach ($history as $h) {
			if (($h['month'] ?? '') === $month) {
				$row = $h;
			}
		}
		$months = $this->settings->getInactivityThresholdMonths();
		$dash = $l->t('Not recorded');
		$count = static fn ($v): string => LocaleFormatter::number((int)$v, $locale);

		$sections = [
			[
				'title' => $monthName,
				'rows' => [
					[$l->t('Licences in use'), $count($row['licencesInUse'] ?? $overview['licencesInUse'] ?? 0)],
					[$l->t('Logged in during the month'), isset($row['loggedIn']) ? $count($row['loggedIn']) : $dash],
					[$l->t('Paid but no login'), isset($row['notUsed']) ? $count($row['notUsed']) : $dash, (int)($row['notUsed'] ?? 0) > 0],
				],
			],
			[
				'title' => $l->t('Right now'),
				'rows' => [
					[$l->t('Accounts with no login for %s+ months', [$months]), $count($overview['inactiveCandidates'] ?? 0)],
					[$l->t('Storage held by inactive accounts'), LocaleFormatter::bytes((int)($overview['inactiveStorageBytes'] ?? 0), $locale)],
					[$l->t('Space used by duplicate files'), LocaleFormatter::bytes((int)($overview['duplicateWastedBytes'] ?? 0), $locale)],
					[$l->t('Licences freed by FreeUp so far'), $count($overview['archivedAccounts'] ?? 0)],
				],
			],
		];

		$out = fopen('php://temp', 'r+');
		fputcsv($out, [$l->t('Month'), $l->t('Licences in use'), $l->t('Logged in during the month'), $l->t('No login')], ',', '"', '\\');
		// Only months up to the one being reported: a month that has just begun does not belong in this report.
		foreach (array_reverse($history) as $h) {
			if (($h['month'] ?? $month) > $month) {
				continue;
			}
			fputcsv($out, [LocaleFormatter::monthYear(strtotime(($h['month'] ?? $month) . '-01'), $locale), $h['licencesInUse'] ?? 0, $h['loggedIn'] ?? '', $h['notUsed'] ?? ''], ',', '"', '\\');
		}
		rewind($out);
		$csv = "\xEF\xBB\xBF" . stream_get_contents($out);
		fclose($out);

		return ['sections' => $sections, 'csv' => $csv];
	}
}
