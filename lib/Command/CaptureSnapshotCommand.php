<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Command;

use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CaptureSnapshotCommand extends Command {
	public function __construct(
		private LicenceHistoryService $licenceHistory,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('nexeno_freeup:capture-snapshot')
			->setDescription('Capture current month licence and storage snapshot into database');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<info>Capturing current month licence and storage snapshot...</info>');
		$snapshot = $this->licenceHistory->captureSnapshot();
		$output->writeln("<info>Successfully captured snapshot for month: {$snapshot->getSnapshotMonth()}</info>");
		$output->writeln("Total accounts: {$snapshot->getTotalAccounts()}");
		$output->writeln("Active accounts: {$snapshot->getActiveAccounts()}");
		$output->writeln("Inactive accounts: {$snapshot->getInactiveAccounts()}");
		$output->writeln("Archived accounts: {$snapshot->getArchivedAccounts()}");
		return Command::SUCCESS;
	}
}
