<?php

namespace App\Services\SharePoint;

use Illuminate\Database\Eloquent\Model;

/** Soggetto (fornitore o dipendente) a cui legare i documenti di una cartella. */
final class SubjectMatch
{
    public function __construct(
        public readonly ?Model $subject,
        public readonly string $kind,
        public readonly string $documentableType,
        public readonly ?string $label = null,
    ) {}

    public static function fromFornitore(FornitoreMatch $match): self
    {
        return new self($match->fornitore, $match->kind, 'fornitore', $match->fornitore?->nome);
    }
}
