-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 05, 2026 at 05:39 AM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eastpol1_news_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `source_name` varchar(100) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `media_type` varchar(20) NOT NULL DEFAULT 'none',
  `media_url` varchar(500) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `allow_comments` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `articles`
--

INSERT INTO `articles` (`id`, `title`, `slug`, `summary`, `content`, `cover_image`, `source_name`, `source_url`, `media_type`, `media_url`, `meta_title`, `meta_description`, `category_id`, `status`, `is_featured`, `allow_comments`, `created_by`, `published_at`, `created_at`, `updated_at`) VALUES
(12, 'बालेन्द्र रास्वपाको वरिष्ठ नेता बन्दा कुलमान पक्षलाई जिम्मेवारी कहिले ?', 'balendra', 'बालेन्द्र रास्वपाको वरिष्ठ नेता बन्दा कुलमान पक्षलाई जिम्मेवारी कहिले ?\r\nजयसिंह महरा\r\nरास्वपाले बालेन्द्र शाहसहित उनको टिमका २६ र विवेकशील साझाका ७ जनालाई केन्द्रीय समिति सदस्यमा चयन गरेको छ ।', 'काठमाडौँ — रास्वपाले बालेन्द्र शाहसहित उनको टिमका २६ र विवेकशील साझाका ७ जनालाई केन्द्रीय समिति सदस्यमा चयन गरेको छ ।\r\n\r\nजिम्मेवारी तोक्ने क्रममा बालेन्द्र शाहलाई केन्द्रीय सदस्य मनोनीत गर्दै ‘वरिष्ठ नेता’ बनाइएको हो । त्यस्तै उनको टिमबाट बालेन्द्र शाहसहित सुनील लम्साल, भूपदेव शाह, सस्मित पोखरेल, सागर ढकाल, लक्ष्मण थारू, रमेश पौडेल र खगेन्द्र सुनार छन् । सुनार हाम्रो पार्टी नेपालका अध्यक्ष थिए तर रास्वपामा उनी बालेन्द्रको पक्षबाट समाहित भएका हुन् । त्यस्तै, बालेन्द्रकै तर्फबाट गणेश पौडेल, सन्तोष गिरी, नमिता यादव, सरिता ज्ञवाली, जेम्स कार्की, सुशान्त वैदिक, लक्ष्मी बर्देवा, शिव यादव, रोहन कार्की, अनन्तराज घिमिरे, रामकुमार ढुंगाना, प्रदीप ज्ञवाली, मधुसुधन ढकाल, ओजस्वी थापा, केपी खनाल, आदित्य आचार्य, प्रदीप पाण्डे र खेमराज साउद केन्द्रीय सदस्य चयन भएका छन् ।\r\n\r\nरास्वपाका सभापति रवि लामिछाने र बालेन्द्र शाहबीच १३ पुसमा एकता सहमति भएको थियो । सहमतिअनुसार बालेन्द्रलाई आगामी चुनावपछि बहुमत आएको अवस्थामा प्रधानमन्त्री बनाउने र रास्वपामा नेताहरूको समायोजन गर्ने सहमति गरिएको थियो । त्यस्तै, रास्वपा र कुलमान घिसिङको उज्यालो नेपाल पार्टी (उनेपा) बीच १४ पुसमा सातबुँदे सहमतिसहित एकता भएको थियो । त्यसअघि विवेकशील साझा पार्टी मंसिर १४ मा रास्वपामा समाहित भएको थियो ।\r\n\r\nविवेकशील साझाका सदस्य र सल्लाहकारहरूलाई केन्द्रीय, प्रादेशिक र स्थानीय तहगत संरचनामा समुचित समायोजन गरी जिम्मेवारी तोक्ने सहमति भएको थियो । उक्त सहमतिअनुसार आइतबार बसेको रास्वपा केन्द्रीय समितिको बैठकले विवेकशील साझाबाट आएका ७ नेतालाई केन्द्रीय समिति सदस्यमा मनोनीत गरेको हो । केन्द्रीय सदस्यमा मनोनीत भएका नेताहरूमा समीक्षा बाँस्कोटा, प्रकाशचन्द्र परियार, सुरज प्रधान, नवराज थापा, आशुतोष प्रधान, धनेज थापा र रञ्जु दर्शना छन् ।\r\n\r\nरास्वपाको केन्द्रीय समिति बैठकले पदाधिकारी संख्या बढाउने निर्णयसमेत गरेको छ । बैठकले उपसभापतिसहित ५ पदाधिकारी थप गर्ने निर्णय गरेको हो । पार्टी एकीकरण र विस्तारका लागि पदाधिकारी संख्या थप गर्ने निर्णय गरेको प्रवक्ता मनीष झाले जानकारी दिए । बैठकले एक उपसभापति, एक महामन्त्री, एक सहमहामन्त्री र एक सहप्रवक्ता पद थपेको छ । ‘पार्टी एकीकरण एवं विस्तारका क्रममा पार्टीले यसअघि गरेको सम्झौता कार्यान्वयनका लागि पार्टी विधान संशोधनमा समावेश हुने गरी पदाधिकारीको संख्या थप गर्ने’ निर्णय भएको प्रवक्ता झाले जानकारी दिए ।\r\n\r\nहाल रास्वपामा दुई उपसभापति छन् । घिसिङसँगको सहमति कार्यान्वयन गर्नका लागि उपसभापति पद थप गरिएको हो । त्यस्तै, हाल रास्वपामा एक महामन्त्री छन् । कविन्द्र बुर्लाकोटी महामन्त्री पदमा रहेको अवस्थामा एक महामन्त्री पद थपेर दुई बनाइएको छ । रास्वपामा हाल विपिन आचार्य सहमहामन्त्री छन् । सुमना श्रेष्ठले पार्टी छाडेपछि एउटा सहमहामन्त्री पद रिक्त छ । थप एक सहमहामन्त्री थप गरेसँगै रास्वपामा तीन सहमहामन्त्री हुनेछन् । रास्वपामा प्रतिभा रावल र रमेश प्रसाईं सहप्रवक्ता छन् । यी दुईमध्ये एउटालाई फिर्ता गरेर सहप्रवक्ता दुई जना मात्र राख्ने रास्वपाले जानकारी दिएको छ ।\r\n\r\nबालेन्द्रको पक्षबाट रास्वपाको महामन्त्रीमा सुनील लम्साल, सहमहामन्त्रीमा भूपदेव शाह र सहप्रवक्तामा सस्मित पोखरेललाई पठाउने तयारी रहेको नेताहरूले बताएका छन् । बाँकी नेताहरूको जिम्मेवारी तोक्नेबारे भने अझै समझदारी भइसकेको छैन । भूपदेव शाहले भने, ‘नेताहरूलाई जिम्मेवारी दिनेबारे छलफलको चरणमा छौं । तीन जनालाई पदाधिकारीको जिम्मेवारी दिने भनिएको छ । अरू राम्रा मान्छेहरूलाई पनि पार्टी पदाधिकारीदेखि अन्य जिम्मेवारीमा राख्ने बाटो खुला छ ।’', 'cover_balendra_1767590556.jpg', 'Kantipur', NULL, 'none', NULL, 'balen', NULL, 6, 'published', 0, 1, 1, '2026-01-05 05:22:36', '2026-01-05 05:22:36', '2026-01-05 05:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `article_tags`
--

CREATE TABLE `article_tags` (
  `article_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `article_views`
--

CREATE TABLE `article_views` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `article_views`
--

INSERT INTO `article_views` (`id`, `article_id`, `user_id`, `session_id`, `ip_hash`, `user_agent`, `created_at`) VALUES
(8, 12, NULL, '73d135eb1648401a741ccfe4b02492a6', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:31:55'),
(9, 12, NULL, '73d135eb1648401a741ccfe4b02492a6', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:31:58'),
(10, 12, NULL, '73d135eb1648401a741ccfe4b02492a6', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:32:00'),
(11, 12, 3, '30b97a2a2e8de0f1c3da51b8ed8c77cd', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:32:09'),
(12, 12, 3, '30b97a2a2e8de0f1c3da51b8ed8c77cd', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:32:11'),
(13, 12, 1, 'bb491401bc81785177d0a54ec248b677', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:34:02'),
(14, 12, 3, '30b97a2a2e8de0f1c3da51b8ed8c77cd', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:34:09'),
(15, 12, 3, '30b97a2a2e8de0f1c3da51b8ed8c77cd', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-05 05:34:19'),
(16, 12, NULL, 'e6abc9d2d2a14bdcc808c2306c400946', '87e18e92fc4245c18a81f6e063c57171c6e6d3f78aa8b1d1d9c1afe6b55db3fd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-01-05 05:38:05');

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookmarks`
--

INSERT INTO `bookmarks` (`id`, `user_id`, `article_id`, `created_at`) VALUES
(1, 3, 12, '2026-01-05 05:32:11');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(120) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `meta_title`, `meta_description`, `sort_order`, `is_active`, `created_at`) VALUES
(5, 'ताजा समाचार', NULL, 'taja-samachar', NULL, NULL, 1, 1, '2026-01-05 05:02:36'),
(6, 'राजनीति', NULL, 'rajniti', NULL, NULL, 2, 1, '2026-01-05 05:02:36'),
(7, 'अर्थ / वाणिज्य', NULL, 'artha-banijya', NULL, NULL, 3, 1, '2026-01-05 05:02:36'),
(8, 'विचार', NULL, 'bichar', NULL, NULL, 4, 1, '2026-01-05 05:02:36'),
(9, 'खेलकुद', NULL, 'khelkud', NULL, NULL, 5, 1, '2026-01-05 05:02:36'),
(10, 'मनोरञ्जन', NULL, 'manoranjan', NULL, NULL, 6, 1, '2026-01-05 05:02:36'),
(11, 'विश्व', NULL, 'bishwo', NULL, NULL, 7, 1, '2026-01-05 05:02:36'),
(12, 'शिक्षा', NULL, 'shiksha', NULL, NULL, 8, 1, '2026-01-05 05:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `article_id`, `user_id`, `comment`, `status`, `created_at`, `updated_at`) VALUES
(2, 12, 3, 'wow this is very good', 'approved', '2026-01-05 05:34:19', '2026-01-05 05:36:05');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `user_id`, `article_id`, `rating`, `created_at`, `updated_at`) VALUES
(1, 3, 12, 4, '2026-01-05 05:34:09', '2026-01-05 05:34:09');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(120) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `group_name` varchar(50) NOT NULL DEFAULT 'general',
  `type` enum('string','text','int','bool','json') NOT NULL DEFAULT 'string',
  `label` varchar(120) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `group_name`, `type`, `label`, `description`, `is_public`, `updated_at`) VALUES
