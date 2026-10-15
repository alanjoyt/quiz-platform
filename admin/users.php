<?php
$pageTitle = "Manage Participants";
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../db.php';

// Require admin
$adminUser = requireAdmin();

// Handle Post Actions (Disqualify / Reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $targetUserId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($targetUserId > 0) {
        $chk = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $chk->execute([$targetUserId]);
        $chkRole = $chk->fetchColumn();
        
        if ($chkRole !== 'admin') {
            if ($action === 'disqualify') {
                $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Disqualified by administrator.';
                
                $chkState = $pdo->prepare("SELECT COUNT(*) FROM user_quiz_state WHERE user_id = ?");
                $chkState->execute([$targetUserId]);
                $exists = $chkState->fetchColumn();
                
                if ($exists) {
                    $upd = $pdo->prepare("UPDATE user_quiz_state SET is_disqualified = 1, disqualification_reason = ? WHERE user_id = ?");
                    $upd->execute([$reason, $targetUserId]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO user_quiz_state (user_id, is_disqualified, disqualification_reason, quiz_started_at) VALUES (?, 1, ?, ?)");
                    $ins->execute([$targetUserId, $reason, date('Y-m-d H:i:s')]);
                }
                setFlashMessage("Participant has been disqualified.", "warning");
                
            } elseif ($action === 'reset') {
                $delResp = $pdo->prepare("DELETE FROM user_responses WHERE user_id = ?");
                $delResp->execute([$targetUserId]);
                
                $delState = $pdo->prepare("DELETE FROM user_quiz_state WHERE user_id = ?");
                $delState->execute([$targetUserId]);
                
                $delAssigned = $pdo->prepare("DELETE FROM user_quiz_questions WHERE user_id = ?");
                $delAssigned->execute([$targetUserId]);
                
                setFlashMessage("Participant quiz progress has been reset.", "success");
            } elseif ($action === 'clear_device_lock') {
                $upd = $pdo->prepare("UPDATE users SET browser_fingerprint = NULL WHERE id = ?");
                $upd->execute([$targetUserId]);
                setFlashMessage("Participant device lock / fingerprint has been cleared.", "success");
            }
        }
    }
    
    header("Location: " . BASE_URI . "admin/users" . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
    exit;
}

// Handle GET Search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $searchWild = "%$search%";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ?");
    $stmt->execute([$searchWild, $searchWild]);
    $allUsers = $stmt->fetchAll();
} else {
    $allUsers = $pdo->query("SELECT * FROM users")->fetchAll();
}

$fingerprintCounts = [];
foreach ($allUsers as $u) {
    if (!empty($u['browser_fingerprint'])) {
        $fp = $u['browser_fingerprint'];
        if (!isset($fingerprintCounts[$fp])) {
            $fingerprintCounts[$fp] = 0;
        }
        $fingerprintCounts[$fp]++;
    }
}

