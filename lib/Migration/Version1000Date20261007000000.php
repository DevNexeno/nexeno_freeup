<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Notice period: when the person was told and the earliest archive time. */
class Version1000Date20261007000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('nexenofreeup_requests')) {
			return null;
		}

		$table = $schema->getTable('nexenofreeup_requests');
		$changed = false;

		foreach (['notice_sent_at', 'archive_after'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::DATETIME, ['notnull' => false]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
