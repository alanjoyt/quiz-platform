<?php
$pageTitle = "Leaderboard";
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

// High performance query to calculate scores, exclude disqualified users, 
// and sort by total score descending, then completed timestamp ascending (earlier finishes win ties).
$query = "
    SELECT u.id, u.username, 
           COALESCE(SUM(r.points_earned + r.speed_bonus), 0) AS score,
           q.quiz_completed_at
    FROM users u
    LEFT JOIN user_quiz_state q ON u.id = q.user_id
    LEFT JOIN user_responses r ON u.id = r.user_id
    WHERE u.role = 'user' AND u.is_verified = 1 AND (q.is_disqualified IS NULL OR q.is_disqualified = 0)
    GROUP BY u.id, u.username, q.quiz_completed_at
    ORDER BY score DESC, COALESCE(q.quiz_completed_at, '9999-12-31 23:59:59') ASC
";

$stmt = $pdo->query($query);
$leaderboard = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
        <h2 class="card-title" style="margin-bottom: 0;">Live Leaderboard</h2>
        <span style="font-size: 0.75rem; background-color: rgba(15, 82, 87, 0.1); color: var(--secondary-accent); padding: 0.25rem 0.5rem; border-radius: 50px; font-weight: 600;">
            Auto-refreshing in <span id="refresh-timer">15</span>s
        </span>
    </div>

    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem;">
        Ranking is based on highest scores. In case of a tie, the user who finished the quiz earlier is ranked higher. No private user information is displayed.
    </p>

    <div class="table-responsive">
        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th style="width: 60px; text-align: center;">Rank</th>
                    <th>Competitor</th>
                    <th style="text-align: right;">Total Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($leaderboard) > 0): ?>
                    <?php foreach ($leaderboard as $index => $entry): ?>
                        <?php 
                        $rank = $index + 1; 
                        $isSelf = ($currentUser && $currentUser['id'] === $entry['id']);
                        ?>
                        <tr class="<?php echo $isSelf ? 'leaderboard-row-self' : ''; ?>">
                            <td style="text-align: center; vertical-align: middle;">
                                <?php if ($rank === 1): ?>
                                    <span class="rank-badge rank-1">1</span>
                                <?php elseif ($rank === 2): ?>
                                    <span class="rank-badge rank-2">2</span>
                                <?php elseif ($rank === 3): ?>
                                    <span class="rank-badge rank-3">3</span>
                                <?php else: ?>
                                    <span style="font-weight: 600; color: var(--text-muted);"><?php echo $rank; ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span><?php echo htmlspecialchars($entry['username']); ?></span>
                                    <?php if ($isSelf): ?>
                                        <span style="font-size: 0.65rem; background-color: var(--primary-accent); color: #FAF6F0; padding: 0.05rem 0.3rem; border-radius: 4px; font-weight: 700;">YOU</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: right; vertical-align: middle;" class="score-badge">
                                <?php echo htmlspecialchars($entry['score']); ?> pts
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                            No scores recorded yet. The leaderboard will populate when the quiz competition is live.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraScripts = '
<script>
    document.addEventListener("DOMContentLoaded", () => {
        let refreshSeconds = 15;
        const timerEl = document.getElementById("refresh-timer");
        
        const timerInterval = setInterval(() => {
            refreshSeconds--;
            if (timerEl) timerEl.textContent = refreshSeconds;
            
            if (refreshSeconds <= 0) {
                clearInterval(timerInterval);
                window.location.reload();
            }
        }, 1000);
    });
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
