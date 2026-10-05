<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'ai_confidence_score' => 'integer',
        ];
    }
}
