<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicUsedQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = ['topic_id', 'question_id', 'exam_id'];
}
