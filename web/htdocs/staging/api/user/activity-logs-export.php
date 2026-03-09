<?php
/**
 * GDPR Activity Log Export Endpoint
 * Downloads the user's own activity log as a JSON file.
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

$actLog = new ActivityLog();
$logs = $actLog->exportUserLogs($loggedInUser->id);
log_activity($loggedInUser->id, 'activity_log_export');

$export = [
    'user_id'    => $loggedInUser->id,
    'exported_at' => date('Y-m-d H:i:s'),
    'note'       => 'Le attività vengono conservate per 12 mesi. Gli indirizzi IP non sono memorizzati in chiaro (pseudonimizzazione GDPR Art. 25).',
    'activities' => $logs
];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="registro_attivita_' . date('Y-m-d') . '.json"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
