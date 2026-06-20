<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

function db_query(string $sql, array $params = []): array {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_fetch(string $sql, array $params = []): ?array {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_execute(string $sql, array $params = []): string {
    $db = get_db();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (string)$db->lastInsertId();
}

function db_transaction(callable $fn): mixed {
    $db = get_db();
    $db->beginTransaction();
    try {
        $result = $fn($db);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function new_id(): string {
    return 'c' . bin2hex(random_bytes(12));
}

function new_order_number(): string {
    return 'ORD-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * Create a notification for every user with the given roles (ADMIN, SUPERADMIN).
 * Used to broadcast system-wide events like new orders, stock alerts, menu changes.
 */
function notify_all_admins(
    string $type,
    string $title,
    string $message,
    string $orderId = ''
): void {
    $roles = ['ADMIN', 'SUPERADMIN'];
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    try {
        $admins = db_query(
            "SELECT id FROM User WHERE role IN ($placeholders) AND isActive = 1",
            $roles
        );
        foreach ($admins as $admin) {
            $nid = new_id();
            db_execute(
                'INSERT INTO Notification (id, userId, orderId, type, title, message, isRead, createdAt)
                 VALUES (?, ?, ?, ?, ?, ?, 0, datetime("now"))',
                [$nid, $admin['id'], $orderId, $type, $title, $message]
            );
        }
    } catch (Throwable $e) {
        // Never break the main request due to notification failure
    }
}

function get_restaurant(?string $slug = null): ?array {
    $slug = $slug ?? DEFAULT_RESTAURANT_SLUG;
    return db_fetch('SELECT * FROM Restaurant WHERE slug = ? AND isActive = 1', [$slug]);
}
