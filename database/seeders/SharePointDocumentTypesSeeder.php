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
            1 => '/casel|carichi|cas\w*\.?\s*e\s*car/i',
            2 => '/^(?!.*casel).*carichi.*pendenti/i',
            4 => '/requisiti.*organizzativi|\bart\.?\s*6\b/i',
            3 => '/dichiarazione.*sostitutiva.*certificato.*onorabilit|dich\w*[\s._]+(sos|sot)/i',
            5 => null,
            6 => '/12\s*ore/i',
            7 => '/15\s*ore/i',
            8 => '/(20|30|45)[\s._-]*ore[\s._-]*oam|oam.{0,15}(20|30|45)[\s._-]*ore|(20|30|45)\s*oam|agg\.?\s*0\s*ore\s*oam|form\w*\.?\s*25\s*ore/i',
            9 => '/60\s*(ore|h)/i',
            10 => '/(30|45)[\s._-]*ore[\s._-]*(ivass|rui)|(ivass|rui).{0,15}(30|45)[\s._-]*ore/i',
            11 => '/titolo.*studio|diploma|laurea|conferma.*titolo|itcs/i',
            27 => '/carta.*identit|c\.i\.|identit|doc\w*\.?\s*personali|residenza|patente.*tess/i',
            28 => '/^(?!.*tess).*patente/i',
            35 => '/^(?!.*(identit|patente)).*(tess\w*\.?\s*san|codice.*fiscale)/i',
            48 => null,
            57 => '/^(?!.*12\s*ore).*(esito.*prova|esito.*esame|corso.*esame|prova.*esito|prova.*valut|prov\.?\s*valut|\bsu?p\w*\.?.*prova)/i',
            36 => '/p\.?\s?iva|partita.*iva|attribuzione.*iva|ateco/i',
            37 => '/visura|visuord|camera.*commercio|camerale/i',
            38 => '/curriculum|\bcv\b/i',
            39 => '/liberatoria/i',
            40 => '/tassa.*(ivass|168|conces|iscriz)|ivass.*168|168\s*(€|euro)|bollettino.*ivass/i',
            44 => '/contratto.*(collaborazione|agenzia)/i',
            45 => '/busta.*paga|cedolino|\bcu\b/i',
            46 => '/estratto.*conto/i',
            47 => '/delibera/i',
        ];

        foreach ($regexById as $id => $regex) {
            DocumentType::where('id', $id)->update(['regex' => $regex]);
        }

        $newTypes = [
            ['code' => 'NOTA_ADDEBITO', 'name' => 'Nota di addebito / Comunicazione di rivalsa', 'regex' => '/nota.*(addebito|races)|rivalsa|annullamento.*pratica/i'],
            ['code' => 'CONTO_ESTINTIVO', 'name' => 'Conto estintivo', 'regex' => '/conto.*estintivo/i'],
            ['code' => 'RAPPEL', 'name' => 'Rappel', 'regex' => '/rappel/i'],
            ['code' => 'CAMPAGNA_MARKETING', 'name' => 'Campagna di marketing', 'regex' => '/campagna|marketing/i'],
            ['code' => 'RICONOSCIMENTO_PREMIO', 'name' => 'Riconoscimento premio', 'regex' => '/riconoscimento.*premio|welcome.?b/i'],
            ['code' => 'ENASARCO', 'name' => 'Enasarco', 'regex' => '/enasarco/i'],
            ['code' => 'RECESSO_RISOLUZIONE', 'name' => 'Recesso / Risoluzione contrattuale', 'regex' => '/recesso|risoluzione/i'],
            ['code' => 'ESITO_PROVA_OAM', 'name' => 'Esito prova valutativa OAM', 'regex' => '/^(?!.*12\s*ore).*(esito.*prova|esito.*esame|corso.*esame|prova.*esito|prova.*valut|prov\.?\s*valut|\bsu?p\w*\.?.*prova)/i'],
            ['code' => 'ATTESTATO_FORMAZIONE_OAM', 'name' => 'Attestato Formazione OAM', 'regex' => '/attestato.*formazione.*oam/i'],
            ['code' => 'CONTRATTO_COLLABORAZIONE', 'name' => 'Contratto di collaborazione / agenzia', 'regex' => '/^(?!.*(segnalazione|a\.?\s?3|variazione|mediazione|rappel|appendic|recesso|risoluzione)).*(contratt|agenzia)/i'],
            ['code' => 'ALLEGATO_A3_PROVVIGIONI', 'name' => 'Allegato A.3 / variazione provvigioni', 'regex' => '/allegato\s*a\.?\s?[13]|variazione.*provvigion|modifica.*all\s*a\s?3/i'],
            ['code' => 'ACCORDO_LETTERA_APPENDICE', 'name' => 'Accordo / lettera / appendice', 'regex' => '/^(?!.*(contratt|rappel|mediazione|lettera.*incarico|conf.?mandato|allegato\s*a|variazione|riconoscimento.*premio|recesso|welcome)).*(intent|antic|appendic|scrittura|accordo|incarico|revoca|utilizzo|mandato|compe|provvigion|supervisione)/i'],
            ['code' => 'REPORT_CERVED', 'name' => 'Report Cerved', 'regex' => '/cerved|entry.*persona|standard.*(report|persona)|persona.*impresa/i'],
            ['code' => 'ATTESTATO_PRIVACY_TRASPARENZA', 'name' => 'Attestato Privacy / Trasparenza / D.Lgs 231', 'regex' => '/privacy|trasparenza|lgs.{0,3}231/i'],
            ['code' => 'REGIME_FORFETTARIO', 'name' => 'Regime forfettario', 'regex' => '/forfet/i'],
            ['code' => 'IBAN', 'name' => 'Coordinate bancarie IBAN', 'regex' => '/\biban\b/i'],
            ['code' => 'ISTANZA_PROVVEDIMENTO', 'name' => 'Istanza / provvedimento', 'regex' => '/istanza|provvedimento/i'],
            ['code' => 'RATEAZIONE_VERSAMENTI', 'name' => 'Rateazione / versamenti', 'regex' => '/rateazione|versamenti/i'],
            ['code' => 'DICH_SOST_CONFERMA_MANDATO', 'name' => 'Dichiarazione sostitutiva conferma mandato', 'regex' => '/conf.?mandato|conferma.*mandato/i'],
            ['code' => 'CESSAZIONE_COLLABORAZIONE', 'name' => 'Cessazione collaborazione', 'regex' => '/^(?!.*enasarco).*cessazione/i'],
            ['code' => 'POSIZIONE_IVASS', 'name' => 'Posizione IVASS annuale', 'regex' => '/posizione.*ivass|ivass.*posizione/i'],
            ['code' => 'SCHEDA_CANDIDATO', 'name' => 'Scheda candidato / presentazione', 'regex' => '/scheda.*presentazione|dati.*erogato/i'],
            ['code' => 'ESITO_TEST_IVASS', 'name' => 'Esito test IVASS', 'regex' => '/\btest\b.*ivass|superamento.*test/i'],
            ['code' => 'CONDIZIONI_DEROGHE', 'name' => 'Condizioni, obiettivi e deroghe', 'regex' => '/obiettivi|deroga|precis|spedizione/i'],
            ['code' => 'ESITO_EVASIONE_PROTOCOLLO', 'name' => 'Esito evasione protocollo', 'regex' => '/evasione.*protocollo/i'],
            ['code' => 'SEGNALAZIONE_CONTRATTI_FORNITURA', 'name' => 'Segnalazione contratti di fornitura', 'regex' => '/segnalazione.*fornitura/i'],
            ['code' => 'ATTESTATO_BIENNALE', 'name' => 'Attestato biennale', 'regex' => '/biennale/i'],
            ['code' => 'ISCRIZIONE_ELENCO_OAM', 'name' => 'Iscrizione / cancellazione elenco agenti OAM', 'regex' => '/iscrizione.*agenti|cancell.*elenco|blocco.*oam/i'],
            ['code' => 'AGGIORNAMENTO_5_ANNI', 'name' => 'Aggiornamento OAM / IVASS 5 anni', 'regex' => '/5\s*anni/i'],
            ['code' => 'SPECIMEN_FIRMA', 'name' => 'Specimen di firma', 'regex' => '/specimen/i'],
            ['code' => 'LETTERA_IMPEGNO_ASSUNZIONE', 'name' => 'Lettera impegno assunzione', 'regex' => '/lettera.*(impegno|assunzione)/i'],
            ['code' => 'ATTESTATO_COMPASS', 'name' => 'Attestato corso Compass', 'regex' => '/compass/i'],
            ['code' => 'ATTESTATO_ANTIRICICLAGGIO', 'name' => 'Attestato Antiriciclaggio', 'regex' => '/antiriciclaggio/i'],
            ['code' => 'EVIDENZA_ISCRIZIONE_RUI', 'name' => 'Evidenza iscrizione RUI', 'regex' => '/^(?!.*\d\s*ore).*(iscr\w*\.?.*r\.?u\.?i|evidenza.*rui|sez\w*\.?\s*e\s*del\s*rui)/i'],
            ['code' => 'MODELLO_ELETTRONICO_INTERMEDIARI', 'name' => 'Modello elettronico intermediari', 'regex' => '/modello.*elettronico.*intermediari/i'],
            ['code' => 'DIFENSIVA_LEGALE', 'name' => 'Difensiva / Studio legale', 'regex' => '/difensiv|appello|studio.*legale|avv\./i'],
        ];

        foreach ($newTypes as $type) {
            DocumentType::withTrashed()->updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
