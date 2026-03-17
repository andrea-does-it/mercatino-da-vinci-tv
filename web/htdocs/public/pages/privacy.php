<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
    die;
}
?>

<h1>Informativa sulla Privacy</h1>
<p class="text-muted">Ultimo aggiornamento: <?php echo date('d/m/Y'); ?></p>

<div class="privacy-policy">

<h2>1. Titolare del Trattamento</h2>
<p>
    Il Titolare del trattamento dei dati personali è il <strong>Comitato Genitori Liceo Scientifico "Leonardo Da Vinci"</strong> di Treviso.<br>
    Email di contatto: <a href="mailto:mercatino@comitatogenitoridavtv.it">mercatino@comitatogenitoridavtv.it</a>
</p>

<h2>2. Finalità del Trattamento</h2>
<p>I dati personali raccolti vengono trattati per le seguenti finalità:</p>
<ul>
    <li><strong>Gestione account utente:</strong> per permettere la registrazione e l'accesso all'area riservata del sito</li>
    <li><strong>Gestione delle pratiche di vendita/acquisto libri:</strong> per consentire lo scambio di libri usati tra i genitori degli studenti</li>
    <li><strong>Comunicazioni di servizio:</strong> per inviare conferme di ordini, aggiornamenti sullo stato delle pratiche</li>
    <li><strong>Newsletter (solo con consenso esplicito):</strong> per inviare comunicazioni relative alle attività del Comitato</li>
</ul>

<h2>3. Base Giuridica del Trattamento</h2>
<p>Il trattamento dei dati personali si basa su:</p>
<ul>
    <li><strong>Consenso dell'interessato</strong> (Art. 6.1.a GDPR): per la registrazione al sito e l'iscrizione alla newsletter</li>
    <li><strong>Esecuzione di un contratto</strong> (Art. 6.1.b GDPR): per la gestione delle pratiche di vendita/acquisto libri</li>
    <li><strong>Legittimo interesse</strong> (Art. 6.1.f GDPR): per comunicazioni strettamente necessarie al servizio</li>
</ul>

<h2>4. Tipologie di Dati Raccolti</h2>
<p>Raccogliamo le seguenti categorie di dati personali:</p>
<ul>
    <li><strong>Dati identificativi:</strong> nome, cognome, indirizzo email</li>
    <li><strong>Dati di contatto:</strong> indirizzo (via, città, CAP) - opzionale, utilizzato per la consegna dei libri</li>
    <li><strong>Dati bancari:</strong> codice IBAN - opzionale, utilizzato esclusivamente per accreditare il ricavato dalla vendita dei libri</li>
    <li><strong>Dati di accesso:</strong> password (memorizzata in forma criptata)</li>
    <li><strong>Dati relativi alle transazioni:</strong> storico delle pratiche di vendita/acquisto libri</li>
</ul>

<h2>5. Modalità di Trattamento</h2>
<p>
    I dati personali sono trattati con strumenti informatici e telematici, con logiche strettamente correlate alle finalità indicate.
    Adottiamo misure di sicurezza tecniche e organizzative adeguate per proteggere i dati da accessi non autorizzati, perdita o distruzione.
</p>
<p>Le misure di sicurezza includono:</p>
<ul>
    <li>Crittografia delle password</li>
    <li>Connessione sicura HTTPS</li>
    <li>Protezione CSRF contro attacchi informatici</li>
    <li>Crittografia AES-256 dei dati bancari sensibili (IBAN)</li>
    <li>Accesso limitato ai dati solo al personale autorizzato</li>
</ul>

<h2>6. Registro Attività (Log)</h2>
<p>
    Il sito registra alcune attività degli utenti autenticati (accesso, logout, registrazione) per finalità di sicurezza e
    trasparenza, in conformità all'Art. 5.1.f del GDPR (integrità e riservatezza).
