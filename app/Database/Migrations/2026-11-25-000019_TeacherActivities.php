<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Kegiatan guru di luar KBM (rapat, upacara, piket, pelatihan, kepanitiaan, …) dengan lampiran bukti opsional. */
class TeacherActivities extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11],
            'academic_year_id' => ['type' => 'INT', 'constraint' => 11],
            'semester_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'activity_date'    => ['type' => 'DATE'],
            'activity_type'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'start_time'       => ['type' => 'TIME', 'null' => true],
            'end_time'         => ['type' => 'TIME', 'null' => true],
            'location'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'material_file'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'material_name'    => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'material_size'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'activity_date']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('teacher_activities', true);
    }

    public function down()
    {
        $this->forge->dropTable('teacher_activities', true);
    }
}
