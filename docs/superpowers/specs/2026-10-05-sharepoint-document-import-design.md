# Import storico documenti SharePoint → `documents`

Data: 2026-10-05

## Obiettivo
Riportare lo storico dei documenti dei collaboratori presenti su SharePoint
(`1 - COLLABORATORI ATTIVI`) nella tabella `documents`, classificando ogni
documento con `DocumentType` e collegandolo al `Fornitori` corrispondente.

## Contesto verificato
- Drive SharePoint letto via Graph (`ListSharePointTree`): una cartella per
  collaboratore ("Cognome Nome"), con sottocartelle numerate 1–6
  (contratto, documenti personali, onorabilità, professionalità OAM/IVASS/AML,
  P.IVA/visura, altro), a volte annidate (es. "Rappel 2026").
- `fornitoris.nome` è "COGNOME NOME" (67 record, 62 non cancellati, 28 `is_active`).
- `documents` esiste già (808 righe, polimorfica) con i campi `source_app`,
  `app_id`, `app_drive_id`, `app_etag`, `document_url`, `ai_confidence_score`,
  `metadata`. Precedente: 87 righe con `documentable_type='fornitore'`.
- `document_types`: 49 righe; `regex` valorizzata sui primi ~32 tipi, alcune
  regex identiche tra tipi diversi; i tipi 33–49 non hanno regex e in parte
  duplicano gli altri.
- `.env`: `ANTHROPIC_MODEL=claude-sonnet-4-6`.

## Componenti

### 1. `App\Services\SharePoint\SharePointClient`
Token client-credentials, elenco figli di un item, scansione ricorsiva che
produce per ogni file: `id`, `name`, `path`, `folder`, `etag`, `webUrl`, `size`.
`ListSharePointTree` viene rifattorizzato per usarlo, senza cambiare output.

### 2. `App\Services\SharePoint\FornitoreMatcher`
Normalizza (minuscolo, senza accenti/punteggiatura, parole ordinate) il nome
cartella e `fornitoris.nome`/`name`; cerca anche tra i record soft-deleted.
Esito: `exact` | `fuzzy` (da rivedere) | `none`. Su `none` i file della
cartella non vengono importati e compaiono nel report.

### 3. `App\Services\SharePoint\DocumentClassifier`
- Passo A: regex di `document_types.regex` sul nome file, filtrate in base
  alla cartella numerata 1–6 (tipi compatibili, tramite `folder_hints` in config).
  Non si filtra per `is_agent`: indica solo se un agente AI ha classificato il
  contenuto del tipo, non se il tipo riguarda i collaboratori.
  Un solo match regex ⇒ tipo assegnato (confidenza 90); zero o più match ⇒ Passo B
  (con i soli tipi che hanno matchato, se ce ne sono).
- Passo B (file ambigui, senza match, o con più tipi): chiamata a Claude con
  nome file, percorso e tipi candidati `(id, name)`; risposta JSON
  `[{document_type_id, confidence}]`. Più elementi ⇒ più documenti nel file.
- Classificazione solo da nome/percorso; nessun download dei PDF (fuori scope).
- Sotto soglia (`min_confidence` del tipo, default 70) ⇒ `document_type_id` null,
  segnalato per revisione.

### 4. Import
Un record `documents` per ogni coppia (file, tipo):
`documentable_type='fornitore'`, `documentable_id`=fornitore, `company_id` dal
fornitore, `source_app='sharepoint'`, `sync_status='synced'`, `app_id`,
`app_drive_id`, `app_etag`, `document_url`=webUrl, `name`, `ai_confidence_score`,
`metadata` (percorso, esito match, origine classificazione).
Idempotenza: chiave (`app_id`, `document_type_id`); rilancio = nessun duplicato.
Le righe esistenti non vengono modificate.

### 5. Comando `sharepoint:import-documents`
`--root="1 - COLLABORATORI ATTIVI"` (default). Default dry-run: nessuna scrittura,
CSV in `storage/app/` con file, fornitore, tipo/i proposti, confidenza, esito
match. Scrittura nel DB solo con `--commit`.

## Fuori scope
Lettura contenuto PDF/OCR, cartelle 2 e 3 (ingresso/recessi), pulizia dei tipi
duplicati in `document_types` (emersa dal report, a parte).

## Test
Matcher (ordine/accenti/fuzzy/none), classificatore (regex + Claude simulato,
multi-documento, sotto soglia), import idempotente e dry-run senza scritture.
