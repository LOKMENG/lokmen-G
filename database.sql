-- =====================================================================
--  منصة الاختبارات المهنية السعودية | Professional License Platform
--  قاعدة البيانات الكاملة - MySQL 5.7+ / MariaDB 10.4+ (XAMPP Ready)
--  الترميز: utf8mb4 (دعم كامل للغة العربية)
-- =====================================================================
--  هذا الملف قابل للاستيراد مباشرة من phpMyAdmin أو سطر الأوامر:
--      mysql -u root -p < database.sql
--  أو من خلال معالج التثبيت: http://localhost/professional-license/install.php
-- =====================================================================
--  تحذير أمني: غيّر كلمة مرور حساب المدير الافتراضي فور أول تسجيل دخول.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";

CREATE DATABASE IF NOT EXISTS `professional_license`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `professional_license`;

-- =====================================================================
--  القسم 1: الإعدادات العامة
-- =====================================================================

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `setting_type` ENUM('string','text','int','bool','json','secret') NOT NULL DEFAULT 'string',
  `label_ar`    VARCHAR(190) NULL,
  `is_public`   TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='إعدادات المنصة القابلة للتعديل من لوحة التحكم';

-- =====================================================================
--  القسم 2: المستخدمون والصلاحيات والأمان
-- =====================================================================

