<?php

namespace App\Models;

use CodeIgniter\Model;

class AssessmentModel extends Model
{
    protected $table          = 'assessments';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['assessment_type_id', 'academic_year_id', 'semester_id', 'class_group_id', 'subject_id', 'learning_objective_id', 'title', 'assessment_date', 'max_score', 'status', 'user_id', 'missing_policy'];
}
