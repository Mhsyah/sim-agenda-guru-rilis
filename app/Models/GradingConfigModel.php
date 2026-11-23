<?php

namespace App\Models;

use CodeIgniter\Model;

class GradingConfigModel extends Model
{
    protected $table          = 'grading_configs';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['academic_year_id', 'semester_id', 'class_group_id', 'subject_id', 'name', 'is_active', 'rounding_rule', 'aggregation_method', 'user_id'];
}
