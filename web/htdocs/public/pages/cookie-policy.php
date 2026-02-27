<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
    die;
}
?>

<h1>Cookie Policy</h1>
<p class="text-muted">Ultimo aggiornamento: <?php echo date('d/m/Y'); ?></p>

<div class="cookie-policy">

<h2>Cosa sono i Cookie</h2>
<p>
    I cookie sono piccoli file di testo che vengono memorizzati sul tuo dispositivo quando visiti un sito web.
    Sono ampiamente utilizzati per far funzionare i siti web, migliorarne l'efficienza e fornire informazioni ai proprietari del sito.
</p>

<h2>Cookie Utilizzati da questo Sito</h2>

<h3>Cookie Tecnici (Necessari)</h3>
<p>
    Questi cookie sono essenziali per il funzionamento del sito e non possono essere disattivati.
    Vengono impostati in risposta ad azioni da te effettuate, come la richiesta di servizi (login, compilazione moduli).
</p>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Nome Cookie</th>
            <th>Finalità</th>
            <th>Durata</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>PHPSESSID</code></td>
            <td>Cookie di sessione necessario per mantenere lo stato di login e il carrello</td>
            <td>Sessione (cancellato alla chiusura del browser)</td>
        </tr>
        <tr>
            <td><code>cookie_consent</code></td>
            <td>Memorizza la tua preferenza sui cookie</td>
            <td>1 anno</td>
        </tr>
    </tbody>
</table>

<h3>Cookie di Terze Parti</h3>
<p>
    Il sito potrebbe includere contenuti di terze parti (es. pulsante di condivisione Facebook) che potrebbero impostare propri cookie.
    Per questi cookie, ti invitiamo a consultare le rispettive informative privacy:
</p>
<ul>
    <li><a href="https://www.facebook.com/policies/cookies/" target="_blank" rel="noopener">Facebook Cookie Policy</a></li>
</ul>

<h2>Come Gestire i Cookie</h2>
<p>
    Puoi gestire le tue preferenze sui cookie in qualsiasi momento cliccando sul pulsante qui sotto:
</p>
<p>
    <button type="button" class="btn btn-primary" onclick="resetCookieConsent()">Gestisci Preferenze Cookie</button>
</p>

<p>
    Puoi anche controllare e/o eliminare i cookie attraverso le impostazioni del tuo browser.
    Ecco le guide per i browser più comuni:
</p>
<ul>
    <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
    <li><a href="https://support.mozilla.org/it/kb/Gestione%20dei%20cookie" target="_blank" rel="noopener">Mozilla Firefox</a></li>
    <li><a href="https://support.apple.com/it-it/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
    <li><a href="https://support.microsoft.com/it-it/microsoft-edge/eliminare-i-cookie-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Microsoft Edge</a></li>
</ul>

<h2>Conseguenze della Disattivazione dei Cookie</h2>
<p>
    Se decidi di bloccare i cookie tecnici, alcune funzionalità del sito potrebbero non essere disponibili:
</p>
<ul>
    <li>Non potrai effettuare il login</li>
    <li>Il carrello non funzionerà correttamente</li>
    <li>Le tue preferenze non verranno salvate</li>
</ul>

<h2>Aggiornamenti</h2>
<p>
    Questa Cookie Policy può essere aggiornata periodicamente.
    Ti invitiamo a consultare questa pagina regolarmente per essere informato su eventuali modifiche.
</p>

<h2>Contatti</h2>
<p>
    Per qualsiasi domanda relativa ai cookie, puoi contattarci a:
    <a href="mailto:mercatino@comitatogenitoridavtv.it">mercatino@comitatogenitoridavtv.it</a>
</p>

<p class="mt-4">
    <a href="<?php echo ROOT_URL; ?>public?page=privacy">&laquo; Torna all'Informativa Privacy</a>
</p>

</div>

<script>
function resetCookieConsent() {
    document.cookie = 'cookie_consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    location.reload();
}
</script>

<style>
.cookie-policy h2 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-size: 1.3rem;
    color: #17a2b8;
}
.cookie-policy h3 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-size: 1.1rem;
}
.cookie-policy p {
    text-align: justify;
}
</style>
