<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassGroupModel extends Model
{
    protected $table          = 'class_groups';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['academic_year_id', 'name', 'grade_level', 'is_active'];
}
