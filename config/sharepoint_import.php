<?php

return [
    'root' => '1 - COLLABORATORI ATTIVI',
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
];
