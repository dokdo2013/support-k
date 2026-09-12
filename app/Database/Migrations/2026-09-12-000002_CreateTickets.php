<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTickets extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'number' => ['type' => 'VARCHAR', 'constraint' => 24],
            'lookup_password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'requester_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'requester_email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'subject' => ['type' => 'VARCHAR', 'constraint' => 200],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'open'],
            'version' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'client_request_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'last_message_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('number', 'tickets_number_unique');
        $this->forge->addUniqueKey('client_request_key', 'tickets_request_key_unique');
        $this->forge->addKey(['status', 'last_message_at'], false, false, 'tickets_status_activity');
        $this->forge->createTable('tickets', true);

        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ticket_id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 16],
            'body' => ['type' => 'TEXT'],
            'author_user_id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'client_request_key' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'version' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['ticket_id', 'created_at'], false, false, 'ticket_messages_ticket_created');
        $this->forge->addUniqueKey(['ticket_id', 'client_request_key'], 'ticket_messages_request_unique');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ticket_messages', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_messages', true);
        $this->forge->dropTable('tickets', true);
    }
}
