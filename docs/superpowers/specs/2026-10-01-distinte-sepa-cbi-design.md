# Design — Distinte SEPA (XML CBI) per i rimborsi venditori con bonifico

**Data:** 2026-10-01
**Stato:** approvato (brainstorming concluso), in attesa di revisione della spec

## Obiettivo

Generare dal sito il file di **bonifici multipli SEPA** da caricare sull'home banking
UniCredit del Comitato, per rimborsare in un colpo solo i venditori che hanno scelto il
bonifico (IBAN nel profilo). Il sito tiene traccia di quali rimborsi sono finiti in quale
distinta e permette di segnarli come pagati quando la banca ha eseguito i bonifici.

## Decisioni prese

| Decisione | Scelta |
|---|---|
| Formato file | **XML SEPA CBI v04.01** (`CBIPaymentRequest.00.04.01`), richiesto da UniCredit (la v04.00 è ancora accettata ma convertita; generiamo direttamente la 04.01) |
| Paese | Obbligatorio per ordinante e beneficiario: ordinante da impostazione (`IT`), beneficiario dalle prime 2 lettere dell'IBAN |
| Effetto della generazione | I rimborsi inclusi passano allo stato nuovo **`xmlsaved`** ("Distinta generata"); il pagamento si registra solo con **"Segna come pagati"** |
| Rigenerazione | Ammessa anche per i rimborsi già `xmlsaved` (correzioni IBAN/nome); nuovo `MsgId` ogni volta |
| Selezione | **Checklist** degli idonei: preselezionati quelli mai inclusi in una distinta, non selezionati quelli `xmlsaved`; non idonei elencati a parte con il motivo |
| Storico | Tabelle `sepa_batch` + `sepa_batch_item`; **l'XML non viene salvato** sul server (IBAN in chiaro) |
| Causale | Template modificabile salvato in `site_settings`, default `Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}`, max 140 caratteri |
| Data esecuzione | Campo data, default **prossimo giorno lavorativo** (salta sabato/domenica), non nel passato |
| Addebito | **Unico addebito cumulativo** (`BtchBookg = true`) |
| Dati ordinante | In `site_settings`, modificabili dal box "Dati ordinante" della pagina |
| Costruzione XML | Classe dedicata con **DOMDocument** + validazione XSD se lo schema è disponibile |

## Database

Nuova migrazione `202610010001_sepa_distinte.sql` in **entrambi** i `sql/`
(`web/htdocs/sql/` e `web/htdocs/staging/sql/`). **Va applicata a mano su ogni
ambiente**: finché non lo è, qualunque scrittura di `status = 'xmlsaved'` fallisce.

```sql
-- 1. Nuovo stato del rimborso (solo aggiunta di un valore: le righe esistenti non cambiano)
ALTER TABLE `seller_refund`
  MODIFY `status` ENUM('pending','partial','xmlsaved','completed','cancelled')
  NOT NULL DEFAULT 'pending';

-- 2. Una riga per file generato
CREATE TABLE IF NOT EXISTS `sepa_batch` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `year` SMALLINT(4) NOT NULL,
  `msg_id` VARCHAR(35) NOT NULL COMMENT 'MsgId/PmtInfId inviati alla banca',
  `execution_date` DATE NOT NULL,
  `remittance_template` VARCHAR(140) NOT NULL,
  `debtor_iban_masked` VARCHAR(20) NOT NULL,
  `tx_count` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('generated','paid','discarded') NOT NULL DEFAULT 'generated',
  `created_by` INT(11) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` DATETIME NULL,
  `paid_by` INT(11) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_msg_id` (`msg_id`),
  INDEX `idx_year_status` (`year`, `status`),
  CONSTRAINT `fk_sepa_batch_created_by` FOREIGN KEY (`created_by`) REFERENCES `user`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sepa_batch_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `user`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Una riga per bonifico
CREATE TABLE IF NOT EXISTS `sepa_batch_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_id` INT(11) NOT NULL,
  `seller_refund_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `end_to_end_id` VARCHAR(35) NOT NULL,
  `remittance` VARCHAR(140) NOT NULL,
  `beneficiary_name` VARCHAR(70) NOT NULL,
  `iban_masked` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_refund` (`seller_refund_id`),
  CONSTRAINT `fk_sbi_batch` FOREIGN KEY (`batch_id`) REFERENCES `sepa_batch`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sbi_refund` FOREIGN KEY (`seller_refund_id`) REFERENCES `seller_refund`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Impostazioni ordinante (vuote: si compilano dalla pagina)
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('sepa_debtor_name', '', 'Distinte SEPA: intestatario conto del Comitato'),
  ('sepa_debtor_iban', '', 'Distinte SEPA: IBAN del Comitato'),
  ('sepa_debtor_cuc', '', 'Distinte SEPA: Codice Univoco CBI (CUC) assegnato dalla banca'),
  ('sepa_debtor_country', 'IT', 'Distinte SEPA: paese ordinante'),
  ('sepa_remittance_template', 'Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}', 'Distinte SEPA: causale (max 140 caratteri)');
