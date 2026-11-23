<?php

namespace App\Models;

use CodeIgniter\Model;

class ScheduleModel extends Model
{
    protected $table          = 'teaching_schedules';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['academic_year_id', 'class_group_id', 'subject_id', 'day_of_week', 'start_time', 'end_time', 'room', 'lesson_hours', 'user_id'];
}
