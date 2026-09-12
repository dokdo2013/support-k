<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateInstallation extends Migration
{
    public function up()
    {
        $id = ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true];
        $text = ['type' => 'VARCHAR', 'constraint' => 255];
        $date = ['type' => 'DATETIME'];
        $this->forge->addField(['id' => $id, 'email' => $text, 'password_hash' => $text, 'display_name' => $text, 'role' => ['type' => 'VARCHAR', 'constraint' => 16], 'auth_version' => ['type' => 'INTEGER', 'default' => 1], 'created_at' => $date]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true);
        $this->forge->addField(['id' => $id, 'name' => $text, 'value' => ['type' => 'TEXT']]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('settings', true);
        $this->forge->addField(['id' => ['type' => 'VARCHAR', 'constraint' => 64], 'status' => ['type' => 'VARCHAR', 'constraint' => 32], 'schema_version' => ['type' => 'INTEGER', 'default' => 1], 'created_at' => $date]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('installation_state', true);
        $this->forge->addField(['id' => $id, 'user_id' => ['type' => 'INTEGER', 'unsigned' => true], 'code_hash' => $text, 'used_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('recovery_codes', true);
    }

    public function down()
    {
        foreach (['recovery_codes', 'installation_state', 'settings', 'users'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
