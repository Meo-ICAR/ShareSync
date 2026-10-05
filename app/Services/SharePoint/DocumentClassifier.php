<?php

namespace App\Services\SharePoint;

use App\Models\DocumentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentClassifier
{
    /** @var Collection<int, DocumentType>|null */
    private ?Collection $types = null;

    public function __construct(private readonly AiClassifier $ai) {}

    /** @param Collection<int, DocumentType> $types */
    public function withTypes(Collection $types): static
    {
        $this->types = $types;

        return $this;
    }

    /** @return list<Classification> */
    public function classify(string $relativePath): array
    {
        $segments = explode('/', $relativePath);
        $fileName = pathinfo((string) end($segments), PATHINFO_FILENAME);
        $folderNo = count($segments) >= 3 && preg_match('/^(\d+)\s*-/', $segments[1], $m) ? (int) $m[1] : null;

        // Le regex valgono su tutti i tipi; l'hint di cartella restringe solo i candidati dell'AI.
        $hits = $this->types()->filter(fn ($t) => $this->matches($t->regex, $fileName))->values();
        $candidates = $this->candidatesFor($folderNo);

        if ($hits->count() === 1) {
            return [$this->finalize($hits->first(), (int) config('sharepoint_import.rule_confidence'), 'rule')];
        }

        return $this->classifyWithAi($relativePath, $hits->isNotEmpty() ? $hits : $candidates);
    }

    /** @return Collection<int, DocumentType> */
    private function candidatesFor(?int $folderNo): Collection
    {
        $all = $this->types();
        $hint = $folderNo !== null ? config("sharepoint_import.folder_hints.{$folderNo}") : null;

        if ($hint === null) {
            return $all;
        }

        $filtered = $all->filter(fn ($t) => (bool) preg_match($hint, (string) $t->name))->values();

        return $filtered->isNotEmpty() ? $filtered : $all;
    }

    /** @return Collection<int, DocumentType> */
    private function types(): Collection
    {
        return $this->types ??= DocumentType::all();
    }

    private function matches(?string $regex, string $subject): bool
    {
        if ($regex === null || $regex === '') {
            return false;
        }

        return @preg_match($regex, $subject) === 1;
    }

    /**
     * @param  Collection<int, DocumentType>  $pool
     * @return list<Classification>
     */
    private function classifyWithAi(string $path, Collection $pool): array
    {
        if ($pool->isEmpty()) {
            return [new Classification(null, 0, 'none')];
        }

        try {
            $answers = $this->ai->classify($path, $pool->map(fn ($t) => ['id' => $t->id, 'name' => (string) $t->name])->all());
        } catch (Throwable $e) {
            Log::warning('Classificazione AI fallita: '.$e->getMessage());

            return [new Classification(null, 0, 'none')];
        }

        $byId = $pool->keyBy('id');
        $out = [];
        foreach ($answers as $answer) {
            $type = $byId->get($answer['document_type_id']);
            if ($type !== null) {
                $out[] = $this->finalize($type, $answer['confidence'], 'ai');
            }
        }

        return $out !== [] ? $out : [new Classification(null, 0, 'none')];
    }

    private function finalize(DocumentType $type, int $confidence, string $source): Classification
    {
        // Soglia propria dell'import: la colonna document_types.min_confidence (70 per tutti) è condivisa con altre app.
        $min = (int) config('sharepoint_import.default_min_confidence');

        return new Classification($confidence >= $min ? $type->id : null, $confidence, $source);
    }
}
