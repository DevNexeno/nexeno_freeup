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

class Version1000Date20260928000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('nexenofreeup_requests')) {
			$table = $schema->createTable('nexenofreeup_requests');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->addColumn('display_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('email', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('groups_list', Types::STRING, [
				'notnull' => false,
				'length' => 512,
			]);

			$table->addColumn('last_login', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('storage_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'candidate',
			]);

			$table->addColumn('proposed_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('proposed_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->addColumn('proposal_reason', Types::TEXT, [
				'notnull' => false,
			]);

			$table->addColumn('reviewed_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('reviewed_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->addColumn('review_notes', Types::TEXT, [
				'notnull' => false,
			]);

			$table->addColumn('archived_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'idx_freeup_req_uid');
			$table->addIndex(['status'], 'idx_freeup_req_stat');
			$table->addIndex(['proposed_at'], 'idx_freeup_req_prop');
		}

		if (!$schema->hasTable('nexenofreeup_snapshots')) {
			$table = $schema->createTable('nexenofreeup_snapshots');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('snapshot_month', Types::STRING, [
				'notnull' => true,
				'length' => 7,
			]);

			$table->addColumn('total_accounts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('active_accounts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('inactive_accounts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('archived_accounts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('total_storage_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('inactive_storage_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('reclaimed_storage_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['snapshot_month'], 'idx_freeup_snap_mth');
		}

		if (!$schema->hasTable('nexenofreeup_audit')) {
			$table = $schema->createTable('nexenofreeup_audit');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('action', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->addColumn('target_user', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('actor_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('actor_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('details', Types::TEXT, [
				'notnull' => false,
			]);

			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['created_at'], 'idx_freeup_aud_created');
			$table->addIndex(['target_user'], 'idx_freeup_aud_tgt');
		}

		if (!$schema->hasTable('nexenofreeup_duplicates')) {
			$table = $schema->createTable('nexenofreeup_duplicates');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('checksum', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->addColumn('file_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);

			$table->addColumn('size', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('occurrences', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
			]);

			$table->addColumn('wasted_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('mimetype', Types::STRING, [
				'notnull' => false,
				'length' => 128,
			]);

			$table->addColumn('instances_json', Types::TEXT, [
				'notnull' => false,
			]);

			$table->addColumn('last_scanned', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['checksum'], 'idx_freeup_dup_csum');
		}

		return $schema;
	}
}
