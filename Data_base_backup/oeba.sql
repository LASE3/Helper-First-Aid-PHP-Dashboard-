-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2026 at 01:15 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `oeba`
-- Final updated restore dump with profile image, questionnaire JSON, incident sync indexes, emergency contacts, and dashboard permissions.
--
DROP DATABASE IF EXISTS `oeba`;
CREATE DATABASE `oeba` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `oeba`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `user_emergency_contacts`;
DROP TABLE IF EXISTS `incident_images`;
DROP TABLE IF EXISTS `incidents`;
DROP TABLE IF EXISTS `guidance_steps`;
DROP TABLE IF EXISTS `devices`;
DROP TABLE IF EXISTS `content_versions`;
DROP TABLE IF EXISTS `content_meta`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `app_users`;
DROP TABLE IF EXISTS `admin_user_permissions`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `admin_password_resets`;
DROP TABLE IF EXISTS `admin_audit_logs`;
DROP TABLE IF EXISTS `permissions`;
SET FOREIGN_KEY_CHECKS = 1;


-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_logs`
--

CREATE TABLE `admin_audit_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_audit_logs`
--

INSERT INTO `admin_audit_logs` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(1, 1, 'create_step', 'step', 24, 'Admin added a new step', '2026-05-23 23:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `admin_password_resets`
--

CREATE TABLE `admin_password_resets` (
  `id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_password_resets`
--

INSERT INTO `admin_password_resets` (`id`, `admin_user_id`, `phone`, `otp_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 1, '0781558393', '$2y$10$Pc/8eFNW55w9myjodqyG2.IPUJ9E1PG3vIUNk86ALQrRg4OwzcRFm', '2026-05-23 13:38:04', '2026-05-23 13:32:58', '2026-05-23 13:28:04'),
(2, 1, '0781558393', '$2y$10$X/3Oih68rKquSqsL6Vbb0OROOa9mlodcCsPZLS7Ttlxq90CI8LWxu', '2026-05-23 13:42:58', '2026-05-23 13:33:00', '2026-05-23 13:32:58'),
(3, 1, '0781558393', '$2y$10$O9Wi13GIO4PS9EtwqS/ioemtLOEL6AH9XD2aL3LMs3.QNegzQPMOW', '2026-05-23 13:43:00', '2026-05-23 13:35:47', '2026-05-23 13:33:00'),
(4, 1, '0781558393', '$2y$10$ULjVFfTduXgZMg.Jbrf2OerCnGiYE8gC5vUoQ4Wo4ewHJbvFxfDk2', '2026-05-23 13:45:47', '2026-05-23 15:55:36', '2026-05-23 13:35:47'),
(5, 1, '0781558393', '$2y$10$wdexOLmtNEAwNwusPe6Nce3GX42yNy4eab6F6CN62ujRxFmR4QKVW', '2026-05-23 16:05:36', NULL, '2026-05-23 15:55:36');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','editor') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Ahmad Nijim', 'admin@gmail.com', '0781558393', '$2y$10$IV3TJujGJOZsehJC2MGc9eAH0I2wlsCkVB3QqTz8wIHa29hVELpAW', 'admin', 1, '2026-05-24 02:00:25', '2026-02-14 04:31:15', '2026-05-24 02:00:25');

-- --------------------------------------------------------

--
-- Table structure for table `admin_user_permissions`
--