$usersData = [];
foreach ($allUsers as $u) {
    $stateStmt = $pdo->prepare("SELECT * FROM user_quiz_state WHERE user_id = ?");
    $stateStmt->execute([$u['id']]);
    $state = $stateStmt->fetch();
    
    $status = 'Not Started';
    $warnings = 0;
    $disqualified = false;
    $reason = '';
    $timeTakenStr = 'N/A';
    
    if ($state) {
        $warnings = intval($state['warnings_count']);
        if ($state['is_disqualified']) {
            $status = 'Disqualified';
            $disqualified = true;
            $reason = $state['disqualification_reason'] ?: 'No reason provided';
        } elseif ($state['quiz_completed_at']) {
            $status = 'Completed (' . date('Y-m-d H:i:s', strtotime($state['quiz_completed_at'])) . ')';
            
            // Calculate total time taken
            if ($state['quiz_started_at']) {
                $start = strtotime($state['quiz_started_at']);
                $end = strtotime($state['quiz_completed_at']);
                $diff = $end - $start; // Difference in seconds
                
                $mins = floor($diff / 60);
                $secs = $diff % 60;
                $timeTakenStr = ($mins > 0 ? "{$mins}m " : "") . "{$secs}s";
            }
        } else {
            $status = 'In Progress';
        }
    }
    
    $scoreStmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned + speed_bonus), 0) FROM user_responses WHERE user_id = ?");
    $scoreStmt->execute([$u['id']]);
    $score = $scoreStmt->fetchColumn();
    
    $usersData[] = [
        'user' => $u,
        'status' => $status,
        'warnings' => $warnings,
        'disqualified' => $disqualified,
        'reason' => $reason,
        'score' => $score,
        'time_taken' => $timeTakenStr,
        'shared_device' => (!empty($u['browser_fingerprint']) && $fingerprintCounts[$u['browser_fingerprint']] > 1)
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-top: 1rem;">
    <h2 class="card-title" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;">
        Participant Management
    </h2>

    <!-- Admin Navigation Links -->
    <div class="admin-nav">
        <a href="<?php echo BASE_URI; ?>admin/dashboard">Overview & Config</a>
        <a href="<?php echo BASE_URI; ?>admin/questions">Questions CRUD</a>
        <a href="<?php echo BASE_URI; ?>admin/users" class="active">Participants</a>
        <a href="<?php echo BASE_URI; ?>admin/export" style="color: var(--primary-accent);">📥 Export CSV</a>
    </div>

    <!-- Search Form -->
    <form method="GET" action="<?php echo BASE_URI; ?>admin/users" style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem;">
        <input type="text" name="search" class="form-control" placeholder="Search by username or phone number..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-secondary" style="min-height: 46px; width: auto; padding: 0.5rem 1.5rem;">
            Search
        </button>
        <?php if (!empty($search)): ?>
            <a href="<?php echo BASE_URI; ?>admin/users" class="btn btn-outline" style="min-height: 46px; width: auto; padding: 0.5rem 1.5rem; display: inline-flex; align-items: center;">
                Clear
            </a>
        <?php endif; ?>
    </form>

    <!-- Participants Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>competitor</th>
                    <th>Status</th>
                    <th>Warnings</th>
                    <th>Score</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($usersData) > 0): ?>
                    <?php foreach ($usersData as $row): ?>
                        <tr>
                            <td>
                                <div><strong><?php echo htmlspecialchars($row['user']['username']); ?></strong></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <?php 
                                    $disp = $row['user']['email'];
                                    $isPhone = (strpos($disp, '@phone.cheranalloorfaces.com') !== false);
                                    if ($isPhone) {
                                        $disp = str_replace('@phone.cheranalloorfaces.com', '', $disp);
                                        echo "📞 " . htmlspecialchars($disp);
                                    } else {
                                        echo "✉️ " . htmlspecialchars($disp);
                                    }
                                    ?>
                                </div>
                                <?php if (!empty($row['user']['browser_fingerprint'])): ?>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.15rem;">
                                        Device FP: <code><?php echo htmlspecialchars($row['user']['browser_fingerprint']); ?></code>
                                    </div>
                                <?php endif; ?>
                                <?php if ($row['user']['role'] === 'admin'): ?>
                                    <span style="font-size: 0.65rem; background-color: var(--secondary-accent); color: #FFF; padding: 0.05rem 0.25rem; border-radius: 4px; font-weight: 700;">ADMIN</span>
                                <?php endif; ?>
                                <?php if ($row['shared_device']): ?>
                                    <span style="font-size: 0.65rem; background-color: var(--error-color); color: #FFF; padding: 0.05rem 0.25rem; border-radius: 4px; font-weight: 700; display: inline-block; margin-top: 0.25rem;">⚠️ SHARED DEVICE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                                <?php if ($row['time_taken'] !== 'N/A'): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                        ⏱️ Duration: <strong><?php echo $row['time_taken']; ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($row['disqualified']): ?>
                                    <div style="font-size: 0.7rem; color: var(--error-color); max-width: 150px; line-height: 1.2; margin-top: 0.25rem;">
                                        Reason: <?php echo htmlspecialchars($row['reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600; color: <?php echo $row['warnings'] >= 4 ? 'var(--error-color)' : ($row['warnings'] > 0 ? 'var(--warning-color)' : 'inherit'); ?>;">
                                <?php echo htmlspecialchars($row['warnings']); ?> / 4
                            </td>
                            <td style="font-weight: 700; color: var(--secondary-accent);">
                                <?php echo htmlspecialchars($row['score']); ?> pts
                            </td>
                            <td style="vertical-align: middle;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                    
                                    <!-- Disqualify Form -->
                                    <?php if (!$row['disqualified'] && $row['user']['role'] !== 'admin'): ?>
                                        <details style="width: 100%; text-align: right;">
                                            <summary style="font-size: 0.75rem; color: var(--error-color); cursor: pointer; font-weight: 600; padding: 0.25rem 0;">
                                                ⚠️ Disqualify
                                            </summary>
                                            <form method="POST" action="<?php echo BASE_URI; ?>admin/users<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" style="margin-top: 0.5rem; text-align: left; background: var(--bg-color); padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border-color);">
                                                <input type="hidden" name="action" value="disqualify">
                                                <input type="hidden" name="user_id" value="<?php echo $row['user']['id']; ?>">
                                                <input type="text" name="reason" class="form-control" placeholder="Reason..." required style="min-height: 30px; font-size: 0.75rem; padding: 0.25rem; margin-bottom: 0.5rem;">
                                                <button type="submit" class="btn btn-danger" style="min-height: 30px; font-size: 0.75rem; padding: 0.1rem 0.5rem; width: 100%;">
                                                    Confirm Disqualification
                                                </button>
                                            </form>
                                        </details>
                                    <?php endif; ?>

                                    <!-- Reset progress Form -->
                                    <?php if ($row['user']['role'] !== 'admin'): ?>
                                        <form method="POST" action="<?php echo BASE_URI; ?>admin/users<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" onsubmit="return confirm('Are you sure you want to reset all answers and progress for <?php echo htmlspecialchars($row['user']['username']); ?>?');">
                                            <input type="hidden" name="action" value="reset">
                                            <input type="hidden" name="user_id" value="<?php echo $row['user']['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="min-height: 30px; font-size: 0.75rem; padding: 0.1rem 0.5rem; border-width: 1px;">
                                                Reset Progress
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Clear Device Lock Form -->
                                    <?php if ($row['user']['role'] !== 'admin' && !empty($row['user']['browser_fingerprint'])): ?>
                                        <form method="POST" action="<?php echo BASE_URI; ?>admin/users<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" onsubmit="return confirm('Are you sure you want to clear the device fingerprint lock for <?php echo htmlspecialchars($row['user']['username']); ?>?');">
                                            <input type="hidden" name="action" value="clear_device_lock">
                                            <input type="hidden" name="user_id" value="<?php echo $row['user']['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="min-height: 30px; font-size: 0.75rem; padding: 0.1rem 0.5rem; border-width: 1px; color: #e67e22; border-color: #e67e22;">
                                                Clear Device Lock
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                            No participants found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
