<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'code',
        'codegroup',
        'slug',
        'regex_pattern',
        'priority',
        'phase',
        'is_person',
        'is_company',
        'is_employee',
        'is_agent',
        'is_principal',
        'is_client',
        'is_practice',
        'trigger_field',
        'is_signed',
        'is_monitored',
        'renewed_by_id',
        'document_url',
        'training_hours',
        'training_organization',
        'duration',
        'duration_unit',
        'nature',
        'doctype',
        'cellposition',
        'emitted_by',
        'is_sensible',
        'is_template',
        'is_stored',
        'regex',
        'is_endMonth',
        'is_AiAbstract',
        'is_AiCheck',
        'AiPattern',
        'min_confidence',
        'allow_auto_verification',
        'notify_days_before',
        'retention_years',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_versioned',
        'document_typable',
        'trigger_state',
        'trigger_value',
        'exclude_field',
        'exclude_state',
        'exclude_value',
        'expire_days_before',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_person' => 'boolean',
            'is_company' => 'boolean',
            'is_employee' => 'boolean',
            'is_agent' => 'boolean',
            'is_principal' => 'boolean',
            'is_client' => 'boolean',
            'is_practice' => 'boolean',
            'is_signed' => 'boolean',
            'is_monitored' => 'boolean',
            'renewed_by_id' => 'integer',
            'training_hours' => 'integer',
            'duration' => 'integer',
            'is_sensible' => 'boolean',
            'is_template' => 'boolean',
            'is_stored' => 'boolean',
            'is_endMonth' => 'boolean',
            'is_AiAbstract' => 'boolean',
            'is_AiCheck' => 'boolean',
            'min_confidence' => 'integer',
            'allow_auto_verification' => 'boolean',
            'notify_days_before' => 'array',
            'retention_years' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
            'is_versioned' => 'boolean',
            'expire_days_before' => 'integer',
        ];
    }

    /**
     * Get the document type that renews this document type.
     */
    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'renewed_by_id');
    }

    /**
     * Get the document types renewed by this document type.
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(DocumentType::class, 'renewed_by_id');
    }
}
