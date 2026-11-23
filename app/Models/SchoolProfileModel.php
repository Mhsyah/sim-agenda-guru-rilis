<?php

namespace App\Models;

use CodeIgniter\Model;

class SchoolProfileModel extends Model
{
    protected $table          = 'school_profiles';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['school_name', 'school_address', 'teacher_name', 'teacher_nip', 'timezone', 'school_logo', 'header_line', 'npsn', 'school_city', 'school_phone', 'school_email', 'school_website', 'principal_name', 'principal_nip', 'kop_settings', 'gov_logo', 'principal_signature', 'school_photo', 'cover_blank', 'cover_blank_opts'];
}
