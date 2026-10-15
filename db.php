<?php
// Cheranalloor Faces — Database PDO Manager with Auto-Setup

require_once __DIR__ . '/config.php';

try {
    // 1. Connect directly to the database first for maximum speed
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // 2. Perform table checks and migrations ONLY ONCE using a lock file
    $lockFile = __DIR__ . '/db_setup.lock';
    if (!file_exists($lockFile)) {
        // Ensure password column exists in users table
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `username`");
        } catch (PDOException $e) {}

        // Ensure browser_fingerprint column exists in users table
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `browser_fingerprint` VARCHAR(100) DEFAULT NULL AFTER `password`");
        } catch (PDOException $e) {}

        // Ensure cheat_logs table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cheat_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `warning_number` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Ensure user_quiz_questions table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_quiz_questions` (
            `user_id` INT NOT NULL,
            `question_id` INT NOT NULL,
            `sort_order` INT NOT NULL,
            `option_order` CHAR(4) NOT NULL,
            PRIMARY KEY (`user_id`, `question_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed default quiz_question_count if not present
        try {
            $pdo->exec("INSERT IGNORE INTO `quiz_config` (`cfg_key`, `cfg_value`) VALUES ('quiz_question_count', '10')");
        } catch (PDOException $e) {}

        // Write the lock file to bypass migrations on subsequent requests
        @file_put_contents($lockFile, 'done');
    }
} catch (PDOException $e) {
    // 3. Fallback to connecting without database to handle initial creation (e.g. clean WAMP install)
    try {
        $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdoWithoutDb = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);
        $pdoWithoutDb->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Connect to the newly created DB
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, $options);
        
        // Import schema
        $schemaPath = __DIR__ . '/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            $pdo->exec($sql);
        }

        // Apply column additions
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `username`");
        } catch (PDOException $ex) {}
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `browser_fingerprint` VARCHAR(100) DEFAULT NULL AFTER `password`");
        } catch (PDOException $ex) {}

        // Ensure cheat_logs exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cheat_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `warning_number` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Ensure user_quiz_questions exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_quiz_questions` (
            `user_id` INT NOT NULL,
            `question_id` INT NOT NULL,
            `sort_order` INT NOT NULL,
            `option_order` CHAR(4) NOT NULL,
            PRIMARY KEY (`user_id`, `question_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed default quiz_question_count if not present
        try {
            $pdo->exec("INSERT IGNORE INTO `quiz_config` (`cfg_key`, `cfg_value`) VALUES ('quiz_question_count', '10')");
        } catch (PDOException $e) {}

        @file_put_contents(__DIR__ . '/db_setup.lock', 'done');
    } catch (PDOException $ex) {
        if (DEVELOPMENT_MODE) {
            die("<h3>Database Connection Error</h3><p>" . htmlspecialchars($ex->getMessage()) . "</p><p>Please ensure your database configurations in <strong>config.php</strong> match your phpMyAdmin details.</p>");
        } else {
            die("Database connection failed. Please contact the administrator.");
        }
    }
}

// Define DEVELOPMENT_MODE dynamically based on Database Override
$dbDevMode = null;
try {
    $stmt = $pdo->prepare("SELECT cfg_value FROM `quiz_config` WHERE `cfg_key` = 'development_mode'");
    $stmt->execute();
    $dbDevMode = $stmt->fetchColumn();
} catch (PDOException $e) {}

if ($dbDevMode === 'true') {
    define('DEVELOPMENT_MODE', true);
} elseif ($dbDevMode === 'false') {
    define('DEVELOPMENT_MODE', false);
} else {
    define('DEVELOPMENT_MODE', DEVELOPMENT_MODE_DEFAULT);
}

/**
 * Fetch a configuration value from the quiz_config table.
 */
function getConfig($key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT cfg_value FROM quiz_config WHERE cfg_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['cfg_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Write/Update a configuration key.
 */
function setConfig($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO quiz_config (cfg_key, cfg_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE cfg_value = ?");
    $stmt->execute([$key, $value, $value]);
}

/**
 * Retrieve current quiz settings bundle.
 */
function getQuizSettings() {
    $state = getConfig('quiz_state', 'inactive');
    $startTimeStr = getConfig('quiz_start_time', '');
    
    $startTime = null;
    if ($startTimeStr) {
        try {
            $startTime = new DateTime($startTimeStr);
        } catch (Exception $e) {}
    }
    
    // Auto-live transition: if state is 'registration' and start_time has passed
    if ($state === 'registration' && $startTime && new DateTime() >= $startTime) {
        setConfig('quiz_state', 'live');
        $state = 'live';
    }
    
    $durationMins = intval(getConfig('quiz_duration_mins', '30'));
    $timeLimitSec = intval(getConfig('question_time_limit', '30'));
    $questionCount = intval(getConfig('quiz_question_count', '10'));
    
    return [
        'state' => $state,
        'start_time' => $startTime,
        'duration_mins' => $durationMins,
        'time_limit_sec' => $timeLimitSec,
        'question_count' => $questionCount
    ];
}
?>
