<?php

namespace App\Services\SharePoint;

use App\Models\Document;
use App\Models\DocumentType;

class DocumentVersioner
{
    /** @var array<int, list<int>>|null */
    private ?array $familyByTypeId = null;

    /**
     * Più documenti dello stesso tipo (o della stessa famiglia di tipi, vedi config sharepoint_import.version_families)
     * per lo stesso soggetto sono aggiornamenti: i più vecchi vengono eliminati (soft delete) alla data di emissione del successivo.
     * Lo stesso file con più tipi non è un aggiornamento di se stesso: le copie del file più recente restano tutte.
     *
     * @return int Numero di versioni sostituite
     */
    public function supersedeOlderVersions(string $documentableType, string $documentableId, int $documentTypeId): int
    {
        $replaced = $this->replacedVersions($documentableType, $documentableId, $documentTypeId);

        foreach ($replaced as [$document, $next]) {
            $document->deleted_at = $next->emitted_at;
            $document->save();
        }

        return count($replaced);
    }

    /**
     * Versioni da sostituire, ciascuna con quella che la sostituisce.
     *
     * @return list<array{0: Document, 1: Document}>
     */
    private function replacedVersions(string $documentableType, string $documentableId, int $documentTypeId): array
    {
        $versions = Document::where('source_app', 'sharepoint')
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->whereIn('document_type_id', $this->familyOf($documentTypeId))
            ->whereNotNull('emitted_at')
            ->orderBy('emitted_at')
            ->orderBy('created_at')
            ->get();

        $latestFile = $versions->last()?->app_id;
        $replaced = [];

        foreach ($versions as $document) {
            if ($latestFile !== null && $document->app_id === $latestFile) {
                continue;
            }
            $replaced[] = [$document, $versions->first(fn (Document $d) => $d->app_id !== $document->app_id && $this->isAfter($d, $document))];
        }

        return $replaced;
    }

    /** Il documento $a segue $b nell'ordine emissione, creazione, id. */
    private function isAfter(Document $a, Document $b): bool
    {
        return [$a->emitted_at, $a->created_at, $a->getKey()] > [$b->emitted_at, $b->created_at, $b->getKey()];
    }

    /**
     * Id dei tipi che si aggiornano a vicenda: la famiglia di config che contiene il tipo, altrimenti il tipo da solo.
     *
     * @return list<int>
     */
    private function familyOf(int $documentTypeId): array
    {
        if ($this->familyByTypeId === null) {
            $this->familyByTypeId = [];
            foreach (config('sharepoint_import.version_families') as $labels) {
                $ids = DocumentType::whereIn('code', $labels)->orWhereIn('name', $labels)->pluck('id')->map(fn ($id) => (int) $id)->all();
                foreach ($ids as $id) {
                    $this->familyByTypeId[$id] = $ids;
                }
            }
        }

        return $this->familyByTypeId[$documentTypeId] ?? [$documentTypeId];
    }

    /**
     * Applica il versioning a tutti i gruppi (soggetto, tipo o famiglia di tipi) con più versioni, per i soggetti indicati.
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
            ->select('documentable_type', 'documentable_id', 'document_type_id')
            ->distinct()
            ->get()
            ->unique(fn ($g) => $g->documentable_type.'|'.$g->documentable_id.'|'.min($this->familyOf((int) $g->document_type_id)));

        $superseded = 0;

        foreach ($groups as $group) {
            $args = [(string) $group->documentable_type, (string) $group->documentable_id, (int) $group->document_type_id];
            $superseded += $commit ? $this->supersedeOlderVersions(...$args) : count($this->replacedVersions(...$args));
        }

        return $superseded;
    }
}
