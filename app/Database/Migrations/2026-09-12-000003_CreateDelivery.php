<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDelivery extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'event_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'payload' => ['type' => 'TEXT'],
            'dedupe_key' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'lease_token' => ['type' => 'CHAR', 'constraint' => 32, 'null' => true],
            'lease_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'fanned_out_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('dedupe_key', 'domain_events_dedupe_unique');
        $this->forge->addKey(['status', 'created_at'], false, false, 'domain_events_pending');
        $this->forge->createTable('domain_events', true);

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 120],
            'settings_namespace' => ['type' => 'VARCHAR', 'constraint' => 160],
            'enabled' => ['type' => 'SMALLINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['channel', 'settings_namespace'], 'notification_destinations_identity_unique');
        $this->forge->addKey(['enabled', 'id'], false, false, 'notification_destinations_enabled');
        $this->forge->createTable('notification_destinations', true);

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'event_id' => ['type' => 'CHAR', 'constraint' => 36],
            'destination_id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'attempt_count' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'cycle_attempt_count' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'manual_retry_count' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'available_at' => ['type' => 'DATETIME'],
            'lease_token' => ['type' => 'CHAR', 'constraint' => 32, 'null' => true],
            'lease_started_at' => ['type' => 'DATETIME', 'null' => true],
            'lease_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'last_result' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'last_error' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['event_id', 'destination_id'], 'delivery_jobs_event_destination_unique');
        $this->forge->addKey(['status', 'available_at'], false, false, 'delivery_jobs_available');
        $this->forge->addKey(['status', 'lease_expires_at'], false, false, 'delivery_jobs_lease');
        $this->forge->addForeignKey('event_id', 'domain_events', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('destination_id', 'notification_destinations', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('delivery_jobs', true);

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'job_id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true],
            'attempt_no' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true],
            'result' => ['type' => 'VARCHAR', 'constraint' => 24],
            'provider_reference' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'error_code' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'retry_at' => ['type' => 'DATETIME', 'null' => true],
            'started_at' => ['type' => 'DATETIME'],
            'finished_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['job_id', 'attempt_no'], 'delivery_attempts_job_attempt_unique');
        $this->forge->addForeignKey('job_id', 'delivery_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('delivery_attempts', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('delivery_attempts', true);
        $this->forge->dropTable('delivery_jobs', true);
        $this->forge->dropTable('notification_destinations', true);
        $this->forge->dropTable('domain_events', true);
    }
}
