<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Fornitori;
use App\Services\SharePoint\AiClassifier;
use App\Services\SharePoint\DocumentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentImporterTest extends TestCase
{
    use RefreshDatabase;

    private Fornitori $fornitore;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sharepoint.drive_id' => 'D',
            'services.sharepoint.tenant_id' => 't',
        ]);

        DocumentType::unguarded(function () {
            DocumentType::create(['id' => 1, 'name' => 'Casellario Giudiziale', 'regex' => '/casellario|giudiz/i']);
            DocumentType::create(['id' => 2, 'name' => 'Carichi Pendenti', 'regex' => '/carichi.*pendenti/i']);
            DocumentType::create(['id' => 3, 'name' => 'Visura Camerale', 'regex' => '/visura/i']);
        });

        $this->fornitore = Fornitori::unguarded(fn () => Fornitori::create([
            'nome' => 'ROSSI MARIO',
            'company_id' => 'company-1',
        ]));

        $this->app->instance(AiClassifier::class, new class implements AiClassifier
        {
            public function classify(string $path, array $candidates): array
            {
                return [
                    ['document_type_id' => 1, 'confidence' => 95],
                    ['document_type_id' => 2, 'confidence' => 92],
                ];
            }
        });

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/drives/D/items/root/children*' => Http::response(['value' => [
                ['id' => 'R', 'name' => '1 - COLLABORATORI ATTIVI', 'folder' => ['childCount' => 3]],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/R/children*' => Http::response(['value' => [
                ['id' => 'c1', 'name' => 'Rossi Mario', 'folder' => ['childCount' => 3]],
                ['id' => 'c2', 'name' => 'Sconosciuto Tizio', 'folder' => ['childCount' => 1]],
                ['id' => 'loose', 'name' => 'readme.pdf', 'file' => [], 'size' => 1, 'eTag' => 'e0', 'webUrl' => 'u0'],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/c1/children*' => Http::response(['value' => [
                ['id' => 'f1', 'name' => 'Visura Camerale 2026.pdf', 'file' => [], 'size' => 10, 'eTag' => 'e1', 'webUrl' => 'u1'],
                ['id' => 'f2', 'name' => 'Casellar. Giudiz. e Carichi pendenti.pdf', 'file' => [], 'size' => 20, 'eTag' => 'e2', 'webUrl' => 'u2'],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/c2/children*' => Http::response(['value' => [
                ['id' => 'f3', 'name' => 'x.pdf', 'file' => [], 'size' => 5, 'eTag' => 'e3', 'webUrl' => 'u3'],
            ]]),
        ]);
    }

    private function importer(): DocumentImporter
    {
        return app(DocumentImporter::class);
    }

    public function test_dry_run_writes_nothing_but_reports_everything(): void
    {
        $rows = $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: false);

        $this->assertSame(0, Document::count());
        $actions = array_count_values(array_column($rows, 'action'));
        $this->assertSame(3, $actions['dry_run']);          // f1 (1 tipo) + f2 (2 tipi)
        $this->assertSame(1, $actions['skipped_no_fornitore']);
        $this->assertSame(1, $actions['skipped_outside_collaborator']);
    }

    public function test_commit_creates_one_record_per_file_and_type(): void
    {
        $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);

        $this->assertSame(3, Document::count());

        $doc = Document::where('app_id', 'f1')->first();
        $this->assertSame('fornitore', $doc->documentable_type);
        $this->assertSame($this->fornitore->id, $doc->documentable_id);
        $this->assertSame('company-1', $doc->company_id);
        $this->assertSame(3, $doc->document_type_id);
        $this->assertSame('sharepoint', $doc->source_app);
        $this->assertSame('synced', $doc->sync_status);
        $this->assertSame('D', $doc->app_drive_id);
        $this->assertSame('e1', $doc->app_etag);
        $this->assertSame('u1', $doc->document_url);
        $this->assertSame('Visura Camerale 2026.pdf', $doc->name);
        $this->assertSame('Rossi Mario/Visura Camerale 2026.pdf', $doc->metadata['path']);
        $this->assertSame('exact', $doc->metadata['match']);

        $this->assertEqualsCanonicalizing(
            [1, 2],
            Document::where('app_id', 'f2')->pluck('document_type_id')->all()
        );
    }

    public function test_rerun_is_idempotent_even_for_soft_deleted_rows(): void
    {
        $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);
        Document::where('app_id', 'f1')->first()->delete();

        $rows = $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);

        $this->assertSame(2, Document::count());
        $this->assertSame(1, Document::onlyTrashed()->count());
        $this->assertSame(3, array_count_values(array_column($rows, 'action'))['exists']);
    }

    public function test_existing_unrelated_documents_are_untouched(): void
    {
        $old = Document::create(['documentable_type' => 'fornitore', 'documentable_id' => 'x', 'name' => 'vecchio']);

        $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);

        $this->assertSame('vecchio', $old->fresh()->name);
        $this->assertSame('local', $old->fresh()->source_app);
    }

    public function test_missing_root_folder_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->importer()->run('Cartella Inesistente', commit: false);
    }

    public function test_command_defaults_to_dry_run_and_writes_csv(): void
    {
        $this->artisan('sharepoint:import-documents')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertSame(0, Document::count());
        $csv = glob(storage_path('app/sharepoint-import-*.csv'));
        $this->assertNotEmpty($csv);
        array_map('unlink', $csv);
    }
}
