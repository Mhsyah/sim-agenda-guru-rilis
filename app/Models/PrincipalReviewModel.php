<?php

namespace App\Models;

use CodeIgniter\Model;

class PrincipalReviewModel extends Model
{
    protected $table          = 'principal_reviews';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['academic_year_id', 'review_date', 'reviewer_name', 'notes', 'is_signed', 'user_id'];
}
