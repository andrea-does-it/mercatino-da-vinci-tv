<?php
/**
 * CSRF Protection Class
 *
 * Provides Cross-Site Request Forgery protection through token generation and validation.
 * Uses cryptographically secure random bytes for token generation.
 */
class CSRF {

    const TOKEN_NAME = 'csrf_token';
    const TOKEN_LENGTH = 32;

    /**
     * Generate a new CSRF token and store it in the session
     * @return string The generated token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }

    /**
     * Get the current CSRF token, generating one if it doesn't exist
     * @return string The current token
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return self::generateToken();
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Validate a submitted CSRF token
     * @param string|null $token The token to validate (defaults to POST data)
     * @param bool $regenerate Whether to regenerate the token after validation
     * @return bool True if valid, false otherwise
     */
    public static function validateToken($token = null, $regenerate = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get token from POST if not provided
        if ($token === null) {
            $token = isset($_POST[self::TOKEN_NAME]) ? $_POST[self::TOKEN_NAME] : '';
        }

        // Check if session token exists
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        // Use hash_equals for timing-safe comparison
        $valid = hash_equals($_SESSION[self::TOKEN_NAME], $token);

        // Optionally regenerate token after successful validation (for single-use tokens)
        if ($valid && $regenerate) {
            self::generateToken();
        }

        return $valid;
    }

    /**
     * Output a hidden form field with the CSRF token
     * @return string HTML hidden input element
     */
    public static function tokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get token for use in AJAX requests (returns token value only)
     * @return string The token value
     */
    public static function getTokenForAjax() {
        return self::getToken();
    }

    /**
     * Validate and abort if token is invalid
     * Call this at the beginning of any POST request handler
     * @param string $redirectUrl URL to redirect to on failure (optional)
     */
    public static function validateOrDie($redirectUrl = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return; // Only validate POST requests
        }

        if (!self::validateToken()) {
            if ($redirectUrl) {
                header('Location: ' . $redirectUrl);
                exit;
            }

            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }

    /**
     * Validate for AJAX requests and return JSON error if invalid
     * @return bool True if valid
     */
    public static function validateAjaxOrDie() {
        if (!self::validateToken()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'CSRF token validation failed',
                'reload' => true
            ]);
            exit;
        }
        return true;
    }

    /**
     * Get meta tag for including token in page head (useful for AJAX)
     * @return string HTML meta tag
     */
    public static function metaTag() {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
