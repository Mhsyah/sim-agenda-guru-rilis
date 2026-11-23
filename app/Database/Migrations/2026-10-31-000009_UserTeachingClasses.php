<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Pembagian mengajar: kelas yang diajar guru untuk setiap mata pelajaran. */
class UserTeachingClasses extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11],
            'subject_id'     => ['type' => 'INT', 'constraint' => 11],
            'class_group_id' => ['type' => 'INT', 'constraint' => 11],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'subject_id', 'class_group_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_teaching', true);
    }

    public function down()
    {
        $this->forge->dropTable('user_teaching', true);
    }
}
