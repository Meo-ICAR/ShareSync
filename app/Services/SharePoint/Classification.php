<?php

namespace App\Services\SharePoint;

final class Classification
{
    public function __construct(
        public readonly ?int $documentTypeId,
        public readonly int $confidence,
        public readonly string $source,
    ) {}
}
