<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Pemeriksa & perbaikan struktur database: mendeteksi tabel aplikasi yang hilang
 * (mis. migrasi tercatat selesai di hosting padahal tabelnya tidak terbuat) dan membuatnya kembali.
 */
final class SchemaRepair
{
    /** Definisi tabel yang dapat dibuat ulang otomatis (tanpa foreign key agar diterima semua server). */
    public const TEACHER_ACTIVITIES = [
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
    ];

    /** Tabel yang bisa diperbaiki otomatis. */
    private const REPAIRABLE = ['teacher_activities'];

    private static function tables($db): array
    {
        $db->resetDataCache();

        return $db->listTables();
    }

    public static function has(string $table, $db = null): bool
    {
        $db ??= db_connect();

        return in_array($db->prefixTable($table), self::tables($db), true);
    }

    /**
     * Tabel yang dapat diperbaiki tetapi tidak ada (hanya dicek bila migrasinya sudah tercatat
     * atau tabel memang diharapkan ada oleh versi ini).
     *
     * @return list<string>
     */
    public static function missing($db = null): array
    {
        $db ??= db_connect();
        try {
            $all = self::tables($db);
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter(self::REPAIRABLE, static fn ($t) => ! in_array($db->prefixTable($t), $all, true)));
    }

    /** Buat tabel teacher_activities bila belum ada; galat dari server ditampilkan apa adanya. */
    public static function ensureTeacherActivities($forge = null, $db = null): bool
    {
        $db    ??= db_connect();
        $forge ??= \Config\Database::forge($db);
        if (self::has('teacher_activities', $db)) {
            return false;
        }
        $forge->addField(self::TEACHER_ACTIVITIES);
        $forge->addPrimaryKey('id');
        $forge->addKey(['user_id', 'activity_date']);
        $forge->addKey('academic_year_id');
        try {
            $ok = $forge->createTable('teacher_activities', true);
        } catch (\Throwable $e) {
            throw new RuntimeException('Tabel teacher_activities gagal dibuat: ' . $e->getMessage(), 0, $e);
        }
        if ($ok === false || ! self::has('teacher_activities', $db)) {
            $err = $db->error();

            throw new RuntimeException('Tabel teacher_activities gagal dibuat' . (! empty($err['message']) ? ': ' . $err['message'] : '.'));
        }

        return true;
    }

    /** Perbaiki semua tabel yang hilang. @return list<string> tabel yang dibuat */
    public static function repair(): array
    {
        $made = [];
        foreach (self::missing() as $t) {
            if ($t === 'teacher_activities' && self::ensureTeacherActivities()) {
                $made[] = $t;
            }
        }

        return $made;
    }
}
