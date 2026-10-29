-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: project_ta_absensi
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `project_ta_absensi`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `project_ta_absensi` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `project_ta_absensi`;

--
-- Table structure for table `absensi`
--

DROP TABLE IF EXISTS `absensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_siswa` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `waktu_pulang` time DEFAULT NULL,
  `status` enum('Hadir','Terlambat','Sakit','Izin','Alpa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Hadir',
  `metode_scan` enum('qr_kamera','scanner_usb','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qr_kamera',
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `absensi_id_siswa_tanggal_unique` (`id_siswa`,`tanggal`),
  KEY `absensi_tanggal_status_index` (`tanggal`,`status`),
  KEY `absensi_tanggal_index` (`tanggal`),
  CONSTRAINT `absensi_id_siswa_foreign` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `absensi`
--

LOCK TABLES `absensi` WRITE;
/*!40000 ALTER TABLE `absensi` DISABLE KEYS */;
/*!40000 ALTER TABLE `absensi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agenda`
--

DROP TABLE IF EXISTS `agenda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agenda` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `judul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Umum',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agenda_tanggal_index` (`tanggal`),
  KEY `agenda_tanggal_selesai_index` (`tanggal_selesai`),
  KEY `agenda_kategori_index` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agenda`
--

LOCK TABLES `agenda` WRITE;
/*!40000 ALTER TABLE `agenda` DISABLE KEYS */;
/*!40000 ALTER TABLE `agenda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('project-ta-absensi-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1793119137),('project-ta-absensi-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1793119137;',1793119137),('project-ta-absensi-cache-pengaturan_hari_aktif','s:35:\"Senin,Selasa,Rabu,Kamis,Jumat,Sabtu\";',1793203657),('project-ta-absensi-cache-pengaturan_jam_masuk','s:5:\"07:00\";',1793196987),('project-ta-absensi-cache-pengaturan_jam_pulang','s:5:\"12:00\";',1793196987),('project-ta-absensi-cache-pengaturan_jam_terlambat','s:5:\"07:15\";',1793196987),('project-ta-absensi-cache-pengaturan_nama_instansi','s:52:\"Sistem Terpadu Presensi & Notifikasi WhatsApp Santri\";',1793196987);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chatbot_logs`
--

DROP TABLE IF EXISTS `chatbot_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbot_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_pengirim` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesan_masuk` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `intent_terdeteksi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `id_siswa` bigint unsigned DEFAULT NULL,
  `pesan_keluar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sukses','gagal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sukses',
  `keterangan_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chatbot_logs_id_siswa_foreign` (`id_siswa`),
  KEY `chatbot_logs_no_pengirim_index` (`no_pengirim`),
  KEY `chatbot_logs_intent_terdeteksi_index` (`intent_terdeteksi`),
  CONSTRAINT `chatbot_logs_id_siswa_foreign` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbot_logs`
--

LOCK TABLES `chatbot_logs` WRITE;
/*!40000 ALTER TABLE `chatbot_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `chatbot_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hari_libur`
--

DROP TABLE IF EXISTS `hari_libur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hari_libur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hari_libur_tanggal_mulai_index` (`tanggal_mulai`),
  KEY `hari_libur_tanggal_selesai_index` (`tanggal_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hari_libur`
--

LOCK TABLES `hari_libur` WRITE;
/*!40000 ALTER TABLE `hari_libur` DISABLE KEYS */;
/*!40000 ALTER TABLE `hari_libur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kelas`
--

DROP TABLE IF EXISTS `kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kelas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_kelas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wali_kelas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_hp_wali` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warna_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2563eb',
  `urutan` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kelas_nama_kelas_unique` (`nama_kelas`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kelas`
--

LOCK TABLES `kelas` WRITE;
/*!40000 ALTER TABLE `kelas` DISABLE KEYS */;
INSERT INTO `kelas` VALUES (1,'JILID 1','Ustadzah Siti Fatimah (Sekretaris)','081234567801','#f44336',1,'2026-10-27 04:38:06','2026-10-27 09:35:23'),(2,'JILID 2','Ustadz Fairus','081234567802','#e91e63',2,'2026-10-27 04:38:06','2026-10-27 09:33:45'),(3,'JILID 3','Ustadzah Ummi','081234567803','#f2a93b',3,'2026-10-27 04:38:06','2026-10-27 09:33:45'),(4,'JILID 4','Ustadzah Nur Aini','081234567804','#2196f3',4,'2026-10-27 04:38:06','2026-10-27 09:10:35'),(7,'JILID Al-Quran','Ustadzah Dinil',NULL,'#059669',5,'2026-10-27 07:07:05','2026-10-27 09:14:27');
/*!40000 ALTER TABLE `kelas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_18_152335_create_kelas_table',1),(5,'2026_10_18_152342_create_siswa_table',1),(6,'2026_10_18_152343_create_absensi_table',1),(7,'2026_10_18_152344_create_hari_libur_table',1),(8,'2026_10_18_152345_create_agenda_table',1),(9,'2026_10_18_152346_create_tagihan_spp_table',1),(10,'2026_10_18_152347_create_pembayaran_spp_table',1),(11,'2026_10_18_152348_create_pengaturan_table',1),(12,'2026_10_18_153023_create_personal_access_tokens_table',1),(13,'2026_10_19_000001_create_chatbot_logs_table',1),(14,'2026_10_19_161336_add_tanggal_selesai_and_kategori_to_agenda_table',1),(15,'2026_10_24_131500_add_soft_deletes_to_siswa_and_tagihan_spp_table',1),(16,'2026_10_26_055403_create_whatsapp_logs_table',1),(17,'2026_10_27_180000_remove_spp_feature_from_database',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengaturan`
--

DROP TABLE IF EXISTS `pengaturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengaturan` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengaturan`
--

LOCK TABLES `pengaturan` WRITE;
/*!40000 ALTER TABLE `pengaturan` DISABLE KEYS */;
INSERT INTO `pengaturan` VALUES ('fonnte_token','','2026-10-27 04:38:09','2026-10-27 04:38:09'),('hari_aktif','Senin,Selasa,Rabu,Kamis,Jumat,Sabtu','2026-10-27 04:38:09','2026-10-27 04:38:09'),('jam_masuk','07:00','2026-10-27 04:38:09','2026-10-27 04:38:09'),('jam_pulang','12:00','2026-10-27 04:38:09','2026-10-27 04:38:09'),('jam_terlambat','07:15','2026-10-27 04:38:09','2026-10-27 04:38:09'),('nama_instansi','Sistem Terpadu Presensi & Notifikasi WhatsApp Santri','2026-10-27 04:38:09','2026-10-27 04:38:09'),('spreadsheet_auto_create_kelas','1','2026-10-27 06:51:23','2026-10-27 06:51:23'),('spreadsheet_santri_url','https://docs.google.com/spreadsheets/d/1mVn1iXEGDYQoQ1Ft-_nHgC7WOGJeuAg--Bu-cdoiTNk/edit?usp=sharing','2026-10-27 06:51:23','2026-10-27 06:53:38'),('tahun_ajaran_aktif','2026/2027','2026-10-27 04:38:09','2026-10-27 04:38:09'),('telegram_bot_token','','2026-10-27 04:38:09','2026-10-27 04:38:09'),('telegram_chat_id','','2026-10-27 04:38:09','2026-10-27 04:38:09');
/*!40000 ALTER TABLE `pengaturan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (4,'App\\Models\\User',4,'auth_token','87a3719a94aa834a3aefd054b90e8f3d0a90828e20651d03121e344dd9a6e7de','[\"*\"]','2026-10-27 09:37:07',NULL,'2026-10-27 09:37:06','2026-10-27 09:37:07'),(5,'App\\Models\\User',2,'auth_token','691ae0c71846ac79f5e1049ccddf6c211efeb0051e54933c9b63aad300785a9d','[\"*\"]','2026-10-27 09:38:47',NULL,'2026-10-27 09:37:58','2026-10-27 09:38:47');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('BxO5o1v9ZePeeStk2Y8CNGWDeAsAdSSMnpCtF5x5',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','eyJfdG9rZW4iOiJ4VDBNSVg1dWpvdWt1d2xFbnRpeGQwd3FtS1dOenFBRk51ZFM3SkZ3IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hYnNlbnNpXC9zaXN3YSIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1793110492),('t7cHtG2VQqfP03av8DKqbxvUlb2aSi0KXGSWY2CY',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','eyJfdG9rZW4iOiJFQmI5U1RRNjJWRHI5UHdDSmZIVWliRk1SdUpOVUh3dGpDTWl0VXlWIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1793119007),('ypzzn6IJ9VQ2LFjOzc07Qn0HEQhEf3QDkX9Vsc4s',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','eyJfdG9rZW4iOiI1dkRVUGRPSzRQa3RHQUJKVWVYdFAwb2puQmZWVnRGRVJSQmJHblJaIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1793119074);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `siswa`
--

DROP TABLE IF EXISTS `siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nisn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_lengkap` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kelamin` enum('L','P') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'L',
  `id_kelas` bigint unsigned NOT NULL,
  `no_hp_ortu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_ortu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('aktif','nonaktif','lulus','pindah') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `siswa_nisn_unique` (`nisn`),
  KEY `siswa_id_kelas_foreign` (`id_kelas`),
  KEY `siswa_status_index` (`status`),
  KEY `siswa_no_hp_ortu_index` (`no_hp_ortu`),
  CONSTRAINT `siswa_id_kelas_foreign` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `siswa`
--

LOCK TABLES `siswa` WRITE;
/*!40000 ALTER TABLE `siswa` DISABLE KEYS */;
INSERT INTO `siswa` VALUES (7,'1237','Moh. Afthon amin as Shiddiqi','L',1,NULL,'auwyri','paiton','aktif',NULL,NULL,'2026-10-27 07:06:45','2026-10-27 07:06:45'),(8,'12321','Ashif barkhiy Muhammad','L',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:45','2026-10-27 07:06:45'),(9,'12323','Ahmad Mahfudz iqomiiddin','L',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:45','2026-10-27 07:06:45'),(10,'12311','Ahmad Syafiq lizamul abror','L',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(11,'12312','Muhammad fadhlur Rohman','L',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(12,'12314','Alman Abqari Tegar Mahawira','L',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(13,'13222','Ahmad Zaini Mun\'im','L',1,NULL,'samsul','paiton','aktif',NULL,NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(14,'1233','Muhammad nizam al-barzan nuruddin','L',1,NULL,'hosiin','paiton','aktif',NULL,NULL,'2026-10-27 07:06:47','2026-10-27 07:06:47'),(15,'12318','M.shafiyurrahman Ar-Rabbani','L',1,NULL,'Abd Basid','paiton','aktif',NULL,NULL,'2026-10-27 07:06:47','2026-10-27 07:06:47'),(16,'26309','Muhammad Busyro Syariful F','L',1,'6282316376325','Abd Basid',NULL,'aktif',NULL,NULL,'2026-10-27 07:06:47','2026-10-27 07:06:47'),(17,'12317','Fatimatuzzahro ibni Hasyim annuryami','P',1,NULL,'hosiinn','paiton','aktif',NULL,NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(18,'12313','Zadana inayatul Maula firdaus','P',1,'6281336810741','Haris Firdaus','paiton','aktif',NULL,NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(19,'123155','Naura firdausiyah Nafisa','P',1,NULL,'MOHAMMAD SYAFI\'I','BONDOWOSO','aktif',NULL,NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(20,'1231','Arifa mumtaza','P',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(21,'123120','Farhana Jinany Firdausiyah','P',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(22,'26277','Habiba Sofiyatul Widad','P',1,'6285257911277','dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(23,'2628','Qismiya Nadhifa Zaini','P',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(24,'2629','Nusaibah Tsabibah AS','P',1,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(25,'12315','Muhammad Alwan Majdi Tegar Mahes Wara','L',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:50','2026-10-27 07:06:50'),(26,'1232','M.syaifullah alamal badroh Kadafi','L',2,NULL,'hosin','paiton','aktif',NULL,NULL,'2026-10-27 07:06:50','2026-10-27 07:06:50'),(27,'232','Abdul Rouf','L',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:50','2026-10-27 07:06:50'),(28,'236','Moh. Hosinu Asroril Fawaid','L',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(29,'237','Mohammad Farel Fadlur Rahman','L',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(30,'2315','Said Ahmad Mustofa','L',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(31,'2318','Muhammad Taufiqur Rohman','L',2,NULL,'dhasj',NULL,'aktif',NULL,NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(32,'2626','Muhammad Daffa Ikrar Ramadhan','L',2,NULL,'dhasj',NULL,'aktif',NULL,NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(33,'123299','Adiba Qotrul Nada','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(34,'12329','Muhammad Aufa fahri','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(35,'12319','Keysa roghda Fahira','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(36,'233','Asmaul Jannah Aprilia','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(37,'2310','Naflah Afrina Huwaida','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(38,'2313','Syifa Alifia Afida','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(39,'2316','Tsurayya Keumala Hayati','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(40,'2621','Qonitah Shofiyatul Widad','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(41,'2622','Cahatura Arifatul Jannah','P',2,'6287811041525','dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(42,'2623','Muhammad Alwan Majdi Tegar Maheswara','P',2,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(43,'2624','Muhammad Ilyas Yunus','P',2,'6281233908402','dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(44,'231','Ahmad Nashihul Ibad','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(45,'327','Muhammad Naqib Ruqbani','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(46,'328','Muhammad Birrul Alim','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(47,'329','Muhammad Syafiil Fauzi','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(48,'320','Said Fahri Rizki','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(49,'3212','Muhammad Fairuz Abdulbar Tirmidzi','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(50,'3216','Ilman Al Ghani','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(51,'32177','Mohammad Aldy Ali Yahya','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(52,'32199','Atar','L',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(53,'2633','Febri Tri nanda','L',3,'6282139209946','Saifuddin',NULL,'aktif',NULL,NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(54,'235','Hasanah','P',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(55,'234','Anisah Mahfudhah','P',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(56,'238','Maysarah Nafi\'ah','P',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(57,'2311','Indi Najila At-Thayyibah','P',3,NULL,'Muhammad Iksan','paiton','aktif',NULL,NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(58,'239','Najla Mahfudzah As-shafariyah','P',3,NULL,'Lailur','paiton','aktif',NULL,NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(59,'321','Hafsah Adibah','P',3,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:06:59','2026-10-27 07:06:59'),(60,'2635','Asmi Bisyarotul Aisyi','P',3,NULL,'dhasj',NULL,'aktif',NULL,NULL,'2026-10-27 07:06:59','2026-10-27 07:06:59'),(61,'2631','Nayla Ainun Mahya Assiddiqi','P',3,'6282233365870','dhasj',NULL,'aktif',NULL,NULL,'2026-10-27 07:06:59','2026-10-27 07:06:59'),(62,'2634','Sinta Maulidia Mahira','P',3,NULL,'dhasj',NULL,'aktif',NULL,NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(63,'262680','Amira Latifatul Fuadah Namira','P',3,NULL,NULL,NULL,'aktif',NULL,NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(64,'3211','Rafan Irsyad Al Firdaus','L',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(65,'3217','Muhammad Arif Fadhil Abdullah','L',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(66,'32118','Muhammad Agham Abdillah Pratama','L',4,'6282338540089','Muhammad Ghofir','paiton','aktif',NULL,NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(67,'3219','Firial Hana Majida Amir','L',4,NULL,'dhasj',NULL,'aktif',NULL,NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(68,'434','Rovil Maulana','L',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(69,'262728','Muahammad Robit Nashihul Ibad','L',4,NULL,NULL,NULL,'aktif',NULL,NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(70,'23177','Nafisa Ufairuz Zaini','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(71,'2312','Masyya Sahila','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(72,'2317','Ananda Rizqiy Amalia D.','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(73,'2314','Anis Aliza Nurud Diana','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(74,'326','Aufa Mahsunah Al-Sabiqiah','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(75,'322','Azizah Hafidotul Mukarromah','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(76,'435','Atihfah Bariroh','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(77,'436','Usfuri Hamidah','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(78,'437','Karima Haura Zuhdi Fadli','P',4,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(79,'4388','Haura Kanza Salsabila','P',4,NULL,'dhasj','Besuki','aktif',NULL,NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(80,'438','Anindita Keyza Azzahra','P',4,NULL,'dhasj','Besuki','aktif',NULL,NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(81,'262729','Alifah Aila Fitriyah Luthfi','P',4,NULL,NULL,NULL,'aktif',NULL,NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(82,'432','Mahadma Muhammad','L',7,NULL,'dhasj','paiton','aktif',NULL,NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(83,'433','Muhammad Gabriel Nur Ramadhani','L',7,NULL,NULL,NULL,'aktif',NULL,NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(84,'431','Ahmad Dzaki Mubarok','L',7,NULL,'AAAAA','paiton','aktif',NULL,NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(85,'542','Muhammad Kayyis Al-Wijdan','L',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(86,'543','Febrian Tri Anjasmoro','L',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(87,'2651','Ahmad Athoillah Ali Zainal Abidin Ar-ruhami','L',7,NULL,'Nasrul','paiton','aktif',NULL,NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(88,'544','Alaika Rizki Hasan Mubarok','L',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(89,'545','Samuel Putra Sugara','L',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(90,'546','Muhammad Beryl Esa Alvian Hafid','L',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(91,'547','Ahmad Mahdi Sabilal Haq','L',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(92,'2627','Muahmmad Bassam Rowaiul Bayan','L',7,NULL,'kosong','paiton','aktif',NULL,NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(93,'548','Shofia Idris','P',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(94,'549','Nuri Firdaus','P',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(95,'5410','Indana Najma Lailatul Qodariyah','P',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(96,'541123','Hilwa Qudrotul Karimah','P',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(97,'5411','Najwa','P',7,NULL,NULL,'paiton','aktif',NULL,NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(98,'5412','Anisa Shofyah Maulidia Arifin','P',7,NULL,NULL,'Situbondo','aktif',NULL,NULL,'2026-10-27 07:07:09','2026-10-27 07:07:09');
/*!40000 ALTER TABLE `siswa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `id_kelas` bigint unsigned DEFAULT NULL,
  `nisn` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_id_kelas_index` (`id_kelas`),
  KEY `users_nisn_index` (`nisn`)
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrator','admin','admin@sekolah.id',NULL,'$2y$12$gTK5zgok.9f9FIfo0bP/R.rs435tw2qfeqQ/NLsRxqKyP4CdjjzU.','admin',NULL,NULL,NULL,'2026-10-27 04:38:07','2026-10-27 04:38:07'),(2,'Ny. Shofiyah','kepala','kepala@sekolah.id',NULL,'$2y$12$xYR9EO3ZUInBpzIm8zAJs.MrFNjWcB21x188hdZoNgwCYer3cq1PW','kepala',NULL,NULL,NULL,'2026-10-27 04:38:07','2026-10-27 09:13:46'),(3,'Ustadzah Siti Fatimah (Sekretaris)','fatim','sekretaris@sekolah.id',NULL,'$2y$12$u4Kz6QdawgIqxgPS3qikLuHT.eIp5atg6a7NgTsIZQ9FOyDXh2Xyy','sekretaris',1,NULL,NULL,'2026-10-27 04:38:07','2026-10-27 09:35:23'),(4,'Ustadzah Ummi','Ummi','bendahara@sekolah.id',NULL,'$2y$12$OSVWXjnZvU9k3w2MKTjZz.ZjqfCc9QPABBsQKQygIlgJ3e8TUqcp6','bendahara',3,NULL,NULL,'2026-10-27 04:38:07','2026-10-27 09:12:28'),(5,'Ustadz Fairus','guru','guru@sekolah.id',NULL,'$2y$12$x/E1YH7G6sPEbUGdosO6SOx2HYFj4am5rWpExCvtCgO2l/w2fZhcG','guru',2,NULL,NULL,'2026-10-27 04:38:08','2026-10-27 09:33:45'),(6,'Ahmad Fauzi','1001','1001@santri.sekolah.id',NULL,'$2y$12$XDYkcnyXgsDtlkfOlWKmK.0oFgIcojLQlbCKNHCFSnMSAPwVLyIKW','siswa',1,'1001',NULL,'2026-10-27 04:38:08','2026-10-27 04:38:08'),(7,'Muhammad Bilal','1002','1002@santri.sekolah.id',NULL,'$2y$12$qlR3rSbq/Tgz8tgZBxNnauV852kgtQLIOpV0osocfSJlx6HEhh4eS','siswa',1,'1002',NULL,'2026-10-27 04:38:08','2026-10-27 04:38:08'),(8,'Zahra Amelia','1003','1003@santri.sekolah.id',NULL,'$2y$12$av3oYMr8b154yfbD7YalSOvN.L5hy5yEh8QxW7IMsuEDW.96EQ3fS','siswa',1,'1003',NULL,'2026-10-27 04:38:08','2026-10-27 04:38:08'),(9,'Farhan Al-Farisi','2001','2001@santri.sekolah.id',NULL,'$2y$12$SDc05QayIOx5BbS3h6/NcOjW7ngalnHD0UCrIgGI49nttbdYGhcJa','siswa',2,'2001',NULL,'2026-10-27 04:38:09','2026-10-27 04:38:09'),(10,'Siti Khadijah','2002','2002@santri.sekolah.id',NULL,'$2y$12$gB6gdP2kugTAxNADr9yPKulMDcB0QL.NrJBHmJV49knPMp4K2o7xy','siswa',2,'2002',NULL,'2026-10-27 04:38:09','2026-10-27 04:38:09'),(11,'Hamzah Asadullah','3001','3001@santri.sekolah.id',NULL,'$2y$12$7BSjAgMDkj/pQhzYeulA5OY.OOo5x2GIE/Q0SLhSS1U3yzlfkGS4a','siswa',3,'3001',NULL,'2026-10-27 04:38:09','2026-10-27 04:38:09'),(12,'Moh. Afthon amin as Shiddiqi','1237','1237@santri.sekolah.id',NULL,'$2y$12$DlqYMsn8Ig9hFfGq2qHB6e937nenq7KmdmrY78TFF3EkgNIick6Au','siswa',1,'1237',NULL,'2026-10-27 07:06:45','2026-10-27 07:06:45'),(13,'Ashif barkhiy Muhammad','12321','12321@santri.sekolah.id',NULL,'$2y$12$XvnIcTACktmENWJWdmruwOuOlEM8aaPKDq/QuIVHirC3je6ngM8GO','siswa',1,'12321',NULL,'2026-10-27 07:06:45','2026-10-27 07:06:45'),(14,'Ahmad Mahfudz iqomiiddin','12323','12323@santri.sekolah.id',NULL,'$2y$12$kj6gBnR1vgODPWd.0WPwUuESiksuKGJ.WP1ZMqJZivO.EWz8TO4NO','siswa',1,'12323',NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(15,'Ahmad Syafiq lizamul abror','12311','12311@santri.sekolah.id',NULL,'$2y$12$khQO5DcfvH7O.KqSffPRd.p67W3htbA/JvAT0LMoKAWIHqLuLOYIS','siswa',1,'12311',NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(16,'Muhammad fadhlur Rohman','12312','12312@santri.sekolah.id',NULL,'$2y$12$1.lnAPFUe8iXgYHEy6Ba1eg0.yLrAsiavlEsrKrbTfncaMpG13bBy','siswa',1,'12312',NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(17,'Alman Abqari Tegar Mahawira','12314','12314@santri.sekolah.id',NULL,'$2y$12$xIEflY8pkPNMRlSNDQGv0eVASj54Cw6Od/Q5KvLwxOf/hr4Lb6hdG','siswa',1,'12314',NULL,'2026-10-27 07:06:46','2026-10-27 07:06:46'),(18,'Ahmad Zaini Mun\'im','13222','13222@santri.sekolah.id',NULL,'$2y$12$eqOfoHsElIiSrk4CSwdSd.yPkg2ugvMRD.ts5.clRqMy.Gu0Ukty2','siswa',1,'13222',NULL,'2026-10-27 07:06:47','2026-10-27 07:06:47'),(19,'Muhammad nizam al-barzan nuruddin','1233','1233@santri.sekolah.id',NULL,'$2y$12$5jMumJYZ5zj4v8S6xkiSK.Rf.5kMGUaf/N.lUmRUFGp9hoVIBEdU2','siswa',1,'1233',NULL,'2026-10-27 07:06:47','2026-10-27 07:06:47'),(20,'M.shafiyurrahman Ar-Rabbani','12318','12318@santri.sekolah.id',NULL,'$2y$12$puMUTNXoxD.Gen4ttfYJquvjGEdDN2/gKghIglHWDolIvUnBClyGS','siswa',1,'12318',NULL,'2026-10-27 07:06:47','2026-10-27 07:06:47'),(21,'Muhammad Busyro Syariful F','26309','26309@santri.sekolah.id',NULL,'$2y$12$XJsE2CCW2LE6woBcsKG4bu/MoZRnpwnfIa1nANQ9H1GnB9dJT.OSO','siswa',1,'26309',NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(22,'Fatimatuzzahro ibni Hasyim annuryami','12317','12317@santri.sekolah.id',NULL,'$2y$12$W8JdaMPNH3BGtEqX6HPKB.W6uAhoi8VMzkUhXdkPFnNw9o/oelr4W','siswa',1,'12317',NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(23,'Zadana inayatul Maula firdaus','12313','12313@santri.sekolah.id',NULL,'$2y$12$ovC5TmdKKsaKbIBoqYe9Xe8SRJI9ozZmoYKNU4iwxlBC.Cl73WfXa','siswa',1,'12313',NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(24,'Naura firdausiyah Nafisa','123155','123155@santri.sekolah.id',NULL,'$2y$12$Us5A/5N..2GwtIpapS7DUOEXdQ8Hhp0PArv2msiQkI9rexHi3u7tS','siswa',1,'123155',NULL,'2026-10-27 07:06:48','2026-10-27 07:06:48'),(25,'Arifa mumtaza','1231','1231@santri.sekolah.id',NULL,'$2y$12$k3sf7VywMstClHSamLMwnuxhRpiNyOH/CUYWMUZ39/Sh2Sy1joVoS','siswa',1,'1231',NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(26,'Farhana Jinany Firdausiyah','123120','123120@santri.sekolah.id',NULL,'$2y$12$s.3An/AyK/EV5B27GymD.ugrzyN/7VKFzaWyyfJknvMdo4bmxOM1G','siswa',1,'123120',NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(27,'Habiba Sofiyatul Widad','26277','26277@santri.sekolah.id',NULL,'$2y$12$4uNZTURj5vMfbHqX6D5Rxul3WVYEIUtMOHfuvbR2F4/cg.x3yaI8u','siswa',1,'26277',NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(28,'Qismiya Nadhifa Zaini','2628','2628@santri.sekolah.id',NULL,'$2y$12$C2ktSG65isJDQgg1QaoGD.89nqkgKdL.o9c8Bagy3EFKsOOnWtaEi','siswa',1,'2628',NULL,'2026-10-27 07:06:49','2026-10-27 07:06:49'),(29,'Nusaibah Tsabibah AS','2629','2629@santri.sekolah.id',NULL,'$2y$12$4vq4uY5UMDUAO92wQknQ4.AlEl9NvmK9q74FDAQN73gJeWr4q6YyK','siswa',1,'2629',NULL,'2026-10-27 07:06:50','2026-10-27 07:06:50'),(30,'Muhammad Alwan Majdi Tegar Mahes Wara','12315','12315@santri.sekolah.id',NULL,'$2y$12$tMFLPP3gD1MZpZbIKgaocuDCmWxjHUJ6PxVRSnrjC58c06E8IV38e','siswa',2,'12315',NULL,'2026-10-27 07:06:50','2026-10-27 07:06:50'),(31,'M.syaifullah alamal badroh Kadafi','1232','1232@santri.sekolah.id',NULL,'$2y$12$WsZnm47aA5zfnpigB.KxaOPRGtuwy0tjusgP9UhFHfhzzxCn3wS4C','siswa',2,'1232',NULL,'2026-10-27 07:06:50','2026-10-27 07:06:50'),(32,'Abdul Rouf','232','232@santri.sekolah.id',NULL,'$2y$12$YfZgJ3wK83Skr9QyqVZ29uQCQBYBOY.9PafsuM0fsO9OStpd4fihS','siswa',2,'232',NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(33,'Moh. Hosinu Asroril Fawaid','236','236@santri.sekolah.id',NULL,'$2y$12$cAtmGPLljn4VzwSSe6F54uhmsNV3cYPeRBfs.4Lkl4pQyd.oIl1ca','siswa',2,'236',NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(34,'Mohammad Farel Fadlur Rahman','237','237@santri.sekolah.id',NULL,'$2y$12$D9F4y7QWZcGuK2efg2OpIu2TGNFqrQNi12lnzXC2nDOxubWP3Gjzq','siswa',2,'237',NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(35,'Said Ahmad Mustofa','2315','2315@santri.sekolah.id',NULL,'$2y$12$Yb3MaOcDNe7YZ5OPa3N3o.rtdINmoeVZXAdzy08MnNBd8w/g.LbgC','siswa',2,'2315',NULL,'2026-10-27 07:06:51','2026-10-27 07:06:51'),(36,'Muhammad Taufiqur Rohman','2318','2318@santri.sekolah.id',NULL,'$2y$12$IaFZET1xEx5m7OEqv3oH0ulCBeuTS9ZLJISknJqOb2FVLjBBDLmN2','siswa',2,'2318',NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(37,'Muhammad Daffa Ikrar Ramadhan','2626','2626@santri.sekolah.id',NULL,'$2y$12$NxcG/Zsv5enymiLtz2DXz.dm78Ess8xt7kUUmxPAtLtg7I4/6c.ji','siswa',2,'2626',NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(38,'Adiba Qotrul Nada','123299','123299@santri.sekolah.id',NULL,'$2y$12$18iK0qcmzkxuxPDfZyd9iOEMK5MHXecwWvwOgCQfjB1X6qENOFEPG','siswa',2,'123299',NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(39,'Muhammad Aufa fahri','12329','12329@santri.sekolah.id',NULL,'$2y$12$/8rvHo37AK52b.f/s29jWOXIZe./v/1EMGmJ5eHZ.OCr5BY9xRgYu','siswa',2,'12329',NULL,'2026-10-27 07:06:52','2026-10-27 07:06:52'),(40,'Keysa roghda Fahira','12319','12319@santri.sekolah.id',NULL,'$2y$12$M7f64tSnBy1/HMFybelBkOkTeGMT0WA4AhovGzc7DU86v3vmresxe','siswa',2,'12319',NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(41,'Asmaul Jannah Aprilia','233','233@santri.sekolah.id',NULL,'$2y$12$BUogHxGza9EP.LjTDQI1ReRahjThwIY8DOZccMwdZiEax9xDlbiN6','siswa',2,'233',NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(42,'Naflah Afrina Huwaida','2310','2310@santri.sekolah.id',NULL,'$2y$12$OfeE.KEdd17pNu3rnfZV5OW2VvFHsp59pBrmpIjMALBAr.rI6yQpe','siswa',2,'2310',NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(43,'Syifa Alifia Afida','2313','2313@santri.sekolah.id',NULL,'$2y$12$6Wp.mw3.pBO9L.ZoYxNhx.BV2KDy0c4oSXhhGRr5EEKbTSFMKMZgu','siswa',2,'2313',NULL,'2026-10-27 07:06:53','2026-10-27 07:06:53'),(44,'Tsurayya Keumala Hayati','2316','2316@santri.sekolah.id',NULL,'$2y$12$uaLLGO0IPqEmVgrlepSSGOrLwrEDAPD1SnTFAwpFXJR9p21k3qxym','siswa',2,'2316',NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(45,'Qonitah Shofiyatul Widad','2621','2621@santri.sekolah.id',NULL,'$2y$12$iEEOpUSgPFijCnGTMAILjeOHjxrBgh9RnNYVaSZ/PSsqZT88CzzlO','siswa',2,'2621',NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(46,'Cahatura Arifatul Jannah','2622','2622@santri.sekolah.id',NULL,'$2y$12$EZ7e3qJxBicHrJKGRSlSHOLGPurSyaAMQ.AB5IHDuzO4f8.4WVgcu','siswa',2,'2622',NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(47,'Muhammad Alwan Majdi Tegar Maheswara','2623','2623@santri.sekolah.id',NULL,'$2y$12$xKDZnYfr9Ev4J/pDUaL29OcqV0CXRMkNrADCA63SAqmcUQC6SjfKC','siswa',2,'2623',NULL,'2026-10-27 07:06:54','2026-10-27 07:06:54'),(48,'Muhammad Ilyas Yunus','2624','2624@santri.sekolah.id',NULL,'$2y$12$BILqTwlDeIrykTjQlvbSceNocw0qpQWJNZc5iXm3HOe2MaGtuaLbW','siswa',2,'2624',NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(49,'Ahmad Nashihul Ibad','231','231@santri.sekolah.id',NULL,'$2y$12$v4NhD4Wloz30CO5ae/5jsOWpfmgs5/Otx1e9hbBV2YiskmADdLvRC','siswa',3,'231',NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(50,'Muhammad Naqib Ruqbani','327','327@santri.sekolah.id',NULL,'$2y$12$sf024MV/WH8i/x6uw82ctOSD1Wk66xhtmGdxmruA/e2z7RB4k.xhW','siswa',3,'327',NULL,'2026-10-27 07:06:55','2026-10-27 07:06:55'),(51,'Muhammad Birrul Alim','328','328@santri.sekolah.id',NULL,'$2y$12$bj8f4y4PJL8ErHsE2j4.oupsJi02Pa11ACf15/tRvF.wkF1P58pOq','siswa',3,'328',NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(52,'Muhammad Syafiil Fauzi','329','329@santri.sekolah.id',NULL,'$2y$12$.bbDT0661FY8kS3pOnMoxO5HbmK7bbptMB2Av8.6wO/dbx.5.o0XC','siswa',3,'329',NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(53,'Said Fahri Rizki','320','320@santri.sekolah.id',NULL,'$2y$12$35iECCC5xozPOPnUCDpVHeu8p5JVK/U8Wy8DI5Jld3dfKSIDVpSlW','siswa',3,'320',NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(54,'Muhammad Fairuz Abdulbar Tirmidzi','3212','3212@santri.sekolah.id',NULL,'$2y$12$OtLVSNE1j5JaJLyNw1Bo2.aGQym.2NOsAa26ayL3Y9IpYm7hsYgEG','siswa',3,'3212',NULL,'2026-10-27 07:06:56','2026-10-27 07:06:56'),(55,'Ilman Al Ghani','3216','3216@santri.sekolah.id',NULL,'$2y$12$92Hkqe1mtvyeRp6vM5Iy6uNBUux6AofmKrKeMLvjg/hLi.f61X8VS','siswa',3,'3216',NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(56,'Mohammad Aldy Ali Yahya','32177','32177@santri.sekolah.id',NULL,'$2y$12$u9QUyixNB3ZOWAoLw/uGlO9nikFvX905V2FEoqB.K5MgCP8ej1O96','siswa',3,'32177',NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(57,'Atar','32199','32199@santri.sekolah.id',NULL,'$2y$12$voFYLsOHQ9eMTC1b730Kte9YyyCe0JMP7K68YeiwrnvAkKaFFxuN2','siswa',3,'32199',NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(58,'Febri Tri nanda','2633','2633@santri.sekolah.id',NULL,'$2y$12$3ox0Dux9rJ/eymiC9oCrYeyAXfDVrylE29xKrr9/9s3hjZkJMQiEC','siswa',3,'2633',NULL,'2026-10-27 07:06:57','2026-10-27 07:06:57'),(59,'Hasanah','235','235@santri.sekolah.id',NULL,'$2y$12$4.jhr3fsGsItFqpe0Ze2guoNW7UkxkrGCysd4ziz/73zXHvVtZq6S','siswa',3,'235',NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(60,'Anisah Mahfudhah','234','234@santri.sekolah.id',NULL,'$2y$12$n7kUOYZaOFYkQp3BEOurqOXnDqFUQNYBwXlogOdD9lLeWQLv/EOjm','siswa',3,'234',NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(61,'Maysarah Nafi\'ah','238','238@santri.sekolah.id',NULL,'$2y$12$dW0Fmj3NYlucR1kM/mII1e16Qfd5z24ElEoEBdBSFXX/8.Oo6Bf7y','siswa',3,'238',NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(62,'Indi Najila At-Thayyibah','2311','2311@santri.sekolah.id',NULL,'$2y$12$oH8esC1QeVbmTi53x8IgIu.6jQobi/yNErJLSPMBP5p9L3fNqtheS','siswa',3,'2311',NULL,'2026-10-27 07:06:58','2026-10-27 07:06:58'),(63,'Najla Mahfudzah As-shafariyah','239','239@santri.sekolah.id',NULL,'$2y$12$yerwmeTU4YAt3vHML6FPhu..l7G/ez8eeoBdSPVVbmG66w3MJt5/G','siswa',3,'239',NULL,'2026-10-27 07:06:59','2026-10-27 07:06:59'),(64,'Hafsah Adibah','321','321@santri.sekolah.id',NULL,'$2y$12$9GdKq1aYvRTEvEu8AirXGePo7ZnyZiXIrs4HvOug61iB9REiwsjH2','siswa',3,'321',NULL,'2026-10-27 07:06:59','2026-10-27 07:06:59'),(65,'Asmi Bisyarotul Aisyi','2635','2635@santri.sekolah.id',NULL,'$2y$12$ZnwRCdnKDnKoI2KAhac9T.aqH7iuCLPTdBeLumJ/Q8o3Lnsg.9LNW','siswa',3,'2635',NULL,'2026-10-27 07:06:59','2026-10-27 07:06:59'),(66,'Nayla Ainun Mahya Assiddiqi','2631','2631@santri.sekolah.id',NULL,'$2y$12$XSrSBCEkFO.mRv/QR4dGDuByQStFtzDgYyhDT7wBKCHSFZGYIeTlS','siswa',3,'2631',NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(67,'Sinta Maulidia Mahira','2634','2634@santri.sekolah.id',NULL,'$2y$12$RlJoYvnimbhp8ZE.3DM7veH7JSD2eT3wAQf6ITE1cofFhb5t7RfXq','siswa',3,'2634',NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(68,'Amira Latifatul Fuadah Namira','262680','262680@santri.sekolah.id',NULL,'$2y$12$Czr5tWMsZqHxEQCv5OuWJ.gBKPK5sW08esHY99TQH5jbEq8NUYvRe','siswa',3,'262680',NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(69,'Rafan Irsyad Al Firdaus','3211','3211@santri.sekolah.id',NULL,'$2y$12$zY5ZUmMSK.LJLU48xDyJeOdW5ir0074LsOd6z4tOwyEnWkLJcm8pO','siswa',4,'3211',NULL,'2026-10-27 07:07:00','2026-10-27 07:07:00'),(70,'Muhammad Arif Fadhil Abdullah','3217','3217@santri.sekolah.id',NULL,'$2y$12$2vcMPkWl8BR3b0gPLPkAcuSlsDXXQdADa61Ge3AJsFsoU1E9qO4xe','siswa',4,'3217',NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(71,'Muhammad Agham Abdillah Pratama','32118','32118@santri.sekolah.id',NULL,'$2y$12$xKGgQsyc7Zoqb4N/z/.aaOvOUuSwRrY86dNn/qvrdWQ55oYOQz7.K','siswa',4,'32118',NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(72,'Firial Hana Majida Amir','3219','3219@santri.sekolah.id',NULL,'$2y$12$CtR5X93nxDx8xNwAJ1Fw8u1wEXwsA6xXgJMIwgFaEjABIS0JZK/tK','siswa',4,'3219',NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(73,'Rovil Maulana','434','434@santri.sekolah.id',NULL,'$2y$12$XqAQ6AZhc8KePR3JbCjdT.zXiTuI7I5Z4zvr9I1SN2YHpDEZKMRR2','siswa',4,'434',NULL,'2026-10-27 07:07:01','2026-10-27 07:07:01'),(74,'Muahammad Robit Nashihul Ibad','262728','262728@santri.sekolah.id',NULL,'$2y$12$0qWn75ZRrMwG5N1HVzomW.REyFrcOPmNTIAlAKmFoMQbkTLVbgQJy','siswa',4,'262728',NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(75,'Nafisa Ufairuz Zaini','23177','23177@santri.sekolah.id',NULL,'$2y$12$UgHKVQ/yYmxbW3ZtjRWRrOHnQN6H3B2HIHNGkCVxuk3tgyiA.kolm','siswa',4,'23177',NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(76,'Masyya Sahila','2312','2312@santri.sekolah.id',NULL,'$2y$12$Xdl3j0QZPgf8eze3MNS6Ge3Mrr31BzL1NE2QLkjGVk76qndERV/ha','siswa',4,'2312',NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(77,'Ananda Rizqiy Amalia D.','2317','2317@santri.sekolah.id',NULL,'$2y$12$BWI6X9UpHGjmaUHPWYJM/.wl24BQk1P7GcHPkh1ftIm5HIiYNLtlS','siswa',4,'2317',NULL,'2026-10-27 07:07:02','2026-10-27 07:07:02'),(78,'Anis Aliza Nurud Diana','2314','2314@santri.sekolah.id',NULL,'$2y$12$55laXzDhZfu06sFfDHkNPeTXDspgSbXO9GcZLbBe9ZtXKvemkEI9W','siswa',4,'2314',NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(79,'Aufa Mahsunah Al-Sabiqiah','326','326@santri.sekolah.id',NULL,'$2y$12$nUX3qHkwrdVBBZRXsllXTOWNwT/IINXUqBA2nOHMp3agPJwyAdDyu','siswa',4,'326',NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(80,'Azizah Hafidotul Mukarromah','322','322@santri.sekolah.id',NULL,'$2y$12$doh/gfuN7GPIP3dpLksZreA3fhBPVxh2pNbQMT9Owr7pLP9zRSMhG','siswa',4,'322',NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(81,'Atihfah Bariroh','435','435@santri.sekolah.id',NULL,'$2y$12$ClAuZ1o0D.t7J9iJa0PNbeVVcbtR/U9BOpm9iWb/G3oZ1Vd0A.P4y','siswa',4,'435',NULL,'2026-10-27 07:07:03','2026-10-27 07:07:03'),(82,'Usfuri Hamidah','436','436@santri.sekolah.id',NULL,'$2y$12$T1gsy7PeVC2eIeGWvYL/berpdNsLNdC9emJ6uXSZp4td0Bl8bmjZ6','siswa',4,'436',NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(83,'Karima Haura Zuhdi Fadli','437','437@santri.sekolah.id',NULL,'$2y$12$fWcJIHR5kgc27OxK8sQzSez1YvYkuSMAwnceXeOLKBM4I.N/er8A.','siswa',4,'437',NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(84,'Haura Kanza Salsabila','4388','4388@santri.sekolah.id',NULL,'$2y$12$0/BdBbrKY8mirXna909O3OXkTSPJeMATPP5EZc7hlWgfSL2tX/NWW','siswa',4,'4388',NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(85,'Anindita Keyza Azzahra','438','438@santri.sekolah.id',NULL,'$2y$12$ssSIkPmM75aygWNgr4nwXuKLrDVTQgFGhDX8vMiJ0nga2WY1WJkYy','siswa',4,'438',NULL,'2026-10-27 07:07:04','2026-10-27 07:07:04'),(86,'Alifah Aila Fitriyah Luthfi','262729','262729@santri.sekolah.id',NULL,'$2y$12$Ol1qID2zl/JNXuU1hvmbje/Pfrr7LrgONVI.s8wXZiwq53k1XPJyu','siswa',4,'262729',NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(87,'Mahadma Muhammad','432','432@santri.sekolah.id',NULL,'$2y$12$ybJSxdsZYMi6AXngAHKGNeC1m4gv2/MmBLn.vQLCQaKfEdSsVyxQ.','siswa',7,'432',NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(88,'Muhammad Gabriel Nur Ramadhani','433','433@santri.sekolah.id',NULL,'$2y$12$xqsIvt3lRlX4D2MwqcRaveFGkpV8hQQoRctoYsquJvkZLrRFQGPHy','siswa',7,'433',NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(89,'Ahmad Dzaki Mubarok','431','431@santri.sekolah.id',NULL,'$2y$12$cBeJ5Pedu1IGkWCgC8yTIuBfnBtfbePOQPzSr/r0ayhFNLgX7UbkO','siswa',7,'431',NULL,'2026-10-27 07:07:05','2026-10-27 07:07:05'),(90,'Muhammad Kayyis Al-Wijdan','542','542@santri.sekolah.id',NULL,'$2y$12$ViaRWbEwOy3Q2BCFAKJ29uwYcloFJgddSXpH2lhTXUPl.zhHIpGOe','siswa',7,'542',NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(91,'Febrian Tri Anjasmoro','543','543@santri.sekolah.id',NULL,'$2y$12$ar14nZalcjUSWZzvgJuCl.ipF0rjeav9geBuUxfWuM.cg03yZv.Du','siswa',7,'543',NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(92,'Ahmad Athoillah Ali Zainal Abidin Ar-ruhami','2651','2651@santri.sekolah.id',NULL,'$2y$12$.t7OxiGjeByrINInnH5ZSezaHqG5V68k0Cf3ZCOF52Gh43epoyj.y','siswa',7,'2651',NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(93,'Alaika Rizki Hasan Mubarok','544','544@santri.sekolah.id',NULL,'$2y$12$A4AfKnTzR7UhgdmvJ5P0.uI4/bpIiItK18domQpO2vRWRPZaa3HRK','siswa',7,'544',NULL,'2026-10-27 07:07:06','2026-10-27 07:07:06'),(94,'Samuel Putra Sugara','545','545@santri.sekolah.id',NULL,'$2y$12$TlFTVpWIq6vT.4nmvgpLTuPncqUmDjp/hVJ.u8TfnZcINpk2JYEAm','siswa',7,'545',NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(95,'Muhammad Beryl Esa Alvian Hafid','546','546@santri.sekolah.id',NULL,'$2y$12$X/8vQKideDFbI584Jx7qe.ILv.EyugG1I2G4Vd8Qml1DBQ0BVJATq','siswa',7,'546',NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(96,'Ahmad Mahdi Sabilal Haq','547','547@santri.sekolah.id',NULL,'$2y$12$.SH79vMH2Q3mG5XCPJdHtOHYG64HKyW0U2UpzBfwWhnMhi.aQ2nwe','siswa',7,'547',NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(97,'Muahmmad Bassam Rowaiul Bayan','2627','2627@santri.sekolah.id',NULL,'$2y$12$WrN/PwYZj7AnENy0kL0pOOwlgL/wflNtim/dQ2ewfzxyKY/7PxLm2','siswa',7,'2627',NULL,'2026-10-27 07:07:07','2026-10-27 07:07:07'),(98,'Shofia Idris','548','548@santri.sekolah.id',NULL,'$2y$12$AcEsThbgDYt7BGRRR0pp8Oky9UVfvb.P7dPU6SrM3hdsMfrlDhU1y','siswa',7,'548',NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(99,'Nuri Firdaus','549','549@santri.sekolah.id',NULL,'$2y$12$mos67K5TV0djjvNWVW0KGuX5TkVs/8DW.U4nq90GYATHGXEpl6j4q','siswa',7,'549',NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(100,'Indana Najma Lailatul Qodariyah','5410','5410@santri.sekolah.id',NULL,'$2y$12$iiUDzP1pIJK.G6aGGpc3.O6A6BzyN9qH3ZzbKhny9sFIqfo1.8QuC','siswa',7,'5410',NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(101,'Hilwa Qudrotul Karimah','541123','541123@santri.sekolah.id',NULL,'$2y$12$6uconZoSN99Ygf81IN3VcejizTrydE9xHwEZ/DIS.F.POxM78Ilea','siswa',7,'541123',NULL,'2026-10-27 07:07:08','2026-10-27 07:07:08'),(102,'Najwa','5411','5411@santri.sekolah.id',NULL,'$2y$12$oA1DzH1T/CZZUX7RzzWsGuVNTdlNihKwELuvpeIqUuYJngufGBJD2','siswa',7,'5411',NULL,'2026-10-27 07:07:09','2026-10-27 07:07:09'),(103,'Anisa Shofyah Maulidia Arifin','5412','5412@santri.sekolah.id',NULL,'$2y$12$gbgqNtxQQScG3ZB9GvI1fOT9RjWrFya7mBWXiSFg/5jW50cVc0E.6','siswa',7,'5412',NULL,'2026-10-27 07:07:09','2026-10-27 07:07:09'),(104,'Ustadzah Dinil','dinil',NULL,NULL,'$2y$12$Y/6lRB79WPS/CISG7OieEuuyBu3JqvkQLlbbtz/capscI749SjWhS','guru',7,NULL,NULL,'2026-10-27 09:14:13','2026-10-27 09:14:27');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_logs`
--

DROP TABLE IF EXISTS `whatsapp_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fonnte_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nomor_tujuan` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_penerima` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pesan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe_pesan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` enum('pending','sent','delivered','read','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `keterangan_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `retry_count` int unsigned NOT NULL DEFAULT '0',
  `last_retried_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_logs_status_created_at_index` (`status`,`created_at`),
  KEY `whatsapp_logs_tipe_pesan_created_at_index` (`tipe_pesan`,`created_at`),
  KEY `whatsapp_logs_fonnte_id_index` (`fonnte_id`),
  KEY `whatsapp_logs_nomor_tujuan_index` (`nomor_tujuan`),
  KEY `whatsapp_logs_nama_penerima_index` (`nama_penerima`),
  KEY `whatsapp_logs_tipe_pesan_index` (`tipe_pesan`),
  KEY `whatsapp_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_logs`
--

LOCK TABLES `whatsapp_logs` WRITE;
/*!40000 ALTER TABLE `whatsapp_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-27 23:58:25
