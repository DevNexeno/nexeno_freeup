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
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * First half of renaming the tables from nexenofreeup_* to nx_freeup_*: create the new tables and copy every row across,
 * checking the counts. A rename does not carry over index and sequence names on every database, so the old tables are
 * dropped by the next migration, once this one has proven the copy. An installation that never had the old tables simply
 * gets the new ones.
 */
class Version1002Date20261019000000 extends SimpleMigrationStep {
	private const RENAMES = [
		'nexenofreeup_requests' => 'nx_freeup_requests',
		'nexenofreeup_snapshots' => 'nx_freeup_snapshots',
		'nexenofreeup_audit' => 'nx_freeup_audit',
		'nexenofreeup_duplicates' => 'nx_freeup_duplicates',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/** @return array<string, array{columns: array<int, array{0:string,1:string,2:array<string,mixed>}>, indexes: array<string, string[]>}> */
	private function definitions(): array {
		$id = ['id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]];
		$str = static fn (string $name, int $length, bool $notnull = false, $default = null): array => [$name, Types::STRING, array_filter(
			['notnull' => $notnull, 'length' => $length, 'default' => $default],
			static fn ($v, $k) => $k !== 'default' || $v !== null,
			ARRAY_FILTER_USE_BOTH,
		)];
		$big = static fn (string $name, bool $notnull = true, $default = 0): array => [$name, Types::BIGINT, ['notnull' => $notnull, 'default' => $default]];
		$int = static fn (string $name, bool $notnull = true, $default = 0): array => [$name, Types::INTEGER, $notnull ? ['notnull' => true, 'default' => $default] : ['notnull' => false]];
		$time = static fn (string $name, bool $notnull = false): array => [$name, Types::DATETIME, ['notnull' => $notnull]];
		$text = static fn (string $name): array => [$name, Types::TEXT, ['notnull' => false]];

		return [
			'nx_freeup_requests' => [
				'columns' => [
					$id, $str('user_id', 64, true), $str('display_name', 255), $str('email', 255), $str('groups_list', 512),
					$big('last_login'), $big('storage_bytes'), $str('status', 32, true, 'candidate'),
					$str('proposed_by', 64), $time('proposed_at'), $text('proposal_reason'),
					$str('reviewed_by', 64), $time('reviewed_at'), $text('review_notes'), $time('archived_at'),
					$str('request_type', 16, true, 'account'), $str('target_ref', 255), $str('target_name', 255), $text('target_meta'),
					$time('notice_sent_at'), $time('archive_after'),
				],
				'indexes' => [
					'nx_freeup_req_uid' => ['user_id'],
					'nx_freeup_req_stat' => ['status'],
					'nx_freeup_req_prop' => ['proposed_at'],
					'nx_freeup_req_type_stat' => ['request_type', 'status'],
				],
			],
			'nx_freeup_snapshots' => [
				'columns' => [
					$id, $str('snapshot_month', 7, true),
					$int('total_accounts'), $int('active_accounts'), $int('inactive_accounts'), $int('archived_accounts'),
					$big('total_storage_bytes'), $big('inactive_storage_bytes'), $big('reclaimed_storage_bytes'),
					$time('created_at', true), $int('licences_in_use', false), $int('logged_in_accounts', false),
				],
				'indexes' => ['nx_freeup_snap_mth' => ['snapshot_month']],
			],
			'nx_freeup_audit' => [
				'columns' => [
					$id, $str('action', 64, true), $str('target_user', 64), $str('actor_id', 64), $str('actor_name', 255),
					$text('details'), $time('created_at', true), $str('target_name', 255),
				],
				'indexes' => [
					'nx_freeup_aud_action' => ['action', 'created_at'],
					'nx_freeup_aud_created' => ['created_at'],
					'nx_freeup_aud_tgt' => ['target_user'],
				],
			],
			'nx_freeup_duplicates' => [
				'columns' => [
					$id, $str('checksum', 64, true), $str('file_name', 255, true), $big('size'), $int('occurrences', true, 1),
					$big('wasted_bytes'), $str('mimetype', 128), $text('instances_json'), $time('last_scanned', true),
				],
				'indexes' => ['nx_freeup_dup_csum' => ['checksum']],
			],
		];
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		foreach ($this->definitions() as $name => $definition) {
			if ($schema->hasTable($name)) {
				continue;
			}
			$table = $schema->createTable($name);
			foreach ($definition['columns'] as [$column, $type, $columnOptions]) {
				$table->addColumn($column, $type, $columnOptions);
			}
			$table->setPrimaryKey(['id']);
			foreach ($definition['indexes'] as $indexName => $columns) {
				$table->addIndex($columns, $indexName);
			}
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		foreach (self::RENAMES as $old => $new) {
			if (!$this->db->tableExists($old)) {
				continue;
			}

			if ($this->countRows($new) === 0) {
				$this->copyRows($schema, $old, $new);
			}

			$oldCount = $this->countRows($old);
			$newCount = $this->countRows($new);
			if ($oldCount !== $newCount) {
				throw new \RuntimeException("Copying {$old} to {$new} stopped: {$oldCount} rows before, {$newCount} after. The old table was kept.");
			}

			$this->syncIdCounter($new);
			$output->info("Copied {$newCount} rows from {$old} to {$new}");
		}
	}

	private function countRows(string $table): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'total'))->from($table);
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		return $total;
	}

	private function copyRows(ISchemaWrapper $schema, string $old, string $new): void {
		$wanted = array_column($this->definitions()[$new]['columns'], 0);
		$present = $schema->hasTable($old) ? $this->columnNames($schema, $old) : $wanted;
		$shared = array_values(array_intersect($present, $wanted));
		$list = implode(', ', array_map(static fn (string $c) => '`' . $c . '`', $shared));
		$this->db->executeStatement("INSERT INTO `*PREFIX*{$new}` ({$list}) SELECT {$list} FROM `*PREFIX*{$old}`");
	}

	/**
	 * Older database libraries key the column list by name, newer ones return a plain list; ask each column for its name.
	 *
	 * @return string[]
	 */
	private function columnNames(ISchemaWrapper $schema, string $table): array {
		return array_values(array_map(
			static fn ($column) => method_exists($column, 'getName') ? $column->getName() : $column->getObjectName()->toString(),
			$schema->getTable($table)->getColumns(),
		));
	}

	/** PostgreSQL keeps its own counter, which explicit ids do not move. */
	private function syncIdCounter(string $table): void {
		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
			return;
		}
		$full = $this->config->getSystemValueString('dbtableprefix', 'oc_') . $table;
		$this->db->executeStatement(
			"SELECT setval(pg_get_serial_sequence('{$full}', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM `*PREFIX*{$table}`"
		);
	}
}
