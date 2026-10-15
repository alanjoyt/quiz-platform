<?php
$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../db.php';

// Require admin
$adminUser = requireAdmin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $state = isset($_POST['quiz_state']) ? trim($_POST['quiz_state']) : 'inactive';
    $startTimeStr = isset($_POST['quiz_start_time']) ? trim($_POST['quiz_start_time']) : '';
    $durationMins = isset($_POST['quiz_duration_mins']) ? intval($_POST['quiz_duration_mins']) : 30;
    $timeLimitSec = isset($_POST['question_time_limit']) ? intval($_POST['question_time_limit']) : 30;
    $devMode = isset($_POST['development_mode']) ? trim($_POST['development_mode']) : 'false';
    $questionCount = isset($_POST['quiz_question_count']) ? intval($_POST['quiz_question_count']) : 10;
    
    setConfig('quiz_state', $state);
    setConfig('quiz_start_time', $startTimeStr);
    setConfig('quiz_duration_mins', $durationMins);
    setConfig('question_time_limit', $timeLimitSec);
    setConfig('development_mode', $devMode);
    setConfig('quiz_question_count', $questionCount);
    
    setFlashMessage("Quiz configurations updated successfully.", "success");
    header("Location: " . BASE_URI . "admin/dashboard");
    exit;
}

// Fetch stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$verifiedUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
$activeTakers = $pdo->query("SELECT COUNT(*) FROM user_quiz_state WHERE quiz_completed_at IS NULL AND is_disqualified = 0")->fetchColumn();
$completedTakers = $pdo->query("SELECT COUNT(*) FROM user_quiz_state WHERE quiz_completed_at IS NOT NULL AND is_disqualified = 0")->fetchColumn();
$disqualifiedTakers = $pdo->query("SELECT COUNT(*) FROM user_quiz_state WHERE is_disqualified = 1")->fetchColumn();
$questionsCount = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();

