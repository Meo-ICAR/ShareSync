<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Services\SharePoint\AiClassifier;
use App\Services\SharePoint\DocumentClassifier;
use Database\Seeders\SharePointDocumentTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharePointDocumentTypesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $existing = [
            35 => 'Tessera Sanitaria / Codice fiscale', 36 => 'Certificato P.IVA cod Ateco 66.19.22',
            37 => 'Visura camerale', 38 => 'Curriculum vitae aggiornato ',
            39 => 'Liberatoria incarichi precedenti / Copia PEC recesso', 40 => 'Tassa IVASS 168 euro',
            44 => 'Contratto Firmato', 45 => 'Busta Paga / CU', 46 => 'Estratto Conto Bancario',
            47 => 'Delibera Istituto Finanziatore',
        ];
        foreach ($existing as $id => $name) {
            DocumentType::unguarded(fn () => DocumentType::create(['id' => $id, 'name' => $name]));
        }

        $this->seed(SharePointDocumentTypesSeeder::class);
    }

    private function classify(string $path): ?int
    {
        $ai = new class implements AiClassifier
        {
            public function classify(string $path, array $candidates): array
            {
                throw new \RuntimeException('la regola doveva bastare');
            }
        };

        return (new DocumentClassifier($ai))->withTypes(DocumentType::all())->classify($path)[0]->documentTypeId;
    }

    private function idOf(string $code): int
    {
        return DocumentType::where('code', $code)->firstOrFail()->id;
    }

    public function test_new_types_are_created_once(): void
    {
        $this->seed(SharePointDocumentTypesSeeder::class);

        foreach (['NOTA_ADDEBITO', 'CONTO_ESTINTIVO', 'RAPPEL', 'CAMPAGNA_MARKETING', 'RICONOSCIMENTO_PREMIO',
            'ENASARCO', 'RECESSO_RISOLUZIONE', 'ESITO_PROVA_OAM', 'DIFENSIVA_LEGALE'] as $code) {
            $this->assertSame(1, DocumentType::where('code', $code)->count(), $code);
        }
    }

    public function test_new_types_classify_the_real_file_names_by_rule(): void
    {
        $cases = [
            'Rossi/1 - C/20260630 Nota_addebito_Pellegrino_Graziani.pdf' => 'NOTA_ADDEBITO',
            'Rossi/1 - C/Conto Estintivo (1).pdf' => 'CONTO_ESTINTIVO',
            'Rossi/1 - C/BONDANESE G_ Lettera Rappel 2025.pdf' => 'RAPPEL',
            'Rossi/1 - C/Innero Emilia - Campagna lead I Trimestre 2025.pdf' => 'CAMPAGNA_MARKETING',
            'Rossi/1 - C/INNERO EMILIA - Riconoscimento premio Gennaio 2025.pdf' => 'RICONOSCIMENTO_PREMIO',
            'Rossi/6 - ALTRO/InEnasarco_Decina Giacomo.pdf' => 'ENASARCO',
            'Rossi/1 - C/Risoluzione contrattuale_2182514.eml' => 'RECESSO_RISOLUZIONE',
            'Rossi/1 - C/Revoca Risoluzione Contrattuale - Fabbri.eml' => 'RECESSO_RISOLUZIONE',
            'Rossi/4 - R/1 - OAM/OAM Prova Valutativa - Esito Prova.eml' => 'ESITO_PROVA_OAM',
            'Rossi/3 - O/Difensiva appello/STUDIO LEGALE - Avv. Caloprestiti - Giofrè.pdf' => 'DIFENSIVA_LEGALE',
        ];

        foreach ($cases as $path => $code) {
            $this->assertSame($this->idOf($code), $this->classify($path), $path);
        }
    }

    public function test_regex_added_to_existing_types_classify_the_real_file_names_by_rule(): void
    {
        $cases = [
            'Rossi/5 - P/Decina Giacomo - Partita Iva.pdf' => 36,
            'Rossi/5 - P/Accogli - Certificazione Attribuzione P. IVA.pdf' => 36,
            'Rossi/5 - P/Grana Annalisa - visura camerale 13_05_2025.pdf' => 37,
            'Rossi/2 - D/Albanese Manlio - Tessera Sanitaria.pdf' => 35,
            'Rossi/2 - D/Rossi - Curriculum Vitae.pdf' => 38,
        ];

        foreach ($cases as $path => $id) {
            $this->assertSame($id, $this->classify($path), $path);
        }
    }
}
