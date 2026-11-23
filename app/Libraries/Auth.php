<?php

namespace App\Libraries;

use App\Models\UserModel;

/** Autentikasi sesi sederhana untuk satu guru utama (+ admin teknis opsional). */
final class Auth
{
    private static ?array $user = null;

    public const MAX_ATTEMPTS = 5;
    public const LOCK_SECONDS = 300;

    public static function id(): ?int
    {
        $id = session()->get('uid');

        return $id ? (int) $id : null;
    }

    public static function user(): ?array
    {
        if (self::$user === null && ($id = self::id())) {
            $u = (new UserModel())->where('is_active', 1)->find($id);
            self::$user = $u ?: null;
            if (! $u) {
                session()->remove('uid');
            }
        }

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isTeacher(): bool
    {
        return (self::user()['role'] ?? '') === 'teacher';
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public const ROLES = ['teacher' => 'Guru', 'admin' => 'Admin teknis'];

    /** @return true|string true jika berhasil, pesan galat jika gagal */
    public static function attempt(string $email, string $password, string $ip)
    {
        $email = strtolower(trim($email));
        $cache = cache();
        $key   = 'login_' . md5($email . '|' . $ip);
        $tries = (int) ($cache->get($key) ?? 0);

        if ($tries >= self::MAX_ATTEMPTS) {
            Audit::log('login_blocked', 'user', null, null, ['email' => $email]);

            return 'Terlalu banyak percobaan login. Coba lagi dalam ' . (self::LOCK_SECONDS / 60) . ' menit.';
        }

        $model = new UserModel();
        $user  = $model->where('email', $email)->first();
        if (! $user || ! (int) $user['is_active'] || ! password_verify($password, $user['password_hash'])) {
            $cache->save($key, $tries + 1, self::LOCK_SECONDS);
            Audit::log('login_failed', 'user', isset($user['id']) ? (int) $user['id'] : null, null, ['email' => $email]);

            return 'Email atau kata sandi salah.';
        }

        $cache->delete($key);
        $update = ['last_login_at' => date('Y-m-d H:i:s')];
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $model->update($user['id'], $update);

        session()->regenerate(true);
        session()->set('uid', (int) $user['id']);
        // Masih memakai kata sandi awal hasil impor → tampilkan pengingat untuk menggantinya.
        if (hash_equals(\App\Libraries\MasterImporter::DEFAULT_TEACHER_PASSWORD, $password)) {
            session()->set('pw_default', true);
        }
        self::$user = null;
        Audit::log('login', 'user', (int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        if (self::id()) {
            Audit::log('logout', 'user', self::id());
        }
        self::$user = null;
        session()->remove(['uid', 'period_year', 'period_semester', 'pw_default']);
        session()->regenerate(true);
    }

    public static function forget(): void
    {
        self::$user = null;
    }
}