(1, 'site_name', '\"सूचनापाटी\"', 'branding', 'string', NULL, NULL, 1, '2026-01-05 04:52:26'),
(2, 'site_tagline', '\"Minimal news. Fast.\"', 'branding', 'string', NULL, NULL, 1, '2026-01-05 04:52:26'),
(3, 'homepage_featured_limit', '4', 'homepage', 'int', NULL, NULL, 0, '2026-01-05 04:52:26'),
(4, 'homepage_latest_limit', '12', 'homepage', 'int', NULL, NULL, 0, '2026-01-05 04:52:26'),
(5, 'algo_weights', '{\"views\":0.6,\"ratings\":0.25,\"bookmarks\":0.15}', 'general', 'json', NULL, NULL, 0, '2026-01-05 04:52:26'),
(6, 'comment_policy', '{\"default_status\":\"pending\",\"allow_guests\":false}', 'comments', 'json', NULL, NULL, 0, '2026-01-05 04:52:26');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
(1, 'Breaking', 'breaking', '2026-01-05 04:56:25', '2026-01-05 04:56:25'),
(2, 'AI', 'ai', '2026-01-05 04:56:25', '2026-01-05 04:56:25'),
(3, 'Startup', 'startup', '2026-01-05 04:56:25', '2026-01-05 04:56:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','editor','admin') NOT NULL DEFAULT 'user',
  `avatar_url` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `avatar_url`, `bio`, `is_active`, `created_at`) VALUES
