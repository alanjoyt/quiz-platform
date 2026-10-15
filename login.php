<?php
$pageTitle = "Login / Register";
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();
if ($currentUser) {
    header("Location: " . BASE_URI);
    exit;
}

$identity = '';
$emailOrPhone = '';
$pin = '';
$username = '';

// Default to register for first time, login for returning visitors
$action = 'register';
if (isset($_COOKIE['has_account']) || isset($_COOKIE['device_lock'])) {
    $action = 'login';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'login';
    $deviceLockLocal = isset($_POST['device_lock_local']) ? trim($_POST['device_lock_local']) : '';
    
    // Read device lock status
    $deviceLockUser = null;
    if (isset($_COOKIE['device_lock'])) {
        $deviceLockUser = trim($_COOKIE['device_lock']);
    } elseif (!empty($deviceLockLocal)) {
        $deviceLockUser = $deviceLockLocal;
    }

    if ($action === 'register') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $emailOrPhone = isset($_POST['email_or_phone']) ? trim($_POST['email_or_phone']) : '';
        $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
        $fingerprint = isset($_POST['browser_fingerprint']) ? trim($_POST['browser_fingerprint']) : '';

        // Validations
        if (empty($username) || empty($emailOrPhone) || empty($pin)) {
            setFlashMessage("All fields are compulsory for registration.", "error");
        } elseif (!preg_match('/^[a-zA-Z0-9_ ]+$/', $username) || strlen($username) < 3 || strlen($username) > 25) {
            setFlashMessage("Username must be alphanumeric (plus spaces/underscores) and 3-25 characters.", "error");
        } else {
            // Determine phone validity (must be a valid 10-digit mobile number)
            $cleanPhone = preg_replace('/[^0-9]/', '', $emailOrPhone);
            $isPhone = preg_match('/^(0|91)?\d{10}$/', $cleanPhone);

            if (!$isPhone) {
                setFlashMessage("Please enter a valid 10-digit mobile number (e.g., 9876543210).", "error");
            } else {
                // Extract the last 10 digits for absolute consistency in the database
                $standardPhone = substr($cleanPhone, -10);
                $email = $standardPhone . '@phone.cheranalloorfaces.com';

                // Enforce single entry per device lock
                $blocked = false;
                if ($deviceLockUser && !DEVELOPMENT_MODE) {
                    if (strtolower($deviceLockUser) !== strtolower($username)) {
                        setFlashMessage("Only one competitor account is allowed per device. This device is already linked to '$deviceLockUser'.", "error");
                        $blocked = true;
                    }
                }

                // Check canvas fingerprint to block double accounts in private/incognito modes
                $isGenericFp = in_array($fingerprint, ['no-canvas', 'empty', 'blank-canvas']) || (strpos($fingerprint, 'error_') === 0);
                if (!$blocked && !empty($fingerprint) && !$isGenericFp && !DEVELOPMENT_MODE) {
                    $fpQuery = $pdo->prepare("SELECT username FROM users WHERE browser_fingerprint = ?");
                    $fpQuery->execute([$fingerprint]);
                    $fpUser = $fpQuery->fetchColumn();
                    if ($fpUser && strtolower($fpUser) !== strtolower($username)) {
                        setFlashMessage("Only one competitor account is allowed per device. This device is already associated with the account '$fpUser'.", "error");
                        $blocked = true;
                    }
                }

                if (!$blocked) {
                    // Check username uniqueness
                    $userStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $userStmt->execute([$username]);
                    if ($userStmt->fetch()) {
                        setFlashMessage("Username is already taken.", "error");
                    } else {
                        // Check phone uniqueness
                        $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $emailStmt->execute([$email]);
                        if ($emailStmt->fetch()) {
                            setFlashMessage("This phone number is already registered.", "error");
                        } else {
                            // Hash PIN and insert user as auto-verified
                            $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
                            $insert = $pdo->prepare("INSERT INTO users (email, username, password, browser_fingerprint, role, is_verified) VALUES (?, ?, ?, ?, 'user', 1)");
                            $insert->execute([$email, $username, $hashedPin, $fingerprint]);

                            // Retrieve user
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                            $stmt->execute([$username]);
                            $user = $stmt->fetch();

                            if ($user) {
                                // Log in user
                                loginUser($user);

                                // Lock device
                                setcookie('device_lock', $user['username'], time() + (365 * 24 * 60 * 60), '/', null, false, true);
                                setcookie('has_account', 'true', time() + (365 * 24 * 60 * 60), '/', null, false, false);
                                setFlashMessage("Registration successful! Welcome to the competition.", "success");
                                header("Location: " . BASE_URI . "quiz");
                                exit;
                            }
                        }
                    }
                }
            }
        }
    } else {
        // Sign In Action
        $identity = isset($_POST['login_identity']) ? trim($_POST['login_identity']) : '';
        $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';

        if (empty($identity) || empty($pin)) {
            setFlashMessage("Both fields are required to sign in.", "error");
        } else {
            // Match username or phone number
            $normalizedEmail = $identity;
            $cleanPhone = preg_replace('/[^0-9]/', '', $identity);
            if (strlen($cleanPhone) >= 10 && preg_match('/^\d{10}$/', substr($cleanPhone, -10))) {
                $normalizedEmail = substr($cleanPhone, -10) . '@phone.cheranalloorfaces.com';
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR email = ?");
            $stmt->execute([$identity, $identity, $normalizedEmail]);
            $user = $stmt->fetch();

            if (!$user) {
                setFlashMessage("Account not found. Please register if you are new.", "error");
            } else {
                // Enforce single competitor per device
                $blocked = false;
                if ($deviceLockUser && !DEVELOPMENT_MODE) {
                    if (strtolower($deviceLockUser) !== strtolower($user['username'])) {
                        setFlashMessage("This device is locked to '$deviceLockUser'. You cannot sign in with a different account.", "error");
                        $blocked = true;
                    }
                }

                if (!$blocked) {
                    // Legacy user check (no password set)
                    if (empty($user['password'])) {
                        // Secure account with the PIN they just entered
                        $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE users SET password = ?, is_verified = 1 WHERE id = ?");
                        $upd->execute([$hashedPin, $user['id']]);
                        $user['password'] = $hashedPin;
                        $user['is_verified'] = 1;
                    }

                    // Verify PIN
                    if (password_verify($pin, $user['password'])) {
                        loginUser($user);
                        setcookie('device_lock', $user['username'], time() + (365 * 24 * 60 * 60), '/', null, false, true);
                        setcookie('has_account', 'true', time() + (365 * 24 * 60 * 60), '/', null, false, false);
                        setFlashMessage("Signed in successfully!", "success");
                        
                        if ($user['role'] === 'admin') {
                            header("Location: " . BASE_URI . "admin/dashboard");
                        } else {
                            header("Location: " . BASE_URI . "quiz");
                        }
                        exit;
                    } else {
                        setFlashMessage("Incorrect PIN / Password. Please try again.", "error");
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-top: 2rem;">
    <!-- Tab Controls -->
    <div class="login-tabs" style="display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem;">
        <button type="button" id="tab-login" class="tab-btn active" onclick="switchTab('login')" style="flex: 1; padding: 0.75rem; background: none; border: none; font-size: 1rem; font-weight: 600; cursor: pointer; color: var(--text-color); border-bottom: 2px solid var(--secondary-accent); font-family: var(--font-heading);">Sign In</button>
        <button type="button" id="tab-register" class="tab-btn" onclick="switchTab('register')" style="flex: 1; padding: 0.75rem; background: none; border: none; font-size: 1rem; font-weight: 600; cursor: pointer; color: var(--text-muted); border-bottom: 2px solid transparent; font-family: var(--font-heading);">Register</button>
    </div>

    <form method="POST" action="<?php echo BASE_URI; ?>login" id="login-form">
        <!-- Hidden Inputs -->
        <input type="hidden" id="device_lock_local" name="device_lock_local" value="">
        <input type="hidden" id="form-action" name="action" value="<?php echo htmlspecialchars($action); ?>">
        <input type="hidden" id="browser_fingerprint" name="browser_fingerprint" value="">

        <!-- Identity Field (Changes label and name dynamically) -->
        <div class="form-group">
            <label for="login-field-input" id="login-field-label" class="form-label">Username or Phone Number</label>
            <input type="text" id="login-field-input" name="login_identity" class="form-control" 
                   value="<?php echo htmlspecialchars($identity ?: $username); ?>" placeholder="Enter your username or phone number" required>
        </div>

        <!-- Register-only fields -->
        <div id="register-only-fields" style="display: none;">
            <div class="form-group">
                <label for="email_or_phone" class="form-label">Phone Number</label>
                <input type="text" id="email_or_phone" name="email_or_phone" class="form-control" 
                       value="<?php echo htmlspecialchars($emailOrPhone); ?>" placeholder="9876543210">
                <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                    Used to secure and identify your account.
                </small>
            </div>
        </div>

        <!-- PIN Field -->
        <div class="form-group">
            <label for="pin" class="form-label">PIN / Password (4+ digits)</label>
            <input type="password" id="pin" name="pin" class="form-control" 
                   placeholder="••••" required minlength="4" style="letter-spacing: 0.25rem;">
            <small style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                Remember this PIN to log back in later.
            </small>
        </div>

        <button type="submit" id="submit-btn" class="btn btn-primary" style="margin-top: 1rem;">
            Sign In
        </button>
    </form>
    
    <div style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 1rem;">
        For any INFO contact: <strong>Abel ~ 9497720048</strong>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Compute browser fingerprint
        const fpInput = document.getElementById('browser_fingerprint');
        if (fpInput) {
            fpInput.value = getBrowserFingerprint();
        }

        // Safe storage helpers
        const getStoredItem = (key) => {
            try { return localStorage.getItem(key); } catch (e) { return null; }
        };

        // Read client-side device lock status from localStorage safely
        const localVal = getStoredItem('device_lock_username');
        if (localVal) {
            const inputEl = document.getElementById('device_lock_local');
            if (inputEl) {
                inputEl.value = localVal;
            }
        }
        
        // Determine default tab based on whether they have visited/registered before
        const hasAccount = getStoredItem('has_account') || document.cookie.includes('has_account=true');
        const hasError = document.querySelector('.alert') !== null;
        let targetAction = document.getElementById('form-action').value;
        
        const isDev = <?php echo (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) ? 'true' : 'false'; ?>;
        
        if (!hasError) {
            if (isDev) {
                targetAction = 'register';
            } else {
                targetAction = hasAccount ? 'login' : 'register';
            }
        }
        
        // Respect explicit action in query parameter if present
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action')) {
            targetAction = urlParams.get('action');
        }
        
        switchTab(targetAction);
    });

    function getBrowserFingerprint() {
        try {
            // 1. Canvas Fingerprint
            let canvasHash = '';
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                if (ctx) {
                    canvas.width = 200;
                    canvas.height = 50;
                    ctx.textBaseline = "top";
                    ctx.font = "14px 'Arial', sans-serif";
                    ctx.textBaseline = "alphabetic";
                    ctx.fillStyle = "#f60";
                    ctx.fillRect(125,1,62,20);
                    ctx.fillStyle = "#069";
                    ctx.fillText("CheranalloorFacesQuiz_192b*", 2, 15);
                    ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
                    ctx.fillText("CheranalloorFacesQuiz_192b*", 4, 17);
                    
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    let isBlank = true;
                    for (let i = 0; i < imgData.data.length; i += 4) {
                        if (imgData.data[i+3] !== 0) { // Alpha is non-zero
                            isBlank = false;
                            break;
                        }
                    }
                    if (!isBlank) {
                        canvasHash = canvas.toDataURL();
                    }
                }
            } catch (err) {
                // Ignore canvas readback blocks/errors
            }

            // 2. WebGL Renderer (GPU details - extremely distinct across different mobile chipsets)
            let webglRenderer = '';
            try {
                const glCanvas = document.createElement('canvas');
                const gl = glCanvas.getContext('webgl') || glCanvas.getContext('experimental-webgl');
                if (gl) {
                    const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                    if (debugInfo) {
                        webglRenderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                    }
                }
            } catch (e) {}

            // 3. User Agent
            const ua = navigator.userAgent || '';

            // 4. Screen details
            const screenDetails = `${screen.width}x${screen.height}x${screen.colorDepth}x${window.devicePixelRatio || 1}`;

            // 5. System config & Timezone
            const tz = new Date().getTimezoneOffset();
            const lang = navigator.language || navigator.userLanguage || '';
            const hw = navigator.hardwareConcurrency || '';

            // Combine all attributes to form a highly unique raw fingerprint
            const rawFp = [canvasHash, webglRenderer, ua, screenDetails, tz, lang, hw].join('||');

            // 6. Compute hash
            let hash = 0;
            if (rawFp.length === 0) return 'empty';
            for (let i = 0; i < rawFp.length; i++) {
                const char = rawFp.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return 'fp_' + Math.abs(hash).toString(16);
        } catch (e) {
            return 'error_' + e.message.substring(0, 10);
        }
    }

    function switchTab(tab) {
        const tabLogin = document.getElementById('tab-login');
        const tabRegister = document.getElementById('tab-register');
        const registerFields = document.getElementById('register-only-fields');
        const loginFieldLabel = document.getElementById('login-field-label');
        const loginFieldInput = document.getElementById('login-field-input');
        const submitBtn = document.getElementById('submit-btn');
        const formAction = document.getElementById('form-action');

        if (tab === 'login') {
            tabLogin.classList.add('active');
            tabLogin.style.color = 'var(--text-color)';
            tabLogin.style.borderBottomColor = 'var(--secondary-accent)';
            
            tabRegister.classList.remove('active');
            tabRegister.style.color = 'var(--text-muted)';
            tabRegister.style.borderBottomColor = 'transparent';
            
            registerFields.style.display = 'none';
            document.getElementById('email_or_phone').required = false;
            
            loginFieldLabel.textContent = "Username or Phone Number";
            loginFieldInput.placeholder = "Enter your username or phone number";
            loginFieldInput.name = "login_identity";
            
            submitBtn.textContent = "Sign In";
            formAction.value = "login";
        } else {
            tabRegister.classList.add('active');
            tabRegister.style.color = 'var(--text-color)';
            tabRegister.style.borderBottomColor = 'var(--secondary-accent)';
            
            tabLogin.classList.remove('active');
            tabLogin.style.color = 'var(--text-muted)';
            tabLogin.style.borderBottomColor = 'transparent';
            
            registerFields.style.display = 'block';
            document.getElementById('email_or_phone').required = true;
            
            loginFieldLabel.textContent = "Choose Username";
            loginFieldInput.placeholder = "Enter a public username";
            loginFieldInput.name = "username";
            
            submitBtn.textContent = "Create Account & Sign In";
            formAction.value = "register";
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