```

**L'IBAN completo non viene mai salvato** in `sepa_batch`/`sepa_batch_item`: solo la
versione mascherata (`IT60…0123`, prime 4 + ultime 4). L'IBAN dei venditori resta
cifrato in `user.iban` e viene decifrato solo in memoria durante la generazione.

### Identificativi
- `msg_id` (= `PmtInfId`): `MDV-{anno}-{id batch a 4 cifre}-{YmdHis}`, es.
  `MDV-2026-0007-20261001153012` (≤ 35 caratteri, univoco per costruzione).
- `end_to_end_id`: `MDV{anno}-R{seller_refund_id}-B{batch_id}`, es. `MDV2026-R123-B7`.
- Poiché l'id del batch serve negli identificativi, la riga `sepa_batch` si inserisce
  per prima (dentro la transazione), poi si costruisce l'XML.

## Regole di dominio

- **Idonei a una distinta** (anno selezionato): `seller_refund.payment_preference =
  'wire_transfer'`, `status IN ('pending','partial','xmlsaved')`,
  `amount_owed - amount_paid > 0`, e `sqlIsRealSeller('sr')` (pratica 100 esclusa,
  come ogni altra query della sezione rimborsi).
- **Importo del bonifico** = `amount_owed - amount_paid`, arrotondato a 2 decimali.
- **Non includibili** (elencati a parte con il motivo, link a `seller-refund-view`):
  - IBAN assente;
  - IBAN non decifrabile (es. dopo rotazione della chiave, cfr. `jobs/reencrypt-ibans.php`);
  - IBAN con checksum errato (mod 97);
  - IBAN di un paese fuori dall'area SEPA.
- **Beneficiario**: `user.iban_owner_name`, altrimenti `first_name last_name`;
  traslitterato al set di caratteri SEPA (vedi sotto), max 70 caratteri.
- **Causale**: template con `{anno}` e `{pratiche}` (numeri pratica del venditore escluso
  il 100, separati da `, `), traslitterata; se supera 140 caratteri si tronca l'elenco
  pratiche all'ultima pratica intera e si chiude con `...` (il carattere `…` non è
  ammesso nel set SEPA).
- **Stati**:
  - generazione → i rimborsi inclusi diventano `xmlsaved`;
  - "Segna come pagati" → pagamento registrato, stato `completed` (o `partial` se
    nel frattempo `amount_owed` è cresciuto);
  - "Scarta" → il batch diventa `discarded`; i suoi rimborsi ancora `xmlsaved` tornano
    a `pending` (o `partial` se `amount_paid > 0`), **a meno che** non siano in un altro
    batch `generated`.
- **Rigenerazione / batch superati**: un rimborso può comparire in più batch. Le righe
  dei batch precedenti restano (storico); nello storico la riga è marcata **"superata"**
  se lo stesso rimborso è in un batch più recente non scartato. "Segna come pagati" su
  un batch **salta le righe superate**: un rimborso non può essere pagato due volte
  tramite due distinte.
- Ogni azione (generazione, pagati, scarta, modifica dati ordinante) va in
  `user_activity_log` con id batch, numero bonifici e totale — **mai un IBAN in chiaro**.
- **[2026-10-01, implementazione]** La regola "superata" è stata resa più severa di quanto
  descritto sopra: "Segna come pagati" non si limita a *saltare* le righe di un rimborso
  presente anche in un batch più recente, ma **rifiuta l'intera operazione con un errore**
  se quel batch più recente è ancora `generated` (non pagato né scartato) — l'operatore deve
  prima scartarlo o segnarlo come pagato. Vengono invece saltate, come da spec originale, solo
  le righe superate da un batch più recente già `paid`.

## Componenti

### `classes/SepaCbiExport.php` — costruzione XML (nessun accesso al DB)
Input: dati ordinante (nome, IBAN, CUC, paese), `msg_id`, data esecuzione, elenco di
bonifici (`end_to_end_id`, importo, nome, IBAN, paese, causale). Output: stringa XML.
Contiene anche gli helper puri:
- `ibanIsValid($iban)` (normalizza spazi/maiuscole, lunghezza per paese, mod 97);
- `isSepaCountry($cc)` (elenco paesi SEPA);
- `ibanMask($iban)`;
- `toSepaCharset($text, $maxLen)` (rimuove accenti — `iconv` ASCII//TRANSLIT con
  fallback a tabella — e sostituisce i caratteri fuori da `a-z A-Z 0-9 / - ? : ( ) . , ' + spazio`);
