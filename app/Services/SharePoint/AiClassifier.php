<?php

namespace App\Services\SharePoint;

interface AiClassifier
{
    /**
     * @param  list<array{id:int,name:string}>  $candidates
     * @return list<array{document_type_id:int,confidence:int}>
     */
    public function classify(string $path, array $candidates): array;
}
