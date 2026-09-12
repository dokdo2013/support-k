<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKnowledge extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 16],
            'title' => ['type' => 'VARCHAR', 'constraint' => 200],
            'body' => ['type' => 'TEXT'],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'internal'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'draft'],
            'version' => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['kind', 'visibility', 'status', 'updated_at'], false, false, 'knowledge_public_lookup');
        $this->forge->addKey(['status', 'visibility', 'updated_at'], false, false, 'knowledge_visibility_lookup');
        $this->forge->createTable('knowledge_documents', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('knowledge_documents', true);
    }
}
