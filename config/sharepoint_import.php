<?php

return [
    'root' => '1 - COLLABORATORI ATTIVI',
    'default_min_confidence' => 70,
    'rule_confidence' => 90,
    'fuzzy_threshold' => 85,

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
];
