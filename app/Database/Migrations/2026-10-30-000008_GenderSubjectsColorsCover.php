<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * - users.gender (L/P) + tabel user_subjects (mapel yang diajarkan guru)
 * - school_calendar_events.color (warna kegiatan di kalender)
 * - school_profiles.school_photo (foto sekolah untuk sampul Buku Agenda Guru)
 */
class GenderSubjectsColorsCover extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', ['gender' => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => true]]);
        $this->forge->addColumn('school_calendar_events', ['color' => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true]]);
        $this->forge->addColumn('school_profiles', ['school_photo' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]]);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11],
            'subject_id' => ['type' => 'INT', 'constraint' => 11],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'subject_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_subjects', true);
    }

    public function down()
    {
        $this->forge->dropTable('user_subjects', true);
        $this->forge->dropColumn('users', 'gender');
        $this->forge->dropColumn('school_calendar_events', 'color');
        $this->forge->dropColumn('school_profiles', 'school_photo');
    }
}
