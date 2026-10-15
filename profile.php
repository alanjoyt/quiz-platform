<?php
$pageTitle = "Your Profile";
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = requireLogin();

// Fetch score aggregates
$scoreStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(points_earned), 0) as base_score,
        COALESCE(SUM(speed_bonus), 0) as speed_bonus_score
    FROM user_responses
    WHERE user_id = ?
");
$scoreStmt->execute([$currentUser['id']]);
$scores = $scoreStmt->fetch();

$baseScore = intval($scores['base_score']);
$speedBonus = intval($scores['speed_bonus_score']);
$totalScore = $baseScore + $speedBonus;

// Fetch quiz state
$stateStmt = $pdo->prepare("SELECT * FROM user_quiz_state WHERE user_id = ?");
$stateStmt->execute([$currentUser['id']]);
$state = $stateStmt->fetch();

// Fetch response log history
$historyStmt = $pdo->prepare("
    SELECT r.selected_option, r.is_correct, r.points_earned, r.speed_bonus, r.response_time_ms, q.text as question_text, q.correct_option
    FROM user_responses r
    JOIN questions q ON r.question_id = q.id
    LEFT JOIN user_quiz_questions uqq ON uqq.user_id = r.user_id AND uqq.question_id = r.question_id
    WHERE r.user_id = ?
    ORDER BY COALESCE(uqq.sort_order, q.id) ASC
");
$historyStmt->execute([$currentUser['id']]);
$history = $historyStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($state && $state['quiz_completed_at']): ?>
    <!-- Premium Glassmorphic Celebration Card -->
    <div class="celebration-card" style="margin-top: 1rem;">
        <!-- CSS Confetti Burst on Load -->
        <div class="confetti-burst">
            <div class="piece" style="--angle: 0deg; --color: #f09433;"></div>
            <div class="piece" style="--angle: 45deg; --color: #e6683c;"></div>
            <div class="piece" style="--angle: 90deg; --color: #dc2743;"></div>
            <div class="piece" style="--angle: 135deg; --color: #cc2366;"></div>
            <div class="piece" style="--angle: 180deg; --color: #bc1888;"></div>
            <div class="piece" style="--angle: 225deg; --color: #3f51b5;"></div>
            <div class="piece" style="--angle: 270deg; --color: #4caf50;"></div>
            <div class="piece" style="--angle: 315deg; --color: #ffeb3b;"></div>
         </div>

        <div class="insta-ring-wrapper" style="display: flex; justify-content: center; align-items: center; gap: 0.75rem; width: auto; height: auto; margin: 1.5rem auto 1rem auto;">
            <div class="insta-ring" style="width: 80px; height: 80px; margin: 0;">
                <div class="insta-ring-inner">
                    <img src="<?php echo BASE_URI; ?>static/images/cheranalloor_faces_profile.jpg" alt="Cheranalloor Faces Profile">
                </div>
            </div>
            <img src="<?php echo BASE_URI; ?>static/images/painted_x.png" alt="x" style="width: 16px; height: 16px; object-fit: contain; opacity: 0.85; flex-shrink: 0; margin: 0 0.25rem;">
            <div class="insta-ring" style="width: 80px; height: 80px; margin: 0;">
                <div class="insta-ring-inner">
                    <img src="<?php echo BASE_URI; ?>static/images/kcym_logo.png" alt="KCYM Cheranalloor Profile">
                </div>
            </div>
        </div>

        <h2 style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 800; margin-top: 1.5rem; margin-bottom: 0.5rem; color: var(--text-color);">
            Quiz Completed! 🎉
        </h2>
        <p style="font-size: 0.95rem; color: var(--text-muted); max-width: 450px; margin: 0 auto 1.5rem auto; line-height: 1.5;">
            Awesome job! You finished with a score of <strong><?php echo $totalScore; ?> points</strong>.<br>
            Please visit our official Instagram page below to view status updates and final standings.
        </p>

        <!-- Interactive CTA Button -->
        <a href="https://www.instagram.com/cheranalloor_faces?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener noreferrer" class="btn btn-instagram">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="vertical-align: middle;"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
            <span>Visit Instagram Profile</span>
        </a>
    </div>
<?php endif; ?>

<!-- Profile Overview Card -->
<div class="card" style="margin-top: 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 class="card-title" style="margin-bottom: 0;">competitor Profile</h2>
        <span style="font-size: 0.8rem; background-color: var(--secondary-accent); color: #FAF6F0; padding: 0.25rem 0.75rem; border-radius: 50px; font-weight: 600;">
            <?php 
            if ($state && $state['is_disqualified']) {
                echo 'Disqualified';
            } elseif ($state && $state['quiz_completed_at']) {
                echo 'Quiz Completed';
            } elseif ($state) {
                echo 'In Progress';
            } else {
                echo 'Registered';
            }
            ?>
        </span>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <p style="font-size: 0.9rem; color: var(--text-muted);">Username</p>
        <p style="font-size: 1.15rem; font-weight: 700; color: var(--text-color); margin-bottom: 0.75rem;">
            <?php echo htmlspecialchars($currentUser['username']); ?>
        </p>

        <?php 
        $displayContact = $currentUser['email'];
        if (strpos($displayContact, '@phone.cheranalloorfaces.com') !== false) {
            $displayContact = str_replace('@phone.cheranalloorfaces.com', '', $displayContact);
            echo '<p style="font-size: 0.9rem; color: var(--text-muted);">Phone Number</p>';
        } else {
            echo '<p style="font-size: 0.9rem; color: var(--text-muted);">Email Address</p>';
        }
        ?>
        <p style="font-size: 1rem; font-weight: 600; color: var(--text-color);">
            <?php echo htmlspecialchars($displayContact); ?>
        </p>

        <div style="margin-top: 1.5rem;">
            <a href="<?php echo BASE_URI; ?>logout" class="btn btn-outline" style="min-height: 40px; font-size: 0.9rem; border-color: var(--error-color); color: var(--error-color); width: auto; padding: 0.5rem 1.5rem; display: inline-flex; align-items: center; justify-content: center;">
                Logout
            </a>
        </div>
    </div>

    <!-- Warnings indicator -->
    <?php if ($state && intval($state['warnings_count']) > 0): ?>
        <div class="alert alert-warning" style="margin-bottom: 0;">
            ⚠️ <strong>Anti-Cheat Notice:</strong> You accumulated <strong><?php echo htmlspecialchars($state['warnings_count']); ?></strong> out of 4 maximum warnings for tab switching during the quiz.
        </div>
    <?php endif; ?>
</div>

<!-- Performance Summary -->
<div class="card">
    <h3 class="card-title">Quiz Performance</h3>
    
    <div class="profile-stats">
        <div class="profile-stat-box">
            <span class="profile-stat-label">Total Score</span>
            <span class="profile-stat-value" style="color: var(--primary-accent);">
                <?php echo $totalScore; ?> pts
            </span>
        </div>
        <div class="profile-stat-box">
            <span class="profile-stat-label">Completion Status</span>
            <span class="profile-stat-value" style="color: var(--secondary-accent); font-size: 1.15rem; padding-top: 0.35rem; display: block; font-weight: 700;">
                <?php 
                if ($state && $state['is_disqualified']) {
                    echo 'Disqualified';
                } elseif ($state && $state['quiz_completed_at']) {
                    echo 'Completed';
                } elseif ($state) {
                    echo 'Active';
                } else {
                    echo 'Not Started';
                }
                ?>
            </span>
        </div>
    </div>

    <div style="display: flex; justify-content: space-around; padding-top: 0.5rem; border-top: 1px solid var(--border-color); font-size: 0.9rem;">
        <div>
            <span style="color: var(--text-muted);">Base Score:</span> 
            <strong><?php echo $baseScore; ?></strong>
        </div>
        <div>
            <span style="color: var(--text-muted);">Speed Bonus:</span> 
            <strong><?php echo $speedBonus; ?></strong>
        </div>
    </div>
</div>

<!-- Submission History -->
<div class="card">
    <h3 class="card-title" style="margin-bottom: 1.25rem;">Response Log</h3>
    
    <?php if (count($history) > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <?php foreach ($history as $index => $item): ?>
                <div class="history-item">
                    <div class="history-header">
                        <strong style="color: var(--text-color); font-size: 0.95rem;">Question <?php echo ($index + 1); ?></strong>
                        <span class="history-result-badge <?php echo $item['is_correct'] ? 'badge-correct' : 'badge-incorrect'; ?>">
                            <?php echo $item['is_correct'] ? 'Correct' : 'Incorrect / Timeout'; ?>
                        </span>
                    </div>
                    
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0.25rem 0 0.5rem 0; line-height: 1.4;">
                        <?php echo htmlspecialchars($item['question_text']); ?>
                    </p>
                    
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; background-color: var(--bg-color); padding: 0.4rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color);">
                        <div>Selected: <strong><?php echo htmlspecialchars($item['selected_option'] ?: 'None (Timeout)'); ?></strong></div>
                        <div>Correct: <strong><?php echo htmlspecialchars($item['correct_option']); ?></strong></div>
                        <div>Time Spent: <strong><?php echo round($item['response_time_ms'] / 1000, 1); ?>s</strong></div>
                        <div>Points: <strong>+<?php echo (intval($item['points_earned']) + intval($item['speed_bonus'])); ?></strong></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; padding: 1.5rem 0;">
            No answers submitted yet. Your history will appear here once you take the live quiz.
        </p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