- `validate($xml)` → `DOMDocument::schemaValidate()` sull'XSD CBI se il file
  `classes/xsd/CBIPaymentRequest.00.04.01.xsd` esiste; restituisce l'elenco errori.

Struttura del messaggio (namespace `urn:CBI:xsd:CBIPaymentRequest.00.04.01`):

```
CBIPaymentRequest
├─ GrpHdr
│  ├─ MsgId, CreDtTm, NbOfTxs, CtrlSum
│  └─ InitgPty / Nm + Id/OrgId/Othr/{Id = CUC, Issr = "CBI"}
└─ PmtInf
   ├─ PmtInfId (= MsgId), PmtMtd = TRF, BtchBookg = true
   ├─ PmtTpInf / InstrPrty = NORM, SvcLvl/Cd = SEPA
   ├─ ReqdExctnDt / Dt (annidato per doc. CBI 2023, non ancora verificato da UniCredit — vedi addendum XSD sotto)
   ├─ Dbtr / Nm + PstlAdr/Ctry
   ├─ DbtrAcct / Id/IBAN
   ├─ DbtrAgt / FinInstnId/ClrSysMmbId/MmbId = ABI (caratteri 6–10 dell'IBAN IT)
   ├─ ChrgBr = SLEV
   └─ CdtTrfTxInf (×N)
      ├─ PmtId / InstrId (progressivo) + EndToEndId
      ├─ PmtTpInf / CtgyPurp/Cd (per transazione, non in PmtInf — vedi addendum XSD sotto)
      ├─ Amt / InstdAmt Ccy="EUR" (punto decimale, 2 cifre)
      ├─ Cdtr / Nm + PstlAdr/Ctry (dall'IBAN)
      ├─ CdtrAcct / Id/IBAN
      └─ RmtInf / Ustrd (causale)
```

**Verificato sull'XSD ufficiale CBI (validazione UniCredit 2026-10, vedi addendum sotto)**:
`CtgyPurp` va in `CdtTrfTxInf/PmtTpInf`, non in `PmtInf/PmtTpInf`. **Non verificato** da
UniCredit: `ReqdExctnDt/Dt` annidato — la validazione si è fermata su `CtgyPurp`, che nel
documento viene prima di `ReqdExctnDt`, quindi non dice nulla su quell'elemento; la forma
annidata resta indicata solo dalla documentazione CBI 2023. La struttura sopra riflette
questo esito; la classe isola questi dettagli in un solo punto.

