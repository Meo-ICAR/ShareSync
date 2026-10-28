<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_is_created_with_integer_id_defaults_and_json_metadata(): void
    {
        $doc = Document::create([
            'documentable_type' => 'fornitore',
            'documentable_id' => 'abc',
            'name' => 'x.pdf',
            'metadata' => ['path' => 'a/b'],
        ]);

        $fresh = Document::find($doc->id);

        $this->assertIsInt($fresh->id);
        $this->assertSame('caricato', $fresh->status->value);
        $this->assertSame('local', $fresh->source_app);
        $this->assertSame(['path' => 'a/b'], $fresh->metadata);
    }
}
