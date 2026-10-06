<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Regex di riconoscimento per i tipi dei collaboratori (id del DB di sviluppo) e
 * nuovi tipi emersi dal primo import da SharePoint. Idempotente.
 *
 * Le coppie duplicate (41/27 carta d'identità, 42/35 codice fiscale, 43/37 visura,
 * 48/6 attestato OAM, 49/12 polizza RC) hanno la regex su un solo tipo, così la
 * regola trova un unico match.
 */
class SharePointDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $regexById = [
            35 => '/tessera.*sanitaria|codice.*fiscale/i',
            36 => '/p\.?\s?iva|partita.*iva|attribuzione.*iva|ateco/i',
            37 => '/visura|visuord/i',
            38 => '/curriculum|\bcv\b/i',
            39 => '/liberatoria/i',
            40 => '/tassa.*ivass|ivass.*168/i',
            44 => '/contratto.*(collaborazione|agenzia)/i',
            45 => '/busta.*paga|cedolino|\bcu\b/i',
            46 => '/estratto.*conto/i',
            47 => '/delibera/i',
        ];

        foreach ($regexById as $id => $regex) {
            DocumentType::where('id', $id)->update(['regex' => $regex]);
        }

        $newTypes = [
            ['code' => 'NOTA_ADDEBITO', 'name' => 'Nota di addebito / Comunicazione di rivalsa', 'regex' => '/nota.*addebito|rivalsa/i'],
            ['code' => 'CONTO_ESTINTIVO', 'name' => 'Conto estintivo', 'regex' => '/conto.*estintivo/i'],
            ['code' => 'RAPPEL', 'name' => 'Rappel', 'regex' => '/rappel/i'],
            ['code' => 'CAMPAGNA_MARKETING', 'name' => 'Campagna di marketing', 'regex' => '/campagna|marketing/i'],
            ['code' => 'RICONOSCIMENTO_PREMIO', 'name' => 'Riconoscimento premio', 'regex' => '/riconoscimento.*premio|welcome.?bonus/i'],
            ['code' => 'ENASARCO', 'name' => 'Enasarco', 'regex' => '/enasarco/i'],
            ['code' => 'RECESSO_RISOLUZIONE', 'name' => 'Recesso / Risoluzione contrattuale', 'regex' => '/recesso|risoluzione/i'],
            ['code' => 'ESITO_PROVA_OAM', 'name' => 'Esito prova valutativa OAM', 'regex' => '/esito.*prova|prova.*esito/i'],
            ['code' => 'DIFENSIVA_LEGALE', 'name' => 'Difensiva / Studio legale', 'regex' => '/difensiv|studio.*legale|avv\./i'],
        ];

        foreach ($newTypes as $type) {
            DocumentType::withTrashed()->updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
