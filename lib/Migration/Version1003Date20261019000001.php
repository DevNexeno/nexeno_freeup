<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Second half of the table rename: drop the nexenofreeup_* tables. It is a schema change, so Nextcloud's schema check, which
 * replays every migration, ends up with exactly the nx_freeup_* tables. It refuses to drop a table whose rows were not copied.
 */
class Version1003Date20261019000001 extends SimpleMigrationStep {
	private const RENAMES = [
		'nexenofreeup_requests' => 'nx_freeup_requests',
		'nexenofreeup_snapshots' => 'nx_freeup_snapshots',
		'nexenofreeup_audit' => 'nx_freeup_audit',
		'nexenofreeup_duplicates' => 'nx_freeup_duplicates',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** Real migrations only: Nextcloud's schema check replays changeSchema() without a database behind it, so the check lives here. */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach (self::RENAMES as $old => $new) {
			if (!$this->db->tableExists($old)) {
				continue;
			}
			if (!$this->db->tableExists($new)) {
				throw new \RuntimeException("{$new} does not exist, so {$old} will not be dropped.");
			}
			$oldCount = $this->countRows($old);
			$newCount = $this->countRows($new);
			if ($oldCount > $newCount) {
				throw new \RuntimeException("{$old} still holds {$oldCount} rows but {$new} only {$newCount}. Nothing was dropped.");
			}
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		foreach (array_keys(self::RENAMES) as $old) {
			if ($schema->hasTable($old)) {
				$schema->dropTable($old);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}

	private function countRows(string $table): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'total'))->from($table);
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		return $total;
	}
}