// Load current settings
$settings = getQuizSettings();
$startTimeVal = getConfig('quiz_start_time', '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-top: 1rem;">
    <h2 class="card-title" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;">
        Admin Control Panel
    </h2>

    <!-- Admin Navigation Links -->
    <div class="admin-nav">
        <a href="<?php echo BASE_URI; ?>admin/dashboard" class="active">Overview & Config</a>
        <a href="<?php echo BASE_URI; ?>admin/questions">Questions CRUD</a>
        <a href="<?php echo BASE_URI; ?>admin/users">Participants</a>
        <a href="<?php echo BASE_URI; ?>admin/export" style="color: var(--primary-accent);">📥 Export CSV</a>
    </div>

    <!-- Live Proctoring Alerts Feed -->
    <div id="proctor-alerts-container" class="card" style="border-color: var(--error-color); display: none; margin-bottom: 2rem; background-color: rgba(192, 57, 43, 0.05); padding: 1rem;">
        <h3 class="card-title" style="color: var(--error-color); margin-bottom: 0.75rem; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
            🚨 Live Anti-Cheating Alerts
        </h3>
        <div id="proctor-alerts-list" style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 180px; overflow-y: auto;">
            <!-- Live alerts appear here -->
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <h3 class="card-title" style="font-size: 1.1rem; margin-bottom: 1rem;">Real-time Statistics</h3>
    <div class="admin-grid" style="margin-bottom: 2rem;">
        <div class="profile-stat-box admin-stat-card">
            <span class="profile-stat-label">Total Users / Verified</span>
            <div class="admin-stat-number"><?php echo $totalUsers; ?> <span style="font-size: 1.25rem; font-weight:500; color:var(--text-muted);">/ <?php echo $verifiedUsers; ?></span></div>
        </div>
        <div class="profile-stat-box admin-stat-card">
            <span class="profile-stat-label">Quiz Active / Completed</span>
            <div class="admin-stat-number"><?php echo $activeTakers; ?> <span style="font-size: 1.25rem; font-weight:500; color:var(--text-muted);">/ <?php echo $completedTakers; ?></span></div>
        </div>
        <div class="profile-stat-box admin-stat-card">
            <span class="profile-stat-label">Disqualified Takers</span>
            <div class="admin-stat-number" style="color: var(--error-color);"><?php echo $disqualifiedTakers; ?></div>
        </div>
        <div class="profile-stat-box admin-stat-card">
            <span class="profile-stat-label">Total Questions</span>
            <div class="admin-stat-number"><?php echo $questionsCount; ?></div>
        </div>
    </div>

    <!-- Quiz Configurations Form -->
    <h3 class="card-title" style="font-size: 1.1rem; margin-bottom: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
        Quiz Phase & Timing Controls
    </h3>

    <form method="POST" action="<?php echo BASE_URI; ?>admin/dashboard">
        <!-- Quiz State -->
        <div class="form-group">
            <label for="quiz_state" class="form-label">Quiz Status State</label>
            <select id="quiz_state" name="quiz_state" class="form-control" required>
                <option value="inactive" <?php echo $settings['state'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Closed)</option>
                <option value="registration" <?php echo $settings['state'] === 'registration' ? 'selected' : ''; ?>>Registration (Countdown Open)</option>
                <option value="live" <?php echo $settings['state'] === 'live' ? 'selected' : ''; ?>>Live (Quiz Session Active)</option>
                <option value="ended" <?php echo $settings['state'] === 'ended' ? 'selected' : ''; ?>>Ended (Submissions Closed, Leaderboard Viewable)</option>
            </select>
        </div>

        <!-- Start Time -->
        <div class="form-group">
            <label for="quiz_start_time" class="form-label">Quiz Start Date & Time (ISO/Local Format)</label>
            <input type="datetime-local" id="quiz_start_time" name="quiz_start_time" class="form-control" 
                   value="<?php echo htmlspecialchars($startTimeVal); ?>">
            <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                Used for countdown displays during the registration phase. (Format: YYYY-MM-DDTHH:MM)
            </small>
        </div>

        <!-- Duration Mins -->
        <div class="form-group">
            <label for="quiz_duration_mins" class="form-label">Quiz Expected Duration (minutes)</label>
            <input type="number" id="quiz_duration_mins" name="quiz_duration_mins" class="form-control" 
                   value="<?php echo $settings['duration_mins']; ?>" min="1" required>
        </div>

        <!-- Questions Count per attempt (N) -->
        <div class="form-group">
            <label for="quiz_question_count" class="form-label">Questions per Attempt (N)</label>
            <input type="number" id="quiz_question_count" name="quiz_question_count" class="form-control" 
                   value="<?php echo isset($settings['question_count']) ? $settings['question_count'] : 10; ?>" min="1" required>
            <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                The number of questions randomly drawn and assigned to each user when they start their quiz.
            </small>
        </div>

        <!-- Time Limit per question -->
        <div class="form-group">
            <label for="question_time_limit" class="form-label">Per-Question Time Limit (seconds)</label>
            <input type="number" id="question_time_limit" name="question_time_limit" class="form-control" 
                   value="<?php echo $settings['time_limit_sec']; ?>" min="5" required>
            <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                Determines the ticking countdown and the speed bonus points calculation.
            </small>
        </div>

        <!-- Development Mode Toggle -->
        <div class="form-group" style="margin-top: 1.5rem; border-top: 1px dashed var(--border-color); padding-top: 1.5rem;">
            <label for="development_mode" class="form-label" style="font-weight: 700; color: var(--secondary-accent);">
                Development Mode Status
            </label>
            <select id="development_mode" name="development_mode" class="form-control" required>
                <option value="false" <?php echo !DEVELOPMENT_MODE ? 'selected' : ''; ?>>OFF (Enforce Single Account per Device & Email validations)</option>
                <option value="true" <?php echo DEVELOPMENT_MODE ? 'selected' : ''; ?>>ON (Bypass Device Lock & Enable Testing Mode)</option>
            </select>
            <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                Turn this **ON** temporarily if you want to test the site with multiple logins on the same device. Turn it **OFF** when the live quiz competition starts.
            </small>
        </div>

        <button type="submit" class="btn btn-secondary" style="margin-top: 1rem;">
            Save Configurations
        </button>
    </form>
</div>

<?php
$extraScripts = '
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const container = document.getElementById("proctor-alerts-container");
        const list = document.getElementById("proctor-alerts-list");
        
        if (!container || !list) return;
        
        // Track the current timestamp for polling
        let lastChecked = Math.floor(Date.now() / 1000) - 10;
        
        // audio Context synthesizer for live alarm beep
        let audioCtx = null;
        function playWarningSound() {
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                
                osc.type = "sine";
                osc.frequency.setValueAtTime(800, audioCtx.currentTime); // High pitch alert beep
                gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
                
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.18);
            } catch(e) {}
        }
        
        function pollCheatAlerts() {
            fetch("cheat_alerts?since=" + lastChecked)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.logs && data.logs.length > 0) {
                    container.style.display = "block";
                    playWarningSound();
                    
                    data.logs.forEach(log => {
                        const alertDiv = document.createElement("div");
                        alertDiv.style.padding = "0.6rem";
                        alertDiv.style.borderLeft = "4px solid var(--error-color)";
                        alertDiv.style.backgroundColor = "var(--bg-color)";
                        alertDiv.style.borderRadius = "var(--border-radius-sm)";
                        alertDiv.style.fontSize = "0.85rem";
                        alertDiv.style.display = "flex";
                        alertDiv.style.justifyContent = "space-between";
                        alertDiv.style.alignItems = "center";
                        alertDiv.style.boxShadow = "var(--shadow)";
                        
                        let eventDesc = "attempted violation";
                        if (log.event_type === "visibility_hidden") {
                            eventDesc = "switched browser tabs";
                        } else if (log.event_type === "window_blur") {
                            eventDesc = "exited/blurred the quiz window (click-out)";
                        } else if (log.event_type === "split_screen_resize") {
                            eventDesc = "activated split-screen / multi-window mode";
                        }
                        
                        let warningBadge = `<span style="font-weight: 700; color: var(--warning-color);">${log.warning_number}/4 warnings</span>`;
                        if (parseInt(log.warning_number) >= 4) {
                            warningBadge = `<span style="font-weight: 800; color: var(--error-color); background-color: rgba(192,57,43,0.1); padding: 0.1rem 0.4rem; border-radius: 4px;">DISQUALIFIED</span>`;
                        }
                        
                        alertDiv.innerHTML = `
                            <div>
                                <span style="font-size: 0.75rem; color: var(--text-muted); margin-right: 0.5rem;">${log.created_at.split(" ")[1]}</span>
                                competitor <strong>${log.username}</strong> ${eventDesc}
                            </div>
                            <div>${warningBadge}</div>
                        `;
                        
                        list.insertBefore(alertDiv, list.firstChild);
                    });
                    
                    // Update check timestamp
                    lastChecked = data.since;
                }
            })
            .catch(err => console.error("Error polling cheat alerts:", err));
        }
        
        // Initial poll and set interval every 3 seconds
        pollCheatAlerts();
        setInterval(pollCheatAlerts, 3000);
    });
</script>
';
require_once __DIR__ . '/../includes/footer.php'; 
?>
