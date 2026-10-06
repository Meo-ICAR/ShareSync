<?php

namespace App\Services\SharePoint;

use App\Models\Document;
use Throwable;

/**
 * Imposta emitted_at ai documenti importati da SharePoint che ne sono privi,
 * usando la data di ultima modifica del file (stessa regola di DocumentImporter).
 * Con $commit il salvataggio ricalcola expires_at (hook del model) e le versioni
 * più vecchie dello stesso tipo vengono sostituite come nell'import.
 */
class EmissionDateBackfiller
{
    public function __construct(private readonly DocumentVersioner $versioner) {}

    /**
     * @return array{updated: int, missing: int, errors: int, superseded: int, rows: list<array{id: string, emitted_at: string}>}
     */
    public function run(bool $commit): array
    {
        $result = ['updated' => 0, 'missing' => 0, 'errors' => 0, 'superseded' => 0, 'rows' => []];
        /** @var array<string, array{string, string, int}> $touched */
        $touched = [];
        /** @var array<string, SharePointClient> $clients */
        $clients = [];

        $documents = Document::withTrashed()
            ->where('source_app', 'sharepoint')
            ->whereNull('emitted_at')
            ->whereNotNull('app_id')
            ->whereNotNull('app_drive_id')
            ->orderBy('id')
            ->get();

        foreach ($documents as $document) {
            $client = $clients[$document->app_drive_id] ??= new SharePointClient($document->app_drive_id);

            try {
                $modifiedAt = $client->lastModified($document->app_id);
            } catch (Throwable) {
                $result['errors']++;

                continue;
            }

            if ($modifiedAt === null) {
                $result['missing']++;

                continue;
            }

            $emittedAt = $modifiedAt->toDateString();

            if ($commit) {
                $document->update(['emitted_at' => $emittedAt]);

                if ($document->document_type_id !== null) {
                    $touched["{$document->documentable_type}|{$document->documentable_id}|{$document->document_type_id}"] = [
                        (string) $document->documentable_type,
                        (string) $document->documentable_id,
                        (int) $document->document_type_id,
                    ];
                }
            }

            $result['updated']++;
            $result['rows'][] = ['id' => (string) $document->id, 'emitted_at' => $emittedAt];
        }

        foreach ($touched as [$documentableType, $documentableId, $documentTypeId]) {
            $result['superseded'] += $this->versioner->supersedeOlderVersions($documentableType, $documentableId, $documentTypeId);
        }

        return $result;
    }
}
