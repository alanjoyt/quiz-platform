<?php
// Cheranalloor Faces — CSV Leaderboard Exporter with Duration

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../db.php';

// Enforce admin check
$adminUser = requireAdmin();

// Set HTTP headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leaderboard_' . date('Ymd_His') . '.csv');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Write column headers
fputcsv($output, ['Rank', 'Username', 'Email/Phone', 'Total Score', 'Base Score', 'Speed Bonus', 'Completion Time', 'Duration (Seconds)']);

// Query full leaderboard details including private email listings for administrative exports
$query = "
    SELECT u.id, u.username, u.email,
           COALESCE(SUM(r.points_earned), 0) AS base_score,
           COALESCE(SUM(r.speed_bonus), 0) AS speed_bonus,
           COALESCE(SUM(r.points_earned + r.speed_bonus), 0) AS score,
           q.quiz_completed_at,
           q.quiz_started_at
    FROM users u
    LEFT JOIN user_quiz_state q ON u.id = q.user_id
    LEFT JOIN user_responses r ON u.id = r.user_id
    WHERE u.role = 'user' AND u.is_verified = 1 AND (q.is_disqualified IS NULL OR q.is_disqualified = 0)
    GROUP BY u.id, u.username, u.email, q.quiz_completed_at, q.quiz_started_at
    ORDER BY score DESC, COALESCE(q.quiz_completed_at, '9999-12-31 23:59:59') ASC
";

$stmt = $pdo->query($query);
$leaderboard = $stmt->fetchAll();

foreach ($leaderboard as $index => $row) {
    $rank = $index + 1;
    $compTime = $row['quiz_completed_at'] ? date('Y-m-d H:i:s', strtotime($row['quiz_completed_at'])) : 'Not Completed';
    
    $duration = 'N/A';
    if ($row['quiz_started_at'] && $row['quiz_completed_at']) {
        $duration = strtotime($row['quiz_completed_at']) - strtotime($row['quiz_started_at']);
    }
    
    $contact = $row['email'];
    if (strpos($contact, '@phone.cheranalloorfaces.com') !== false) {
        $contact = str_replace('@phone.cheranalloorfaces.com', '', $contact);
    }
    
    fputcsv($output, [
        $rank,
        $row['username'],
        $contact,
        $row['score'],
        $row['base_score'],
        $row['speed_bonus'],
        $compTime,
        $duration
    ]);
}

fclose($output);
exit;
?>