DROP TABLE IF EXISTS `tracks`;
CREATE TABLE `tracks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(50) NOT NULL COMMENT 'specialist | educational | ...',
  `name_ar`     VARCHAR(190) NOT NULL,
  `name_en`     VARCHAR(190) NULL,
  `description` TEXT NULL,
  `icon`        VARCHAR(50) NULL,
  `color`       VARCHAR(20) NULL,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracks_code` (`code`),
  KEY `idx_tracks_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مسارات الاختبارات: تخصصي / تربوي / أي تخصص يُضاف لاحقاً';
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `track_id`    INT UNSIGNED NOT NULL,
  `parent_id`   INT UNSIGNED NULL COMMENT 'للتصنيف الهرمي: المجال ← المحور',
  `code`        VARCHAR(80) NOT NULL,
  `name_ar`     VARCHAR(190) NOT NULL,
  `name_en`     VARCHAR(190) NULL,
  `description` TEXT NULL,
  `icon`        VARCHAR(50) NULL,
  `color`       VARCHAR(20) NULL,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_track_code` (`track_id`, `code`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_track_active` (`track_id`, `is_active`, `sort_order`),
  CONSTRAINT `fk_categories_track`  FOREIGN KEY (`track_id`)  REFERENCES `tracks` (`id`)     ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='المجالات والمحاور - قابلة للتعديل بالكامل من لوحة التحكم (لا تعتمد على تصنيف رسمي ثابت)';
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`         VARCHAR(190) NOT NULL COMMENT 'الاسم الكامل',
  `phone`             VARCHAR(20)  NOT NULL COMMENT 'رقم الجوال بصيغة دولية مثل 9665XXXXXXXX',
  `email`             VARCHAR(190) NOT NULL,
  `password_hash`     VARCHAR(255) NOT NULL COMMENT 'password_hash() - لا تُخزن كلمة المرور أبداً كنص صريح',
  `role`              ENUM('student','admin','supervisor') NOT NULL DEFAULT 'student',
  `status`            ENUM('active','disabled','pending') NOT NULL DEFAULT 'active',
  `city`              VARCHAR(100) NULL COMMENT 'المدينة',
  `specialty`         VARCHAR(190) NULL COMMENT 'التخصص الأكاديمي/المهني',
  `target_track_id`   INT UNSIGNED NULL COMMENT 'المسار المستهدف (تخصصي/تربوي)',
  `avatar`            VARCHAR(255) NULL,
  `telegram_chat_id`  BIGINT NULL COMMENT 'معرّف المحادثة في تلجرام (يُضبط تلقائياً عند الربط)',
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `last_login_at`     DATETIME NULL,
  `last_login_ip`     VARCHAR(45) NULL,
  `failed_login_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`      DATETIME NULL,
  `notes`             TEXT NULL COMMENT 'ملاحظات إدارية',
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_role_status` (`role`, `status`),
  KEY `idx_users_created` (`created_at`),
  KEY `idx_users_target_track` (`target_track_id`),
  CONSTRAINT `fk_users_track` FOREIGN KEY (`target_track_id`) REFERENCES `tracks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حسابات المستخدمين (طلاب / مديرين / مشرفين)';

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 للمفتاح المُرسل (لا يُخزن المفتاح نفسه)',
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  `ip`         VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token_hash`),
  KEY `idx_pr_user` (`user_id`),
  KEY `idx_pr_expires` (`expires_at`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='طلبات استعادة كلمة المرور';

DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE `remember_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `selector`   CHAR(32) NOT NULL COMMENT 'معرّف البحث في ملف تعريف الارتباط',
  `token_hash` CHAR(64) NOT NULL,
  `user_agent` VARCHAR(190) NULL,
  `ip`         VARCHAR(45) NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rt_selector` (`selector`),
  KEY `idx_rt_user` (`user_id`),
  KEY `idx_rt_expires` (`expires_at`),
  CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='جلسات "تذكر تسجيل الدخول" (Token منفصل عن كلمة المرور)';

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(190) NOT NULL COMMENT 'البريد أو الجوال المستخدم في المحاولة',
  `ip`         VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(190) NULL,
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_identifier_time` (`identifier`, `created_at`),
  KEY `idx_la_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل محاولات الدخول لمنع هجمات التخمين (Rate Limiting)';

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `action`      VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id`   INT UNSIGNED NULL,
  `meta`        TEXT NULL COMMENT 'JSON',
  `ip`          VARCHAR(45) NULL,
  `user_agent`  VARCHAR(190) NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user` (`user_id`),
  KEY `idx_al_action_time` (`action`, `created_at`),
  KEY `idx_al_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل العمليات الحساسة (تدقيق أمني)';

-- =====================================================================
--  القسم 3: المسارات والتصنيفات والمصادر (بنية مرنة قابلة للتوسع)
-- =====================================================================




-- =====================================================================
--  القسم 4: بنك الأسئلة
-- =====================================================================

DROP TABLE IF EXISTS `sources`;
CREATE TABLE `sources` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(190) NOT NULL COMMENT 'اسم المصدر كما سيظهر للطالب',
  `type`          ENUM('pdf','book','website','official','teacher','other') NOT NULL DEFAULT 'pdf',
  `author`        VARCHAR(190) NULL,
  `year`          SMALLINT NULL,
  `file_path`     VARCHAR(255) NULL COMMENT 'مسار الملف المرفوع (uploads/questions)',
  `file_hash`     CHAR(64) NULL COMMENT 'لتفادي استيراد الملف نفسه مرتين',
  `url`           VARCHAR(255) NULL,
  `license_note`  VARCHAR(255) NULL COMMENT 'إقرار بحق الاستخدام / التصريح',
  `notes`         TEXT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sources_active` (`is_active`),
  KEY `idx_sources_hash` (`file_hash`),
  CONSTRAINT `fk_sources_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مصادر الأسئلة - نحفظ المصدر مع كل سؤال (حقوق المحتوى)';
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `track_id`       INT UNSIGNED NOT NULL COMMENT 'المسار: تخصصي / تربوي',
  `category_id`    INT UNSIGNED NULL COMMENT 'المجال',
  `subcategory_id` INT UNSIGNED NULL COMMENT 'المحور (تصنيف فرعي)',
  `source_id`      INT UNSIGNED NULL,
  `question_text`  TEXT NOT NULL,
  `question_type`  ENUM('mcq','true_false') NOT NULL DEFAULT 'mcq',
  `option_a`       TEXT NULL,
  `option_b`       TEXT NULL,
  `option_c`       TEXT NULL,
  `option_d`       TEXT NULL,
  `correct_answer` ENUM('a','b','c','d') NULL COMMENT 'NULL = يحتاج مراجعة',
  `explanation`    TEXT NULL COMMENT 'شرح الإجابة',
  `difficulty`     ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `source_note`    VARCHAR(255) NULL COMMENT 'مرجع السؤال / سنة المصدر إن وجد',
  `source_page`    INT NULL COMMENT 'رقم الصفحة في ملف المصدر',
  `year`           SMALLINT NULL,
  `needs_review`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = السؤال/الإجابة غير واضحة في المصدر ولم يتم التخمين',
  `review_note`    VARCHAR(255) NULL COMMENT 'سبب الحاجة للمراجعة',
  `content_hash`   CHAR(64) NULL COMMENT 'SHA-256 للنص المُطبّع - لمنع تكرار الأسئلة',
  `active`         TINYINT(1) NOT NULL DEFAULT 1,
  `times_answered` INT UNSIGNED NOT NULL DEFAULT 0,
  `times_correct`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`     INT UNSIGNED NULL,
  `updated_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_questions_hash` (`content_hash`),
  KEY `idx_q_track_active` (`track_id`, `active`),
  KEY `idx_q_category` (`category_id`, `active`),
  KEY `idx_q_subcategory` (`subcategory_id`),
  KEY `idx_q_difficulty` (`difficulty`),
  KEY `idx_q_needs_review` (`needs_review`),
  KEY `idx_q_source` (`source_id`),
  KEY `idx_q_accuracy` (`times_answered`, `times_correct`),
  CONSTRAINT `fk_q_track`    FOREIGN KEY (`track_id`)       REFERENCES `tracks` (`id`)     ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_q_category` FOREIGN KEY (`category_id`)    REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_q_subcat`   FOREIGN KEY (`subcategory_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_q_source`   FOREIGN KEY (`source_id`)      REFERENCES `sources` (`id`)    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_q_creator`  FOREIGN KEY (`created_by`)     REFERENCES `users` (`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_q_updater`  FOREIGN KEY (`updated_by`)     REFERENCES `users` (`id`)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بنك الأسئلة - يدعم أكثر من مسار (تخصصي/تربوي) دون تغيير البنية';

DROP TABLE IF EXISTS `question_reports`;
CREATE TABLE `question_reports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `reason`      ENUM('wrong_answer','typo','unclear','duplicate','other','copyright') NOT NULL DEFAULT 'other',
  `note`        TEXT NULL,
  `status`      ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qr_status` (`status`, `created_at`),
  KEY `idx_qr_question` (`question_id`),
  CONSTRAINT `fk_qr_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_qr_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بلاغات الطلاب عن أخطاء في الأسئلة';

DROP TABLE IF EXISTS `import_batches`;
CREATE TABLE `import_batches` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name`       VARCHAR(255) NOT NULL,
  `file_path`       VARCHAR(255) NULL,
  `file_type`       ENUM('pdf','txt','csv','json','manual') NOT NULL DEFAULT 'pdf',
  `file_hash`       CHAR(64) NULL,
  `total_rows`      INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_rows`   INT UNSIGNED NOT NULL DEFAULT 0,
  `duplicate_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `invalid_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
  `needs_review_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`          ENUM('pending','previewed','imported','failed','cancelled') NOT NULL DEFAULT 'pending',
  `report`          TEXT NULL COMMENT 'JSON: تفاصيل الفحص',
  `imported_by`     INT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ib_status` (`status`, `created_at`),
  CONSTRAINT `fk_ib_user` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عمليات استيراد الأسئلة من PDF/CSV/JSON';

DROP TABLE IF EXISTS `import_staging`;
CREATE TABLE `import_staging` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`       INT UNSIGNED NOT NULL,
  `row_number`     INT UNSIGNED NOT NULL,
  `raw_text`       TEXT NULL,
  `question_text`  TEXT NULL,
  `option_a`       TEXT NULL,
  `option_b`       TEXT NULL,
  `option_c`       TEXT NULL,
  `option_d`       TEXT NULL,
  `correct_answer` ENUM('a','b','c','d') NULL,
  `explanation`    TEXT NULL,
  `category_guess` VARCHAR(190) NULL,
  `difficulty`     ENUM('easy','medium','hard') NULL,
  `source_page`    INT NULL,
  `content_hash`   CHAR(64) NULL,
  `is_valid`       TINYINT(1) NOT NULL DEFAULT 1,
  `is_duplicate`   TINYINT(1) NOT NULL DEFAULT 0,
  `needs_review`   TINYINT(1) NOT NULL DEFAULT 0,
  `issues`         VARCHAR(500) NULL COMMENT 'JSON: قائمة المشاكل المكتشفة (اختيارات ناقصة، إجابة غير واضحة، OCR)',
  `status`         ENUM('pending','approved','rejected','imported') NOT NULL DEFAULT 'pending',
  `question_id`    INT UNSIGNED NULL COMMENT 'معرّف السؤال بعد الإدخال الفعلي',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_batch_status` (`batch_id`, `status`),
  KEY `idx_is_hash` (`content_hash`),
  CONSTRAINT `fk_is_batch` FOREIGN KEY (`batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='منطقة مراجعة مؤقتة قبل إدخال الأسئلة المستوردة إلى بنك الأسئلة';

-- =====================================================================
--  القسم 5: الاختبارات ومحرك الاختبار
-- =====================================================================

DROP TABLE IF EXISTS `exam_templates`;
CREATE TABLE `exam_templates` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`               VARCHAR(190) NOT NULL,
  `description`         TEXT NULL,
  `track_id`            INT UNSIGNED NOT NULL,
  `mode`                ENUM('mock','category','random','practice','daily') NOT NULL DEFAULT 'mock',
  `category_ids`        TEXT NULL COMMENT 'JSON: المجالات المشمولة - فارغ = الكل',
  `difficulty`          ENUM('any','easy','medium','hard') NOT NULL DEFAULT 'any',
  `question_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `duration_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 60 COMMENT '0 = بدون مؤقت',
  `pass_percentage`     TINYINT UNSIGNED NOT NULL DEFAULT 60,
  `randomize_questions` TINYINT(1) NOT NULL DEFAULT 1,
  `randomize_options`   TINYINT(1) NOT NULL DEFAULT 0,
  `show_explanation`    TINYINT(1) NOT NULL DEFAULT 1,
  `require_subscription` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = متاح كتجربة مجانية',
  `max_attempts`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = بلا حدود',
  `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`          SMALLINT NOT NULL DEFAULT 0,
  `created_by`          INT UNSIGNED NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_et_track_active` (`track_id`, `is_active`),
  KEY `idx_et_mode` (`mode`),
  CONSTRAINT `fk_et_track` FOREIGN KEY (`track_id`)   REFERENCES `tracks` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_et_user`  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='قوالب الاختبارات (ما يظهر للطالب في صفحة الاختبارات)';

DROP TABLE IF EXISTS `exam_attempts`;
CREATE TABLE `exam_attempts` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `exam_template_id`  INT UNSIGNED NULL COMMENT 'NULL = اختبار حر/عشوائي',
  `track_id`          INT UNSIGNED NOT NULL,
  `mode`              ENUM('mock','category','random','practice','daily') NOT NULL DEFAULT 'mock',
  `title`             VARCHAR(190) NULL COMMENT 'عنوان يُحفظ وقت البدء',
  `category_ids`      TEXT NULL COMMENT 'JSON',
  `difficulty`        ENUM('any','easy','medium','hard') NOT NULL DEFAULT 'any',
  `total_questions`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `duration_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `randomize_options` TINYINT(1) NOT NULL DEFAULT 0,
  `status`            ENUM('in_progress','completed','expired','abandoned') NOT NULL DEFAULT 'in_progress',
  `started_at`        DATETIME NOT NULL,
  `expires_at`        DATETIME NULL COMMENT 'انتهاء مؤقت الاختبار',
  `finished_at`       DATETIME NULL,
  `time_spent_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `correct_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `wrong_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `unanswered_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `score`             DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'النسبة المئوية',
  `passed`            TINYINT(1) NOT NULL DEFAULT 0,
  `ip`                VARCHAR(45) NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ea_user_status` (`user_id`, `status`),
  KEY `idx_ea_user_finished` (`user_id`, `finished_at`),
  KEY `idx_ea_template` (`exam_template_id`),
  KEY `idx_ea_track` (`track_id`, `status`),
  CONSTRAINT `fk_ea_user`     FOREIGN KEY (`user_id`)          REFERENCES `users` (`id`)           ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_ea_template` FOREIGN KEY (`exam_template_id`) REFERENCES `exam_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ea_track`    FOREIGN KEY (`track_id`)         REFERENCES `tracks` (`id`)          ON DELETE CASCADE  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='محاولات الاختبار (جلسات الطالب)';

DROP TABLE IF EXISTS `exam_attempt_questions`;
CREATE TABLE `exam_attempt_questions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`     INT UNSIGNED NOT NULL,
  `question_id`    INT UNSIGNED NOT NULL,
  `question_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `option_order`   VARCHAR(10) NULL COMMENT 'JSON: ترتيب الاختيارات المعروض مثل ["c","a","d","b"]',
  `selected_answer` ENUM('a','b','c','d') NULL,
  `is_correct`     TINYINT(1) NULL COMMENT 'NULL = لم تُجب',
  `is_flagged`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'علامة "راجع السؤال"',
  `answered_at`    DATETIME NULL,
  `time_spent_seconds` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eaq_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_eaq_attempt_order` (`attempt_id`, `question_order`),
  KEY `idx_eaq_question` (`question_id`),
  CONSTRAINT `fk_eaq_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_eaq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أسئلة كل محاولة مع إجابة الطالب ونتيجة التصحيح';

-- =====================================================================
--  القسم 6: الاشتراكات والمدفوعات
-- =====================================================================

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(50) NOT NULL,
  `name_ar`        VARCHAR(190) NOT NULL,
  `description`    TEXT NULL,
  `price_sar`      DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `duration_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  `track_ids`      TEXT NULL COMMENT 'JSON: المسارات الممنوحة - فارغ = كل المسارات',
  `features`       TEXT NULL COMMENT 'JSON: قائمة المزايا لعرضها في صفحة الأسعار',
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured`    TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`     SMALLINT NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_code` (`code`),
  KEY `idx_plans_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='باقات الاشتراك - القيمة الأساسية 100 ريال سعودي قابلة للتعديل';

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `plan_id`          INT UNSIGNED NOT NULL,
  `status`           ENUM('pending','approved','active','expired','rejected','cancelled') NOT NULL DEFAULT 'pending',
  -- Pending (بانتظار الدفع) → Approved (تم التحقق من الدفع) → Active (مفعّل) → Expired (منتهي)
  `amount`           DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `currency`         CHAR(3) NOT NULL DEFAULT 'SAR',
  `duration_days`    SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  `started_at`       DATETIME NULL,
  `expires_at`       DATETIME NULL,
  `approved_at`      DATETIME NULL,
  `approved_by`      INT UNSIGNED NULL,
  `extended_days`    SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'أيام تمديد إضافية من الإدارة',
  `rejection_reason` VARCHAR(255) NULL,
  `cancelled_at`     DATETIME NULL,
  `notes`            TEXT NULL,
  `reminder_sent_at` DATETIME NULL COMMENT 'تاريخ إرسال تنبيه قرب الانتهاء',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_user_status` (`user_id`, `status`),
  KEY `idx_sub_status_expires` (`status`, `expires_at`),
  KEY `idx_sub_plan` (`plan_id`),
  CONSTRAINT `fk_sub_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)               ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_sub_plan`     FOREIGN KEY (`plan_id`)     REFERENCES `subscription_plans` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sub_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)               ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='اشتراكات المستخدمين وحالاتها';

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_id`  INT UNSIGNED NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `method`           ENUM('bank_transfer','stc_pay','mada','apple_pay','moyasar','myfatoorah','cash','admin_manual','free') NOT NULL DEFAULT 'bank_transfer',
  `gateway`          ENUM('manual','moyasar','myfatoorah','tap','none') NOT NULL DEFAULT 'manual',
  `amount`           DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `currency`         CHAR(3) NOT NULL DEFAULT 'SAR',
  `reference_number` VARCHAR(120) NULL COMMENT 'رقم العملية/الإيداع الذي يزوده الطالب',
  `payer_note`       VARCHAR(255) NULL,
  `receipt_path`     VARCHAR(255) NULL COMMENT 'صورة إثبات التحويل',
  `gateway_txn_id`   VARCHAR(190) NULL COMMENT 'معرّف العملية في بوابة الدفع',
  `gateway_payload`  TEXT NULL COMMENT 'JSON: رد البوابة الكامل للمراجعة',
  `gateway_status`   VARCHAR(50) NULL,
  `status`           ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending',
  `reviewed_by`      INT UNSIGNED NULL,
  `reviewed_at`      DATETIME NULL,
  `admin_note`       VARCHAR(255) NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_txn` (`gateway`, `gateway_txn_id`),
  KEY `idx_pay_status` (`status`, `created_at`),
  KEY `idx_pay_user` (`user_id`),
  KEY `idx_pay_sub` (`subscription_id`),
  CONSTRAINT `fk_pay_sub`      FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_user`     FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`)         ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_reviewer` FOREIGN KEY (`reviewed_by`)     REFERENCES `users` (`id`)         ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='المدفوعات: يدوية (تحويل/إثبات) أو عبر بوابات سعودية (مدى، Apple Pay، STC Pay، Moyasar، MyFatoorah)';

-- =====================================================================
--  القسم 7: تلجرام والإشعارات والدعم
-- =====================================================================

DROP TABLE IF EXISTS `telegram_links`;
CREATE TABLE `telegram_links` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_user_id`   BIGINT NOT NULL COMMENT 'معرّف المستخدم في تلجرام',
  `user_id`            INT UNSIGNED NULL COMMENT 'حساب المنصة المرتبط',
  `telegram_username`  VARCHAR(190) NULL,
  `telegram_first_name` VARCHAR(190) NULL,
  `telegram_chat_id`   BIGINT NULL,
  `link_code`          VARCHAR(12) NULL COMMENT 'رمز الربط المؤقت الذي يولده الطالب من حسابه',
  `link_code_expires_at` DATETIME NULL,
  `linked_at`          DATETIME NULL,
  `is_blocked`         TINYINT(1) NOT NULL DEFAULT 0,
  `last_interaction_at` DATETIME NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tg_user` (`telegram_user_id`),
  KEY `idx_tg_account` (`user_id`),
  KEY `idx_tg_code` (`link_code`),
  CONSTRAINT `fk_tg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ربط telegram_user_id بحساب المستخدم داخل المنصة';

-- أكواد الربط (تُخزَّن مشفّرة بـ SHA-256 ولا تظهر إلا مرة واحدة للمستخدم)
DROP TABLE IF EXISTS `telegram_link_codes`;
CREATE TABLE `telegram_link_codes` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `code_hash`    CHAR(64) NOT NULL COMMENT 'SHA-256 للرمز بصيغة ABCD-EFGH',
  `code_hint`    VARCHAR(12) NULL COMMENT 'آخر 4 أحرف لعرضها للطالب (لا يكفي للربط)',
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `used_at`      DATETIME NULL,
  `expires_at`   DATETIME NOT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tlc_hash` (`code_hash`),
  KEY `idx_tlc_user` (`user_id`, `expires_at`),
  CONSTRAINT `fk_tlc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أكواد ربط حساب تلجرام - رمز قصير العمر يُستهلك مرة واحدة';

DROP TABLE IF EXISTS `telegram_states`;
CREATE TABLE `telegram_states` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_user_id` BIGINT NOT NULL,
  `state`            VARCHAR(50) NOT NULL DEFAULT 'idle' COMMENT 'idle | await_name | await_phone | ...',
  `payload`          TEXT NULL COMMENT 'JSON: بيانات مؤقتة أثناء المحادثة',
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tgs_user` (`telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حالة المحادثة في بوت تلجرام (Wizard)';

DROP TABLE IF EXISTS `telegram_messages`;
CREATE TABLE `telegram_messages` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_user_id` BIGINT NULL,
  `user_id`          INT UNSIGNED NULL,
  `direction`        ENUM('in','out') NOT NULL,
  `message_type`     VARCHAR(30) NOT NULL DEFAULT 'text',
  `content`          TEXT NULL,
  `meta`             VARCHAR(500) NULL COMMENT 'JSON',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tgm_user_time` (`telegram_user_id`, `created_at`),
  KEY `idx_tgm_direction` (`direction`, `created_at`),
  CONSTRAINT `fk_tgm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل رسائل البوت (تدقيق + دعم فني)';

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`            INT UNSIGNED NULL,
  `telegram_user_id`   BIGINT NULL,
  `name`               VARCHAR(190) NULL,
  `contact`            VARCHAR(190) NULL COMMENT 'جوال أو بريد للتواصل',
  `channel`            ENUM('telegram','site','email','whatsapp') NOT NULL DEFAULT 'site',
  `subject`            VARCHAR(190) NOT NULL,
  `message`            TEXT NOT NULL,
  `attachment_path`    VARCHAR(255) NULL,
  `status`             ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  `priority`           ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  `admin_reply`        TEXT NULL,
  `replied_by`         INT UNSIGNED NULL,
  `replied_at`         DATETIME NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_st_status` (`status`, `created_at`),
  KEY `idx_st_user` (`user_id`),
  CONSTRAINT `fk_st_user`     FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_st_replier`  FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تذاكر الدعم الفني (من الموقع أو من بوت تلجرام)';

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(50) NOT NULL DEFAULT 'system' COMMENT 'subscription | exam | system | support',
  `title`      VARCHAR(190) NOT NULL,
  `body`       TEXT NULL,
  `link`       VARCHAR(255) NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`    DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='إشعارات المستخدم داخل المنصة';

-- =====================================================================
--  القسم 8: إحصائيات المستخدم حسب المجال (تحليل نقاط القوة/الضعف)
-- =====================================================================

DROP TABLE IF EXISTS `user_category_stats`;
CREATE TABLE `user_category_stats` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `track_id`         INT UNSIGNED NOT NULL,
  `category_id`      INT UNSIGNED NOT NULL,
  `total_answered`   INT UNSIGNED NOT NULL DEFAULT 0,
  `correct_answers`  INT UNSIGNED NOT NULL DEFAULT 0,
  `wrong_answers`    INT UNSIGNED NOT NULL DEFAULT 0,
  `accuracy`         DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'النسبة المئوية',
  `last_attempt_at`  DATETIME NULL,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ucs_user_category` (`user_id`, `category_id`),
  KEY `idx_ucs_user_accuracy` (`user_id`, `accuracy`),
  CONSTRAINT `fk_ucs_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ucs_track`    FOREIGN KEY (`track_id`)    REFERENCES `tracks` (`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ucs_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ملخص أداء الطالب في كل مجال - يُحدَّث بعد كل اختبار (نقاط القوة/الضعف)';

-- =====================================================================
--  القسم 9: شهادات المتدربين (جدار الآراء)
-- =====================================================================

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL COMMENT 'صاحب الشهادة إن كان مسجلاً في المنصة',
  `name`        VARCHAR(190) NOT NULL COMMENT 'الاسم كما يُعرض علناً',
  `role`        VARCHAR(190) NULL COMMENT 'الصفة: معلم علوم / مرشح للرخصة ...',
  `city`        VARCHAR(100) NULL,
  `body`        TEXT NOT NULL COMMENT 'نص الشهادة',
  `rating`      TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'من 1 إلى 5',
  `consent`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = موافقة صريحة من صاحب الشهادة على النشر',
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tst_published` (`is_published`, `consent`, `sort_order`),
  CONSTRAINT `fk_tst_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='شهادات المتدربين - لا يُنشر أي نص بدون موافقة صريحة (consent = 1)';

-- لا تُضاف شهادات وهمية: الصفوف التالية أمثلة معطّلة (is_published = 0)
-- يستبدلها المالك بشهادات حقيقية موثّقة الموافقة من لوحة الإدارة → شهادات المتدربين.
INSERT INTO `testimonials` (`name`, `role`, `city`, `body`, `rating`, `consent`, `is_published`, `sort_order`) VALUES
('نموذج شهادة (استبدله)', 'معلم', 'الرياض', 'استبدل هذا النص بشهادة حقيقية من متدرب وافق على نشرها. لا يُعرض أي نص على الموقع قبل تفعيل خياري الموافقة والنشر.', 5, 0, 0, 1);

-- =====================================================================
--  القسم 10: عروض (Views) لتقارير سريعة
-- =====================================================================

DROP VIEW IF EXISTS `vw_active_subscriptions`;
CREATE VIEW `vw_active_subscriptions` AS
SELECT
  s.`id`,
  s.`user_id`,
  u.`full_name`,
  u.`email`,
  u.`phone`,
  p.`name_ar`  AS plan_name,
  s.`status`,
  s.`amount`,
  s.`started_at`,
  s.`expires_at`,
  DATEDIFF(s.`expires_at`, NOW()) AS days_remaining
FROM `subscriptions` s
JOIN `users` u             ON u.`id` = s.`user_id`
JOIN `subscription_plans` p ON p.`id` = s.`plan_id`
WHERE s.`status` IN ('approved','active')
  AND (s.`expires_at` IS NULL OR s.`expires_at` >= NOW());

DROP VIEW IF EXISTS `vw_question_stats`;
CREATE VIEW `vw_question_stats` AS
SELECT
  q.`id`,
  q.`track_id`,
  q.`category_id`,
  c.`name_ar` AS category_name,
  LEFT(q.`question_text`, 120) AS question_preview,
  q.`difficulty`,
  q.`active`,
  q.`needs_review`,
  q.`times_answered`,
  q.`times_correct`,
  CASE WHEN q.`times_answered` = 0 THEN NULL
       ELSE ROUND((q.`times_correct` / q.`times_answered`) * 100, 2) END AS accuracy
FROM `questions` q
LEFT JOIN `categories` c ON c.`id` = q.`category_id`;

DROP VIEW IF EXISTS `vw_category_performance`;
CREATE VIEW `vw_category_performance` AS
SELECT
  c.`id`   AS category_id,
  c.`name_ar`,
  c.`track_id`,
  COUNT(eaq.`id`) AS total_answers,
  SUM(CASE WHEN eaq.`is_correct` = 1 THEN 1 ELSE 0 END) AS correct_answers,
  CASE WHEN COUNT(eaq.`id`) = 0 THEN 0
       ELSE ROUND((SUM(CASE WHEN eaq.`is_correct` = 1 THEN 1 ELSE 0 END) / COUNT(eaq.`id`)) * 100, 2) END AS accuracy
FROM `categories` c
LEFT JOIN `questions` q                ON q.`category_id` = c.`id`
LEFT JOIN `exam_attempt_questions` eaq ON eaq.`question_id` = q.`id`
GROUP BY c.`id`, c.`name_ar`, c.`track_id`;

-- =====================================================================
--  القسم 10: البيانات الأساسية (Seed Data)
-- =====================================================================

-- 10.1 الإعدادات ------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `label_ar`, `is_public`) VALUES
('site_name',              'الرخصة المهنية | منصة التدريب', 'general', 'string', 'اسم المنصة', 1),
('site_tagline',           'استعد لاختبار الرخصة المهنية للمعلمين بثقة', 'general', 'string', 'الشعار النصي', 1),
('site_description',       'منصة سعودية للتدريب على اختبار الرخصة المهنية للمعلمين: الاختبار التخصصي (حاسب آلي) والاختبار التربوي العام، مع اختبارات تجريبية وتحليل مستوى.', 'general', 'text', 'وصف المنصة', 1),
('contact_email',          'support@example.com', 'general', 'string', 'البريد للدعم', 1),
('contact_phone',          '966500000000', 'general', 'string', 'جوال الدعم (واتساب)', 1),
('default_currency',       'SAR', 'general', 'string', 'العملة', 1),
('subscription_price',     '100', 'subscription', 'int', 'قيمة الاشتراك الأساسية (ريال)', 1),
('subscription_days',      '365', 'subscription', 'int', 'مدة الاشتراك بالأيام', 1),
('payment_instructions',   'حوّل مبلغ الاشتراك إلى الحساب البنكي التالي ثم ارفع صورة الإيصال ورقم العملية:\n\nالبنك: البنك الأهلي السعودي\nرقم الحساب / الآيبان: SA00 0000 0000 0000 0000 0000\nاسم المستفيد: منصة الرخصة المهنية\n\nملاحظة: يتم تفعيل الحساب بعد التحقق من الدفع (عادة أقل من 24 ساعة).', 'subscription', 'text', 'تعليمات الدفع اليدوي', 1),
('bank_name',              'البنك الأهلي السعودي', 'subscription', 'string', 'اسم البنك', 1),
('bank_iban',              'SA000000000000000000000', 'subscription', 'string', 'الآيبان', 1),
('bank_account_name',      'منصة الرخصة المهنية', 'subscription', 'string', 'اسم المستفيد', 1),
('stc_pay_number',         '', 'subscription', 'string', 'رقم STC Pay', 1),
('free_trial_enabled',     '0', 'subscription', 'bool', 'تفعيل تجربة مجانية', 0),
('allow_guest_exam',       '0', 'subscription', 'bool', 'السماح باختبار تجريبي مجاني للزوار', 0),
('telegram_bot_username',  '', 'telegram', 'string', 'معرّف البوت @', 1),
('telegram_admin_chat_id', '', 'telegram', 'string', 'Chat ID لإشعارات الإدارة', 0),
('telegram_notify_expiry_days', '7', 'telegram', 'int', 'التنبيه قبل انتهاء الاشتراك (أيام)', 0),
('moyasar_enabled',        '0', 'payment', 'bool', 'تفعيل Moyasar', 0),
('myfatoorah_enabled',     '0', 'payment', 'bool', 'تفعيل MyFatoorah', 0),
('manual_payment_enabled', '1', 'payment', 'bool', 'تفعيل الدفع اليدوي (تحويل بنكي)', 1),
('exam_default_pass',      '60', 'exam', 'int', 'نسبة النجاح الافتراضية %', 0),
('strength_threshold',     '80', 'exam', 'int', 'حد نقطة القوة %', 1),
('weakness_threshold',     '60', 'exam', 'int', 'حد نقطة الضعف %', 1),
('maintenance_mode',       '0', 'general', 'bool', 'وضع الصيانة', 0),
('demo_data_installed',    '1', 'general', 'bool', 'تم إدخال بيانات تجريبية', 0);

-- 10.2 المسارات ------------------------------------------------
INSERT INTO `tracks` (`id`, `code`, `name_ar`, `name_en`, `description`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(1, 'specialist',  'الاختبار التخصصي', 'Specialist Test',  'الاختبار التخصصي للرخصة المهنية - تخصص الحاسب الآلي وتقنية المعلومات.', 'bi-cpu',           '#0d6efd', 1, 1),
(2, 'educational', 'الاختبار التربوي العام', 'General Educational Test', 'الاختبار التربوي العام للمعلمين: المناهج، القياس، علم النفس التربوي، الإدارة الصفية.', 'bi-mortarboard',  '#006C35', 2, 1);

-- 10.3 المجالات والمحاور --------------------------------------
-- تنبيه: هذه تصنيفات مرنة للمنصة وليست تصنيفاً رسمياً معلناً من هيئة تقويم التعليم والتدريب.
--         يمكن تعديلها أو إضافة غيرها من لوحة التحكم → التصنيفات.
INSERT INTO `categories` (`id`, `track_id`, `parent_id`, `code`, `name_ar`, `name_en`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(1,  1, NULL, 'computer-science',    'علوم الحاسب',            'Computer Science',      'bi-cpu',            '#0d6efd', 1,  1),
(2,  1, NULL, 'programming',         'البرمجة',                'Programming',           'bi-code-slash',     '#6610f2', 2,  1),
(3,  1, 2,    'python',              'Python',                 'Python',                'bi-filetype-py',    '#3776ab', 1,  1),
(4,  1, 2,    'cpp',                 'C++',                    'C++',                   'bi-filetype-cpp',   '#00599c', 2,  1),
(5,  1, 2,    'java',                'Java',                  'Java',                  'bi-cup-hot',        '#b07219', 3,  1),
(6,  1, NULL, 'data-structures',     'هياكل البيانات',          'Data Structures',       'bi-diagram-3',      '#20c997', 3,  1),
(7,  1, NULL, 'algorithms',          'الخوارزميات',            'Algorithms',            'bi-signpost-split', '#fd7e14', 4,  1),
(8,  1, NULL, 'databases',           'قواعد البيانات',          'Databases',             'bi-database',       '#198754', 5,  1),
(9,  1, 8,    'sql',                 'SQL',                   'SQL',                   'bi-table',          '#0dcaf0', 1,  1),
(10, 1, NULL, 'networks',            'شبكات الحاسب',           'Computer Networks',     'bi-router',         '#0dcaf0', 6,  1),
(11, 1, NULL, 'information-security','أمن المعلومات',           'Information Security',  'bi-shield-lock',    '#dc3545', 7,  1),
(12, 1, 11,   'cybersecurity',       'الأمن السيبراني',         'Cybersecurity',         'bi-shield-check',   '#b02a37', 1,  1),
(13, 1, NULL, 'operating-systems',   'أنظمة التشغيل',           'Operating Systems',     'bi-hdd-stack',      '#6c757d', 8,  1),
(14, 1, NULL, 'software-engineering','هندسة البرمجيات',         'Software Engineering',  'bi-kanban',         '#7952b3', 9,  1),
(15, 1, NULL, 'artificial-intelligence', 'الذكاء الاصطناعي',    'Artificial Intelligence','bi-robot',         '#d63384', 10, 1),
(16, 1, 15,   'machine-learning',    'تعلم الآلة',             'Machine Learning',      'bi-graph-up-arrow', '#c2185b', 1,  1),
(17, 1, NULL, 'cloud-computing',     'الحوسبة السحابية',        'Cloud Computing',       'bi-cloud',          '#0aa2c0', 11, 1),
(18, 1, NULL, 'systems-analysis',    'تحليل وتصميم الأنظمة',    'Systems Analysis',      'bi-bounding-box',   '#6f42c1', 12, 1),
(19, 1, NULL, 'information-technology','تقنية المعلومات',        'Information Technology','bi-pc-display',     '#0d6efd', 13, 1),
(20, 1, NULL, 'computer-applications','تطبيقات الحاسب',          'Computer Applications', 'bi-app-indicator', '#adb5bd', 14, 1),
(21, 1, NULL, 'digital-skills',      'المهارات الرقمية',        'Digital Skills',        'bi-lightbulb',      '#ffc107', 15, 1),
-- الاختبار التربوي العام (محاور عامة قابلة للتعديل)
(22, 2, NULL, 'curriculum-methods',  'المناهج وطرق التدريس',    'Curriculum & Methods',  'bi-journal-bookmark','#006C35', 1,  1),
(23, 2, NULL, 'assessment',          'القياس والتقويم',         'Assessment',            'bi-clipboard-check','#0d6efd', 2,  1),
(24, 2, NULL, 'educational-psychology','علم النفس التربوي',      'Educational Psychology','bi-people',         '#6610f2', 3,  1),
(25, 2, NULL, 'school-leadership',   'الإدارة المدرسية والقيادة التربوية', 'School Leadership', 'bi-building', '#198754', 4, 1),
(26, 2, NULL, 'professional-ethics','أخلاقيات المهنة',         'Professional Ethics',   'bi-award',          '#7952b3', 5,  1),
(27, 2, NULL, 'instructional-technology','تكنولوجيا التعليم',  'Instructional Technology','bi-laptop',       '#0dcaf0', 6,  1),
(28, 2, NULL, 'individual-differences','الفروق الفردية وصعوبات التعلم','Individual Differences','bi-person-lines-fill','#fd7e14', 7, 1),
(29, 2, NULL, 'classroom-management','الإدارة الصفية',         'Classroom Management',  'bi-easel',          '#dc3545', 8,  1),
(30, 2, NULL, 'educational-research','البحث التربوي وتطوير الأداء المهني','Educational Research','bi-search',   '#20c997', 9, 1);

-- 10.4 حساب المدير وحساب طالب تجريبي --------------------------
-- كلمة مرور حساب المدير: Admin@12345    (غيّرها فوراً بعد التثبيت)
-- كلمة مرور الطالب التجريبي: Student@12345
INSERT INTO `users` (`id`, `full_name`, `phone`, `email`, `password_hash`, `role`, `status`, `city`, `specialty`, `target_track_id`, `email_verified_at`, `created_at`) VALUES
(1, 'مدير المنصة', '966500000001', 'admin@example.com', '$2y$10$vz60Eegv/uP27Ap5ZfNvsu7W.CoTyz8nycmWmEDMZUHms79ZMuzMe', 'admin', 'active', 'الرياض', NULL, 1, NOW(), NOW()),
(2, 'طالب تجريبي', '966500000002', 'student@example.com', '$2y$10$a/yZnwezca/NJQG/eKhgSe83RIMG7QL7uRHHy4lELRTfZ9oyOGmC.', 'student', 'active', 'جدة', 'معلم حاسب آلي', 1, NOW(), NOW());
-- ملاحظة: يتم توليد الهاشات الصحيحة تلقائياً بواسطة install.php أو tools/make_admin.php
--         القيمتان أعلاه مجرد بديل؛ استخدم المعالج لإنشاء مدير بكلمة مرور خاصة بك.

-- 10.6 باقات الاشتراك -----------------------------------------
INSERT INTO `subscription_plans` (`id`, `code`, `name_ar`, `description`, `price_sar`, `duration_days`, `track_ids`, `features`, `is_active`, `is_featured`, `sort_order`) VALUES
(1, 'full-license-100', 'باقة الرخصة المهنية – كامل المنصة', 'وصول كامل لكل المسارات: الاختبار التخصصي (حاسب آلي) والاختبار التربوي العام، اختبارات تجريبية غير محدودة، تحليل مستوى تفصيلي.', 100.00, 365, NULL,
 '["وصول كامل لبنك أسئلة الاختبار التخصصي (حاسب آلي)","وصول كامل لبنك أسئلة الاختبار التربوي العام","اختبارات تجريبية Mock Exams غير محدودة","تدريبات حسب المجال والمحور","أسئلة عشوائية + مراجعة الإجابات والشرح","تحليل المستوى ونقاط القوة والضعف","تحديثات مستمرة للأسئلة","دعم فني عبر تلجرام"]', 1, 1, 1),
(2, 'specialist-computer', 'التخصصي – حاسب آلي فقط', 'الوصول لمسار الاختبار التخصصي (حاسب آلي) فقط لمدة سنة.', 100.00, 365, '[1]',
 '["بنك أسئلة الحاسب الآلي","اختبارات تجريبية للمسار التخصصي","تحليل مستوى حسب المجال"]', 1, 0, 2),
(3, 'educational-general', 'التربوي العام فقط', 'الوصول لمسار الاختبار التربوي العام فقط لمدة سنة.', 100.00, 365, '[2]',
 '["بنك أسئلة الاختبار التربوي العام","اختبارات تجريبية للمسار التربوي","تحليل مستوى حسب المحاور"]', 1, 0, 3);

-- 10.5 المصادر ------------------------------------------------
INSERT INTO `sources` (`id`, `name`, `type`, `author`, `year`, `license_note`, `notes`, `is_active`, `created_by`) VALUES
(1, 'بيانات تجريبية للاختبار (Sample Data)', 'other', 'المنصة', NULL, 'بيانات اختبار وظيفية تم إعدادها لأغراض تجريبية فقط', 'هذه الأسئلة لإثبات عمل النظام ويجب استبدالها ببنك أسئلتك الرسمي من لوحة التحكم أو عبر الاستيراد.', 1, 1),
(2, 'ملف PDF لم يُستورد بعد', 'pdf', NULL, NULL, 'مصدر يملك صاحب المنصة حق استخدامه', 'استخدم صفحة استيراد الأسئلة لإضافة ملفاتك.', 1, 1);

-- 10.7 اشتراك الطالب التجريبي (مفعّل) -------------------------
INSERT INTO `subscriptions` (`id`, `user_id`, `plan_id`, `status`, `amount`, `currency`, `duration_days`, `started_at`, `expires_at`, `approved_at`, `approved_by`, `notes`) VALUES
(1, 2, 1, 'active', 100.00, 'SAR', 365, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), NOW(), 1, 'اشتراك تجريبي مُفعّل تلقائياً مع البيانات التجريبية');

INSERT INTO `payments` (`id`, `subscription_id`, `user_id`, `method`, `gateway`, `amount`, `currency`, `reference_number`, `status`, `reviewed_by`, `reviewed_at`, `admin_note`) VALUES
(1, 1, 2, 'bank_transfer', 'manual', 100.00, 'SAR', 'DEMO-0001', 'approved', 1, NOW(), 'دفعة تجريبية للتوضيح');

-- 10.8 قوالب الاختبارات ---------------------------------------
INSERT INTO `exam_templates` (`id`, `title`, `description`, `track_id`, `mode`, `category_ids`, `difficulty`, `question_count`, `duration_minutes`, `pass_percentage`, `randomize_questions`, `randomize_options`, `show_explanation`, `require_subscription`, `is_active`, `sort_order`, `created_by`) VALUES
(1, 'اختبار تجريبي شامل – حاسب آلي', 'محاكاة كاملة لاختبار التخصصي: أسئلة من جميع المجالات بترتيب عشوائي.', 1, 'mock', NULL, 'any', 50, 60, 60, 1, 0, 1, 1, 1, 1, 1),
(2, 'اختبار تجريبي – قواعد البيانات', 'تدريب مركّز على قواعد البيانات و SQL.', 1, 'category', '[8]', 'any', 20, 25, 60, 1, 0, 1, 1, 1, 2, 1),
(3, 'اختبار تجريبي – الشبكات والأمن', 'تدريب على شبكات الحاسب وأمن المعلومات.', 1, 'category', '[10,11]', 'any', 20, 25, 60, 1, 0, 1, 1, 1, 3, 1),
(4, 'اختبار تجريبي – البرمجة وهياكل البيانات', 'تدريب على البرمجة وهياكل البيانات والخوارزميات.', 1, 'category', '[2,6,7]', 'any', 25, 30, 60, 1, 0, 1, 1, 1, 4, 1),
(5, 'اختبار تربوي عام – تجريبي', 'محاكاة لاختبار التربوي العام.', 2, 'mock', NULL, 'any', 30, 40, 60, 1, 0, 1, 1, 1, 5, 1),
(6, 'اختبار سريع عشوائي (10 أسئلة)', 'اختبار سريع من كل التصنيفات في المسار التخصصي.', 1, 'random', NULL, 'any', 10, 12, 60, 1, 1, 1, 1, 1, 6, 1),
(7, 'تدريب حر – تجربة قبل الاشتراك', 'نموذج مجاني محدود لتجربة المنصة قبل الاشتراك.', 1, 'practice', NULL, 'any', 5, 10, 60, 1, 0, 1, 0, 1, 7, 1);

-- =====================================================================
--  القسم 11: بيانات تجريبية (Sample Data)
--  ⚠️ هذه أسئلة عامة مُعدّة لأغراض اختبار النظام فقط، وليست منقولة من أي مصدر محمي.
--      استبدلها بأسئلتك الرسمية من صفحة: لوحة التحكم → بنك الأسئلة → استيراد.
-- =====================================================================

INSERT INTO `questions`
(`id`, `track_id`, `category_id`, `subcategory_id`, `source_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `difficulty`, `source_note`, `needs_review`, `content_hash`, `active`, `created_by`) VALUES
-- ---------- البرمجة (2) ----------
(1, 1, 2, NULL, 1, 'ما هو الناتج الصحيح لبناء الجملة الشرطية في معظم لغات البرمجة؟', 'تنفيذ كتلة برمجية عند تحقق شرط', 'تعريف متغير جديد', 'إيقاف البرنامج نهائياً', 'حذف الملفات المؤقتة', 'a', 'الجمل الشرطية تُستخدم لاتخاذ قرار: تنفيذ كود عند تحقق شرط معين.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-1', 256), 1, 1),
(2, 1, 2, NULL, 1, 'ما وظيفة الدالة (Function) في البرمجة؟', 'تجميع كود قابل لإعادة الاستخدام', 'زيادة حجم الملف التنفيذي', 'منع تنفيذ البرنامج', 'تشفير البيانات', 'a', 'الدالة تُجمّع منطقاً برمجياً قابلاً للاستدعاء المتكرر لتقليل التكرار.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-2', 256), 1, 1),
(3, 1, 2, 3, 1, 'في لغة Python، أي أنواع البيانات التالية غير قابل للتعديل (Immutable)؟', 'tuple', 'list', 'dict', 'set', 'a', 'الـ list والـ dict والـ set قابلة للتعديل، أما الـ tuple فغير قابلة للتعديل.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-3', 256), 1, 1),
(4, 1, 2, 3, 1, 'ما ناتج تنفيذ: len("مرحبا") في Python؟', '5', '4', '6', 'خطأ في التنفيذ', 'a', 'الدالة len تُرجع عدد المحارف، وكلمة "مرحبا" خمسة محارف.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-4', 256), 1, 1),
(5, 1, 2, 4, 1, 'في لغة C++، الكلمة المفتاحية المستخدمة لتعريف ثابت لا يمكن تغيير قيمته هي:', 'const', 'static', 'volatile', 'extern', 'a', 'أمر const يجعل القيمة ثابتة لا يمكن تعديلها بعد التعريف.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-5', 256), 1, 1),
(6, 1, 2, 5, 1, 'في لغة Java، ما نوع العلاقة التي تعني "يورث الصفات من صنف آخر"؟', 'extends', 'implements', 'instanceof', 'import', 'a', 'الكلمة extends تُستخدم للوراثة بين الأصناف (class)، أما implements فللتفاعل مع الواجهات (interface).', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-6', 256), 1, 1),
-- ---------- علوم الحاسب (1) ----------
(7, 1, 1, NULL, 1, 'النظام العددي الذي يعتمد على الرقمين 0 و 1 يسمى:', 'النظام الثنائي', 'النظام العشري', 'النظام الثماني', 'النظام السادس عشري', 'a', 'النظام الثنائي (Binary) يستخدم الرقمين 0 و 1 وهو الأساس في تمثيل البيانات داخل الحاسب.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-7', 256), 1, 1),
(8, 1, 1, NULL, 1, 'وحدة القياس التي تُمثل 1024 ميجابايت هي:', '1 جيجابايت', '1 تيرابايت', '1 كيلوبايت', '1 بت', 'a', '1 جيجابايت = 1024 ميجابايت في نظام القياس الثنائي.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-8', 256), 1, 1),
-- ---------- هياكل البيانات (6) ----------
(9, 1, 6, NULL, 1, 'أي بنية بيانات تعمل بمبدأ "آخر من يدخل أول من يخرج" (LIFO)؟', 'المكدس (Stack)', 'الطابور (Queue)', 'القائمة المتصلة (Linked List)', 'الشجرة (Tree)', 'a', 'المكدس Stack يعمل بمبدأ LIFO، بينما الطابور Queue يعمل بمبدأ FIFO.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-9', 256), 1, 1),
(10, 1, 6, NULL, 1, 'ما التعقيد الزمني للبحث الثنائي (Binary Search) في مصفوفة مرتبة؟', 'O(log n)', 'O(n)', 'O(n^2)', 'O(1)', 'a', 'البحث الثنائي يقسم مجال البحث إلى نصفين في كل خطوة، لذا تعقيده O(log n).', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-10', 256), 1, 1),
(11, 1, 6, NULL, 1, 'البنية التي يعتمد عليها المكدس في إدارة الاستدعاءات (Function Calls) هي:', 'المكدس (Call Stack)', 'الطابور الدائري', 'الجدول الهاش', 'الكومة (Heap)', 'a', 'يُستخدم Call Stack لتتبع الدوال المستدعاة وترتيب رجوعها.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-11', 256), 1, 1),
-- ---------- الخوارزميات (7) ----------
(12, 1, 7, NULL, 1, 'أي خوارزمية ترتيب تعتمد على مبدأ "فرّق تسد" (Divide and Conquer)؟', 'الترتيب السريع (Quick Sort)', 'الترتيب بالفقاعات (Bubble Sort)', 'الترتيب بالإدراج (Insertion Sort)', 'الترتيب بالاختيار (Selection Sort)', 'a', 'Quick Sort يقسم المصفوفة حول محور ثم يرتب كل جزء، وهو تطبيق لمبدأ فرّق تسد.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-12', 256), 1, 1),
(13, 1, 7, NULL, 1, 'الأسوأ حالة في تعقيد خوارزمية الترتيب السريع (Quick Sort) هي:', 'O(n^2)', 'O(n log n)', 'O(log n)', 'O(n)', 'a', 'تحدث أسوأ حالة عند اختيار محور سيئ في كل مرة فيصبح التعقيد O(n^2).', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-13', 256), 1, 1),
-- ---------- قواعد البيانات (8) و SQL (9) ----------
(14, 1, 8, NULL, 1, 'مفتاح الجدول الذي يميّز كل سجل بشكل فريد يسمى:', 'المفتاح الأساسي (Primary Key)', 'المفتاح الأجنبي (Foreign Key)', 'الفهرس (Index)', 'العلاقة (Relation)', 'a', 'المفتاح الأساسي يضمن تميّز كل سجل وعدم تكراره وعدم كونه فارغاً.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-14', 256), 1, 1),
(15, 1, 8, NULL, 1, 'الشكل الطبيعي (Normal Form) الذي يزيل التبعية الجزئية على جزء من المفتاح المركب هو:', 'الشكل الطبيعي الثاني (2NF)', 'الشكل الطبيعي الأول (1NF)', 'الشكل الطبيعي الثالث (3NF)', 'شكل Boyce-Codd', 'a', '2NF يشترط عدم وجود تبعية جزئية، أي أن كل حقل يعتمد على المفتاح الكامل.', 'hard', 'بيانات تجريبية', 0, SHA2('sample-q-15', 256), 1, 1),
(16, 1, 8, NULL, 1, 'أمر ACID الذي يضمن تنفيذ العملية بالكامل أو عدم تنفيذها إطلاقاً هو:', 'الذرية (Atomicity)', 'الاتساق (Consistency)', 'العزل (Isolation)', 'الدوام (Durability)', 'a', 'خاصية Atomicity تعني أن المعاملة (Transaction) وحدة واحدة: تنجح كلها أو تفشل كلها.', 'hard', 'بيانات تجريبية', 0, SHA2('sample-q-16', 256), 1, 1),
(17, 1, 8, 9, 1, 'أي أمر SQL يُستخدم لاسترجاع البيانات من الجدول؟', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'a', 'SELECT هو أمر الاستعلام (Data Retrieval)، والبقية أوامر تعديل بيانات.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-17', 256), 1, 1),
(18, 1, 8, 9, 1, 'في SQL، أي عبارة تُستخدم لتصفية نتائج الاستعلام بعد التجميع؟', 'HAVING', 'WHERE', 'ORDER BY', 'GROUP BY', 'a', 'WHERE تُصفّي الصفوف قبل التجميع، أما HAVING فتُصفّي المجموعات بعد GROUP BY.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-18', 256), 1, 1),
(19, 1, 8, 9, 1, 'نوع JOIN الذي يُرجع كل صفوف الجدول الأيسر حتى لو لم يكن هناك تطابق في الجدول الأيمن هو:', 'LEFT JOIN', 'INNER JOIN', 'CROSS JOIN', 'SELF JOIN', 'a', 'LEFT JOIN يُرجع كل صفوف الجدول الأول مع القيم المطابقة أو NULL من الجدول الثاني.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-19', 256), 1, 1),
(20, 1, 8, 9, 1, 'أي الأمرين يُستخدم لإضافة صف جديد إلى جدول قاعدة بيانات علائقية؟', 'INSERT INTO', 'ALTER TABLE', 'CREATE INDEX', 'GRANT', 'a', 'INSERT INTO تضيف صفوفاً جديدة، وALTER TABLE تعدّل بنية الجدول، وGRANT تمنح صلاحيات.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-20', 256), 1, 1),
-- ---------- الشبكات (10) ----------
(21, 1, 10, NULL, 1, 'البروتوكول المسؤول عن منح عناوين IP تلقائياً للأجهزة في الشبكة هو:', 'DHCP', 'DNS', 'FTP', 'SMTP', 'a', 'DHCP يوزّع عناوين IP والإعدادات المرتبطة بها تلقائياً، وDNS يحوّل الأسماء إلى عناوين.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-21', 256), 1, 1),
(22, 1, 10, NULL, 1, 'العنوان 192.168.1.10 يُصنّف بأنه عنوان:', 'خاص (Private IP)', 'عام (Public IP)', 'بث (Broadcast)', 'افتراضي (Loopback)', 'a', 'النطاق 192.168.0.0/16 من النطاقات الخاصة (RFC 1918) غير القابلة للتوجيه على الإنترنت.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-22', 256), 1, 1),
(23, 1, 10, NULL, 1, 'كم عدد الطبقات في نموذج OSI المرجعي؟', '7 طبقات', '5 طبقات', '4 طبقات', '3 طبقات', 'a', 'نموذج OSI يتكون من سبع طبقات: فيزيائية، ربط بيانات، شبكة، نقل، جلسة، عرض، تطبيقات.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-23', 256), 1, 1),
(24, 1, 10, NULL, 1, 'المنفذ (Port) الافتراضي لخدمة HTTPS هو:', '443', '80', '21', '25', 'a', 'HTTPS يعمل على المنفذ 443 افتراضياً، وHTTP على 80، وFTP على 21، وSMTP على 25.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-24', 256), 1, 1),
-- ---------- أمن المعلومات (11) ----------
(25, 1, 11, 12, 1, 'أي مما يلي يُعد من أفضل الممارسات لتأمين كلمات المرور في قواعد البيانات؟', 'تخزينها بعد التجزئة مع Salt قوي', 'تخزينها كنص صريح', 'تخزينها مشفرة بمفتاح ثابت في الكود', 'إرسالها بالبريد الإلكتروني', 'a', 'التجزئة (Hashing) مع Salt قوي وخوارزمية مثل bcrypt/Argon2 تجعل استرجاع كلمة المرور الأصلية عملياً مستحيلاً.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-25', 256), 1, 1),
(26, 1, 11, 12, 1, 'هجوم يقوم بحقن تعليمات SQL داخل مدخلات المستخدم يسمى:', 'SQL Injection', 'Cross Site Scripting', 'Denial of Service', 'Phishing', 'a', 'الوقاية من SQL Injection تكون باستخدام Prepared Statements وعدم دمج مدخلات المستخدم في نص الاستعلام.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-26', 256), 1, 1),
(27, 1, 11, 12, 1, 'التشفير الذي يستخدم زوجاً من المفاتيح (عام وخاص) يسمى:', 'التشفير غير المتماثل', 'التشفير المتماثل', 'التجزئة (Hashing)', 'التوقيع الرقمي فقط', 'a', 'التشفير غير المتماثل (Asymmetric) يستخدم مفتاحاً عاماً للتشفير ومفتاحاً خاصاً لفك التشفير.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-27', 256), 1, 1),
(28, 1, 11, 12, 1, 'المصادقة متعددة العوامل (MFA) تعتمد على:', 'عاملين أو أكثر من عوامل مختلفة', 'كلمة مرور أطول فقط', 'تغيير كلمة المرور دورياً', 'استخدام متصفح واحد', 'a', 'MFA تدمج ما تعرفه / ما تملكه / ما أنت عليه — أي عوامل من أنواع مختلفة.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-28', 256), 1, 1),
-- ---------- أنظمة التشغيل (13) ----------
(29, 1, 13, NULL, 1, 'الجزء الأساسي في نظام التشغيل الذي يدير العتاد والموارد يسمى:', 'النواة (Kernel)', 'الواجهة الرسومية', 'المترجم (Compiler)', 'المفسّر (Interpreter)', 'a', 'النواة هي قلب نظام التشغيل وتدير العمليات والذاكرة والأجهزة.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-29', 256), 1, 1),
(30, 1, 13, NULL, 1, 'الحالة التي ينتظر فيها البرنامج حدثاً خارجياً (مثل إدخال من المستخدم) تسمى:', 'الحالة المنتظرة (Waiting/Blocked)', 'الحالة الجاهزة (Ready)', 'الحالة المنفذة (Running)', 'حالة الانتهاء (Terminated)', 'a', 'في حالة الانتظار يتوقف البرنامج عن التنفيذ حتى يتحقق شرط أو حدث خارجي.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-30', 256), 1, 1),
(31, 1, 13, NULL, 1, 'الذاكرة الافتراضية (Virtual Memory) تسمح بـ:', 'تنفيذ برامج أكبر من حجم الذاكرة الفعلية', 'إلغاء الحاجة إلى الذاكرة العشوائية', 'تسريع المعالج الداخلي', 'حذف نظام الملفات', 'a', 'تعتمد على التبديل بين الذاكرة الرئيسية والقرص (Paging/Swapping).', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-31', 256), 1, 1),
-- ---------- هندسة البرمجيات (14) ----------
(32, 1, 14, NULL, 1, 'دورة حياة تطوير البرمجيات (SDLC) تبدأ عادة بمرحلة:', 'تحليل المتطلبات', 'كتابة الكود', 'الاختبار', 'الصيانة', 'a', 'التحليل يجمع المتطلبات ويحدد نطاق النظام قبل التصميم والتنفيذ.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-32', 256), 1, 1),
(33, 1, 14, NULL, 1, 'نموذج تطوير البرمجيات الذي يعتمد على دورات قصيرة متكررة وتسليم مستمر يسمى:', 'الرشيق (Agile)', 'الشلالي (Waterfall)', 'النموذج الشامل (V-Model)', 'نموذج الشلال العكسي', 'a', 'Agile يعتمد Sprint قصيرة وتسليم تدريجي مع تغذية راجعة مستمرة.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-33', 256), 1, 1),
(34, 1, 14, NULL, 1, 'اختبار الوحدة (Unit Testing) يهدف إلى:', 'اختبار أصغر وحدة برمجية بشكل مستقل', 'اختبار النظام كاملاً مع المستخدمين', 'قياس سرعة الشبكة', 'مراجعة تصميم الواجهات فقط', 'a', 'Unit Test يتحقق من صحة دالة/صنف بمعزل عن باقي النظام.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-34', 256), 1, 1),
-- ---------- الذكاء الاصطناعي (15) ----------
(35, 1, 15, NULL, 1, 'فرع الذكاء الاصطناعي الذي يتعلم من البيانات دون برمجة صريحة للقواعد هو:', 'تعلم الآلة (Machine Learning)', 'قواعد البيانات العلائقية', 'شبكات الحاسب', 'هندسة البرمجيات', 'a', 'تعلم الآلة يستنتج الأنماط من البيانات لبناء نموذج تنبؤي.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-35', 256), 1, 1),
(36, 1, 15, 16, 1, 'المشكلة التي تكون فيها البيانات مُصنّفة مسبقاً (Labels) تسمى:', 'التعلم المُوجّه (Supervised)', 'التعلم غير الموجّه', 'التعلم المعزز', 'التعلم العميق فقط', 'a', 'في التعلم الموجّه نُدرّب النموذج على بيانات تحمل التصنيف الصحيح.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-36', 256), 1, 1),
(37, 1, 15, 16, 1, 'المقياس المستخدم لتقييم نموذج تصنيف ثنائي يوازن بين الحساسية والدقة هو:', 'منحنى ROC / المساحة تحت المنحنى (AUC)', 'عدد الأسطر البرمجية', 'زمن التحميل', 'حجم قاعدة البيانات', 'a', 'AUC يقيس قدرة النموذج على التمييز بين الأصناف عند مختلف عتبات القرار.', 'hard', 'بيانات تجريبية', 0, SHA2('sample-q-37', 256), 1, 1),
-- ---------- الحوسبة السحابية (17) ----------
(38, 1, 17, NULL, 1, 'نموذج الخدمة السحابية الذي يوفّر أنظمة تشغيل جاهزة للاستخدام دون إدارة العتاد يسمى:', 'IaaS', 'SaaS', 'PaaS', 'DaaS', 'a', 'IaaS يوفّر بنية تحتية (خوادم/تخزين/شبكات) ويمكن تحميل أنظمة تشغيل عليها، بينما PaaS بيئة تطوير جاهزة وSaaS تطبيق جاهز.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-38', 256), 1, 1),
(39, 1, 17, NULL, 1, 'من مزايا الحوسبة السحابية:', 'المرونة والتوسع حسب الحاجة', 'ارتفاع تكلفة العتاد دائماً', 'الحاجة لشراء خوادم لكل مستخدم', 'عدم إمكانية الوصول عن بُعد', 'a', 'المرونة (Scalability) والدفع حسب الاستخدام من أهم مزايا الخدمات السحابية.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-39', 256), 1, 1),
-- ---------- تحليل وتصميم الأنظمة (18) ----------
(40, 1, 18, NULL, 1, 'المخطط الذي يوضّح تفاعل المستخدم مع النظام خطوة بخطوة يسمى:', 'مخطط حالات الاستخدام (Use Case Diagram)', 'مخطط قاعدة البيانات العلائقية', 'مخطط الشبكة الفيزيائية', 'مخطط الدوائر الكهربائية', 'a', 'Use Case يوضّح الفاعلين والوظائف وتفاعلهم مع النظام.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-40', 256), 1, 1),
(41, 1, 18, NULL, 1, 'المتطلب غير الوظيفي (Non-functional Requirement) من الأمثلة التالية:', 'أن يستجيب النظام خلال ثانيتين', 'تسجيل دخول المستخدم', 'إضافة سؤال جديد', 'طباعة التقرير', 'a', 'المتطلبات غير الوظيفية تصف جودة الأداء: السرعة، الأمان، التوفر، قابلية الاستخدام.', 'hard', 'بيانات تجريبية', 0, SHA2('sample-q-41', 256), 1, 1),
-- ---------- تقنية المعلومات وتطبيقات الحاسب والمهارات الرقمية ----------
(42, 1, 19, NULL, 1, 'الوحدة المسؤولة عن تنفيذ التعليمات ومعالجة البيانات في الحاسب هي:', 'وحدة المعالجة المركزية (CPU)', 'الذاكرة العشوائية (RAM)', 'القرص الصلب (HDD)', 'وحدة التغذية بالطاقة', 'a', 'CPU تنفذ التعليمات وتقوم بالعمليات الحسابية والمنطقية.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-42', 256), 1, 1),
(43, 1, 20, NULL, 1, 'الصيغة المناسبة لملف جدول بيانات قابل للفتح في Microsoft Excel هي:', 'xlsx', 'docx', 'pptx', 'mp4', 'a', 'xlsx امتداد مصنفات Excel، وdocx للنصوص، وpptx للعروض.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-43', 256), 1, 1),
(44, 1, 20, NULL, 1, 'الدالة في Excel التي تحسب مجموع نطاق من الخلايا هي:', 'SUM', 'COUNT', 'AVERAGE', 'MAX', 'a', 'SUM تجمع القيم، COUNT تعدّها، AVERAGE تحسب المتوسط، MAX تُرجع أكبر قيمة.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-44', 256), 1, 1),
(45, 1, 21, NULL, 1, 'الممارسة الآمنة عند التعامل مع البريد الإلكتروني المشبوه هي:', 'عدم فتح الروابط أو المرفقات والتحقق من المرسل', 'فتح المرفق لمعرفة محتواه', 'إدخال بيانات الدخول للتأكد', 'إعادة إرساله لكل الزملاء', 'a', 'التصيّد الإلكتروني (Phishing) يعتمد على استدراجك للنقر؛ تحقق من المرسل ولا تُدخل بياناتك.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-45', 256), 1, 1),
-- ---------- الاختبار التربوي العام (المسار 2) ----------
(46, 2, 22, NULL, 1, 'الهدف التعليمي الذي يصف قدرة الطالب على تحليل عناصر الموقف يقع في المستوى:', 'التحليل', 'التذكر', 'الفهم', 'التطبيق', 'a', 'في تصنيف بلوم: تذكر، فهم، تطبيق، تحليل، تقويم، إبداع — والتحليل أعلى من التطبيق.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-46', 256), 1, 1),
(47, 2, 23, NULL, 1, 'أداة القياس التي تقيس أداء الطالب أثناء العمل على مهمة حقيقية تُسمى:', 'التقويم الأدائي (Performance Assessment)', 'الاختبار الموضوعي', 'الاختبار المقالي', 'المقابلة الشخصية', 'a', 'التقويم الأدائي يعتمد على ملاحظة الأداء الفعلي وتقييمه وفق معايير (Rubrics).', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-47', 256), 1, 1),
(48, 2, 24, NULL, 1, 'نظرية التعلم التي تُفسّر التعلم بالمحاولة والخطأ عند ثورندايك تسمى:', 'الارتباطية', 'الاستبصار', 'السلوكية الاجتماعية', 'البنائية', 'a', 'قانون الأثر عند ثورندايك: السلوك الذي يتبعه إشباع يتقوى، والذي يتبعه ضيق يضعف.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-48', 256), 1, 1),
(49, 2, 29, NULL, 1, 'أفضل إجراء لإدارة الصف عند بدء السلوك المشتت بوقت قصير هو:', 'استخدام التنبيه غير اللفظي القريب', 'الاستهزاء بالطالب أمام زملائه', 'إخراج الطالب نهائياً من الصف', 'تجاهل السلوك دائماً', 'a', 'التدخل بأقل إجراء فعّال (مثل الاقتراب أو التواصل البصري) يحفظ النظام ويحافظ على كرامة الطالب.', 'easy', 'بيانات تجريبية', 0, SHA2('sample-q-49', 256), 1, 1),
(50, 2, 27, NULL, 1, 'التقنية التي تساعد في إتاحة المحتوى التعليمي ومناسبته لأصحاب الاحتياجات الخاصة:', 'التقنيات المساعدة (Assistive Technology)', 'الشبكات الاجتماعية', 'قواعد البيانات', 'تحليل البيانات الضخمة', 'a', 'التقنيات المساعدة مثل قارئ الشاشة وتكبير الخط تسهّل تعلم ذوي الاحتياجات الخاصة.', 'medium', 'بيانات تجريبية', 0, SHA2('sample-q-50', 256), 1, 1);

-- =====================================================================
--  القسم 12: تفعيل مفاتيح الحماية
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- للتحقق: SELECT COUNT(*) AS questions_count FROM questions;
-- راجع ملف INSTALL.md لخطوات التشغيل الكاملة.
