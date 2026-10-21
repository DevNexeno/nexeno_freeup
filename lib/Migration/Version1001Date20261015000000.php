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

/** Adds audit target_name: the subject when it is not an account, e.g. a clean-up file. */
class Version1001Date20261015000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('nexenofreeup_audit')) {
			return null;
		}
		$table = $schema->getTable('nexenofreeup_audit');
		if ($table->hasColumn('target_name')) {
			return null;
		}
		$table->addColumn('target_name', Types::STRING, ['notnull' => false, 'length' => 255]);
		return $schema;
	}
}
