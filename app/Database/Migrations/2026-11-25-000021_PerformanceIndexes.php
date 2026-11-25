<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Indeks tambahan untuk query yang sering dipakai (riwayat login, rekap kegiatan per semester, riwayat agenda guru).
 * Aman dijalankan ulang: indeks yang sudah ada atau tabel yang belum ada dilewati.
 */
class PerformanceIndexes extends Migration
{
    private const INDEXES = [
        'ix_audit_logs_user_id'             => ['audit_logs', 'user_id'],
        'ix_teacher_activities_year_sem'    => ['teacher_activities', 'academic_year_id, semester_id'],
        'ix_teaching_agendas_user_updated'  => ['teaching_agendas', 'user_id, updated_at'],
        'ix_teaching_schedules_year_user'   => ['teaching_schedules', 'academic_year_id, user_id'],
    ];

    public function up()
    {
        $this->db->resetDataCache();
        $tables = $this->db->listTables();
        foreach (self::INDEXES as $name => [$table, $cols]) {
            $tbl = $this->db->prefixTable($table);
            if (! in_array($tbl, $tables, true)) {
                continue;
            }
            try {
                $this->db->query('CREATE INDEX ' . $this->db->escapeIdentifiers($this->db->DBPrefix . $name) . ' ON ' . $this->db->escapeIdentifiers($tbl) . ' (' . $cols . ')');
            } catch (\Throwable $e) {
                // indeks sudah ada / tidak didukung → lewati (hanya untuk kecepatan)
            }
        }
    }

    public function down()
    {
        foreach (self::INDEXES as $name => [$table]) {
            try {
                $this->db->DBDriver === 'Postgre'
                    ? $this->db->query('DROP INDEX IF EXISTS ' . $this->db->escapeIdentifiers($this->db->DBPrefix . $name))
                    : $this->db->query('DROP INDEX ' . $this->db->escapeIdentifiers($this->db->DBPrefix . $name) . ' ON ' . $this->db->escapeIdentifiers($this->db->prefixTable($table)));
            } catch (\Throwable $e) {
            }
        }
    }
}
