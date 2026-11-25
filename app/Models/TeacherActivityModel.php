<?php

namespace App\Models;

use CodeIgniter\Model;

class TeacherActivityModel extends Model
{
    protected $table          = 'teacher_activities';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['user_id', 'academic_year_id', 'semester_id', 'activity_date', 'activity_type', 'title', 'start_time', 'end_time',
        'location', 'description', 'material_file', 'material_name', 'material_size'];
}
