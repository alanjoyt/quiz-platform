-- Cheranalloor Faces Database Schema
-- phpMyAdmin / MySQL compatible

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password` VARCHAR(255) DEFAULT NULL,
  `browser_fingerprint` VARCHAR(100) DEFAULT NULL,
  `role` VARCHAR(20) DEFAULT 'user',
  `is_verified` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`email`),
  INDEX (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: quiz_config
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_config` (
  `cfg_key` VARCHAR(50) PRIMARY KEY,
  `cfg_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: questions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `text` TEXT NOT NULL,
  `option_a` VARCHAR(255) NOT NULL,
  `option_b` VARCHAR(255) NOT NULL,
  `option_c` VARCHAR(255) NOT NULL,
  `option_d` VARCHAR(255) NOT NULL,
  `correct_option` CHAR(1) NOT NULL,
  `points` INT DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_quiz_questions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_quiz_questions` (
  `user_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `sort_order` INT NOT NULL,
  `option_order` CHAR(4) NOT NULL,
  PRIMARY KEY (`user_id`, `question_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_quiz_state
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_quiz_state` (
  `user_id` INT PRIMARY KEY,
  `current_question_id` INT DEFAULT NULL,
  `current_question_started_at` DATETIME DEFAULT NULL,
  `quiz_started_at` DATETIME DEFAULT NULL,
  `quiz_completed_at` DATETIME DEFAULT NULL,
  `is_disqualified` TINYINT(1) DEFAULT 0,
  `disqualification_reason` VARCHAR(255) DEFAULT NULL,
  `warnings_count` INT DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_responses
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_responses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `selected_option` CHAR(1) DEFAULT NULL,
  `is_correct` TINYINT(1) DEFAULT 0,
  `points_earned` INT DEFAULT 0,
  `speed_bonus` INT DEFAULT 0,
  `response_time_ms` INT DEFAULT 0,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: cheat_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cheat_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `warning_number` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seeding Defaults
-- --------------------------------------------------------

-- Default Admin User (Default verified, email: admin@cheranalloorfaces.com)
INSERT INTO `users` (`email`, `username`, `role`, `is_verified`) 
VALUES ('admin@cheranalloorfaces.com', 'admin', 'admin', 1)
ON DUPLICATE KEY UPDATE `role`='admin', `is_verified`=1;

-- Default Settings
INSERT INTO `quiz_config` (`cfg_key`, `cfg_value`) VALUES 
('quiz_state', 'registration'),
('quiz_start_time', DATE_ADD(NOW(), INTERVAL 1 DAY)),
('quiz_duration_mins', '15'),
('question_time_limit', '30'),
('quiz_question_count', '10')
ON DUPLICATE KEY UPDATE `cfg_value`=VALUES(`cfg_value`);

-- Malayalam Quiz Questions
INSERT INTO `questions` (`text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `points`) VALUES
('ചേരാനല്ലൂർ വിശുദ്ധ ഫ്രാൻസിസ് സേവിയേഴ്സ് ദേവാലയം സ്ഥാപിക്കപ്പെട്ട വർഷം?', '1904', '1804', '1947', '1504', 'B', 10),
('ചേരാനല്ലൂർ പള്ളിയോടനുബന്ധിച്ചുള്ള സന്യാസിനി സമൂഹം ഏത് കോൺഗ്രിഗേഷനിൽ പെടുന്നതാണ്?', 'CMC', 'SD', 'CMI', 'SH', 'B', 10),
('ചേരാനല്ലൂർ പള്ളി ഏത് ഫൊറോനയുടെ പരിധിയിൽ പെടുന്നു?', 'മലയാറ്റൂർ', 'പെരുമ്പാവൂർ', 'കാഞ്ഞൂർ', 'വല്ലം', 'D', 10),
('ചേരാനല്ലൂർ പ്രദേശം ഏത് താലൂക്കിൽ പെടുന്നു?', 'കൂവപ്പടി', 'കുറുപ്പംപടി', 'കാലടി', 'കുന്നത്തുനാട്', 'D', 10),
('പെരിയാർ നദി ഏത് കുന്നുകളിൽ നിന്ന് ആരംഭിക്കുന്നു?', 'മൂന്നാർ കുന്നുകൾ', 'മലയാറ്റൂർ മല', 'ശിവഗിരി കുന്നുകൾ', 'വാഗമൺ കുന്നുകൾ', 'C', 10),
('ചേരാനല്ലൂർ പള്ളി അങ്ങാടിയിൽ നിന്ന് തോട്ടുവ ഭാഗത്തേക്ക് പോകുമ്പോൾ കാണുന്ന ചിറയുടെ പേര്?', 'തൊട്ടുചിറ', 'മുട്ടുചിറ', 'മണപ്പാട്ടുചിറ', 'കൊടുവേലിചിറ', 'B', 10),
('ചേരാനല്ലൂർ അമ്പലപ്പറമ്പിനടുത്ത് കാണുന്ന ക്ഷേത്രത്തിലെ പ്രതിഷ്ഠ ഏത് ഭഗവാനാണ്?', 'വിഷ്ണു', 'ശിവൻ', 'ഭദ്രകാളി', 'കൃഷ്ണൻ', 'B', 10),
('ചേരാനല്ലൂർ ശ്രീ ശങ്കരനാരായണ ക്ഷേത്രം എവിടെ സ്ഥിതി ചെയ്യുന്നു?', 'തോട്ടുവാ', 'ഓച്ചാംതുരുത്ത്', 'ഇടവൂർ', 'വളവുംപടി', 'C', 10),
('ചേരാനല്ലൂർ പോസ്റ്റ് ഓഫീസ് പിൻകോഡ് എന്ത്?', '653848', '683544', '664188', '618535', 'B', 10),
('ചേരാനല്ലൂർ government higher secondary school എവിടെ സ്ഥിതി ചെയ്യുന്നു?', 'ചേരാനല്ലൂർ', 'ഐമുറി', 'കൂവപ്പടി', 'പെരുമ്പാവൂർ', 'B', 10),
('കാലടിയിൽ ജനിച്ചു വളർന്ന ലോകപ്രശസ്തനായ ഹിന്ദുമതാചാര്യൻ ആര്?', 'ശ്രീ നാരായണ ഗുരു', 'ശ്രീ ശങ്കരാചാര്യർ', 'സ്വാമി ശാശ്വകാനന്ദ', 'സ്വാമി വിവേകാനന്ദൻ', 'B', 10),
('തോട്ടുവായിൽ ബാല്യ കൗമാര കാലങ്ങളിൽ ജീവിച്ച പ്രശസ്തനായ സാഹിത്യകാരൻ?', 'മലയാറ്റൂർ സുരേന്ദ്രൻ', 'തോട്ടുവ തമ്പി', 'മലയാറ്റൂർ രാമകൃഷ്ണൻ', 'കൂവ്വപ്പടി കർത്താവ്', 'C', 10),
('മേള കുലപതി എന്നറിയപ്പെടുന്ന ചേരാനല്ലൂരുകാരൻ ആര്?', 'പെരുവാരൻ കുട്ടൻ മാരാർ', 'പല്ലാവൂർ കൃഷ്ണൻകുട്ടി', 'മോഹനൻ മാരാർ', 'ചേരാനല്ലൂർ ശങ്കരൻകുട്ടി മാരാർ', 'D', 10),
('സിനിമയിലെ വസ്ത്രാലങ്കാരത്തിന് സംസ്ഥാന അവാർഡ് ലഭിച്ച ചേരാനല്ലൂർ നിവാസി ആര്?', 'ജേക്കബ് ജോയി', 'മെൽവിൻ ജോയി', 'സാമുവൽ പോൾ', 'ജിതിൻ ജോൺ', 'B', 10),
('ചേരാനല്ലൂർ ഗവൺമെൻറ് ആയുർവേദ ആശുപത്രി എവിടെ?', 'മങ്കുഴി പള്ളിക്ക് സമീപം', 'St.ജോർജ് കപ്പേളക്ക് സമീപം', 'St.ആൻറണീസ് കപ്പേളക്ക് സമീപം', 'St.ജൂഡ് കപ്പേളക്ക് സമീപം', 'C', 10),
('ചേരാനല്ലൂരിന്റെ ടെലിഫോൺ കോഡ് എത്ര?', '0848', '4848', '0484', '0808', 'C', 10),
('ചേരാനല്ലൂർ Mahaദേവ ക്ഷേത്രത്തിലെ പ്രധാന വാർഷിക ഉത്സവം ഏത് മലയാള മാസത്തിലാണ് സാധാരണ നടക്കുന്നത്?', 'മേടം', 'കുംഭം', 'ചിങ്ങം', 'വൃശ്ചികം', 'B', 10),
('ചേരാനല്ലൂർ സെൻറ് ഫ്രാൻസിസ് സേവിയേഴ്സ് പള്ളിക്ക് ആകെ എത്ര കപ്പേളകൾ ഉണ്ട്?', '6', '8', '7', '5', 'C', 10),
('ചേരാനല്ലൂരിന്റെ പരമ്പരാഗത തൊഴിൽ മേഖല ഏത്?', 'ഇറ്റവെട്ടും പനമ്പെയ്ത്തും', 'കൈത്തറി മേഖല', 'കരകൗശല മേഖല', 'മത്സ്യകൃഷി', 'A', 10),
('ചേരാനല്ലൂർ ചർച്ച് യൂ.പി സ്കൂൾ ആരംഭിച്ച വർഷം?', '1916', '1920', '1918', '1930', 'B', 10);
