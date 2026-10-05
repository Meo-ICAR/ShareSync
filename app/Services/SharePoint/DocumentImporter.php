<?php

namespace App\Services\SharePoint;

use App\Models\Document;
use RuntimeException;
use Throwable;

class DocumentImporter
{
    private const MAX_URL_LENGTH = 255;

    public function __construct(
        private readonly SharePointClient $client,
        private readonly FornitoreMatcher $matcher,
        private readonly EmployeeMatcher $employeeMatcher,
        private readonly DocumentClassifier $classifier,
    ) {}

    /** @return list<array<string, mixed>> */
    public function run(string $rootName, bool $commit, string $subjectType = 'fornitore'): array
    {
        $root = $this->client->findChildFolder('root', $rootName)
            ?? throw new RuntimeException("Cartella radice non trovata: {$rootName}");

        $rows = [];
        $matches = [];
        $touched = [];

        foreach ($this->client->files($root['id']) as $file) {
            $segments = explode('/', $file->path);

            if (count($segments) < 2) {
                $rows[] = $this->row($file, null, null, 'none', null, 'skipped_outside_collaborator');

                continue;
            }

            $collaborator = $segments[0];
            $match = $matches[$collaborator] ??= $this->matchSubject($collaborator, $subjectType);

            if ($match->subject === null) {
                $rows[] = $this->row($file, $collaborator, null, 'none', null, 'skipped_no_fornitore');

                continue;
            }

            foreach ($this->classifier->classify($file->path) as $classification) {
                $error = null;
                try {
                    $action = $commit ? $this->store($file, $match, $classification) : 'dry_run';
                } catch (Throwable $e) {
                    $action = 'error';
                    $error = $e->getMessage();
                }
                if ($commit && $error === null && $classification->documentTypeId !== null) {
                    $touched[$match->documentableType.'|'.$match->subject->id.'|'.$classification->documentTypeId] = [$match->documentableType, (string) $match->subject->id, $classification->documentTypeId];
                }
                $rows[] = $this->row($file, $collaborator, $match, $match->kind, $classification, $action, $error);
            }
        }

        foreach ($touched as [$documentableType, $documentableId, $documentTypeId]) {
            $this->supersedeOlderVersions($documentableType, $documentableId, $documentTypeId);
        }

        return $rows;
    }

    private function store(SharePointFile $file, SubjectMatch $match, Classification $c): string
    {
        $existing = Document::withTrashed()
            ->where('source_app', 'sharepoint')
            ->where('app_id', $file->id)
            ->when(
                $c->documentTypeId === null,
                fn ($q) => $q->whereNull('document_type_id'),
                fn ($q) => $q->where('document_type_id', $c->documentTypeId)
            )
            ->first();

        if ($existing !== null) {
            // Backfill delle date solo per i record importati prima che venissero gestite.
            if ($existing->emitted_at === null && $existing->expires_at === null && $file->modifiedAt !== null) {
                $existing->update(['emitted_at' => $file->modifiedAt]);

                return 'backfilled';
            }

            return 'exists';
        }

        $attributes = $this->attributes($file, $match, $c);

        // Un record importato in precedenza senza tipo viene ricollegato, non duplicato.
        if ($c->documentTypeId !== null) {
            $untyped = Document::where('source_app', 'sharepoint')
                ->where('app_id', $file->id)
                ->whereNull('document_type_id')
                ->first();

            if ($untyped !== null) {
                $untyped->update($attributes);

                return 'updated';
            }
        }

        Document::create($attributes);

        return 'created';
    }

    /**
     * Più documenti dello stesso tipo per lo stesso fornitore sono aggiornamenti:
     * i più vecchi vengono eliminati (soft delete) alla data di emissione del successivo.
     */
    private function supersedeOlderVersions(string $documentableType, string $documentableId, int $documentTypeId): void
    {
        $versions = Document::where('source_app', 'sharepoint')
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->where('document_type_id', $documentTypeId)
            ->whereNotNull('emitted_at')
            ->orderBy('emitted_at')
            ->orderBy('created_at')
            ->get();

        foreach ($versions->slice(0, -1)->values() as $index => $older) {
            $older->deleted_at = $versions[$index + 1]->emitted_at;
            $older->save();
        }
    }

    private function matchSubject(string $folderName, string $subjectType): SubjectMatch
    {
        return $subjectType === 'employee'
            ? $this->employeeMatcher->match($folderName)
            : SubjectMatch::fromFornitore($this->matcher->match($folderName));
    }

    /** @return array<string, mixed> */
    private function attributes(SharePointFile $file, SubjectMatch $match, Classification $c): array
    {
        return [
            'company_id' => $match->subject->company_id,
            'documentable_type' => $match->documentableType,
            'documentable_id' => $match->subject->id,
            'document_type_id' => $c->documentTypeId,
            'name' => $file->name,
            // document_url è varchar(255): l'URL completo resta sempre in metadata.web_url.
            'document_url' => $file->webUrl !== null && strlen($file->webUrl) <= self::MAX_URL_LENGTH ? $file->webUrl : null,
            'status' => 'uploaded',
            'sync_status' => 'synced',
            'source_app' => 'sharepoint',
            'app_id' => $file->id,
            'app_drive_id' => $this->client->driveId(),
            'app_etag' => $file->etag,
            'ai_confidence_score' => $c->confidence,
            'emitted_at' => $file->modifiedAt,
            'metadata' => [
                'path' => $file->path,
                'web_url' => $file->webUrl,
                'match' => $match->kind,
                'classification_source' => $c->source,
                'needs_review' => $match->kind !== 'exact' || $c->documentTypeId === null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function row(SharePointFile $file, ?string $collaborator, ?SubjectMatch $match, string $kind, ?Classification $c, string $action, ?string $error = null): array
    {
        return [
            'path' => $file->path,
            'collaborator' => $collaborator,
            'fornitore_id' => $match?->subject?->id,
            'fornitore' => $match?->label,
            'match' => $kind,
            'document_type_id' => $c?->documentTypeId,
            'confidence' => $c?->confidence,
            'source' => $c?->source,
            'action' => $action,
            'error' => $error,
        ];
    }
}
