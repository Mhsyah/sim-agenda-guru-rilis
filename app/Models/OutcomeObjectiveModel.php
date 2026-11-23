<?php

namespace App\Models;

use CodeIgniter\Model;

class OutcomeObjectiveModel extends Model
{
    protected $table          = 'outcome_objectives';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = ['learning_outcome_id', 'learning_objective_id'];
}
