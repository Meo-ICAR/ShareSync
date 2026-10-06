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

    /**
     * Applica il versioning a tutti i gruppi (soggetto, tipo) con più versioni, per i soggetti indicati.
     *
     * @param  list<string>  $documentableTypes  Alias morph dei soggetti (es. employee, fornitore)
     * @return int Versioni sostituite (con $commit false: quelle che verrebbero sostituite)
     */
    public function supersedeAll(array $documentableTypes, bool $commit = true): int
    {
        $groups = Document::where('source_app', 'sharepoint')
            ->whereIn('documentable_type', $documentableTypes)
            ->whereNotNull('document_type_id')
            ->whereNotNull('emitted_at')
            ->selectRaw('documentable_type, documentable_id, document_type_id, count(*) as versions')
            ->groupBy('documentable_type', 'documentable_id', 'document_type_id')
            ->havingRaw('count(*) > 1')
            ->get();

        $superseded = 0;

        foreach ($groups as $group) {
            $superseded += $commit
                ? $this->supersedeOlderVersions((string) $group->documentable_type, (string) $group->documentable_id, (int) $group->document_type_id)
                : (int) $group->versions - 1;
        }

        return $superseded;
    }
}
