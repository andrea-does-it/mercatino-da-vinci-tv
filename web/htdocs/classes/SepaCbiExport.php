<?php

/**
 * Distinte SEPA in formato XML CBI (CBIPaymentRequest.00.04.01).
 *
 * Classe pura: nessun accesso al DB. Riceve dati già pronti e restituisce
 * stringhe. Gli IBAN in chiaro esistono solo nei parametri e nell'XML
 * restituito: non vanno loggati né salvati.
 */
class SepaCbiExport {

    const XML_NAMESPACE = 'urn:CBI:xsd:CBIPaymentRequest.00.04.01';
    const XSD_FILE = 'CBIPaymentRequest.00.04.01.xsd';
    const MAX_REMITTANCE = 140;
    const MAX_NAME = 70;

    /** Lunghezza IBAN per i paesi dell'area SEPA (EPC409-09). */
    private static $sepaIbanLengths = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20, 'ES' => 24, 'FI' => 18,
        'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28,
        'IE' => 22, 'IS' => 26, 'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20,
        'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28,
        'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27,
        'VA' => 22,
    ];

    public static function normalizeIban($iban) {
        return strtoupper(preg_replace('/\s+/', '', (string)$iban));
    }

    public static function isSepaCountry($cc) {
        return isset(self::$sepaIbanLengths[strtoupper((string)$cc)]);
    }

    /**
     * Formato, lunghezza per paese (solo paesi SEPA) e controllo mod 97.
     */
    public static function ibanIsValid($iban) {
        $iban = self::normalizeIban($iban);
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $cc = substr($iban, 0, 2);
        if (isset(self::$sepaIbanLengths[$cc]) && strlen($iban) !== self::$sepaIbanLengths[$cc]) {
            return false;
        }
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $ch) {
            $numeric .= ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
        }
        // mod 97 a blocchi: il numero è troppo lungo per un intero
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int)($remainder . $chunk) % 97;
        }
        return $remainder === 1;
    }

    public static function ibanMask($iban) {
        $iban = self::normalizeIban($iban);
        if (strlen($iban) < 8) {
            return str_repeat('*', strlen($iban));
        }
        return substr($iban, 0, 4) . '…' . substr($iban, -4);
    }

    /** Codice ABI (5 cifre) di un IBAN italiano: IT + 2 check + CIN + ABI. */
    public static function abiFromIban($iban) {
        return substr(self::normalizeIban($iban), 5, 5);
    }

    /**
     * Riduce il testo al set di caratteri SEPA:
     * a-z A-Z 0-9 / - ? : ( ) . , ' + spazio.
     * Gli accenti diventano la lettera base, il resto diventa spazio;
     * gli spazi multipli si riducono a uno.
     */
    public static function toSepaCharset($text, $maxLen) {
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','À'=>'A','Á'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o','Ò'=>'O','Ó'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O','Ø'=>'O',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
            'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N','ß'=>'ss','ý'=>'y','ÿ'=>'y','Ý'=>'Y',
            'š'=>'s','Š'=>'S','ž'=>'z','Ž'=>'Z','č'=>'c','Č'=>'C','ć'=>'c','Ć'=>'C',
            "\u{2019}"=>"'", "\u{2018}"=>"'", '`'=>"'", "\u{201C}"=>' ', "\u{201D}"=>' ', "\u{2013}"=>'-', "\u{2014}"=>'-', "\u{2026}"=>'...',
        ];
        $text = strtr((string)$text, $map);
        $text = preg_replace("~[^A-Za-z0-9/\-?:().,'+ ]~", ' ', $text);
        $text = trim(preg_replace('/ {2,}/', ' ', $text));
        if (strlen($text) > $maxLen) {
            $text = rtrim(substr($text, 0, $maxLen));
        }
        return $text;
    }

    /**
     * Causale da template con {anno} e {pratiche}. Se supera $maxLen, l'elenco
     * pratiche viene troncato all'ultima pratica intera e chiuso con " ...".
     */
    public static function buildRemittance($template, $year, array $praticas, $maxLen = self::MAX_REMITTANCE) {
        $praticas = array_values(array_map('intval', $praticas));
        $fill = function ($list) use ($template, $year) {
            return self::toSepaCharset(
                str_replace(['{anno}', '{pratiche}'], [(string)(int)$year, $list], $template),
                10000
            );
        };

        $text = $fill(implode(', ', $praticas));
        if (strlen($text) <= $maxLen) {
            return $text;
        }
        for ($n = count($praticas) - 1; $n >= 1; $n--) {
            $text = $fill(implode(', ', array_slice($praticas, 0, $n)) . ' ...');
            if (strlen($text) <= $maxLen) {
                return $text;
            }
        }
        // Template da solo già troppo lungo: taglio secco.
        return self::toSepaCharset($fill('...'), $maxLen);
    }

    /** Prossimo giorno lavorativo (salta sabato e domenica) dopo $from. */
    public static function nextBusinessDay(DateTimeInterface $from) {
        $d = new DateTime($from->format('Y-m-d'));
        do {
            $d->modify('+1 day');
        } while ((int)$d->format('N') >= 6);
        return $d->format('Y-m-d');
    }
}