CREATE TABLE `admin_user_permissions` (
  `admin_user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_user_permissions`
--

INSERT INTO `admin_user_permissions` (`admin_user_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 22),
(1, 23);

-- --------------------------------------------------------

--
-- Table structure for table `app_users`
--

CREATE TABLE `app_users` (
  `id` int(11) NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(20) DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `questionnaire_json` longtext DEFAULT NULL,
  `language` varchar(10) DEFAULT 'en',
  `country_code` varchar(10) DEFAULT '+962',
  `emergency_number` varchar(20) DEFAULT '911',
  `ambulance_number` varchar(20) DEFAULT '193',
  `fire_number` varchar(20) DEFAULT '199',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_users`
--

INSERT INTO `app_users` (`id`, `device_id`, `email`, `profile_image_path`, `full_name`, `age`, `sex`, `blood_type`, `allergies`, `conditions`, `medications`, `notes`, `questionnaire_json`, `language`, `country_code`, `emergency_number`, `ambulance_number`, `fire_number`, `created_at`, `updated_at`) VALUES
(1, '1779577379857668786', 'admin1@gmail.com', NULL, 'Ahmad Iyad Nijim', 22, 'Male', 'A+', 'None', 'None', 'None', 'none', NULL, 'en', '+962', '911', '193', '199', '2026-05-23 23:02:57', '2026-05-23 23:11:23');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `CODE` varchar(50) NOT NULL,
  `urgency_level` enum('low','medium','high','extreme') NOT NULL DEFAULT 'medium',
  `name_en` varchar(120) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `icon_key` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `CODE`, `urgency_level`, `name_en`, `name_ar`, `icon_key`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'adult_choking', 'high', 'Adult Choking', 'اختناق بالغ', 'lungs', 1, 10, '2026-02-14 02:31:21', '2026-05-20 14:05:11'),
(2, 'child_choking', 'high', 'Child Choking', 'اختناق طفل', 'baby', 1, 20, '2026-02-14 02:31:21', '2026-05-20 14:05:11'),
(3, 'asthma', 'medium', 'Asthma Attack', 'نوبة ربو', 'wind', 1, 30, '2026-02-14 02:31:21', '2026-05-20 14:05:11'),
(4, 'anaphylaxis', 'high', 'Severe Allergy', 'حساسية شديدة', 'alert', 1, 40, '2026-02-14 02:31:21', '2026-05-20 14:05:11'),
(5, 'unconscious_breathing', 'high', 'Unconscious Breathing', 'فاقد الوعي ويتنفس', 'person', 1, 50, '2026-02-14 02:31:21', '2026-05-20 14:05:11'),
(6, 'not_breathing_cpr', 'high', 'Not Breathing / CPR', 'لا يتنفس / إنعاش', 'heart-pulse', 1, 60, '2026-02-14 02:31:21', '2026-05-20 14:05:11'),
(7, 'bleeding', 'high', 'Heavy Bleeding', 'نزيف شديد', NULL, 1, 70, '2026-05-20 14:05:11', '2026-05-20 14:05:11'),
(8, 'burns', 'medium', 'Burn Injury', 'حروق', NULL, 1, 80, '2026-05-20 14:05:11', '2026-05-20 14:05:11'),
(9, 'fracture', 'medium', 'Fracture', 'كسر', NULL, 1, 90, '2026-05-20 14:05:11', '2026-05-20 14:05:11'),
(10, 'seizure', 'high', 'Seizure', 'تشنج', NULL, 1, 100, '2026-05-20 14:05:11', '2026-05-20 14:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `content_meta`
--

CREATE TABLE `content_meta` (
  `id` int(11) NOT NULL,
  `content_version` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_meta`
--

INSERT INTO `content_meta` (`id`, `content_version`, `updated_at`) VALUES
(1, 14, '2026-05-23 23:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `content_versions`
--

CREATE TABLE `content_versions` (
  `id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `change_summary` varchar(255) DEFAULT NULL,
  `published_by_admin_id` int(11) DEFAULT NULL,
  `published_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_versions`
--

INSERT INTO `content_versions` (`id`, `version_number`, `change_summary`, `published_by_admin_id`, `published_at`) VALUES
(1, 1, 'Initial publish', NULL, '2026-02-14 02:31:21'),
(2, 12, 'Manual content version update', 1, '2026-05-20 15:19:07'),
(3, 13, 'Admin deleted a category', 1, '2026-05-24 02:09:13'),
(4, 14, 'Admin added a new guidance step', 1, '2026-05-24 02:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_uuid` varchar(64) NOT NULL,
  `platform` enum('android','ios','windows','web','unknown') NOT NULL DEFAULT 'unknown',
  `app_version` varchar(20) DEFAULT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guidance_steps`
--

CREATE TABLE `guidance_steps` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `category_code` varchar(50) NOT NULL,
  `step_no` tinyint(3) UNSIGNED NOT NULL,
  `title_en` varchar(140) NOT NULL,
  `title_ar` varchar(140) NOT NULL,
  `body_en` text NOT NULL,
  `body_ar` text NOT NULL,
  `warning_en` varchar(240) DEFAULT NULL,
  `warning_ar` varchar(240) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_asset` varchar(255) DEFAULT '',
  `audio_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by_admin_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guidance_steps`
--

INSERT INTO `guidance_steps` (`id`, `category_id`, `category_code`, `step_no`, `title_en`, `title_ar`, `body_en`, `body_ar`, `warning_en`, `warning_ar`, `image_path`, `image_asset`, `audio_path`, `is_active`, `updated_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'adult_choking', 1, 'Ask if they can speak', 'اسأل إذا كان يستطيع الكلام', 'Ask \"Are you choking?\" If they cannot speak, cough, or breathe — act immediately.', 'اسأل \"هل تختنق؟\" إذا لم يستطع الكلام أو السعال — تصرف فوراً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(2, 1, 'adult_choking', 2, 'Give 5 back blows', 'أعطِ 5 ضربات على الظهر', 'Lean them forward. Strike firmly between shoulder blades 5 times with the heel of your hand.', 'أمله للأمام. اضرب بقوة بين لوحي الكتف 5 مرات براحة يدك.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(3, 1, 'adult_choking', 3, 'Give 5 abdominal thrusts', 'أعطِ 5 دفعات بطنية', 'Stand behind them, make a fist above the navel, and thrust sharply inward and upward 5 times.', 'قف خلفه، ضع قبضتك فوق السرة، وادفع بقوة للداخل والأعلى 5 مرات.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(4, 1, 'adult_choking', 4, 'Repeat until clear or unconscious', 'كرر حتى يتحرر أو يفقد الوعي', 'Alternate 5 back blows and 5 thrusts. If unconscious, call 911 and start CPR.', 'بادل بين 5 ضربات ظهر و5 دفعات. إذا فقد الوعي اتصل بـ 911 وابدأ الإنعاش.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(5, 2, 'child_choking', 1, 'Check if child can cry or cough', 'تحقق إذا كان الطفل يبكي أو يسعل', 'If the child cannot cry, cough or breathe, begin first aid immediately.', 'إذا لم يستطع البكاء أو السعال أو التنفس، ابدأ الإسعاف فوراً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(6, 2, 'child_choking', 2, '5 back blows (child position)', '5 ضربات ظهر (وضعية الطفل)', 'Lay child face-down on your forearm. Support head. Give 5 firm back blows.', 'ضع الطفل وجهه للأسفل على ساعدك. دعم الرأس. أعطِ 5 ضربات ظهر قوية.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(7, 2, 'child_choking', 3, '5 chest thrusts', '5 دفعات صدرية', 'Turn child face-up. Give 5 chest thrusts with 2 fingers on center of chest.', 'اقلب الطفل وجهه للأعلى. أعطِ 5 دفعات صدرية بإصبعين على مركز الصدر.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(8, 2, 'child_choking', 4, 'Call 911 if no improvement', 'اتصل بـ 911 إذا لم يتحسن', 'If the object does not come out after several cycles, call emergency services immediately.', 'إذا لم يخرج الجسم بعد عدة دورات، اتصل بالإسعاف فوراً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(9, 3, 'asthma', 1, 'Sit them upright', 'أجلسه في وضع مستقيم', 'Sit the person upright, leaning slightly forward. Do not lay them down.', 'أجلس الشخص منتصباً مائلاً قليلاً للأمام. لا تضعه مستلقياً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(10, 3, 'asthma', 2, 'Use their inhaler', 'استخدم البخاخ', 'Help them use their reliever inhaler (usually blue). 1 puff every 30-60 seconds, up to 10 puffs.', 'ساعده على استخدام بخاخ الإغاثة (عادةً أزرق). نفخة كل 30-60 ثانية، حتى 10 نفخات.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(11, 3, 'asthma', 3, 'Call 911 if no improvement', 'اتصل بـ 911 إذا لم يتحسن', 'If no improvement after 10 minutes or breathing worsens, call 911 immediately.', 'إذا لم يتحسن بعد 10 دقائق أو ساء التنفس، اتصل بـ 911 فوراً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(12, 4, 'anaphylaxis', 1, 'Use EpiPen immediately', 'استخدم حقنة الأدرينالين فوراً', 'If available, use an epinephrine auto-injector (EpiPen) on outer thigh immediately.', 'إذا كانت متوفرة، استخدم حقنة الأدرينالين على الفخذ الخارجي فوراً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(13, 4, 'anaphylaxis', 2, 'Call 911 immediately', 'اتصل بـ 911 فوراً', 'Call emergency services immediately even if symptoms improve after EpiPen.', 'اتصل بالإسعاف فوراً حتى لو تحسنت الأعراض بعد الحقنة.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(14, 4, 'anaphylaxis', 3, 'Lay them down, legs raised', 'أضجعه ورفع قدميه', 'Lay the person flat with legs raised (unless breathing is difficult). Keep warm.', 'أضجع الشخص مع رفع ساقيه (إلا إذا صعب التنفس). أبقه دافئاً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(15, 4, 'anaphylaxis', 4, 'Second EpiPen if needed', 'حقنة ثانية عند الحاجة', 'If no improvement after 5-15 minutes and a second EpiPen is available, use it.', 'إذا لم يتحسن بعد 5-15 دقيقة وتوفرت حقنة ثانية، استخدمها.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(16, 5, 'unconscious_breathing', 1, 'Check responsiveness', 'تحقق من الاستجابة', 'Tap shoulders and shout \"Are you okay?\" If no response, call 911.', 'اربت على الكتفين واصرخ \"هل أنت بخير؟\" إذا لم يستجب، اتصل بـ 911.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(17, 5, 'unconscious_breathing', 2, 'Recovery position', 'وضعية الإفاقة', 'Roll them on their side (recovery position) to keep airway clear and prevent choking.', 'اقلبه على جانبه (وضعية الإفاقة) للحفاظ على مجرى الهواء ومنع الاختناق.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(18, 5, 'unconscious_breathing', 3, 'Monitor breathing', 'راقب التنفس', 'Keep monitoring breathing. If breathing stops, start CPR immediately.', 'استمر في مراقبة التنفس. إذا توقف التنفس، ابدأ الإنعاش فوراً.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(19, 6, 'not_breathing_cpr', 1, 'Call 911 now', 'اتصل بـ 911 الآن', 'Call 911 immediately or ask someone nearby to call while you begin CPR.', 'اتصل بـ 911 فوراً أو اطلب من شخص قريب الاتصال بينما تبدأ الإنعاش.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(20, 6, 'not_breathing_cpr', 2, 'Position hands on chest', 'ضع يديك على الصدر', 'Place the heel of your hand on center of chest. Place other hand on top, fingers interlaced.', 'ضع راحة يدك على مركز الصدر. ضع اليد الأخرى فوقها مع تشبيك الأصابع.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(21, 6, 'not_breathing_cpr', 3, '30 chest compressions', '30 ضغطة على الصدر', 'Push down hard and fast. At least 5cm deep, 100-120 compressions per minute.', 'اضغط بقوة وسرعة. عمق 5 سم على الأقل، 100-120 ضغطة في الدقيقة.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(22, 6, 'not_breathing_cpr', 4, '2 rescue breaths', 'نفسان إنقاذ', 'Tilt head back, lift chin. Pinch nose. Give 2 slow breaths, each over 1 second.', 'أمل الرأس للخلف، ارفع الذقن. أغلق الأنف. أعطِ نفسين بطيئين، كل منهما لثانية واحدة.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(23, 6, 'not_breathing_cpr', 5, 'Repeat 30:2 cycle', 'كرر دورة 30:2', 'Continue cycles of 30 compressions and 2 breaths until help arrives or person recovers.', 'استمر في دورات 30 ضغطة و2 نفس حتى تصل المساعدة أو يتعافى الشخص.', NULL, NULL, NULL, '', NULL, 1, NULL, '2026-05-08 18:40:10', '2026-05-08 18:40:10'),
(24, 3, 'asthma', 4, 'TEST', 'تيست', 'TEST', 'تسيت', '', '', '', '', NULL, 1, NULL, '2026-05-24 02:10:43', '2026-05-24 02:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `local_id` int(11) NOT NULL,
  `device_id` varchar(64) DEFAULT NULL,
  `device_db_id` int(11) DEFAULT NULL,
  `occurred_at` datetime DEFAULT NULL,
  `synced_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lang` varchar(10) DEFAULT 'en',
  `input_text` text DEFAULT NULL,
  `predicted_category` varchar(50) DEFAULT NULL,
  `category_code` varchar(50) DEFAULT NULL,
  `urgency` varchar(20) DEFAULT 'medium',
  `urgency_level` enum('low','medium','high','extreme') NOT NULL DEFAULT 'medium',
  `confidence` decimal(5,4) DEFAULT NULL,
  `manual_override` tinyint(1) NOT NULL DEFAULT 0,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `location_source` enum('gps','last_known','none') NOT NULL DEFAULT 'none',
  `notes` varchar(255) DEFAULT NULL,
  `synced` tinyint(1) NOT NULL DEFAULT 1,
  `server_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `local_id`, `device_id`, `device_db_id`, `occurred_at`, `synced_at`, `created_at`, `updated_at`, `lang`, `input_text`, `predicted_category`, `category_code`, `urgency`, `urgency_level`, `confidence`, `manual_override`, `lat`, `lng`, `location_source`, `notes`, `synced`, `server_id`) VALUES
(1, 1, '1779577379857668786', NULL, '2026-05-24 02:06:49', '2026-05-24 02:06:47', '2026-05-24 02:06:47', '2026-05-24 02:06:47', 'en', 'Choking adult', NULL, 'adult_choking', 'medium', 'high', 1.0000, 0, 32.066831, 36.046410, 'gps', NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `incident_images`
--

CREATE TABLE `incident_images` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `perm_key` varchar(100) NOT NULL,
  `perm_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `perm_key`, `perm_name`) VALUES
(1, 'dashboard.view', 'View dashboard'),
(2, 'categories.view', 'View categories'),
(3, 'categories.create', 'Create categories'),
(4, 'categories.edit', 'Edit categories'),
(5, 'categories.delete', 'Delete categories'),
(6, 'steps.view', 'View steps'),
(7, 'steps.create', 'Create steps'),
(8, 'steps.edit', 'Edit steps'),
(9, 'steps.delete', 'Delete steps'),
(10, 'incidents.view', 'View incidents'),
(11, 'incidents.create', 'Create incidents'),
(12, 'incidents.edit', 'Edit incidents'),
(13, 'incidents.delete', 'Delete incidents'),
(14, 'admins.view', 'View admins'),
(15, 'admins.create', 'Create admins'),
(16, 'admins.edit', 'Edit admins'),
(17, 'admins.delete', 'Delete admins'),
(18, 'system.manage_permissions', 'Manage permissions'),
(19, 'users.view', 'View App Users'),
(22, 'users.edit', 'Edit App Users'),
(23, 'users.delete', 'Delete App Users');

-- --------------------------------------------------------

--
-- Table structure for table `user_emergency_contacts`
--

CREATE TABLE `user_emergency_contacts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `relation` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_emergency_contacts`
--

INSERT INTO `user_emergency_contacts` (`id`, `user_id`, `contact_name`, `phone`, `relation`, `created_at`) VALUES
(4, 1, 'DR iyad Nijim', '0781944406', 'Father', '2026-05-23 23:11:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_audit_admin` (`admin_id`);

--
-- Indexes for table `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_password_resets_admin` (`admin_user_id`),
  ADD KEY `idx_admin_password_resets_phone` (`phone`),
  ADD KEY `idx_admin_password_resets_expires` (`expires_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_user_permissions`
--
ALTER TABLE `admin_user_permissions`
  ADD PRIMARY KEY (`admin_user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `app_users`
--
ALTER TABLE `app_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD KEY `idx_app_users_email` (`email`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `CODE` (`CODE`);

--
-- Indexes for table `content_meta`
--
ALTER TABLE `content_meta`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `content_versions`
--
ALTER TABLE `content_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `version_number` (`version_number`),
  ADD KEY `fk_versions_published_by` (`published_by_admin_id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_uuid` (`device_uuid`);

--
-- Indexes for table `guidance_steps`
--
ALTER TABLE `guidance_steps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_category_step` (`category_id`,`step_no`),
  ADD KEY `idx_steps_category` (`category_id`),
  ADD KEY `idx_steps_category_code` (`category_code`),
  ADD KEY `fk_steps_updated_by` (`updated_by_admin_id`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device_local` (`device_id`,`local_id`),
  ADD KEY `idx_incidents_occurred_at` (`occurred_at`),
  ADD KEY `idx_incidents_category_code` (`category_code`),
  ADD KEY `idx_incidents_predicted_category` (`predicted_category`),
  ADD KEY `idx_incidents_urgency` (`urgency_level`),
  ADD KEY `idx_incidents_device_time` (`device_id`,`occurred_at`),
  ADD KEY `fk_incidents_device_db` (`device_db_id`);

--
-- Indexes for table `incident_images`
--
ALTER TABLE `incident_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `perm_key` (`perm_key`);

--
-- Indexes for table `user_emergency_contacts`
--
ALTER TABLE `user_emergency_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `app_users`
--
ALTER TABLE `app_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `content_meta`
--
ALTER TABLE `content_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `content_versions`
--
ALTER TABLE `content_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guidance_steps`
--
ALTER TABLE `guidance_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `incident_images`
--
ALTER TABLE `incident_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_emergency_contacts`
--
ALTER TABLE `user_emergency_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  ADD CONSTRAINT `fk_admin_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  ADD CONSTRAINT `fk_admin_password_resets_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_user_permissions`
--
ALTER TABLE `admin_user_permissions`
  ADD CONSTRAINT `admin_user_permissions_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `content_versions`
--
ALTER TABLE `content_versions`
  ADD CONSTRAINT `fk_versions_published_by` FOREIGN KEY (`published_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `guidance_steps`
--
ALTER TABLE `guidance_steps`
  ADD CONSTRAINT `fk_steps_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_steps_updated_by` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `fk_incidents_device_db` FOREIGN KEY (`device_db_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `incident_images`
--
ALTER TABLE `incident_images`
  ADD CONSTRAINT `incident_images_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_emergency_contacts`
--
ALTER TABLE `user_emergency_contacts`
  ADD CONSTRAINT `user_emergency_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
