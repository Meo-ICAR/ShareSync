<?php

return [
    'root' => '1 - COLLABORATORI ATTIVI',
    'employee_root' => '4 - DIPENDENTI RACES ISCRITTI IN OAM',
    'default_min_confidence' => 55,
    'rule_confidence' => 90,
    'fuzzy_threshold' => 85,

    // sharepoint:scan-raccolte ignora i file con una cartella che corrisponde (storico).
    'scan_exclude_folders' => '/cessat|recedut|componenti cda/i',

    // Numero cartella (es. "3 - REQUISITI...") => regex sul NOME del DocumentType.
    // null = nessun filtro.
    'folder_hints' => [
        1 => '/contratt?o|mediazione|incarico|collaborazione/i',
        2 => '/identit|patente|tessera|codice fiscale|residenza|curriculum|titolo/i',
        3 => '/casellario|carichi|onorabilit/i',
        4 => '/oam|ivass|formazione|attestato|prova|antiriciclaggio|aml|trasparenza|privacy|231|polizza|titolo|studio|nomina|codice etico/i',
        5 => '/p\.?\s?iva|visura|partita|ateco|camerale/i',
        6 => null,
    ],

    // sharepoint:link-raccolte: come abbinare i file senza tipo, per raccolta.
    //   clienti = istituti (tabella clientis); client = tabella clients; societari = company / employee (cartella DIPENDENTI).
    'link_targets' => [
        'Istituti Cessione del Quinto' => 'clienti',
        'Istituti di Credito alle Imprese' => 'clienti',
        'Accordi di segnalazione' => 'clienti',
        'Prodotti Assicurativi' => 'clienti',
        'Fornitori' => 'client',
        'Documenti Societari' => 'societari',
    ],

    // Cartella (senza prefisso "NN - ") => nome dell'anagrafica, quando il nome non coincide.
    'link_aliases' => [
        'MDI' => 'MICROCREDITO DI IMPRESA SPA',
        'Assicura Point Broker Srl' => 'ASSICURAPOINT',
        'Money360.it S.p.A' => 'MONEY 360',
        'ViviBanca' => 'VIVIBANCA SPA',
    ],

    // Cartelle di Fornitori che corrispondono alla company (non a un record di clients).
    'link_company_folders' => ['RACES FINANCE SRL'],

    // File con questo nome esatto, sotto un istituto, prendono il tipo "Varie istituti".
    'link_varie_istituti' => ['vivibanca.pdf'],

    // sharepoint:link-raccolte: tipo dedotto dal percorso (cartella + nome). Si applica la prima regola che
    // corrisponde; il target è il `code` di un DocumentType o, in mancanza, il suo `name`.
    'link_type_rules' => [
        ['/30 ore oam/i', 'Formazione 30h aggiornamento OAM'],
        ['/12 ore oam/i', 'Attestato formazione OAM 12h'],
        ['/30 ore ivass/i', 'Formazione 30h aggiornamento IVASS'],
        ['/\bore? privacy/i', 'FORMAZIONE_PRIVACY'],
        ['/\bore? antiriciclaggio|antiriciclaggio - trasparenza - privacy/i', 'FORMAZIONE_ANTIRICICLAGGIO'],
        ['/documenti personali/i', 'DOCUMENTO_IDENTIFICATIVO'],
        ['/\btari\b/i', 'TARI'],
        ['/consulenza|procacciamento/i', 'CONTRATTO_CONSULENZA'],
        ['/verbali? cda/i', 'VERBALE_CDA'],
        ['/verbal\w* (di )?assemblea|assemblea soci/i', 'VERBALE_ASSEMBLEA'],
        ['/atto costitutivo|statuto|cessione quot|patto parasociale|atti notarili/i', 'ATTO_SOCIETARIO'],
        ['/visura camerale|certif\. cciaa/i', 'Visura camerale'],
        ['/polizza|questionario rinnovo/i', 'Polizza RC Professionale'],
        ['/enasarco/i', 'ENASARCO'],
        ['/contributo annuale oam|oam - (pagamento|versamento)/i', 'CONTRIBUTO_OAM'],
        ['/ispezione gdf|verbale ispettiv/i', 'ISPEZIONE_GDF'],
        ['/cont[oi] corrent[ei]|dati e documenti bancari|fidejussione|\bfido\b/i', 'CONTO_CORRENTE_FIDO'],
        ['/relazione.*compliance|relazione attivit/i', 'RELAZIONE_COMPLIANCE'],
        ['/istanza di iscrizione|corecom/i', 'ISTANZA_AUTORIZZATIVA'],
        ['/carta intestata/i', 'IMMAGINE_COORDINATA'],
        ['/mappatura/i', 'LISTA_COLLABORATORI'],
        ['/dichiaraz.*requis.*onorab/i', 'Dichiarazione sostitutiva certificato onorabilità'],
        ['/schede? prodotto|fascicoli informativi/i', 'SCHEDA_PRODOTTO'],
        ['/reclam/i', 'Gestione Reclami'],
        ['/policy banche/i', 'POLICY_LISTINO'],
        ['/credenziali|utenze/i', 'ATTIVAZIONE_UTENZE'],
        ['/nomina|designazione responsabile/i', 'Nomina Responsabile'],
        ['/allegato economico|compenso di mediazione|allegato d corrispettivi/i', 'ALLEGATO_PROVVIGIONALE'],
        ['/remunerazione|premio (correttezza|qualit)|extra\s?provv|modific\w* (provvigioni|tassi|condizioni|fasce|listini)|mod (tassi|provv|listini)|variazione condizioni|tabella tassi|monitoraggio qualit/i', 'CONDIZIONI_ECONOMICHE'],
        ['/codice di comportamento|codice collocamento/i', 'CODICE_COLLOCAMENTO'],
        ['/requisiti di onorabilit/i', 'Casellario Giudiziale & Carichi pendenti'],
        ['/contratti (attivi|cessati)/i', 'CONTRATTO_FORNITORE'],
        ['/convenzion|accordo|contratto|licenza|incarico|risoluzione/i', 'CONVENZIONE_MEDIAZIONE'],
    ],
];
