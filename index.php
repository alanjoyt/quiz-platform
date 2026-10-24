<?php
$pageTitle = "Welcome";
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();
$quizSettings = getQuizSettings();

// Load statistics
$userStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$totalUsers = $userStmt->fetch()['count'];

$questionStmt = $pdo->query("SELECT COUNT(*) as count FROM questions");
$totalQuestions = $questionStmt->fetch()['count'];
$nQuestions = intval(getConfig('quiz_question_count', '10'));

// Fetch user quiz state
$userState = null;
if ($currentUser) {
    $stateStmt = $pdo->prepare("SELECT * FROM user_quiz_state WHERE user_id = ?");
    $stateStmt->execute([$currentUser['id']]);
    $userState = $stateStmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Starting Splash Screen Intro Animation -->
<div id="splash-screen" class="splash-screen">
    <div class="splash-content">
        <div class="splash-logos-wrapper" style="display: flex; justify-content: center; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
            <div class="splash-dp-container" style="margin: 0;">
                <img src="<?php echo BASE_URI; ?>static/images/cheranalloor_faces_profile.jpg" alt="Cheranalloor Faces" class="splash-dp">
            </div>
            <img src="<?php echo BASE_URI; ?>static/images/painted_x.png" alt="x" style="width: 18px; height: 18px; object-fit: contain; opacity: 0; flex-shrink: 0; animation: logo-fade-in 0.4s ease 0.1s forwards; margin: 0 0.25rem;">
            <div class="splash-dp-container" style="margin: 0; animation-delay: 0.15s;">
                <img src="<?php echo BASE_URI; ?>static/images/kcym_logo.png" alt="KCYM Cheranalloor" class="splash-dp">
            </div>
        </div>
        <h1 class="splash-logo" style="font-size: clamp(1.2rem, 5vw, 2.25rem); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.25rem; line-height: 1.1; width: 100%;">
            <span>cheranalloor_faces</span>
            <img src="<?php echo BASE_URI; ?>static/images/painted_x.png" alt="X" style="width: 18px; height: 18px; object-fit: contain; flex-shrink: 0; margin: 0.25rem 0;">
            <span>kcym_cheranalloor</span>
        </h1>
    </div>
    <div class="splash-footer">
        <div class="splash-typing-container">
            <span id="splash-typing-text"></span><span class="typing-cursor">|</span>
        </div>
    </div>
</div>

<div class="hero">
    <div class="hero-logos" style="display: flex; justify-content: center; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
        <img src="<?php echo BASE_URI; ?>static/images/cheranalloor_faces_profile.jpg" alt="Cheranalloor Faces" style="width: 70px; height: 70px; border-radius: 50%; border: 3px solid var(--primary-accent); box-shadow: var(--shadow);">
        <img src="<?php echo BASE_URI; ?>static/images/painted_x.png" alt="x" style="width: 16px; height: 16px; object-fit: contain; opacity: 0.85; flex-shrink: 0; margin: 0 0.25rem;">
        <img src="<?php echo BASE_URI; ?>static/images/kcym_logo.png" alt="KCYM Cheranalloor" style="width: 70px; height: 70px; border-radius: 50%; border: 3px solid var(--secondary-accent); box-shadow: var(--shadow);">
    </div>
    <h1 class="hero-title" style="font-size: clamp(1.2rem, 5vw, 2.25rem); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.25rem; line-height: 1.1; width: 100%; margin-bottom: 0.75rem;">
        <span>cheranalloor_faces</span>
        <img src="<?php echo BASE_URI; ?>static/images/painted_x.png" alt="X" style="width: 18px; height: 18px; object-fit: contain; flex-shrink: 0; margin: 0.25rem 0;">
        <span>kcym_cheranalloor</span>
    </h1>
    <p class="hero-subtitle">The official live community quiz competition. Register today, test your speed and precision, and rise to the top of the leaderboard!</p>
</div>

<!-- Quiz Status Card -->
<div class="card" style="text-align: center; border-color: var(--primary-accent);">
    <h2 class="card-title" style="color: var(--primary-accent);">Competition Status</h2>
    
    <?php if ($quizSettings['state'] == 'live'): ?>
        <p style="margin-bottom: 1rem; font-weight: 600; font-size: 1.1rem; color: var(--success-color);">
            ● The Quiz is LIVE now!
        </p>
        <p style="margin-bottom: 1.5rem;">Answer the questions as fast as possible to claim the top spot.</p>
        <?php if ($currentUser): ?>
            <?php if ($userState && $userState['quiz_completed_at']): ?>
                <a href="<?php echo BASE_URI; ?>profile" class="btn btn-secondary">View Your Performance</a>
            <?php elseif ($userState && $userState['is_disqualified']): ?>
                <div class="alert alert-danger" style="margin-bottom: 0;">You have been disqualified.</div>
            <?php else: ?>
                <a href="<?php echo BASE_URI; ?>quiz" class="btn btn-primary">Enter Quiz Room</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?php echo BASE_URI; ?>login" class="btn btn-primary">Login to Participate</a>
        <?php endif; ?>
        
    <?php elseif ($quizSettings['state'] == 'registration'): ?>
        <p style="margin-bottom: 0.5rem; font-weight: 600; font-size: 1.1rem; color: var(--secondary-accent);">
            Registration is OPEN
        </p>
        
        <?php if ($quizSettings['start_time']): ?>
            <p style="font-size: 0.9rem; color: var(--text-muted);">Quiz starts on: <?php echo $quizSettings['start_time']->format('F d, Y h:i A'); ?></p>
            
            <!-- Live Countdown Timer -->
            <div class="countdown-box">
                <p style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">Starts In</p>
                <div id="index-countdown" class="countdown-timer-display" data-target="<?php echo $quizSettings['start_time']->format('c'); ?>">--:--:--</div>
            </div>
        <?php else: ?>
            <p style="margin-bottom: 1.5rem;">Quiz start time will be announced shortly.</p>
        <?php endif; ?>
        
        <?php if ($currentUser): ?>
            <div class="alert alert-success" style="margin-bottom: 0; text-align: left;">
                ✓ You are registered! You will be able to take the quiz when the timer expires and the administrator starts the event.
            </div>
        <?php else: ?>
            <a href="<?php echo BASE_URI; ?>login" class="btn btn-primary">Register Now</a>
        <?php endif; ?>
        
    <?php elseif ($quizSettings['state'] == 'ended'): ?>
        <p style="margin-bottom: 1rem; font-weight: 600; font-size: 1.1rem; color: var(--text-muted);">
            Quiz Competition Finished
        </p>
        <p style="margin-bottom: 1.5rem;">The answers have been recorded and the final scores are computed.</p>
        <a href="<?php echo BASE_URI; ?>leaderboard" class="btn btn-secondary">View Leaderboard Winners</a>
        
    <?php else: ?>
        <p style="margin-bottom: 1rem; font-weight: 600; font-size: 1.1rem; color: var(--text-muted);">
            Quiz Inactive
        </p>
        <p style="margin-bottom: 1.5rem;">Stay tuned for the upcoming quiz schedule!</p>
        <a href="<?php echo BASE_URI; ?>leaderboard" class="btn btn-outline">Check Past Rankings</a>
    <?php endif; ?>
</div>

<!-- Competition Rules -->
<div class="card">
    <h3 class="card-title">Competition Rules</h3>
    <ul style="list-style-type: none; margin: 1.5rem 0;">
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">1. ക്വിസ്യിൽ പങ്കെടുക്കാനുള്ള സമയം 15/08/2026 ശനിയാഴ്ച 6:00pm   തൊട്ട് 22/08/2026 ശനിയാഴ്ച 6:00pm വരെയായിരിക്കും </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">2. ഒരിക്കൽ കയറിയ ഫോണിൽ മറ്റു നമ്പർ ഉബയോഗിച്ച വീണ്ടും പങ്കെടുക്കാൻ സാധിക്കുന്നതല്ല </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">3. ഫോൺ നമ്പർ കൃത്യമായിരിക്കണം </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">4. <?php echo $nQuestions; ?> ചോദ്യങ്ങളാൺ ഉണ്ടാവുക ഇവ ചേരാനല്ലൂരിനോട് ബന്ധപ്പെട്ടവയായിരിക്കും </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">5. ഒരു വിന്നർ ആണ്  ഉണ്ടാവാത്തൊള്ളൂ. ലീഡേഴ്ബോര്ഡിൽ ആദ്യം നികുന്നവരാൺ വിജയ് ആവുന്നത് </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">6. നിങ്ങളുടെ ഫോൺ നമ്പർ ഉബയോഗിച്ച് പങ്കെടുക്കാവുന്നതാണ്</li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">7. നിങ്ങളുടെ പേര് 'username'എന്നിടത്  അടിച്ചുകൊടുക്കുക </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">8. വിജയ്ക് ₹1000 സമാനം ആയി ലഭിക്കും </li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">9. 23/08/2026 ഞായറാഴ്ച ഓണപരിപാടിക് ഇടയിൽ സാമാനത്തുക നൽകുന്നതാണ്</li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">10. ഒന്നിലധികം മത്സരാർത്ഥികൾക്ക് ഒരേ സ്കോർ ലഭിച്ചാൽ, ക്വിസ് ആദ്യം പൂർത്തിയാക്കിയ മത്സരാർത്ഥിക്ക് ഒന്നാം സ്ഥാനം നൽകുന്നതാണ്. ഇത് അന്തിമ തീരുമാനമായിരിക്കും. ഈ നിയമത്തെക്കുറിച്ച് എന്തെങ്കിലും സംശയമുണ്ടെങ്കിൽ കോർഡിനേറ്ററെ ബന്ധപ്പെടുക   9497720048.</li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">11. ചോദ്യങ്ങൾ മാറി വരുന്നതിനാൽ ഓരോരുത്തർക്കും വ്യത്യസ്ത ചോദ്യങ്ങൾ ആണ് ലഭിക്കുക.</li>
        <li style="position: relative; margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5; color: var(--text-color);">12. ടാബുകൾ മാറാൻ ശ്രമിച്ചാലോ സ്ക്രീന്ഷോട് എടുത്താലോ വാർണിങ് ലഭിക്കുന്നതാണ്. 4 വാർണിങ് കഴിയുമ്പോൾ പുറത്താവുന്നതാണ്.</li>
    </ul>
</div>

<!-- Competition Stats -->
<div class="profile-stats">
    <div class="profile-stat-box">
        <span class="profile-stat-label">Total Participants</span>
        <span class="profile-stat-value"><?php echo $totalUsers; ?></span>
    </div>
    <div class="profile-stat-box">
        <span class="profile-stat-label">Total Questions</span>
        <span class="profile-stat-value"><?php echo $nQuestions; ?></span>
    </div>
</div>

<?php
$extraScripts = '
<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Splash screen typing effect
        const splash = document.getElementById("splash-screen");
        if (splash) {
            if (sessionStorage.getItem("splash_played")) {
                splash.style.display = "none";
            } else {
                const typingText = document.getElementById("splash-typing-text");
                const phrase = "developed by BOLDBYTE solutions";
                let index = 0;
                
                function typeEffect() {
                    if (index < phrase.length) {
                        typingText.textContent += phrase.charAt(index);
                        index++;
                        setTimeout(typeEffect, 30);
                    } else {
                        setTimeout(() => {
                            splash.style.opacity = 0;
                            splash.style.transition = "opacity 0.3s ease";
                            setTimeout(() => {
                                splash.style.display = "none";
                                sessionStorage.setItem("splash_played", "true");
                            }, 300);
                        }, 450);
                    }
                }
                setTimeout(typeEffect, 400);
            }
        }

        const countdownEl = document.getElementById("index-countdown");
        if (!countdownEl) return;
        
        const targetStr = countdownEl.dataset.target;
        if (!targetStr) return;
        
        const targetDate = new Date(targetStr).getTime();
        
        function updateTimer() {
            const now = new Date().getTime();
            const difference = targetDate - now;
            
            if (difference <= 0) {
                countdownEl.textContent = "00:00:00";
                clearInterval(timerInterval);
                setTimeout(() => window.location.reload(), 2000);
                return;
            }
            
            const days = Math.floor(difference / (1000 * 60 * 60 * 24));
            const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((difference % (1000 * 60)) / 1000);
            
            let displayStr = "";
            if (days > 0) {
                displayStr += days + "d ";
            }
            
            const hPad = String(hours).padStart(2, "0");
            const mPad = String(minutes).padStart(2, "0");
            const sPad = String(seconds).padStart(2, "0");
            
            displayStr += hPad + ":" + mPad + ":" + sPad;
            countdownEl.textContent = displayStr;
        }
        
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
    });
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
