<?php
require_once __DIR__ . '/../auth_helper.php';
$currentUser = getCurrentUser();
$quizSettings = getQuizSettings();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <meta name="description" content="Cheranalloor Faces is a premium community quiz competition. Compete, check the real-time leaderboard, and test your knowledge.">

    <!-- Open Graph / Link Preview -->
    <meta property="og:title" content="Cheranalloor Faces — Quiz Competition">
    <meta property="og:description" content="Join the live timed community quiz and show your skills on the leaderboard.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?php echo BASE_URI; ?>static/images/quiz-preview.jpg">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Cheranalloor Faces">

    <title><?php echo isset($pageTitle) ? $pageTitle . " — Cheranalloor Faces" : "Cheranalloor Faces — Quiz Competition"; ?></title>

    <!-- Preconnect to Font Servers -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Stylesheet -->
    <link
        rel="stylesheet"
        href="<?php echo BASE_URI; ?>static/css/style.css?v=<?php echo file_exists(__DIR__ . '/../static/css/style.css') ? filemtime(__DIR__ . '/../static/css/style.css') : '1.0.1'; ?>"
    >
</head>

<body>

<div class="app-container">

    <?php $hideNav = isset($GLOBALS['hideNav']) && $GLOBALS['hideNav']; ?>
    <header class="site-header">

        <?php if ($hideNav): ?>
        <div class="logo" style="display: inline-flex; align-items: center; gap: 0.5rem;">
        <?php else: ?>
        <a
            href="<?php echo BASE_URI; ?>"
            class="logo"
            style="display: inline-flex; align-items: center; gap: 0.5rem;"
        >
        <?php endif; ?>
            <svg
                class="logo-icon"
                viewBox="0 0 24 24"
                width="24"
                height="24"
                fill="currentColor"
                style="color: var(--primary-accent); display: inline-block; flex-shrink: 0;"
            >
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
            </svg>

            <div style="display: flex; flex-direction: column; line-height: 1.1; font-size: 0.8rem; font-family: var(--font-heading); font-weight: 800; text-align: left;">
                <span style="letter-spacing: 0.5px;">cheranalloor_faces</span>
                <span style="color: var(--primary-accent); letter-spacing: 0.5px;">kcym_cheranalloor</span>
            </div>
        <?php if ($hideNav): ?>
        </div>
        <?php else: ?>
        </a>
        <?php endif; ?>

        <?php if (!$hideNav): ?>
        <!-- Desktop Nav -->
        <nav class="header-nav">

            <a
                href="<?php echo BASE_URI; ?>"
                class="<?php echo $_SERVER['REQUEST_URI'] == BASE_URI || $_SERVER['REQUEST_URI'] == BASE_URI . 'index' ? 'active' : ''; ?>"
            >
                Home
            </a>

            <a
                href="<?php echo BASE_URI; ?>quiz"
                class="<?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'quiz') !== false ? 'active' : ''; ?>"
            >
                Quiz
            </a>

            <a
                href="<?php echo BASE_URI; ?>leaderboard"
                class="<?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'leaderboard') !== false ? 'active' : ''; ?>"
            >
                Leaderboard
            </a>

            <?php if ($currentUser): ?>

                <a
                    href="<?php echo BASE_URI; ?>profile"
                    class="<?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'profile') !== false ? 'active' : ''; ?>"
                >
                    Profile
                </a>

                <?php if ($currentUser['role'] === 'admin'): ?>

                    <a
                        href="<?php echo BASE_URI; ?>admin/dashboard"
                        class="<?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'admin/') !== false ? 'active' : ''; ?>"
                    >
                        Admin
                    </a>

                <?php endif; ?>

                <a href="<?php echo BASE_URI; ?>logout">
                    Logout
                </a>

            <?php else: ?>

                <a
                    href="<?php echo BASE_URI; ?>login"
                    class="<?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'login') !== false ? 'active' : ''; ?>"
                >
                    Login
                </a>

            <?php endif; ?>

        </nav>
        <?php endif; ?>

        <!-- Theme Toggle Button -->
        <button
            id="theme-toggle-btn"
            class="theme-toggle-btn"
            aria-label="Toggle dark/light theme"
        >

            <!-- Sun Icon -->
            <svg
                id="sun-icon"
                viewBox="0 0 24 24"
                style="display: none;"
            >
                <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39-.39 1.03-.39 1.41 0s.39 1.03 0 1.41l-1.06-1.06zm1.06-10.96c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03-.39 1.41s1.03 1.41 1.41 1.41l1.06-1.06c.39-.38.39-1.02 0-1.41zM6.99 16.99c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03 1.03 1.41 1.41l1.06-1.06c.38-.39.38-1.03 0-1.41z"/>
            </svg>

            <!-- Moon Icon -->
            <svg
                id="moon-icon"
                viewBox="0 0 24 24"
            >
                <path d="M12.3 22h-.1c-5.5 0-10-4.5-10-10 0-4.8 3.5-8.9 8.2-9.8.6-.1 1.2.3 1.3.9.1.6-.3 1.2-.9 1.3-3.7.7-6.4 4-6.4 7.8 0 4.4 3.6 8 8 8 3.8 0 7.1-2.7 7.8-6.4.1-.6.7-1 1.3-.9.6.1 1 .7.9 1.3-.9 4.7-5 8.2-9.8 8.2z"/>
            </svg>

        </button>

        <?php if (!$hideNav): ?>
            <?php if (!$currentUser): ?>

                <a
                    href="<?php echo BASE_URI; ?>login"
                    class="mobile-login-header-btn"
                    title="Sign In"
                >
                    👤
                </a>

            <?php else: ?>

                <a
                    href="<?php echo BASE_URI; ?>logout"
                    class="mobile-login-header-btn"
                    title="Logout"
                    style="color: var(--error-color); padding: 0;"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        style="display: inline-block; vertical-align: middle;"
                    >
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </a>

            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($currentUser) && $currentUser): ?>

            <script>
                try {
                    localStorage.setItem('has_account', 'true');
                    <?php if ($currentUser['role'] === 'user'): ?>
                    localStorage.setItem(
                        'device_lock_username',
                        <?php echo json_encode($currentUser['username']); ?>
                    );
                    <?php endif; ?>
                } catch (e) {
                    // Fail silently if localStorage is blocked (e.g. Brave/Incognito)
                }
            </script>

        <?php endif; ?>

    </header>

    <!-- Main Wrapper -->
    <main class="main-content <?php echo strpos($_SERVER['REQUEST_URI'], BASE_URI . 'admin/') !== false ? 'admin-layout' : ''; ?>">

        <!-- Flash messages -->
        <?php $flash = getFlashMessage(); ?>

        <?php if ($flash): ?>

            <div class="alerts">

                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
                    <?php echo htmlspecialchars($flash['text']); ?>
                </div>

            </div>

        <?php endif; ?>