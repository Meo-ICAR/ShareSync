<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Nuovi tipi emersi da sharepoint:scan-raccolte. Idempotente (chiave: code).
 *
 * Contratti e accordi: fornitore; convenzione, mandato e allegato provvigionale: clienti (istituti);
 * il resto è documentazione di company. I tipi "template" sono moduli nostri, privi di
 * riferimento a un fornitore/client. Requisiti componenti CDA: già coperti, tranne la nomina.
 */
class RaccolteDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $fornitore = ['document_typable' => 'fornitore', 'is_person' => false];
        $client = ['document_typable' => 'clienti', 'is_person' => false, 'is_client' => true];
        $company = ['document_typable' => 'company', 'is_person' => false, 'is_company' => true];
        $template = ['is_template' => true, 'nature' => 'template_fillable', 'doctype' => 'modulo'];

        $types = [
            // Contratti e accordi
            ['CONTRATTO_FORNITORE', 'Contratto fornitore', '/contratt|fornitura|servizi esternalizzati/i', $fornitore],
            ['ACCORDO_SEGNALAZIONE', 'Accordo di segnalazione / co-mediazione', '/accordo.*(segnalazione|co-?mediazione)|contratto.*segnalazione/i', $fornitore],
            ['CONTRATTO_LOCAZIONE', 'Contratto di locazione', '/locazione|planimetria/i', $fornitore],
            ['APPENDICE_CONTRATTO_AGENZIA', 'Appendice al contratto di agenzia', '/appendice/i', $fornitore + $template],
            ['CONVENZIONE_MEDIAZIONE', 'Convenzione di mediazione creditizia', '/convenzione|condizioni particolari/i', $client],
            ['MANDATO_BROKERAGGIO', 'Mandato di brokeraggio assicurativo', '/mandato/i', $client],
            ['ALLEGATO_PROVVIGIONALE', 'Allegato provvigionale / economico compensi', '/provvigional|allegato.*(economico|compensi)|determinazione compensi/i', $client],

            // Documenti societari
            ['VERBALE_ASSEMBLEA', 'Verbale assemblea soci', '/assemblea|finanziamento soci/i', $company],
            ['VERBALE_CDA', 'Verbale CDA', '/verbale.*cda|\bcda\b.*verbale/i', $company],
            ['DELIBERA_COMPENSO_AMM', 'Delibera compenso amministratore', '/compenso.*amministratore/i', $company],
            ['PROGETTO_BILANCIO', 'Progetto di bilancio', '/progetto.*bilancio|bilancio/i', $company],
            ['NOMINA_CDA', 'Nomina CDA', '/nomina.*cda|cda.*nomina/i', $company],

            // Istituti e prodotti
            ['KIT_TRASPARENZA', 'Kit di trasparenza', '/kit.*trasparenza|antiusura|\bABF\b/i', $company],
            ['SCHEDA_PRODOTTO', 'Foglio informativo / scheda prodotto', '/scheda.*prodotto|fascicolo informativo|set informativo|informazioni.generali/i', $company],
            ['CRITERI_ASSUNTIVI', 'Criteri / norme assuntive', '/norme assuntive|criteri|^cri-/i', $company],
            ['MANUALE_OPERATIVO', 'Manuale operativo / portale', '/manuale/i', $company],
            ['CHECKLIST_PRECONTRATTUALE', 'Checklist precontrattuale', '/check-?\s?list/i', $company + $template],
            ['PROCEDURA_KYC', 'Procedura KYC / modulo richiesta finanziamento', '/kyc|modulo richiesta finanziamento/i', $company],
            ['LISTA_COLLABORATORI', 'Lista collaboratori / rete', '/lista.*collaboratori/i', $company],
            ['MODULISTICA_IVASS_RUI', 'Modulistica iscrizione IVASS / RUI', '/modello b1|modulo 1a|\brui\b|iscrizione.*ivass/i', $company],
            ['POLICY_LISTINO', 'Policy banche / listino', '/policy|listino|\btan\b/i', $company],
            ['ATTIVAZIONE_UTENZE', 'Attivazione utenze', '/attivazione utenze/i', $company],

            // Compliance e modulistica
            ['VERIFICA_ISPETTIVA', 'Check-list / scheda verifica ispettiva', '/verifica ispettiva/i', $company + $template],
            ['RELAZIONE_AUDIT_ANNUALE', 'Relazione annuale funzione di controllo interno', '/relazione annuale/i', $company],
            ['REGOLAMENTI_INTERNI', 'Funzionigramma e regolamenti interni', '/funzionigramma|regolament|gestione convenzionati/i', $company],
            ['INFORMATIVA_PRIVACY_CLIENTELA', 'Informativa privacy clientela', '/inf\w*mativa.*privacy|privacy (completa|0)/i', $company + $template],
            ['QUESTIONARIO_AVC', 'Questionario AVC / adeguata verifica', '/questionario.*avc|adeguata verifica/i', $company + $template],
            ['FASCICOLO_MODULISTICA', 'Fascicolo modulistica per prodotto', '/fascicolo completo|legenda modulistica/i', $company + $template],
            ['IMMAGINE_COORDINATA', 'Immagine coordinata', '/carta intestata|brochure|logo/i', $company + $template],

            // Emersi dal campione dei file senza tipo
            ['CONDIZIONI_ECONOMICHE', 'Condizioni economiche / premi', '/commission|premio qualit|condizioni economiche|modifica condizioni|\\bover\\b/i', $company],
            ['COMPANY_PROFILE', 'Company profile / presentazione', '/company.?profile|presentazione/i', $company],
            ['DELEGA_CASELLARI', 'Delega richiesta casellari e carichi', '/delega.*casellar/i', $company + $template],
            ['CODICE_COLLOCAMENTO', 'Codice collocamento prodotti', '/codice collocamento/i', $company],
            ['SISTEMA_DISCIPLINARE', 'Sistema disciplinare', '/sistema disciplinare/i', $company],
            ['LETTERA_COLLABORATORI', 'Lettera collaboratori', '/lettera.*collaboratori/i', $company],
            ['RICHIESTA_MUTUO', 'Richiesta mutuo', '/richiesta ?mutuo/i', $company],
            ['ADDENDUM_PROPOSTA', 'Addendum / proposta commerciale', '/addendum|proposta|preventivo|allegato tecnico/i', $fornitore],
            ['ATTO_SOCIETARIO', 'Atto costitutivo / statuto / cessione quote', '/atto costitutivo|statuto|cessione quot|patto parasociale/i', $company],
            ['CONTRIBUTO_OAM', 'Contributo annuale OAM', '/contributo.*oam|oam.*(pagamento|versamento)/i', $company],
            ['ISPEZIONE_GDF', 'Ispezione Guardia di Finanza', '/ispezione|verbale ispettiv/i', $company],
            ['CONTO_CORRENTE_FIDO', 'Conti correnti e affidamenti', '/fidejussione|\\bfido\\b|conto corrente|banca multicanale/i', $company],
            ['RELAZIONE_COMPLIANCE', 'Relazione funzione compliance', '/relazione.*compliance|relazione attivit/i', $company],
            ['ISTANZA_AUTORIZZATIVA', 'Istanza di iscrizione / autorizzazione', '/istanza.*iscrizione|corecom/i', $company],
            ['FORMAZIONE_ANTIRICICLAGGIO', 'Formazione antiriciclaggio', '/antiriciclaggio/i', ['is_person' => true, 'training_organization' => 'interna']],
            ['FORMAZIONE_PRIVACY', 'Formazione privacy', '/ore? privacy/i', ['is_person' => true, 'training_organization' => 'PRIVACY']],
            ['DOCUMENTO_IDENTIFICATIVO', 'Documento identificativo', '/documenti? (personali|identit)/i', ['is_person' => true]],
            ['TARI', 'TARI / tassa rifiuti', '/\\btari\\b/i', $company],
            ['CONTRATTO_CONSULENZA', 'Contratto consulenza', '/consulenza|procacciamento/i', $company],
            ['VARIE', 'Varie', null, $client],
            ['DA_CLASSIFICARE', 'Da classificare', null, ['is_person' => false]],
            ['VARIE_ISTITUTI', 'Varie istituti', null, $client],

            // Altro
            ['F24_AGENZIA_ENTRATE', 'F24 / Agenzia delle Entrate', '/\bf ?24\b|agenzia.*entrate/i', $company],
            ['COMUNICAZIONE_PEC', 'PEC / comunicazione istituzionale', '/\bpec\b|protocollo|variazione ragione sociale/i', $company],
            ['RITENUTA_RIDOTTA', 'Applicazione ritenuta ridotta', '/ritenuta.*ridotta/i', $company],
        ];

        foreach ($types as [$code, $name, $regex, $flags]) {
            DocumentType::withTrashed()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'regex' => $regex] + $flags,
            );
        }
    }
}
