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
        private readonly DocumentClassifier $classifier,
    ) {}

    /** @return list<array<string, mixed>> */
    public function run(string $rootName, bool $commit): array
    {
        $root = $this->client->findChildFolder('root', $rootName)
            ?? throw new RuntimeException("Cartella radice non trovata: {$rootName}");

        $rows = [];
        $matches = [];

        foreach ($this->client->files($root['id']) as $file) {
            $segments = explode('/', $file->path);

            if (count($segments) < 2) {
                $rows[] = $this->row($file, null, null, 'none', null, 'skipped_outside_collaborator');

                continue;
            }

            $collaborator = $segments[0];
            $match = $matches[$collaborator] ??= $this->matcher->match($collaborator);

            if ($match->fornitore === null) {
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
                $rows[] = $this->row($file, $collaborator, $match, $match->kind, $classification, $action, $error);
            }
        }

        return $rows;
    }

    private function store(SharePointFile $file, FornitoreMatch $match, Classification $c): string
    {
        $exists = Document::withTrashed()
            ->where('source_app', 'sharepoint')
            ->where('app_id', $file->id)
            ->when(
                $c->documentTypeId === null,
                fn ($q) => $q->whereNull('document_type_id'),
                fn ($q) => $q->where('document_type_id', $c->documentTypeId)
            )
            ->exists();

        if ($exists) {
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

    /** @return array<string, mixed> */
    private function attributes(SharePointFile $file, FornitoreMatch $match, Classification $c): array
    {
        return [
            'company_id' => $match->fornitore->company_id,
            'documentable_type' => 'fornitore',
            'documentable_id' => $match->fornitore->id,
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
    private function row(SharePointFile $file, ?string $collaborator, ?FornitoreMatch $match, string $kind, ?Classification $c, string $action, ?string $error = null): array
    {
        return [
            'path' => $file->path,
            'collaborator' => $collaborator,
            'fornitore_id' => $match?->fornitore?->id,
            'fornitore' => $match?->fornitore?->nome,
            'match' => $kind,
            'document_type_id' => $c?->documentTypeId,
            'confidence' => $c?->confidence,
            'source' => $c?->source,
            'action' => $action,
            'error' => $error,
        ];
    }
}
