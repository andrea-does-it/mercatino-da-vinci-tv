<?php

class BookLookup {

    /**
     * Validate ISBN-13 format and checksum
     * @param string $isbn ISBN to validate
     * @return bool True if valid ISBN-13
     */
    public static function validateIsbn13($isbn) {
        $isbn = preg_replace('/[^0-9]/', '', (string)$isbn);

        if (strlen($isbn) !== 13) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $weight = ($i % 2 === 0) ? 1 : 3;
            $sum += (int)$isbn[$i] * $weight;
        }

        $check = (10 - ($sum % 10)) % 10;
        return (int)$isbn[12] === $check;
    }

    /**
     * Get cover URL for an ISBN
     * @param string $isbn ISBN
     * @return string Cover URL
     */
    public static function coverUrl($isbn) {
        $isbnDigits = preg_replace('/[^0-9]/', '', (string)$isbn);
        return 'https://www.libraccio.it/images/' . $isbnDigits . '_0_500_0_75.jpg';
    }

    /**
     * Lookup book details from Libraccio by ISBN
     * @param string $isbn ISBN to lookup
     * @return array|null Array with keys: isbn, title, authors, publisher, list_price, cover_url, warnings
     */
    public static function lookup($isbn) {
        $isbnDigits = preg_replace('/[^0-9]/', '', (string)$isbn);

        if (!self::validateIsbn13($isbnDigits)) {
            return null;
        }

        $url = 'https://www.libraccio.it/src/?FT=' . urlencode($isbnDigits) . '&ch=libraccio';
        $html = self::httpGet($url);

        if ($html === null) {
            return [
                'isbn' => $isbnDigits,
                'title' => '',
                'authors' => '',
                'publisher' => '',
                'list_price' => null,
                'cover_url' => self::coverUrl($isbnDigits),
                'warnings' => ['Libraccio non raggiungibile']
            ];
        }

        $warnings = [];

        // Extract title from <title> tag
        $title = '';
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
            $title = trim($matches[1]);
            // Clean up common title patterns
            $title = preg_replace('/\s*\|\s*Libraccio\.it.*$/i', '', $title);
            $title = preg_replace('/^\s*Compra\s+/i', '', $title);
        }

        if (empty($title)) {
            $warnings[] = 'Titolo non trovato';
        }

        // Extract price (look for patterns like "34,60" or "€ 34,60")
        $listPrice = null;
        if (preg_match('/€?\s*(\d+(?:[.,]\d{2})?)\s*(?:€|EUR)?/i', $html, $matches)) {
            $priceStr = str_replace(',', '.', $matches[1]);
            $listPrice = (float)$priceStr;
        }

        if ($listPrice === null) {
            $warnings[] = 'Prezzo non trovato';
        }

        // Extract authors and publisher (best-effort)
        $authors = '';
        $publisher = '';

        return [
            'isbn' => $isbnDigits,
            'title' => $title,
            'authors' => $authors,
            'publisher' => $publisher,
            'list_price' => $listPrice,
            'cover_url' => self::coverUrl($isbnDigits),
            'warnings' => $warnings
        ];
    }

    /**
     * Download cover image from Libraccio
     * @param string $isbn ISBN
     * @param string $destPath Destination file path
     * @return bool True if successful
     */
    public static function downloadCover($isbn, $destPath) {
        $isbnDigits = preg_replace('/[^0-9]/', '', (string)$isbn);

        if (!self::validateIsbn13($isbnDigits)) {
            return false;
        }

        $url = self::coverUrl($isbnDigits);
        $data = self::httpGet($url);

        if ($data === null || strlen($data) < 1000) {
            return false;
        }

        return file_put_contents($destPath, $data) !== false;
    }

    /**
     * Private HTTP GET with timeout
     * @param string $url URL to fetch
     * @return string|null Response body or null on error
     */
    private static function httpGet($url) {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        // Preferisci cURL: funziona anche dove allow_url_fopen e' disabilitato
        // (tipico hosting condiviso) e non emette warning che corromperebbero il JSON.
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT      => $ua,
            ));
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($data !== false && $data !== '' && $code < 400) {
                return $data;
            }
            return null;
        }

        // Fallback: file_get_contents con warning SOPPRESSI (la @ evita di
        // sporcare l'output, es. quando allow_url_fopen e' off).
        $context = stream_context_create(array(
            'http'  => array('timeout' => 12, 'user_agent' => $ua),
            'https' => array('timeout' => 12, 'user_agent' => $ua),
        ));
        $data = @file_get_contents($url, false, $context);
        return $data !== false ? $data : null;
    }
}