### `classes/SepaBatch.php` — `SepaBatchManager` (DBManager)
- `getEligibleRefunds($year)` → idonei + motivo di esclusione per i non includibili
  (decifra l'IBAN tramite `UserManager::getIBAN()`); per ogni rimborso anche l'ultimo
  batch non scartato in cui compare.
- `getDebtorSettings()` / `saveDebtorSettings($data)` (validazione IBAN, CUC 1–8
  caratteri alfanumerici — da confermare, paese 2 lettere).
- `createBatch($year, $refundIds, $executionDate, $template, $operatorId)` → dentro una
  **transazione**: insert `sepa_batch`, costruzione righe, `SepaCbiExport`, validazione,
  insert `sepa_batch_item`, update `status = 'xmlsaved'`; in caso di errore rollback e
  nessuna traccia. Ricontrolla lato server l'idoneità di ogni id ricevuto (mai fidarsi del
  POST). Restituisce `['batch' => ..., 'xml' => ..., 'filename' => ...]`.
- `markBatchPaid($batchId, $paymentDate, $operatorId)` → in transazione, per ogni riga non
  superata: `SellerRefundManager::recordPayment($refundId, $amount, 'wire_transfer',
  $paymentDate, $msgId, 'Distinta SEPA #'.$batchId, $operatorId)`; batch → `paid`.
  `recordPayment()` oggi non è transazionale ed è chiamato dentro la transazione esterna:
  verificare che il wrapper DB supporti `beginTransaction/commit/rollBack` (aggiungerli se
  mancano).
- `discardBatch($batchId, $operatorId)`.
- `getBatchesForYear($year)`, `getBatchItems($batchId)` (con flag `superseded`).

### `admin/seller-refund-sepa.php` — pagina "Distinte SEPA"
Linkata da `seller-refunds.php`; **aggiunta a `$allowedPages` in `admin/index.php`**
(altrimenti il router ricade sulla dashboard). Testi in italiano, "tu".

1. **Anno + Dati ordinante** — selettore anno; box comprimibile con Intestatario, IBAN,
   CUC, Paese (POST + `csrf_field()`). Avviso rosso se un campo manca o l'IBAN non è
   valido; in quel caso il pulsante di generazione è disabilitato.
2. **Nuova distinta** — data esecuzione (default prossimo giorno lavorativo), causale
   (precompilata dal setting, salvata se modificata, contatore caratteri), tabella con
   checkbox / venditore / pratiche / beneficiario / IBAN mascherato / importo / stato /
   anteprima causale / nota "in distinta #7 del 12/10". Totale selezionato aggiornato in JS.
   Pulsante **"Genera distinta XML"**.
3. **Esclusi** — rimborsi bonifico non includibili con il motivo.
4. **Storico distinte** — n°, data, data esecuzione, bonifici, totale, stato, admin;
   dettaglio espandibile con le righe (superate evidenziate); azioni **"Segna come
   pagati"** (conferma, con data pagamento default = data esecuzione) e **"Scarta"**
   (conferma) finché il batch è `generated`. Nessun "riscarica": per riavere il file si
   rigenera.

Generazione: POST + CSRF (`CSRF::validateToken()`) alla stessa pagina/endpoint dedicato;
in caso di successo risposta diretta con `Content-Type: application/xml`,
`Content-Disposition: attachment; filename="distinta_sepa_2026_007.xml"`. L'output deve
partire prima di qualsiasi HTML: la gestione del POST va fatta **prima** che il layout
admin emetta output (verificare come il router `admin/index.php` include le pagine; se
emette header HTML prima, usare un endpoint separato `admin/sepa-download.php` o simile).
In caso di errore (validazione, XSD) si torna alla pagina con il messaggio.

