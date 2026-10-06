<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use Database\Seeders\RaccolteDocumentTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaccolteDocumentTypesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_idempotent_and_assigns_models(): void
    {
        $this->seed(RaccolteDocumentTypesSeeder::class);
        $count = DocumentType::count();
        $this->seed(RaccolteDocumentTypesSeeder::class);

        $this->assertSame($count, DocumentType::count());
        $this->assertSame('fornitore', DocumentType::where('code', 'ACCORDO_SEGNALAZIONE')->value('document_typable'));
        $this->assertSame('clienti', DocumentType::where('code', 'MANDATO_BROKERAGGIO')->value('document_typable'));
        $this->assertSame('company', DocumentType::where('code', 'VERBALE_CDA')->value('document_typable'));
        $this->assertTrue((bool) DocumentType::where('code', 'VERBALE_CDA')->value('is_company'));
    }
}
