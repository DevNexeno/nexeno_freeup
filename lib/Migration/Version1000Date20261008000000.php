<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Indexes for per-page-load lookups: requests by type and status, newest audit entries per action. */
class Version1000Date20261008000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('nexenofreeup_requests')) {
			$table = $schema->getTable('nexenofreeup_requests');
			if (!$table->hasIndex('idx_freeup_req_type_stat')) {
				$table->addIndex(['request_type', 'status'], 'idx_freeup_req_type_stat');
				$changed = true;
			}
		}

		if ($schema->hasTable('nexenofreeup_audit')) {
			$table = $schema->getTable('nexenofreeup_audit');
			if (!$table->hasIndex('idx_freeup_aud_action')) {
				$table->addIndex(['action', 'created_at'], 'idx_freeup_aud_action');
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
