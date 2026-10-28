<?php

namespace App\Models;

use Database\Factories\FornitoriFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Unico\Core\Models\Fornitore as CoreFornitore;

/**
 * Il produttore dello schema condiviso (database core): campi, relazioni e connessione sono quelli di unico-core.
 */
class Fornitori extends CoreFornitore
{
    /** @use HasFactory<FornitoriFactory> */
    use HasFactory;
}