### Modifiche a codice esistente
- `SellerRefundManager::sellerFilterConditions()` — `$allowedStatus` include `xmlsaved`.
- `getYearSummary()` — `xmlsaved_count`; card/statistiche su `seller-refunds.php`.
- Etichette e badge di stato ovunque compaia lo stato del rimborso (`seller-refunds.php`,
  `seller-refund-view.php`, `seller-refund-report.php`, filtri Stato): "Distinta generata".
- `seller-refund-view.php` — sezione "Distinte SEPA" con i batch in cui compare il
  rimborso.
- Nessuna modifica alla landing page `payment-preference.php`.

Tutto il codice va **duplicato in `web/htdocs/staging/`** (regola dual-tree), la
migrazione in entrambi i `sql/`.

## Sicurezza e privacy
- Pagina e azioni solo per admin (stesso controllo delle altre pagine rimborsi).
- CSRF su tutti i POST (dati ordinante, genera, pagati, scarta).
- L'XML esiste solo nella risposta HTTP; nessun file temporaneo su disco.
- Nessun IBAN in chiaro in DB (oltre a quello cifrato già esistente), nei log o nell'HTML
  della pagina (solo mascherato).
- Id castati a int; output con `esc()`/`esc_html()`.

## Verifica (nessuna suite di test; `php` spesso non disponibile)
- Se `php` è disponibile: script CLI in `docs/`/scratch che genera un XML di prova con
  IBAN fittizi validi e lo valida contro l'XSD.
- Confronto strutturale con un **XML esportato da UniCredit** (distinta di prova inserita a
  mano, IBAN mascherati).
- Su **staging**: migrazione applicata, generazione con 2–3 rimborsi di prova, verifica di
  stati, storico, superata/scarta/pagati.
- **Primo caricamento reale**: importare il file su UniCredit **senza autorizzare** la
  distinta e controllare l'anteprima (numero bonifici, totale, nomi, causali).

## Documentazione da aggiornare (stesso lavoro)
`context/03-codebase-map.md` (nuove classi e pagina), `context/04-database.md`
(tabelle, nuovo stato, settings), `context/05-domain-workflows.md` (sezione distinte SEPA),
`context/06-conventions-and-gotchas.md` (XML solo in risposta, migrazione enum),
`context/INDEX.md` (snapshot).

## Punti aperti (da chiudere prima/durante l'implementazione)
1. **CUC** del Comitato — da recuperare dal portale UniCredit o dalla filiale. Per ora è
   **obbligatorio** (senza CUC la generazione è bloccata), come da standard CBI. Se l'XML
   esportato da UniCredit o un caricamento di prova non autorizzato mostrano che il portale
   non lo richiede, si potrà renderlo facoltativo.
2. **XSD ufficiale CBI 00.04.01** — da ottenere (CBI / UniCredit); senza XSD si salta la
   validazione di schema e restano solo i controlli applicativi.
3. **XML di esempio esportato da UniCredit** (IBAN mascherati) — per confermare
   `ReqdExctnDt`, `CtgyPurp`, `PstlAdr`, presenza di BIC/ABI. **Parzialmente chiuso** dalla
   validazione XSD UniCredit di ottobre 2026 (vedi secondo addendum sotto): `CtgyPurp` deve
   stare in `CdtTrfTxInf/PmtTpInf`, non in `PmtInf`. **Resta aperto**: la forma di
   `ReqdExctnDt` (annidato o no) non è stata verificata da UniCredit — la validazione si è
   fermata su `CtgyPurp`, che viene prima nel documento; indicazione attuale solo dalla
   documentazione CBI 2023.
4. Formato esatto del CUC (lunghezza) per la validazione del campo.

## Addendum 2026-10-01 — Esito primo caricamento UniCredit

Il primo file reale (`distinta_sepa_2026_001.xml`) è stato caricato su UniCredit **senza
autorizzarlo** (come da piano di verifica sopra) ed è stato **scartato**, con questi
messaggi:

```
Informazioni di addebito --> DEBTOR_ERROR : Coppia Località/Paese mancanti
Informazioni relative alla singola transazione --> Creditore : Coppia Località/Paese mancanti (EndToEndId: MDV2026-R6-B1)
Header messaggio logico --> Identificativo : Per permettere il caricamento rispettando le specifiche Banca il CUC verrà sostituito   (warning only)
Informazioni di addebito --> Iban : Il TAG Category Purpose (CtgyPurp) è assente o non valorizzato
File scartato.
```

Decisioni prese per il Task 9 (correzioni):

- **Località venditore**: una **impostazione di default** (`sepa_default_creditor_town`)
  usata per ogni beneficiario, più un **override per singolo venditore** (`user.iban_town`,
  migrazione `202610020001`) editabile dalla pagina di dettaglio del rimborso
  (`seller-refund-view.php`).
- **Category Purpose**: fisso a **`SUPP`**, salvato in un setting (`sepa_category_purpose`)
  così da poterlo cambiare senza toccare il codice.
- **CUC**: diventa **facoltativo** (il punto aperto 1 sopra è chiuso in questo senso — la
  banca lo sostituisce comunque). Se compilato deve comunque essere valido (1–8
  lettere/cifre) e viene inviato; se vuoto, `InitgPty/Id` viene omesso del tutto.

Migrazione `sql/202610020001_sepa_localita.sql`: aggiunge `user.iban_town` e i tre nuovi
settings (`sepa_debtor_town`, `sepa_default_creditor_town`, `sepa_category_purpose`).
`SepaBatchManager::isInstalled()` ora richiede anche questa migrazione.

## Addendum 2026-10-01 — Esito secondo caricamento UniCredit (validazione XSD)

Dopo le correzioni del Task 9 (località, Category Purpose), UniCredit ha validato il nuovo
file contro l'XSD ufficiale CBI e l'ha respinto con questo errore:

```
cvc-complex-type.2.4.a: È stato rilevato contenuto non valido che inizia con l'elemento
'CtgyPurp'. È previsto uno di '{"urn:CBI:xsd:CBIPaymentRequest.00.04.01":LclInstrm}'. line 29
```

Causa: `CtgyPurp` era stato messo in `PmtInf/PmtTpInf`, ma lo schema CBI 04.01 ammette in
quella posizione solo `InstrPrty` e `SvcLvl` (poi, opzionalmente, `LclInstrm`) — il Category
Purpose per lo schema CBI vive **a livello di singola transazione**, come conferma anche
l'esempio ufficiale CBI SCT (`PmtInf/PmtTpInf` = solo `InstrPrty`/`SvcLvl`; ogni
`CdtTrfTxInf` = `PmtId`, `PmtTpInf/CtgyPurp/Cd`, `Amt`, `Cdtr`, `CdtrAcct`, `RmtInf`).

Decisioni prese per il Task 10 (correzione):

- **Category Purpose**: spostato da `PmtInf/PmtTpInf/CtgyPurp/Cd` a
  `CdtTrfTxInf/PmtTpInf/CtgyPurp/Cd` **in ogni bonifico** (stesso valore validato dal
  setting `sepa_category_purpose`, letto una volta per l'intera distinta). Posizione
  nell'elemento: subito dopo `PmtId`, prima di `Amt`.
  `PmtInf/PmtTpInf` ora contiene solo `InstrPrty` e `SvcLvl/Cd`.
- **`ReqdExctnDt/Dt` annidato**: **non verificato** da questo caricamento. L'errore XSD si
  ferma su `CtgyPurp`, che nel documento viene **prima** di `ReqdExctnDt` (dentro
  `PmtInf/PmtTpInf`) — la validazione non è arrivata a leggere `ReqdExctnDt`, quindi non
  conferma né smentisce nulla su quell'elemento. La forma annidata
  (`<ReqdExctnDt><Dt>…</Dt></ReqdExctnDt>`) resta indicata solo dalla documentazione CBI
  2023 (SCT tracciato flusso new 2023). `EXEC_DATE_NESTED` in `SepaCbiExport` resta `true`
  sulla base di quell'indicazione, ma **va ancora verificato** con un caricamento UniCredit
  che superi la fase di `CtgyPurp`.
