<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
    die;
}

global $loggedInUser;
if (!$loggedInUser || $loggedInUser->user_type != 'admin') {
    echo "<script>location.href='".ROOT_URL."auth?page=login';</script>";
    exit;
}

$actLog = new ActivityLog();
$logs = $actLog->getAllLogs(500, 0);
?>

<h1>Registro Attività Utenti</h1>
<p class="text-muted">
    Gli indirizzi IP sono pseudonimizzati (SHA-256) in conformità al GDPR Art. 25.
    Le attività vengono conservate per 12 mesi.
</p>

<table id="activityLogsTable" class="table table-sm table-bordered table-striped" style="width:100%">
    <thead class="thead-dark">
        <tr>
            <th>ID</th>
            <th>Utente</th>
            <th>Email</th>
            <th>Azione</th>
            <th>Dettaglio</th>
            <th>Data</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $row): ?>
        <tr>
            <td><?php echo (int)$row['id']; ?></td>
            <td><?php echo $row['user_id'] !== null ? (int)$row['user_id'] : '<span class="text-muted">—</span>'; ?></td>
            <td><?php echo $row['email'] ? htmlspecialchars($row['email']) : '<span class="text-muted">—</span>'; ?></td>
            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['action']); ?></span></td>
            <td class="small text-muted"><?php echo htmlspecialchars($row['detail'] ?? ''); ?></td>
            <td class="text-nowrap"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
$(document).ready(function() {
    $('#activityLogsTable').DataTable({
        responsive: true,
        order: [[5, 'desc']],
        pageLength: 50,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.20/i18n/Italian.json'
        }
    });
});
</script>
