<?php
/**
 * GDPR Data Export API Endpoint
 * Downloads user data as JSON file
 */

require_once '../../inc/init.php';

if (!defined('ROOT_URL')) {
    die;
}

global $loggedInUser;

// Must be logged in
if (!$loggedInUser) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

// Validate CSRF token
if (!CSRF::validateToken()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Token CSRF non valido']);
    exit;
}

$userMgr = new UserManager();
$exportData = $userMgr->exportUserData($loggedInUser->id);

// Output as JSON download
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="miei_dati_' . date('Y-m-d') . '.json"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
