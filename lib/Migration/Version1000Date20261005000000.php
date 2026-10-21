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

/** Lets the approval queue hold duplicate clean-up requests next to account archival requests. */
class Version1000Date20261005000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('nexenofreeup_requests')) {
			return null;
		}

		$table = $schema->getTable('nexenofreeup_requests');
		$changed = false;

		if (!$table->hasColumn('request_type')) {
			$table->addColumn('request_type', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'account']);
			$changed = true;
		}
		if (!$table->hasColumn('target_ref')) {
			$table->addColumn('target_ref', Types::STRING, ['notnull' => false, 'length' => 255]);
			$changed = true;
		}
		if (!$table->hasColumn('target_name')) {
			$table->addColumn('target_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$changed = true;
		}
		if (!$table->hasColumn('target_meta')) {
			$table->addColumn('target_meta', Types::TEXT, ['notnull' => false]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
