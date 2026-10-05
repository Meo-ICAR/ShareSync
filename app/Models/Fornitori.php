<?php

namespace App\Models;

use Database\Factories\FornitoriFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornitori extends Model
{
    /** @use HasFactory<FornitoriFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fornitoris';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'nome',
        'stipulated_at',
        'pec',
        'description',
        'email_private',
        'supervisor_type',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'ivass',
        'ivass_at',
        'dismissed_at',
        'ivass_name',
        'ivass_section',
        'type',
        'is_active',
        'is_art108',
        'company_branch_id',
        'coordinated_type',
        'coordinated_id',
        'user_id',
        'oam_dismissed_at',
        'welcome_bonus',
        'campagna',
        'available_at',
        'budget',
        'codice',
        'coge',
        'natoil',
        'indirizzo',
        'comune',
        'cap',
        'prov',
        'tel',
        'coordinatore',
        'piva',
        'cf',
        'nomecoge',
        'nomefattura',
        'email',
        'anticipo',
        'enasarco',
        'anticipo_residuo',
        'contributo',
        'contributo_description',
        'anticipo_description',
        'issubfornitore',
        'operatore',
        'iscollaboratore',
        'isdipendente',
        'regione',
        'citta',
        'company_id',
        'contributoperiodicita',
        'contributodalmese',
        'fornitorirole_id',
        'branch_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stipulated_at' => 'date',
            'oam_at' => 'date',
            'ivass_at' => 'date',
            'dismissed_at' => 'date',
            'oam_dismissed_at' => 'date',
            'available_at' => 'date',
            'natoil' => 'date',
            'contributodalmese' => 'date',
            'is_active' => 'boolean',
            'is_art108' => 'boolean',
            'iscollaboratore' => 'boolean',
            'isdipendente' => 'boolean',
            'welcome_bonus' => 'decimal:2',
            'budget' => 'decimal:2',
            'anticipo' => 'decimal:2',
            'anticipo_residuo' => 'decimal:2',
            'contributo' => 'decimal:2',
            'company_branch_id' => 'integer',
            'coordinated_type' => 'integer',
            'coordinated_id' => 'integer',
            'user_id' => 'integer',
            'issubfornitore' => 'integer',
            'contributoperiodicita' => 'integer',
            'fornitorirole_id' => 'integer',
            'branch_id' => 'integer',
        ];
    }
}