(1, 'Admin', 'admin@gmail.com', '$2y$10$fvmMOK8dwQiDefg4ASw8cOmTJ3WFiK72QifdbhPrzGT0.xH5OBrly', 'admin', NULL, NULL, 1, '2026-01-05 04:02:14'),
(2, 'Editor', 'editor@gmail.com', '$2y$10$fZADDHceVoX/vUmF576QUu8sBeNme7wCXpqoDtjV6YKKIqM5So2ha', 'editor', NULL, NULL, 1, '2026-01-05 04:46:53'),
(3, 'User1', 'user1@gmail.com', '$2y$10$RSNGdGjrAHJUhtmxZlmGT.VueKSoeer3gfPfUhbiW2I9aFWAZyBAm', 'user', NULL, NULL, 1, '2026-01-05 04:47:10'),
(4, 'User2', 'user2@gmail.com', '$2y$10$eGkZLb3V0S0PfudyLagTPOW60/Rwkpl6slyYCkilQHq75ETkTlsLW', 'user', NULL, NULL, 1, '2026-01-05 04:47:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uq_articles_slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_articles_category` (`category_id`),
  ADD KEY `idx_articles_status` (`status`),
  ADD KEY `idx_articles_published_at` (`published_at`),
  ADD KEY `idx_articles_created_by` (`created_by`);

--
-- Indexes for table `article_tags`
--
ALTER TABLE `article_tags`
  ADD PRIMARY KEY (`article_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `article_views`
--
ALTER TABLE `article_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_bm` (`user_id`,`article_id`),
  ADD UNIQUE KEY `uq_bookmarks_user_article` (`user_id`,`article_id`),
  ADD KEY `idx_bookmarks_user` (`user_id`),
  ADD KEY `idx_bookmarks_article` (`article_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`),
  ADD KEY `idx_categories_active_sort` (`is_active`,`sort_order`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `article_id` (`article_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_comments_status_created` (`status`,`created_at`),
  ADD KEY `idx_comments_article` (`article_id`),
  ADD KEY `idx_comments_user` (`user_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rate` (`user_id`,`article_id`),
  ADD UNIQUE KEY `uq_ratings_user_article` (`user_id`,`article_id`),
  ADD KEY `idx_ratings_article` (`article_id`),
  ADD KEY `idx_ratings_user` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uq_tags_slug` (`slug`),
  ADD KEY `idx_tags_name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role_active` (`role`,`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `article_views`
--
ALTER TABLE `article_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `article_tags`
--
ALTER TABLE `article_tags`
  ADD CONSTRAINT `article_tags_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `article_views`
--
ALTER TABLE `article_views`
  ADD CONSTRAINT `article_views_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
