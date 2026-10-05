<?php

namespace App\Services\SharePoint;

use App\Models\Fornitori;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FornitoreMatcher
{
    /** @var Collection<int, Fornitori>|null */
    private ?Collection $fornitori = null;

    /** @var array<string, Collection<int, Fornitori>>|null */
    private ?array $index = null;

    /** @param Collection<int, Fornitori> $fornitori */
    public function withFornitori(Collection $fornitori): static
    {
        $this->fornitori = $fornitori;
        $this->index = null;

        return $this;
    }

    public function normalize(string $value): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY);
        sort($tokens);

        return implode(' ', $tokens);
    }

    public function match(string $folderName): FornitoreMatch
    {
        $key = $this->normalize($folderName);
        $index = $this->index();

        if (isset($index[$key])) {
            return new FornitoreMatch($this->preferred($index[$key]), 'exact');
        }

        $bestKey = null;
        $bestScore = 0.0;
        $tie = false;
        foreach (array_keys($index) as $candidate) {
            similar_text($key, (string) $candidate, $percent);
            if ($percent > $bestScore) {
                [$bestKey, $bestScore, $tie] = [$candidate, $percent, false];
            } elseif ($percent === $bestScore) {
                $tie = true;
            }
        }

        if ($bestKey !== null && ! $tie && $bestScore >= config('sharepoint_import.fuzzy_threshold')) {
            return new FornitoreMatch($this->preferred($index[$bestKey]), 'fuzzy');
        }

        return new FornitoreMatch(null, 'none');
    }

    /** @return array<string, Collection<int, Fornitori>> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $this->fornitori ??= Fornitori::withTrashed()->get();
        $index = [];

        foreach ($this->fornitori as $fornitore) {
            foreach (array_unique(array_filter([$fornitore->nome, $fornitore->name])) as $label) {
                $index[$this->normalize($label)][] = $fornitore;
            }
        }

        return $this->index = array_map(fn ($items) => collect($items), $index);
    }

    /** @param Collection<int, Fornitori> $candidates */
    private function preferred(Collection $candidates): Fornitori
    {
        return $candidates
            ->sortBy([
                fn ($a, $b) => ($a->deleted_at !== null) <=> ($b->deleted_at !== null),
                fn ($a, $b) => ($b->is_active ?? 0) <=> ($a->is_active ?? 0),
            ])
            ->first();
    }
}