</p>
<ul>
    <li><strong>Dati registrati:</strong> tipo di azione eseguita, data e ora, informazioni contestuali non personali</li>
    <li><strong>Indirizzo IP:</strong> non viene memorizzato in chiaro; viene conservato solo un hash SHA-256 (pseudonimizzazione, Art. 25 GDPR)</li>
    <li><strong>Conservazione:</strong> 12 mesi, dopodiché i dati vengono eliminati automaticamente</li>
    <li><strong>Diritti dell'utente:</strong> puoi visualizzare, esportare ed eliminare il tuo registro attività dalla tua <a href="<?php echo ROOT_URL; ?>user?page=privacy">area personale</a></li>
</ul>

<h2>7. Periodo di Conservazione</h2>
<p>I dati personali vengono conservati per il tempo necessario al raggiungimento delle finalità per cui sono stati raccolti:</p>
<ul>
    <li><strong>Dati dell'account:</strong> fino alla cancellazione dell'account da parte dell'utente o per 3 anni dall'ultimo accesso</li>
    <li><strong>Dati delle pratiche:</strong> per 5 anni dalla chiusura della pratica (per adempimenti fiscali)</li>
    <li><strong>Registro attività:</strong> 12 mesi</li>
    <li><strong>Dati di navigazione:</strong> per la durata della sessione</li>
</ul>

<h2>8. Diritti dell'Interessato</h2>
<p>In conformità al GDPR, hai diritto di:</p>
<ul>
    <li><strong>Accesso:</strong> ottenere conferma dell'esistenza di un trattamento e accedere ai tuoi dati</li>
    <li><strong>Rettifica:</strong> ottenere la correzione di dati inesatti</li>
    <li><strong>Cancellazione:</strong> ottenere la cancellazione dei dati ("diritto all'oblio")</li>
    <li><strong>Limitazione:</strong> ottenere la limitazione del trattamento</li>
    <li><strong>Portabilità:</strong> ricevere i tuoi dati in formato strutturato e trasferirli ad altro titolare</li>
    <li><strong>Opposizione:</strong> opporti al trattamento dei tuoi dati</li>
    <li><strong>Revoca del consenso:</strong> revocare in qualsiasi momento il consenso prestato</li>
</ul>

<p>
    Puoi esercitare questi diritti dalla tua <a href="<?php echo ROOT_URL; ?>user?page=privacy">area personale</a>
    o contattandoci all'indirizzo <a href="mailto:mercatino@comitatogenitoridavtv.it">mercatino@comitatogenitoridavtv.it</a>.
</p>

<h2>9. Comunicazione e Diffusione dei Dati</h2>
<p>
    I dati personali non vengono diffusi a terzi. Possono essere comunicati a:
</p>
<ul>
    <li>Provider di servizi tecnici (hosting) per l'erogazione del servizio</li>
    <li>Autorità competenti, se richiesto dalla legge</li>
</ul>

<h2>10. Trasferimento dei Dati</h2>
<p>
    I dati personali sono conservati su server situati nell'Unione Europea.
    Non effettuiamo trasferimenti di dati verso paesi terzi.
</p>

<h2>11. Cookie</h2>
<p>
    Il sito utilizza cookie tecnici necessari al funzionamento.
    Per maggiori informazioni, consulta la nostra <a href="<?php echo ROOT_URL; ?>public?page=cookie-policy">Cookie Policy</a>.
</p>

<h2>12. Modifiche all'Informativa</h2>
<p>
    Ci riserviamo il diritto di modificare questa informativa in qualsiasi momento.
    Le modifiche saranno pubblicate su questa pagina con indicazione della data di ultimo aggiornamento.
</p>

<h2>13. Contatti e Reclami</h2>
<p>
    Per qualsiasi domanda relativa al trattamento dei tuoi dati personali, puoi contattarci a:
    <a href="mailto:mercatino@comitatogenitoridavtv.it">mercatino@comitatogenitoridavtv.it</a>
</p>
<p>
    Hai inoltre il diritto di proporre reclamo all'Autorità Garante per la Protezione dei Dati Personali:
    <a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">www.garanteprivacy.it</a>
</p>

</div>

<style>
.privacy-policy h2 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-size: 1.3rem;
    color: #17a2b8;
}
.privacy-policy ul {
    margin-bottom: 1rem;
}
.privacy-policy p {
    text-align: justify;
}
</style>
