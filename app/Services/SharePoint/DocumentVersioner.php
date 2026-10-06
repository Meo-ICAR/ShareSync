<?php

namespace App\Services\SharePoint;

use App\Models\Document;

class DocumentVersioner
{
    /**
     * Più documenti dello stesso tipo per lo stesso soggetto sono aggiornamenti:
     * i più vecchi vengono eliminati (soft delete) alla data di emissione del successivo.
     *
     * @return int Numero di versioni sostituite
     */
    public function supersedeOlderVersions(string $documentableType, string $documentableId, int $documentTypeId): int
    {
        $versions = Document::where('source_app', 'sharepoint')
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->where('document_type_id', $documentTypeId)
            ->whereNotNull('emitted_at')
            ->orderBy('emitted_at')
            ->orderBy('created_at')
            ->get();

        $older = $versions->slice(0, -1)->values();

        foreach ($older as $index => $document) {
            $document->deleted_at = $versions[$index + 1]->emitted_at;
            $document->save();
        }

        return $older->count();
    }
}
