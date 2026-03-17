<?php

/**
 * GDPR-compliant user activity logging.
 *
 * - IP addresses are pseudonymised with SHA-256 (never stored in plaintext).
 * - Logs older than ACTIVITY_LOG_RETENTION_DAYS are pruned automatically.
 * - Users can export and delete their own logs from the privacy page.
 */
class ActivityLog {

    const RETENTION_DAYS = 365; // 12 months

    private $db;

    public function __construct() {
        $this->db = new DB();
    }

    /**
     * Record one activity event.
     *
     * @param int|null $userId  Authenticated user id, or null for pre-auth events
     * @param string   $action  Short identifier, e.g. 'login', 'logout', 'register'
     * @param string   $detail  Optional non-personal context (e.g. order id, page name)
     */
    public function log($userId, $action, $detail = null) {
        $ipHash = isset($_SERVER['REMOTE_ADDR'])
            ? hash('sha256', $_SERVER['REMOTE_ADDR'])
            : null;

        $this->db->execute(
            "INSERT INTO user_activity_log (user_id, action, detail, ip_hash, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [
                $userId ? (int)$userId : null,
                substr((string)$action, 0, 100),
                $detail !== null ? substr((string)$detail, 0, 500) : null,
                $ipHash
            ]
        );
    }

    /**
     * Get recent activity entries for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserLogs($userId, $limit = 50) {
        return $this->db->prepare(
            "SELECT id, action, detail, created_at
             FROM user_activity_log
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT " . (int)$limit,
            [(int)$userId]
        );
    }

    /**
     * Get all activity entries for a user (for export).
     *
     * @param int $userId
     * @return array
     */
    public function exportUserLogs($userId) {
        return $this->db->prepare(
            "SELECT action, detail, created_at
             FROM user_activity_log
             WHERE user_id = ?
             ORDER BY created_at DESC",
            [(int)$userId]
        );
    }

    /**
     * Delete all activity log entries for a user (GDPR right to erasure).
     *
     * @param int $userId
     * @return int Number of rows deleted
     */
    public function deleteUserLogs($userId) {
        return $this->db->execute(
            "DELETE FROM user_activity_log WHERE user_id = ?",
            [(int)$userId]
        );
    }

    /**
     * Get all logs for admin view (paginated).
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllLogs($limit = 100, $offset = 0) {
        return $this->db->prepare(
            "SELECT l.id, l.user_id, u.email, l.action, l.detail, l.created_at
             FROM user_activity_log l
             LEFT JOIN user u ON u.id = l.user_id
             ORDER BY l.created_at DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            []
        );
    }

    /**
     * Delete log entries older than RETENTION_DAYS.
     * Call from a scheduled task / cron.
     *
     * @return int Number of rows deleted
     */
    public function pruneOldLogs() {
        return $this->db->execute(
            "DELETE FROM user_activity_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [self::RETENTION_DAYS]
        );
    }
}
