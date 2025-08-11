CREATE DATABASE  IF NOT EXISTS `idarisoft_laravel_test` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `idarisoft_laravel_test`;
-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: idarisoft_laravel_test
-- ------------------------------------------------------
-- Server version	8.0.41

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `api_credentials`
--

DROP TABLE IF EXISTS `api_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_credentials`
--

LOCK TABLES `api_credentials` WRITE;
/*!40000 ALTER TABLE `api_credentials` DISABLE KEYS */;
INSERT INTO `api_credentials` VALUES (1,'familyoutlet','woocommerce','https://yellowgreen-zebra-732284.hostingersite.com/wp-json/wc/v3','ck_3bcbfabf32ac76bb3c282cf6ed4a5a4e0edee8ab','cs_2759524161fbbcca1c4b1cf6e9028e43f59b9e28',NULL,'e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:08:00','2025-08-02 10:08:00'),(2,'familyoutlet','sirett','https://familyoutletsancarlos.com/webservice.php','114','CODIGO50X','0','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:08:00','2025-08-02 10:08:00'),(3,'familyoutlet','telegram','https://api.telegram.org','7445510927:AAEaXMFZe34rVH2VvH8ZvOIg8fF3KkgXzwU',NULL,'2098696980','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:08:00','2025-08-02 10:08:00');
/*!40000 ALTER TABLE `api_credentials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_logs`
--

DROP TABLE IF EXISTS `api_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_logs`
--

LOCK TABLES `api_logs` WRITE;
/*!40000 ALTER TABLE `api_logs` DISABLE KEYS */;
INSERT INTO `api_logs` VALUES (1,'familyoutlet','api/familyoutlet/woocommerce/categories','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:09:42'),(2,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:46:32'),(3,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:46:38'),(4,'familyoutlet','api/familyoutlet/telegram','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 10:46:43'),(5,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:04:25'),(6,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:04:38'),(7,'familyoutlet','api/familyoutlet/woocommerce/categories','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:04:49'),(8,'familyoutlet','api/familyoutlet/telegram','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:04:52'),(9,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:03'),(10,'familyoutlet','api/familyoutlet/woocommerce/categories','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:08'),(11,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:12'),(12,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:21'),(13,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:29'),(14,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:33'),(15,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:05:36'),(16,'familyoutlet','api/familyoutlet/woocommerce/categories/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:19:53'),(17,'familyoutlet','api/familyoutlet/woocommerce/categories','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:20:43'),(18,'familyoutlet','api/familyoutlet/sirett/familias','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:23:35'),(19,'familyoutlet','api/familyoutlet/sirett/familias','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:25:16'),(20,'familyoutlet','api/familyoutlet/woocommerce/categories/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:26:36'),(21,'familyoutlet','api/familyoutlet/woocommerce/categories/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:32:51'),(22,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 11:57:38'),(23,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:00:08'),(24,'familyoutlet','api/familyoutlet/woocommerce/products/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:11:15'),(25,'familyoutlet','api/familyoutlet/woocommerce/products/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:12:51'),(26,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:13:54'),(27,'familyoutlet','api/familyoutlet/woocommerce/products/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:26:11'),(28,'familyoutlet','api/familyoutlet/woocommerce/products/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:45:51'),(29,'familyoutlet','api/familyoutlet/woocommerce/products/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:50:50'),(30,'familyoutlet','api/familyoutlet/woocommerce/clean-all','DELETE','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 12:59:45'),(31,'familyoutlet','api/familyoutlet/woocommerce/clean-all','DELETE','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:00:33'),(32,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:00:53'),(33,'familyoutlet','api/familyoutlet/woocommerce/categories','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:01:01'),(34,'familyoutlet','api/familyoutlet/sirett','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:01:14'),(35,'familyoutlet','api/familyoutlet/woocommerce','GET','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:01:20'),(36,'familyoutlet','api/familyoutlet/woocommerce/clean-all','DELETE','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:02:33'),(37,'familyoutlet','api/familyoutlet/woocommerce/products/sync-from-sirett','POST','127.0.0.1','e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X','2025-08-02 13:03:09');
/*!40000 ALTER TABLE `api_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
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
-- Table structure for table `categorias_sincronizadas`
--

DROP TABLE IF EXISTS `categorias_sincronizadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_sincronizadas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `woocommerce_id` int DEFAULT NULL,
  `respuesta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_sincronizadas`
--

LOCK TABLES `categorias_sincronizadas` WRITE;
/*!40000 ALTER TABLE `categorias_sincronizadas` DISABLE KEYS */;
/*!40000 ALTER TABLE `categorias_sincronizadas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
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
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
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
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
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
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_08_02_020404_create_api_credentials_table',1),(5,'2025_08_02_034313_add_api_token_to_api_credentials_table',1),(6,'2025_08_02_040735_create_api_logs_table',1),(7,'2025_08_02_053443_create_categorias_sincronizadas_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
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
INSERT INTO `sessions` VALUES ('F0zHExvPqOCYN3APRVGAJWu6fefJRqpMpjI8MEP1',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoibVZDcTJJOTZuWlNDU2pPVFpMWGZaWjE0R2EzcTlncjkxUU1RQWpCciI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dzIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1754118025);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test User','test@example.com','2025-08-02 10:07:59','$2y$12$jlooJHpLKixoX49stpJ6Iul1AjEcMgzF4r4Re3sOoVPlSm2V7Egr2','ldSQaQYe4w','2025-08-02 10:08:00','2025-08-02 10:08:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'idarisoft_laravel_test'
--

--
-- Dumping routines for database 'idarisoft_laravel_test'
--
/*!50003 DROP PROCEDURE IF EXISTS `sp_guardar_agente` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`idarisoft`@`localhost` PROCEDURE `sp_guardar_agente`(IN `p_associate_id` INT, IN `p_first_name` VARCHAR(100), IN `p_last_name` VARCHAR(100), IN `p_office_name` VARCHAR(150), IN `p_remax_email` VARCHAR(150), IN `p_mobile` VARCHAR(50), IN `p_direct_phone` VARCHAR(50), IN `p_birthday` DATE, IN `p_start_date` DATE, IN `p_title` VARCHAR(100), IN `p_lang` VARCHAR(50), IN `p_url_img` TEXT, IN `p_country` VARCHAR(100), IN `p_location` VARCHAR(150), IN `p_property_type_es` VARCHAR(100), IN `p_property_title_es` VARCHAR(255), IN `p_public_remarks_es` TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM agentes_inmobiliarios WHERE associate_id = p_associate_id) THEN
    INSERT INTO agentes_inmobiliarios (
      associate_id, first_name, last_name, office_name, remax_email,
      mobile, direct_phone, birthday, start_date, title, lang,
      url_img, country, location, property_type_es, property_title_es, public_remarks_es
    ) VALUES (
      p_associate_id, p_first_name, p_last_name, p_office_name, p_remax_email,
      p_mobile, p_direct_phone, p_birthday, p_start_date, p_title, p_lang,
      p_url_img, p_country, p_location, p_property_type_es, p_property_title_es, p_public_remarks_es
    );
    
    SELECT 'insertado' AS resultado;
  ELSE
    SELECT 'existe' AS resultado;
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_insert_property_if_not_exists` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`idarisoft`@`localhost` PROCEDURE `sp_insert_property_if_not_exists`(IN `p_listing_id` VARCHAR(50), IN `p_associate_id` INT, IN `p_firstname` VARCHAR(100), IN `p_lastname` VARCHAR(100), IN `p_title` VARCHAR(100), IN `p_office_name` VARCHAR(255), IN `p_remax_email` VARCHAR(255), IN `p_mobile` VARCHAR(100), IN `p_country` VARCHAR(100), IN `p_price` DECIMAL(15,2), IN `p_location` VARCHAR(255), IN `p_bedrooms` INT, IN `p_bathrooms` INT, IN `p_area` DECIMAL(15,2), IN `p_status` VARCHAR(255), IN `p_url_img` TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM properties WHERE listing_id = p_listing_id) THEN
    INSERT INTO properties (
      listing_id, associate_id, firstname, lastname, title,
      office_name, remax_email, mobile, country, price, location,
      bedrooms, bathrooms, area, status, url_img
    ) VALUES (
      p_listing_id, p_associate_id, p_firstname, p_lastname, p_title,
      p_office_name, p_remax_email, p_mobile, p_country, p_price, p_location,
      p_bedrooms, p_bathrooms, p_area, p_status, p_url_img
    );
    SELECT 1 AS inserted;
  ELSE
    SELECT 0 AS inserted;
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-02  1:06:52
