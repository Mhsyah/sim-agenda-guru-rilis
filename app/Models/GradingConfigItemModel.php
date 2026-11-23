<?php

namespace App\Models;

use CodeIgniter\Model;

class GradingConfigItemModel extends Model
{
    protected $table          = 'grading_config_items';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['grading_config_id', 'assessment_type_id', 'weight_percent', 'is_enabled'];
}
