<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Services\SharePoint\AiClassifier;
use App\Services\SharePoint\DocumentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentClassifierTest extends TestCase
{
    use RefreshDatabase;

    private array $aiCalls = [];

    private function type(int $id, string $name, ?string $regex = null, ?int $min = null): DocumentType
    {
        return DocumentType::unguarded(fn () => DocumentType::create(array_filter([
            'id' => $id, 'name' => $name, 'regex' => $regex, 'min_confidence' => $min,
        ], fn ($v) => $v !== null)));
    }

    private function classifier(array $aiAnswer = [], bool $aiThrows = false): DocumentClassifier
    {
        $ai = new class($aiAnswer, $aiThrows, $this->aiCalls) implements AiClassifier
        {
            public function __construct(private array $answer, private bool $throws, public array &$calls) {}

            public function classify(string $path, array $candidates): array
            {
                $this->calls[] = ['path' => $path, 'candidates' => array_column($candidates, 'id')];
                if ($this->throws) {
                    throw new \RuntimeException('api down');
                }

                return $this->answer;
            }
        };

        return (new DocumentClassifier($ai))->withTypes(DocumentType::all());
    }

    public function test_single_regex_hit_is_a_rule_classification_without_ai(): void
    {
        $this->type(2, 'Carichi Pendenti', '/carichi.*pendenti/i');
        $this->type(1, 'Casellario Giudiziale', '/casellario.*giudiziale/i');

        $r = $this->classifier()->classify('Rossi Mario/3 - REQUISITI DI ONORABILITA\'/Rossi - Carichi pendenti del 03_03_2026.pdf');

        $this->assertCount(1, $r);
        $this->assertSame(2, $r[0]->documentTypeId);
        $this->assertSame('rule', $r[0]->source);
        $this->assertSame([], $this->aiCalls);
    }

    public function test_two_regex_hits_go_to_ai_restricted_to_hits_and_yield_two_documents(): void
    {
        $this->type(2, 'Carichi Pendenti', '/carichi.*pendenti/i');
        $this->type(1, 'Casellario Giudiziale', '/casellario|giudiz/i');
        $this->type(9, 'Altro', null);

        $r = $this->classifier([
            ['document_type_id' => 1, 'confidence' => 95],
            ['document_type_id' => 2, 'confidence' => 92],
        ])->classify('Rossi/3 - ONORABILITA/Casellar. Giudiz. e Carichi pendenti.pdf');

        $this->assertSame([1, 2], array_map(fn ($c) => $c->documentTypeId, $r));
        $this->assertSame('ai', $r[0]->source);
        $this->assertEqualsCanonicalizing([1, 2], $this->aiCalls[0]['candidates']);
    }

    public function test_ai_receives_the_full_path(): void
    {
        $this->type(9, 'Altro', null);

        $this->classifier([])->classify('Rossi Mario/6 - ALTRO/foglio.pdf');

        $this->assertSame('Rossi Mario/6 - ALTRO/foglio.pdf', $this->aiCalls[0]['path']);
    }

    public function test_ai_failure_gives_single_untyped_classification(): void
    {
        $this->type(9, 'Altro', null);

        $r = $this->classifier([], aiThrows: true)->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertCount(1, $r);
        $this->assertNull($r[0]->documentTypeId);
        $this->assertSame('none', $r[0]->source);
    }

    public function test_ai_ids_outside_candidates_are_ignored(): void
    {
        $this->type(9, 'Altro', null);

        $r = $this->classifier([['document_type_id' => 777, 'confidence' => 99]])
            ->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertNull($r[0]->documentTypeId);
    }

    public function test_confidence_below_configured_threshold_leaves_type_null(): void
    {
        config(['sharepoint_import.default_min_confidence' => 80]);
        $this->type(9, 'Altro', null);

        $r = $this->classifier([['document_type_id' => 9, 'confidence' => 60]])
            ->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertNull($r[0]->documentTypeId);
        $this->assertSame(60, $r[0]->confidence);
    }

    public function test_confidence_at_default_threshold_of_55_keeps_the_type(): void
    {
        $this->type(9, 'Altro', null, 70); // la colonna min_confidence del tipo non conta per l'import

        $r = $this->classifier([['document_type_id' => 9, 'confidence' => 55]])
            ->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertSame(9, $r[0]->documentTypeId);
    }

    public function test_regex_hit_ignores_the_folder_hint(): void
    {
        $this->type(8, 'Contratto di collaborazione', null); // rientra nell'hint, quindi il filtro non è vuoto
        $this->type(7, 'Rappel', '/rappel/i'); // il nome non rientra nell'hint della cartella 1

        $r = $this->classifier()->classify('Rossi/1 - CONTRATTO DI COLLABORAZIONE/Lettera Rappel 2025.pdf');

        $this->assertSame(7, $r[0]->documentTypeId);
        $this->assertSame('rule', $r[0]->source);
        $this->assertSame([], $this->aiCalls);
    }

    public function test_folder_hint_restricts_ai_candidates(): void
    {
        $this->type(1, 'Casellario Giudiziale', null);
        $this->type(5, 'Visura Camerale', null);

        $this->classifier([])->classify('Rossi/5 - PARTITA IVA - VISURA/documento.pdf');

        $this->assertSame([5], $this->aiCalls[0]['candidates']);
    }

    public function test_file_directly_in_collaborator_folder_uses_all_types(): void
    {
        $this->type(1, 'Casellario Giudiziale', null);
        $this->type(5, 'Visura Camerale', null);

        $this->classifier([])->classify('Rossi/documento.pdf');

        $this->assertEqualsCanonicalizing([1, 5], $this->aiCalls[0]['candidates']);
    }

    public function test_invalid_regex_in_document_types_does_not_break_classification(): void
    {
        $this->type(1, 'Rotto', '/(unclosed');

        $r = $this->classifier()->classify('Rossi/6 - ALTRO/x.pdf');

        $this->assertNull($r[0]->documentTypeId);
    }
}
