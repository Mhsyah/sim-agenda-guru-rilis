<?php

namespace App\Models;

use CodeIgniter\Model;

class AgendaModel extends Model
{
    protected $table          = 'teaching_agendas';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['user_id', 'agenda_date', 'semester_id', 'class_group_id', 'subject_id', 'learning_objective_id', 'topic', 'activities', 'reflection', 'follow_up', 'status', 'lesson_hours', 'meeting_no', 'meeting_total', 'completion', 'incomplete_reason', 'assessment_kind', 'remarks', 'material_file', 'material_name', 'material_size'];
}
