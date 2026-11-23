<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Skema inti Agenda Guru v0.2.
 *
 * Portabel untuk MariaDB/MySQL (MySQLi) dan PostgreSQL (Postgre):
 * - Flag boolean disimpan sebagai SMALLINT 0/1 agar representasinya sama di kedua engine.
 * - DATETIME dipetakan Forge menjadi TIMESTAMP pada PostgreSQL.
 * - Tidak memakai ENUM, UNSIGNED, atau fungsi khusus vendor.
 */
class CreateCoreSchema extends Migration
{
    private function id(): array
    {
        return ['id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true]];
    }

    private function timestamps(bool $softDelete = false): array
    {
        $f = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        if ($softDelete) {
            $f['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $f;
    }

    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'null' => $null];
    }

    private function flag(int $default = 1): array
    {
        return ['type' => 'SMALLINT', 'default' => $default];
    }

    private function str(int $len, bool $null = false, ?string $default = null): array
    {
        $f = ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
        if ($default !== null) {
            $f['default'] = $default;
        }

        return $f;
    }

    private function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    private function create(string $table, array $fields): void
    {
        $this->forge->addField($fields);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable($table, true);
    }

    public function up()
    {
        // users
        $this->forge->addField($this->id() + [
            'name'          => $this->str(100),
            'email'         => $this->str(150),
            'password_hash' => $this->str(255),
            'role'          => $this->str(20, false, 'teacher'),
            'is_active'     => $this->flag(1),
            'theme_pref'    => $this->str(10, false, 'system'),
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true);

        $this->create('school_profiles', $this->id() + [
            'school_name'    => $this->str(150),
            'school_address' => $this->text(),
            'teacher_name'   => $this->str(150),
            'teacher_nip'    => $this->str(30, true),
            'timezone'       => $this->str(50, false, 'Asia/Jakarta'),
        ] + $this->timestamps());

        // periode
        $this->forge->addField($this->id() + [
            'name'       => $this->str(20),
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
            'is_active'  => $this->flag(0),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('academic_years', true);

        $this->forge->addField($this->id() + [
            'academic_year_id' => $this->fk(),
            'name'             => $this->str(20),
            'start_date'       => ['type' => 'DATE'],
            'end_date'         => ['type' => 'DATE'],
            'is_active'        => $this->flag(0),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['academic_year_id', 'name']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('semesters', true);

        // master
        $this->forge->addField($this->id() + [
            'code'      => $this->str(20, true),
            'name'      => $this->str(100),
            'phase'     => $this->str(10, true),
            'is_active' => $this->flag(1),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('subjects', true);

        $this->forge->addField($this->id() + [
            'academic_year_id' => $this->fk(),
            'name'             => $this->str(50),
            'grade_level'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'is_active'        => $this->flag(1),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['academic_year_id', 'name']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('class_groups', true);

        $this->forge->addField($this->id() + [
            'nis'        => $this->str(30, true),
            'nisn'       => $this->str(20, true),
            'full_name'  => $this->str(150),
            'birth_date' => ['type' => 'DATE', 'null' => true],
            'gender'     => $this->str(1, true),
            'status'     => $this->str(20, false, 'aktif'),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('nis');
        $this->forge->addUniqueKey('nisn');
        $this->forge->createTable('students', true);

        $this->forge->addField($this->id() + [
            'student_id'       => $this->fk(),
            'class_group_id'   => $this->fk(),
            'academic_year_id' => $this->fk(),
            'status'           => $this->str(20, false, 'aktif'),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['student_id', 'academic_year_id']);
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('student_enrollments', true);

        // kurikulum
        $this->forge->addField($this->id() + [
            'academic_year_id' => $this->fk(true),
            'subject_id'       => $this->fk(),
            'phase'            => $this->str(10, true),
            'element_name'     => $this->str(150),
            'code'             => $this->str(30, true),
            'description'      => ['type' => 'TEXT'],
            'is_active'        => $this->flag(1),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('learning_outcomes', true);

        $this->forge->addField($this->id() + [
            'academic_year_id'  => $this->fk(true),
            'subject_id'        => $this->fk(),
            'code'              => $this->str(30, true),
            'description'       => ['type' => 'TEXT'],
            'estimated_minutes' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'is_active'         => $this->flag(1),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('learning_objectives', true);

        $this->forge->addField($this->id() + [
            'learning_outcome_id'   => $this->fk(),
            'learning_objective_id' => $this->fk(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['learning_outcome_id', 'learning_objective_id']);
        $this->forge->addForeignKey('learning_outcome_id', 'learning_outcomes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('learning_objective_id', 'learning_objectives', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('outcome_objectives', true);

        // kalender & jadwal
        $this->forge->addField($this->id() + [
            'academic_year_id' => $this->fk(),
            'title'            => $this->str(150),
            'event_type'       => $this->str(20, false, 'kegiatan'),
            'start_date'       => ['type' => 'DATE'],
            'end_date'         => ['type' => 'DATE'],
            'notes'            => $this->text(),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['start_date', 'end_date']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('school_calendar_events', true);

        $this->forge->addField($this->id() + [
            'academic_year_id' => $this->fk(),
            'class_group_id'   => $this->fk(),
            'subject_id'       => $this->fk(),
            'day_of_week'      => ['type' => 'SMALLINT'],
            'start_time'       => ['type' => 'TIME'],
            'end_time'         => ['type' => 'TIME'],
            'room'             => $this->str(50, true),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('teaching_schedules', true);

        $this->forge->addField($this->id() + [
            'user_id'               => $this->fk(true),
            'agenda_date'           => ['type' => 'DATE'],
            'semester_id'           => $this->fk(),
            'class_group_id'        => $this->fk(),
            'subject_id'            => $this->fk(),
            'learning_objective_id' => $this->fk(true),
            'topic'                 => $this->str(200),
            'activities'            => $this->text(),
            'reflection'            => $this->text(),
            'follow_up'             => $this->text(),
            'status'                => $this->str(20, false, 'selesai'),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('agenda_date');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('semester_id', 'semesters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('learning_objective_id', 'learning_objectives', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('teaching_agendas', true);

        // penilaian
        $this->forge->addField($this->id() + [
            'code'       => $this->str(10),
            'name'       => $this->str(50),
            'is_active'  => $this->flag(1),
            'sort_order' => ['type' => 'SMALLINT', 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('assessment_types', true);

        $this->forge->addField($this->id() + [
            'assessment_type_id'    => $this->fk(),
            'academic_year_id'      => $this->fk(),
            'semester_id'           => $this->fk(),
            'class_group_id'        => $this->fk(),
            'subject_id'            => $this->fk(),
            'learning_objective_id' => $this->fk(true),
            'title'                 => $this->str(150),
            'assessment_date'       => ['type' => 'DATE'],
            'max_score'             => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 100],
            'status'                => $this->str(20, false, 'terbuka'),
        ] + $this->timestamps(true));
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('assessment_type_id', 'assessment_types', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('semester_id', 'semesters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('learning_objective_id', 'learning_objectives', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('assessments', true);

        $this->forge->addField($this->id() + [
            'academic_year_id'   => $this->fk(),
            'semester_id'        => $this->fk(),
            'class_group_id'     => $this->fk(true),
            'subject_id'         => $this->fk(),
            'name'               => $this->str(100),
            'is_active'          => $this->flag(1),
            'rounding_rule'      => $this->str(10, false, '2'),
            'aggregation_method' => $this->str(20, false, 'mean'),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('semester_id', 'semesters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('class_group_id', 'class_groups', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('grading_configs', true);

        $this->forge->addField($this->id() + [
            'grading_config_id'  => $this->fk(),
            'assessment_type_id' => $this->fk(),
            'weight_percent'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'is_enabled'         => $this->flag(1),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['grading_config_id', 'assessment_type_id']);
        $this->forge->addForeignKey('grading_config_id', 'grading_configs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('assessment_type_id', 'assessment_types', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('grading_config_items', true);

        $this->forge->addField($this->id() + [
            'assessment_id' => $this->fk(),
            'student_id'    => $this->fk(),
            'score'         => ['type' => 'DECIMAL', 'constraint' => '6,2'],
            'notes'         => $this->text(),
        ] + $this->timestamps());
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['assessment_id', 'student_id']);
        $this->forge->addForeignKey('assessment_id', 'assessments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('assessment_scores', true);

        $this->forge->addField($this->id() + [
            'user_id'     => $this->fk(true),
            'action'      => $this->str(50),
            'entity_type' => $this->str(50),
            'entity_id'   => $this->fk(true),
            'old_values'  => $this->text(),
            'new_values'  => $this->text(),
            'ip_address'  => $this->str(45, true),
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->createTable('audit_logs', true);
    }

    public function down()
    {
        $tables = [
            'audit_logs', 'assessment_scores', 'grading_config_items', 'grading_configs', 'assessments',
            'assessment_types', 'teaching_agendas', 'teaching_schedules', 'school_calendar_events',
            'outcome_objectives', 'learning_objectives', 'learning_outcomes', 'student_enrollments',
            'students', 'class_groups', 'subjects', 'semesters', 'academic_years', 'school_profiles', 'users',
        ];
        foreach ($tables as $t) {
            $this->forge->dropTable($t, true, true);
        }
    }
}
