<?php

namespace App\Models;

use CodeIgniter\Model;

class LearningObjectiveModel extends Model
{
    protected $table          = 'learning_objectives';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['academic_year_id', 'subject_id', 'code', 'description', 'estimated_minutes', 'is_active', 'atp_order', 'grade_level', 'kktp_criteria', 'kktp_intervals', 'semester_id'];
}
