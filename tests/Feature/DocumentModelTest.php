<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_is_created_with_uuid_defaults_and_json_metadata(): void
    {
        $doc = Document::create([
            'documentable_type' => 'fornitore',
            'documentable_id' => 'abc',
            'name' => 'x.pdf',
            'metadata' => ['path' => 'a/b'],
        ]);

        $fresh = Document::find($doc->id);

        $this->assertSame(36, strlen($fresh->id));
        $this->assertSame('uploaded', $fresh->status);
        $this->assertSame('local', $fresh->source_app);
        $this->assertSame(['path' => 'a/b'], $fresh->metadata);
    }
}
