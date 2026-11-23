<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Penanda notifikasi (pengingat otomatis) yang sudah dibaca, per akun. */
class NotificationReads extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11],
            'nkey'    => ['type' => 'VARCHAR', 'constraint' => 120],
            'read_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'nkey']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_reads', true);
    }

    public function down()
    {
        $this->forge->dropTable('notification_reads', true);
    }
}
