<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsVoice extends Model
{
    protected $fillable = [
        'engine',
        'name',
        'title',
        'label',
        'gender',
        'f0',
        'kind',
        'region',
        'timbre_key',
        'note',
        'ref_file',
        'ref_duration_sec',
    ];
}
