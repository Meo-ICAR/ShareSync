<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupersedeSharePointVersionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DocumentType::unguarded(fn () => DocumentType::create(['id' => 3, 'name' => 'Visura']));
    }

    private function document(string $appId, string $emittedAt, string $subjectType = 'fornitore', string $subjectId = 'f1', int $typeId = 3, string $source = 'sharepoint'): Document
    {
        return Document::unguarded(fn () => Document::create([
            'company_id' => 1,
            'documentable_type' => $subjectType,
            'documentable_id' => $subjectId,
            'document_type_id' => $typeId,
            'name' => "{$appId}.pdf",
            'status' => 'caricato',
            'source_app' => $source,
            'app_id' => $appId,
            'emitted_at' => $emittedAt,
        ]));
    }

    public function test_older_versions_of_employees_and_fornitori_are_soft_deleted_at_the_next_emission_date(): void
    {
        $oldF = $this->document('a1', '2024-01-10');
        $newF = $this->document('a2', '2025-03-01');
        $oldE = $this->document('b1', '2023-05-05', 'employee', '7');
        $newE = $this->document('b2', '2024-06-06', 'employee', '7');

        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->assertSuccessful();

        $this->assertSame('2025-03-01', $oldF->refresh()->deleted_at->toDateString());
        $this->assertSame('2024-06-06', $oldE->refresh()->deleted_at->toDateString());
        $this->assertNull($newF->refresh()->deleted_at);
        $this->assertNull($newE->refresh()->deleted_at);
    }

    public function test_it_keeps_a_chain_of_three_versions_with_only_the_latest_alive(): void
    {
        $first = $this->document('a1', '2023-01-01');
        $second = $this->document('a2', '2024-01-01');
        $third = $this->document('a3', '2025-01-01');

        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->assertSuccessful();

        $this->assertSame('2024-01-01', $first->refresh()->deleted_at->toDateString());
        $this->assertSame('2025-01-01', $second->refresh()->deleted_at->toDateString());
        $this->assertNull($third->refresh()->deleted_at);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $old = $this->document('a1', '2024-01-10');
        $this->document('a2', '2025-03-01');

        $this->artisan('sharepoint:supersede-versions')->expectsOutputToContain('DRY-RUN: versioni da sostituire: 1')->assertSuccessful();

        $this->assertNull($old->refresh()->deleted_at);
    }

    public function test_it_ignores_other_subjects_other_types_other_sources_and_undated_documents(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 4, 'name' => 'Altro']));
        $a = $this->document('a1', '2024-01-10');
        $otherType = $this->document('a2', '2025-03-01', typeId: 4);
        $otherSubject = $this->document('a3', '2025-03-01', subjectId: 'f2');
        $local = $this->document('a4', '2025-06-01', source: 'local');
        $client = $this->document('c1', '2024-01-01', 'clienti', 'x');
        $newerClient = $this->document('c2', '2025-01-01', 'clienti', 'x');

        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->assertSuccessful();

        foreach ([$a, $otherType, $otherSubject, $local, $client, $newerClient] as $document) {
            $this->assertNull($document->refresh()->deleted_at, $document->app_id);
        }
    }

    public function test_it_is_idempotent(): void
    {
        $this->document('a1', '2024-01-10');
        $this->document('a2', '2025-03-01');

        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->assertSuccessful();
        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->expectsOutputToContain('Versioni sostituite: 0')->assertSuccessful();

        $this->assertSame(1, Document::onlyTrashed()->count());
    }

    public function test_types_of_the_same_family_supersede_each_other(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 7, 'name' => 'Formazione 15h aggiornamento OAM ( se IVASS )']));
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 8, 'name' => 'Formazione 30h aggiornamento OAM']));
        $fifteen = $this->document('a1', '2024-01-10', typeId: 7);
        $thirty = $this->document('a2', '2025-03-01', typeId: 8);
        $unrelated = $this->document('a3', '2025-06-01');

        $this->artisan('sharepoint:supersede-versions')->expectsOutputToContain('DRY-RUN: versioni da sostituire: 1')->assertSuccessful();
        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->assertSuccessful();

        $this->assertSame('2025-03-01', $fifteen->refresh()->deleted_at->toDateString());
        $this->assertNull($thirty->refresh()->deleted_at);
        $this->assertNull($unrelated->refresh()->deleted_at);
    }

    public function test_the_same_file_with_several_types_is_not_a_version_of_itself(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 7, 'name' => 'Formazione 15h aggiornamento OAM ( se IVASS )']));
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 48, 'name' => 'Attestato Formazione OAM', 'code' => 'ATTESTATO_FORMAZIONE']));
        $old = $this->document('a1', '2024-01-10', typeId: 7);
        $newAsFifteen = $this->document('a2', '2025-03-01', typeId: 7);
        $newAsAttestato = $this->document('a2', '2025-03-01', typeId: 48);

        $this->artisan('sharepoint:supersede-versions', ['--commit' => true])->assertSuccessful();

        $this->assertSame('2025-03-01', $old->refresh()->deleted_at->toDateString());
        $this->assertNull($newAsFifteen->refresh()->deleted_at);
        $this->assertNull($newAsAttestato->refresh()->deleted_at);
    }
}
