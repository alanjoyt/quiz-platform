<?php
$pageTitle = "Quiz Room";
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = requireLogin();

// Ensure user is verified in database if legacy account
if (!$currentUser['is_verified']) {
    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$currentUser['id']]);
    $currentUser['is_verified'] = 1;
}

$quizSettings = getQuizSettings();

// If quiz not live
if ($quizSettings['state'] !== 'live') {
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="text-align: center; margin-top: 2rem;">
        <h2 class="card-title" style="color: var(--warning-color);">Quiz Room</h2>
        <?php if ($quizSettings['state'] == 'inactive'): ?>
            <p style="margin: 1rem 0 1.5rem 0;">The quiz competition is currently closed/inactive.</p>
        <?php elseif ($quizSettings['state'] == 'registration'): ?>
            <p style="margin: 1rem 0 1.5rem 0;">The quiz hasn't started yet! We are currently in the registration phase.</p>
            <?php if ($quizSettings['start_time']): ?>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Scheduled Start: <?php echo $quizSettings['start_time']->format('F d, Y h:i A'); ?></p>
            <?php endif; ?>
        <?php elseif ($quizSettings['state'] == 'ended'): ?>
            <p style="margin: 1rem 0 1.5rem 0;">The quiz competition has ended. Thank you for participating!</p>
        <?php endif; ?>
        <a href="<?php echo BASE_URI; ?>" class="btn btn-primary">Return Home</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch user quiz state
$stmt = $pdo->prepare("SELECT * FROM user_quiz_state WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$userState = $stmt->fetch();

// Check or generate assigned questions for the user
$assignStmt = $pdo->prepare("SELECT COUNT(*) FROM user_quiz_questions WHERE user_id = ?");
$assignStmt->execute([$currentUser['id']]);
$hasAssigned = $assignStmt->fetchColumn() > 0;

if (!$hasAssigned) {
    // Generate assignment
    $n = intval(getConfig('quiz_question_count', '10'));
    
    // Fetch all available questions
    $allQs = $pdo->query("SELECT id FROM questions")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($allQs) > 0) {
        // Shuffle and slice first N
        shuffle($allQs);
        $selectedIds = array_slice($allQs, 0, $n);
        
        // Insert assignment
        $insertAssigned = $pdo->prepare("INSERT INTO user_quiz_questions (user_id, question_id, sort_order, option_order) VALUES (?, ?, ?, ?)");
        $sortOrder = 1;
        foreach ($selectedIds as $qid) {
            $optKeys = ['A', 'B', 'C', 'D'];
            shuffle($optKeys);
            $optOrder = implode('', $optKeys);
            
            $insertAssigned->execute([$currentUser['id'], $qid, $sortOrder, $optOrder]);
            $sortOrder++;
        }
    }
}

if (!$userState) {
    // Start the quiz
    $nowStr = date('Y-m-d H:i:s');
    
    // Get the first question ID from the assigned questions
    $firstQStmt = $pdo->prepare("SELECT question_id FROM user_quiz_questions WHERE user_id = ? AND sort_order = 1");
    $firstQStmt->execute([$currentUser['id']]);
    $firstQId = $firstQStmt->fetchColumn() ?: null;
    
    $insert = $pdo->prepare("INSERT INTO user_quiz_state (user_id, current_question_id, current_question_started_at, quiz_started_at) VALUES (?, ?, ?, ?)");
    $insert->execute([$currentUser['id'], $firstQId, $nowStr, $nowStr]);
    
    // Refetch state
    $stmt->execute([$currentUser['id']]);
    $userState = $stmt->fetch();
}

// Check disqualified
if ($userState['is_disqualified']) {
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="text-align: center; border-color: var(--error-color); margin-top: 2rem;">
        <h2 class="card-title" style="color: var(--error-color);">Disqualified</h2>
        <div class="alert alert-error" style="text-align: left; margin: 1rem 0;">
            <strong>Reason:</strong> <?php echo htmlspecialchars($userState['disqualification_reason'] ?: 'Violation of quiz rules.'); ?>
        </div>
        <p style="margin-bottom: 1.5rem; font-size: 0.95rem; color: var(--text-muted);">
            Our anti-cheat system detected tab switching or page exits. In accordance with competition rules, you have been disqualified from this session.
        </p>
        <a href="<?php echo BASE_URI; ?>" class="btn btn-outline">Back to Home</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Check completed
if ($userState['quiz_completed_at']) {
    setFlashMessage("You have already completed the quiz! Here is your final summary.", "success");
    header("Location: " . BASE_URI . "profile");
    exit;
}

$questionId = $userState['current_question_id'];
if (!$questionId) {
    // Mark completed
    $nowStr = date('Y-m-d H:i:s');
    $update = $pdo->prepare("UPDATE user_quiz_state SET quiz_completed_at = COALESCE(quiz_completed_at, ?) WHERE user_id = ?");
    $update->execute([$nowStr, $currentUser['id']]);
    setFlashMessage("Quiz completed successfully.", "success");
    header("Location: " . BASE_URI . "profile");
    exit;
}

// Fetch active question details
$qStmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
$qStmt->execute([$questionId]);
$question = $qStmt->fetch();

if (!$question) {
    // No active question found, complete the quiz
    $nowStr = date('Y-m-d H:i:s');
    $update = $pdo->prepare("UPDATE user_quiz_state SET current_question_id = NULL, quiz_completed_at = COALESCE(quiz_completed_at, ?) WHERE user_id = ?");
    $update->execute([$nowStr, $currentUser['id']]);
    setFlashMessage("Quiz completed.", "success");
    header("Location: " . BASE_URI . "profile");
    exit;
}

// Reconstruction of option mapping from database assignment
$optOrderStmt = $pdo->prepare("SELECT option_order FROM user_quiz_questions WHERE user_id = ? AND question_id = ?");
$optOrderStmt->execute([$currentUser['id'], $question['id']]);
$option_order = $optOrderStmt->fetchColumn();

if (!$option_order) {
    $option_order = 'ABCD';
}

$options_pool = [
    'A' => $question['option_a'],
    'B' => $question['option_b'],
    'C' => $question['option_c'],
    'D' => $question['option_d']
];

$shuffled_options = [];
$options_map = [];
$letters = ['A', 'B', 'C', 'D'];

for ($i = 0; $i < 4; $i++) {
    $screen_letter = $letters[$i];
    $orig_key = $option_order[$i];
    $shuffled_options[$screen_letter] = [
        'orig_key' => $orig_key,
        'text' => $options_pool[$orig_key]
    ];
    $options_map[$screen_letter] = $orig_key;
}

// Time calculations
$now = time();
$startedAt = strtotime($userState['current_question_started_at']);
$elapsed = $now - $startedAt;
$timeLimit = $quizSettings['time_limit_sec'];
$remaining = max(0, $timeLimit - $elapsed);

// Submit timed out question automatically
if ($remaining <= 0) {
    // Process server-side timeout
    submitAnswerAndAdvance($currentUser['id'], $question, null, $elapsed);
    header("Location: " . BASE_URI . "quiz");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedQId = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    
    // Server-side double-submission verification
    if ($submittedQId !== intval($question['id'])) {
        header("Location: " . BASE_URI . "quiz");
        exit;
    }
    
    $selectedOption = isset($_POST['option']) ? strtoupper(trim($_POST['option'])) : '';
    
    // Resolve the screen option letter (A, B, C, D) back to the original option letter from DB
    $resolvedOption = null;
    if (isset($options_map[$selectedOption])) {
        $resolvedOption = $options_map[$selectedOption];
    } else {
        $resolvedOption = $selectedOption;
    }
    
    submitAnswerAndAdvance($currentUser['id'], $question, $resolvedOption, $elapsed);
    
    header("Location: " . BASE_URI . "quiz");
    exit;
}

function submitAnswerAndAdvance($userId, $question, $selectedOption, $elapsedSeconds) {
    global $pdo, $quizSettings;
    
    // Double check if a response was already inserted for this user and question to prevent concurrent duplicates
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user_responses WHERE user_id = ? AND question_id = ?");
    $checkStmt->execute([$userId, $question['id']]);
    if ($checkStmt->fetchColumn() > 0) {
        return;
    }
    
    $timeLimit = $quizSettings['time_limit_sec'];
    // Allow 10s network latency to prevent slow devices/hosts from timing out
    $isTimeout = $elapsedSeconds > ($timeLimit + 10);
    
    $isCorrect = 0;
    $pointsEarned = 0;
    $speedBonus = 0;
    
    if (!empty($selectedOption) && !$isTimeout) {
        if ($selectedOption === strtoupper($question['correct_option'])) {
            $isCorrect = 1;
            $pointsEarned = $question['points'];
            $speedBonus = max(0, $timeLimit - $elapsedSeconds);
        }
    }
    
    // Insert response
    $insert = $pdo->prepare("INSERT INTO user_responses (user_id, question_id, selected_option, is_correct, points_earned, speed_bonus, response_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $userId,
        $question['id'],
        $selectedOption ?: null,
        $isCorrect,
        $pointsEarned,
        $speedBonus,
        intval($elapsedSeconds * 1000)
    ]);
    
    // Find next question ID based on assigned question sort_order
    $sortStmt = $pdo->prepare("SELECT sort_order FROM user_quiz_questions WHERE user_id = ? AND question_id = ?");
    $sortStmt->execute([$userId, $question['id']]);
    $currentSort = $sortStmt->fetchColumn();
    
    $nextQId = null;
    if ($currentSort !== false) {
        $nextStmt = $pdo->prepare("SELECT question_id FROM user_quiz_questions WHERE user_id = ? AND sort_order = ?");
        $nextStmt->execute([$userId, $currentSort + 1]);
        $nextQId = $nextStmt->fetchColumn() ?: null;
    }
    
    $nowStr = date('Y-m-d H:i:s');
    
    // Update quiz state
    if ($nextQId) {
        $update = $pdo->prepare("UPDATE user_quiz_state SET current_question_id = ?, current_question_started_at = ? WHERE user_id = ?");
        $update->execute([$nextQId, $nowStr, $userId]);
    } else {
        $update = $pdo->prepare("UPDATE user_quiz_state SET current_question_id = NULL, quiz_completed_at = COALESCE(quiz_completed_at, ?) WHERE user_id = ?");
        $update->execute([$nowStr, $userId]);
    }
}

// Calculate progress based on user assigned questions
$countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_quiz_questions WHERE user_id = ?");
$countStmt->execute([$currentUser['id']]);
$totalQ = $countStmt->fetch()['count'];

$respStmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_responses WHERE user_id = ?");
$respStmt->execute([$currentUser['id']]);
$answeredQ = $respStmt->fetch()['count'];

$progressPercent = $totalQ > 0 ? intval(($answeredQ / $totalQ) * 100) : 0;

$showWarningModal = false;
$modalWarningCount = 0;
if (isset($_SESSION['pending_warning_alert'])) {
    $showWarningModal = true;
    $modalWarningCount = $_SESSION['pending_warning_alert'];
    unset($_SESSION['pending_warning_alert']);
}

$GLOBALS['hideNav'] = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="main-content">
    <!-- Anti-Screenshot Watermark Grid -->
    <div class="quiz-watermark">
        <?php for ($w = 0; $w < 12; $w++): ?>
            <div style="text-align: center;">
                <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                <span style="font-size: 0.75rem; opacity: 0.75; display: block;">IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></span>
            </div>
        <?php endfor; ?>
    </div>

    <div class="quiz-header">
        <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">
            QUESTION <?php echo ($answeredQ + 1); ?>
        </span>
        
        <div class="quiz-timer-container">
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">TIME LEFT:</span>
            <span id="quiz-timer" data-remaining="<?php echo $remaining; ?>"><?php echo $remaining; ?></span>s
        </div>
    </div>

    <div class="progress-container">
        <div class="progress-bar" style="width: <?php echo $progressPercent; ?>%;"></div>
    </div>

    <!-- Question Card -->
    <div class="card" style="margin-bottom: 1rem;">
        <p style="font-size: 1.15rem; font-weight: 600; line-height: 1.5;">
            <?php echo htmlspecialchars($question['text']); ?>
        </p>
    </div>

    <!-- Submit Form -->
    <form id="quiz-form" method="POST" action="<?php echo BASE_URI; ?>quiz">
        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($question['id']); ?>">
        <div class="options-list">
            <?php foreach ($shuffled_options as $screen_letter => $opt_details): ?>
                <div class="option-item">
                    <input type="radio" id="option_<?php echo strtolower($screen_letter); ?>" name="option" value="<?php echo $screen_letter; ?>" class="option-input" required>
                    <label for="option_<?php echo strtolower($screen_letter); ?>" class="option-label">
                        <span class="option-letter"><?php echo $screen_letter; ?></span>
                        <span><?php echo htmlspecialchars($opt_details['text']); ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-secondary" style="margin-top: 1rem;">
            Submit Answer
        </button>
    </form>

    <p style="font-size: 0.75rem; text-align: center; color: var(--text-muted); margin-top: 1.5rem;">
        ⚠️ Do not switch tabs, minimize the browser, or exit the page. Your activity is being monitored.
    </p>

    <!-- Warnings Indicator -->
    <div id="quiz-warnings-indicator" class="alert alert-warning" style="margin-top: 1.5rem; text-align: center; font-size: 0.8rem; <?php echo ($userState['warnings_count'] > 0) ? '' : 'display: none;'; ?>">
        ⚠️ <strong>Warning:</strong> Tab switching detected. Warning Count: <span id="quiz-warnings-count-display"><?php echo htmlspecialchars($userState['warnings_count'] ?? 0); ?></span>/4
    </div>
</div>

<!-- Anti-Cheat Overlay warning modal -->
<div id="warning-modal" class="modal-overlay" style="<?php echo $showWarningModal ? 'display: flex;' : 'display: none;'; ?>">
    <div class="modal-content">
        <h3 class="modal-title">Violation Detected!</h3>
        <p id="modal-warning-reason" style="color: var(--error-color); font-weight: 700; margin: 0.5rem 0; font-size: 1.05rem; text-align: center;"></p>
        <p style="margin: 1rem 0; font-size: 0.95rem;">
            You switched tabs, left the screen, or blurred the quiz page. This is a violation of rules.
        </p>
        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
            Warnings: <strong id="modal-warning-count"><?php echo $modalWarningCount; ?></strong> / 4
        </div>
        <p style="margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-muted);">
            If you reach 4 warnings, you will be automatically disqualified from the quiz.
        </p>
        <button id="close-warning-btn" class="btn btn-primary" style="min-height: 40px; font-size: 0.9rem;">
            I Understand & Will Resume
        </button>
    </div>
</div>

<?php
$extraScripts = '
<script>
    const BASE_URI = "' . BASE_URI . '";
</script>
<script src="' . BASE_URI . 'static/js/quiz.js"></script>
';
require_once __DIR__ . '/includes/footer.php';
?>
