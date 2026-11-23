<?php

namespace App\Models;

use CodeIgniter\Model;

class AssessmentTypeModel extends Model
{
    protected $table          = 'assessment_types';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['code', 'name', 'is_active', 'sort_order'];
}
