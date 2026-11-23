<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Banyak guru dalam satu sekolah:
 * - users.nip (identitas guru di cetakan)
 * - kepemilikan data per guru (user_id) pada jadwal, asesmen, bobot, kesan kepala sekolah
 * - indeks untuk filter yang sering dipakai (kecepatan)
 */
class MultiTeacher extends Migration
{
    private const OWNED = ['teaching_schedules', 'assessments', 'grading_configs', 'principal_reviews'];

    public function up()
    {
        $this->forge->addColumn('users', ['nip' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true]]);

        foreach (self::OWNED as $t) {
            $this->forge->addColumn($t, ['user_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
        }

        // Data lama milik guru pertama (sebelumnya aplikasi hanya untuk satu guru).
        $first = $this->db->table('users')->where('role', 'teacher')->orderBy('id', 'ASC')->get()->getRowArray();
        if ($first) {
            foreach (array_merge(self::OWNED, ['teaching_agendas']) as $t) {
                $this->db->table($t)->where('user_id', null)->update(['user_id' => $first['id']]);
            }
            $p = $this->db->table('school_profiles')->get()->getRowArray();
            if ($p && ! empty($p['teacher_nip'])) {
                $this->db->table('users')->where('id', $first['id'])->update(['nip' => $p['teacher_nip']]);
            }
        }

        $idx = [
            'teaching_agendas'    => [['user_id', 'agenda_date'], ['class_group_id', 'subject_id', 'semester_id']],
            'teaching_schedules'  => [['user_id', 'day_of_week']],
            'assessments'         => [['user_id', 'semester_id'], ['class_group_id', 'subject_id', 'semester_id']],
            'grading_configs'     => [['user_id', 'semester_id', 'subject_id']],
            'principal_reviews'   => [['user_id', 'academic_year_id']],
            'student_enrollments' => [['class_group_id', 'status']],
            'audit_logs'          => [['action', 'created_at']],
        ];
        foreach ($idx as $table => $sets) {
            foreach ($sets as $cols) {
                $name = 'ix_' . $table . '_' . implode('_', $cols);
                $this->db->query('CREATE INDEX ' . $this->db->escapeIdentifiers($name) . ' ON ' . $this->db->escapeIdentifiers($this->db->prefixTable($table))
                    . ' (' . implode(', ', array_map([$this->db, 'escapeIdentifiers'], $cols)) . ')');
            }
        }
    }

    public function down()
    {
        foreach (self::OWNED as $t) {
            $this->forge->dropColumn($t, 'user_id');
        }
        $this->forge->dropColumn('users', 'nip');
    }
}
