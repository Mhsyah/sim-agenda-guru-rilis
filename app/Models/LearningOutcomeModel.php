<?php

namespace App\Models;

use CodeIgniter\Model;

class LearningOutcomeModel extends Model
{
    protected $table          = 'learning_outcomes';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['academic_year_id', 'subject_id', 'phase', 'element_name', 'code', 'description', 'is_active', 'semester_id'];
}
