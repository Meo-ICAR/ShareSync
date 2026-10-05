<?php

namespace App\Services\SharePoint;

final class SharePointFile
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $path,
        public readonly ?string $etag,
        public readonly ?string $webUrl,
        public readonly int $size,
    ) {}
}
