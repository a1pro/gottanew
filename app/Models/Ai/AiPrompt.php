<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'system_prompt',
        'max_tokens',
        'temperature',
        'model',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'temperature' => 'float',
        'max_tokens' => 'integer',
    ];
}