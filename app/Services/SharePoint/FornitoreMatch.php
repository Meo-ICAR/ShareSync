<?php

namespace App\Services\SharePoint;

use App\Models\Fornitori;

final class FornitoreMatch
{
    public function __construct(
        public readonly ?Fornitori $fornitore,
        public readonly string $kind,
    ) {}
}
