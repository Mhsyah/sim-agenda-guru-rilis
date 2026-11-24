<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table          = 'users';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['name', 'email', 'password_hash', 'role', 'is_active', 'theme_pref', 'last_login_at', 'nip', 'photo', 'gender', 'teacher_code', 'cover_blank', 'cover_blank_opts', 'employment_status', 'status_date', 'status_note', 'must_change_password'];
}
