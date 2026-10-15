<?php
// Cheranalloor Faces — AJAX Warning Endpoint with Cheat Logging

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$currentUser = getCurrentUser();
if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM user_quiz_state WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$state = $stmt->fetch();

if (!$state || $state['quiz_completed_at'] || $state['is_disqualified']) {
    echo json_encode(['success' => false, 'message' => 'Quiz not in active state']);
    exit;
}

// Read the event type from the JSON POST/Beacon body
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$eventType = isset($input['event_type']) ? trim($input['event_type']) : 'unknown';

$warningsCount = intval($state['warnings_count']);
$disqualified = intval($state['is_disqualified']);
$reason = $state['disqualification_reason'];

if ($eventType === 'time_penalty') {
    $penaltySeconds = isset($input['penalty_seconds']) ? intval(round(floatval($input['penalty_seconds']))) : 0;
    if ($penaltySeconds > 0) {
        // Shift start time backward to consume more of their time limit (representing 1.5x penalty speedup)
        $update = $pdo->prepare("UPDATE user_quiz_state SET current_question_started_at = DATE_SUB(current_question_started_at, INTERVAL ? SECOND) WHERE user_id = ?");
        $update->execute([$penaltySeconds, $currentUser['id']]);
        
        try {
            $insertLog = $pdo->prepare("INSERT INTO cheat_logs (user_id, event_type, warning_number) VALUES (?, 'time_penalty', ?)");
            $insertLog->execute([$currentUser['id'], $warningsCount]);
        } catch (PDOException $e) {}
    }
    
    echo json_encode([
        'success' => true,
        'disqualified' => (bool)$disqualified,
        'warnings_count' => $warningsCount
    ]);
    exit;
}

if ($eventType !== 'sync') {
    $warningsCount += 1;
    if ($warningsCount >= 4) {
        $disqualified = 1;
        $reason = "Exceeded tab switching / screen exit limits (4 warnings count).";
    }

    // 1. Update the user quiz state
    $update = $pdo->prepare("UPDATE user_quiz_state SET warnings_count = ?, is_disqualified = ?, disqualification_reason = ? WHERE user_id = ?");
    $update->execute([$warningsCount, $disqualified, $reason, $currentUser['id']]);

    // 2. Set session flag to trigger modal popup on next load
    if (!$disqualified) {
        $_SESSION['pending_warning_alert'] = $warningsCount;
    }

    // 3. Insert into the cheat_logs table for real-time admin alerting
    try {
        $insertLog = $pdo->prepare("INSERT INTO cheat_logs (user_id, event_type, warning_number) VALUES (?, ?, ?)");
        $insertLog->execute([$currentUser['id'], $eventType, $warningsCount]);
    } catch (PDOException $e) {
        // Fail silently on logging
    }
}

echo json_encode([
    'success' => true,
    'disqualified' => (bool)$disqualified,
    'warnings_count' => $warningsCount
]);
exit;
?>
