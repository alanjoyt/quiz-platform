<?php
// Cheranalloor Faces — Admin AJAX Live Cheat Feed Endpoint

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Assert admin permissions
$adminUser = getCurrentUser();
if (!$adminUser || $adminUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$since = isset($_GET['since']) ? trim($_GET['since']) : '';
if (empty($since)) {
    // If no timestamp is provided, default to logs created in the last 15 seconds
    $sinceTime = date('Y-m-d H:i:s', time() - 15);
} else {
    // Check if timestamp is numeric or ISO
    if (is_numeric($since)) {
        $sinceTime = date('Y-m-d H:i:s', intval($since));
    } else {
        $sinceTime = date('Y-m-d H:i:s', strtotime($since));
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.event_type, c.warning_number, c.created_at, u.username, u.email
        FROM cheat_logs c
        JOIN users u ON c.user_id = u.id
        WHERE c.created_at > ?
        ORDER BY c.id ASC
    ");
    $stmt->execute([$sinceTime]);
    $logs = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'since' => time(),
        'logs' => $logs
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Query error: ' . $e->getMessage()
    ]);
}
exit;
?>
