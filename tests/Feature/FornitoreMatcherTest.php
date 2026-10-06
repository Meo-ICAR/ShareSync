<?php

namespace Tests\Feature;

use App\Models\Fornitori;
use App\Services\SharePoint\FornitoreMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FornitoreMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function fornitore(string $nome, ?string $deletedAt = null): Fornitori
    {
        return Fornitori::unguarded(fn () => Fornitori::create([
            'nome' => $nome,
            'deleted_at' => $deletedAt,
        ]));
    }

    public function test_normalize_ignores_case_accents_punctuation_and_word_order(): void
    {
        $m = new FornitoreMatcher;

        $this->assertSame($m->normalize('Giofrè Alfonso'), $m->normalize('ALFONSO  GIOFRE'));
        $this->assertSame('ciro di maio', $m->normalize('Di Maio, Ciro'));
    }

    public function test_exact_match_with_accents_and_uppercase(): void
    {
        $f = $this->fornitore('GIOFRE ALFONSO');

        $match = (new FornitoreMatcher)->match('Giofrè Alfonso');

        $this->assertSame('exact', $match->kind);
        $this->assertSame($f->id, $match->fornitore->id);
    }

    public function test_homonyms_prefer_the_non_deleted_record(): void
    {
        $this->fornitore('LEO GIOVANNI', now()->toDateTimeString());
        $active = $this->fornitore('LEO GIOVANNI');

        $match = (new FornitoreMatcher)->match('Leo Giovanni');

        $this->assertSame($active->id, $match->fornitore->id);
    }

    public function test_matches_soft_deleted_when_it_is_the_only_one(): void
    {
        $f = $this->fornitore('ALFIERI FABIO', now()->toDateTimeString());

        $this->assertSame($f->id, (new FornitoreMatcher)->match('Alfieri Fabio')->fornitore->id);
    }

    public function test_fuzzy_match_for_small_typos(): void
    {
        $f = $this->fornitore('MNACINI SERENA');

        $match = (new FornitoreMatcher)->match('Mancini Serena');

        $this->assertSame('fuzzy', $match->kind);
        $this->assertSame($f->id, $match->fornitore->id);
    }

    public function test_none_when_no_similar_name(): void
    {
        $this->fornitore('ROSSI MARIO');

        $match = (new FornitoreMatcher)->match('Verdi Luca');

        $this->assertSame('none', $match->kind);
        $this->assertNull($match->fornitore);
    }
}
