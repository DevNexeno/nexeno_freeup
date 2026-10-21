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

/** Licence and logged-in counts on snapshots; nullable because older months have no such data. */
class Version1000Date20261006000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('nexenofreeup_snapshots')) {
			return null;
		}

		$table = $schema->getTable('nexenofreeup_snapshots');
		$changed = false;

		foreach (['licences_in_use', 'logged_in_accounts'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::INTEGER, ['notnull' => false]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
