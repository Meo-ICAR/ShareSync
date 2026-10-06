<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Clienti;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\Fornitori;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalDatabaseConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_and_document_type_use_the_unicooam_connection(): void
    {
        $this->assertSame('mysql_unicooam', (new Document)->getConnectionName());
        $this->assertSame('mysql_unicooam', (new DocumentType)->getConnectionName());
    }

    public function test_employee_uses_the_unicooam_connection(): void
    {
        $this->assertSame('mysql_unicooam', (new Employee)->getConnectionName());
    }

    public function test_fornitori_clienti_and_client_use_the_proforma_connection(): void
    {
        foreach ([Fornitori::class, Clienti::class, Client::class] as $model) {
            $this->assertSame('mysql_proforma', (new $model)->getConnectionName(), $model);
        }

        $this->assertSame('clients', (new Client)->getTable());
    }

    public function test_connection_databases_come_from_the_env_names(): void
    {
        $connections = (require base_path('config/database.php'))['connections'];

        $this->assertSame(env('DB_UNICOOAM', 'unicooam'), $connections['mysql_unicooam']['database']);
        $this->assertSame(env('DB_PROFORMA', 'proforma'), $connections['mysql_proforma']['database']);
        $this->assertSame('mysql', $connections['mysql_unicooam']['driver']);
        $this->assertSame('mysql', $connections['mysql_proforma']['driver']);
    }

    public function test_relation_between_document_and_type_works_on_the_unicooam_connection(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 7, 'name' => 'Casellario']));
        $document = Document::unguarded(fn () => Document::create([
            'company_id' => 'c1',
            'documentable_type' => 'fornitore',
            'documentable_id' => 'f1',
            'document_type_id' => 7,
            'name' => 'x.pdf',
            'status' => 'uploaded',
        ]));

        $this->assertSame('Casellario', $document->refresh()->documentType->name);
    }
}
