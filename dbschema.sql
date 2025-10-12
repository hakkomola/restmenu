CREATE DATABASE  IF NOT EXISTS `modifero_restmenu` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;
USE `modifero_restmenu`;
-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: gator3302.hostgator.com    Database: modifero_restmenu
-- ------------------------------------------------------
-- Server version	5.7.23-23

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
-- Table structure for table `Languages`
--

DROP TABLE IF EXISTS `Languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Languages` (
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `LangName` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT '1',
  `SortOrder` int(11) DEFAULT NULL,
  PRIMARY KEY (`LangCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuCategories`
--

DROP TABLE IF EXISTS `MenuCategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuCategories` (
  `CategoryID` int(11) NOT NULL AUTO_INCREMENT,
  `RestaurantID` int(11) NOT NULL,
  `CategoryName` varchar(255) NOT NULL,
  `ImageURL` varchar(500) DEFAULT NULL,
  `SortOrder` int(11) DEFAULT '0',
  PRIMARY KEY (`CategoryID`),
  KEY `RestaurantID` (`RestaurantID`),
  CONSTRAINT `MenuCategories_ibfk_1` FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants` (`RestaurantID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuCategoryTranslations`
--

DROP TABLE IF EXISTS `MenuCategoryTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuCategoryTranslations` (
  `CategoryID` int(11) NOT NULL,
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `Name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `Description` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`CategoryID`,`LangCode`),
  KEY `ix_mct_lang` (`LangCode`),
  CONSTRAINT `fk_mct_cat` FOREIGN KEY (`CategoryID`) REFERENCES `MenuCategories` (`CategoryID`) ON DELETE CASCADE,
  CONSTRAINT `fk_mct_lang` FOREIGN KEY (`LangCode`) REFERENCES `Languages` (`LangCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuImages`
--

DROP TABLE IF EXISTS `MenuImages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuImages` (
  `MenuImageID` int(11) NOT NULL AUTO_INCREMENT,
  `MenuItemID` int(11) NOT NULL,
  `ImageURL` varchar(500) NOT NULL,
  PRIMARY KEY (`MenuImageID`),
  KEY `MenuItemID` (`MenuItemID`),
  CONSTRAINT `MenuImages_ibfk_1` FOREIGN KEY (`MenuItemID`) REFERENCES `MenuItems` (`MenuItemID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuItemOptionTranslations`
--

DROP TABLE IF EXISTS `MenuItemOptionTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuItemOptionTranslations` (
  `OptionID` int(11) NOT NULL,
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `Name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`OptionID`,`LangCode`),
  KEY `LangCode` (`LangCode`),
  CONSTRAINT `MenuItemOptionTranslations_ibfk_1` FOREIGN KEY (`OptionID`) REFERENCES `MenuItemOptions` (`OptionID`) ON DELETE CASCADE,
  CONSTRAINT `MenuItemOptionTranslations_ibfk_2` FOREIGN KEY (`LangCode`) REFERENCES `Languages` (`LangCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuItemOptions`
--

DROP TABLE IF EXISTS `MenuItemOptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuItemOptions` (
  `OptionID` int(11) NOT NULL AUTO_INCREMENT,
  `MenuItemID` int(11) NOT NULL,
  `OptionName` varchar(255) NOT NULL,
  `Price` decimal(10,2) NOT NULL,
  `IsDefault` tinyint(1) NOT NULL DEFAULT '0',
  `SortOrder` int(11) DEFAULT '0',
  PRIMARY KEY (`OptionID`),
  KEY `MenuItemID` (`MenuItemID`),
  CONSTRAINT `MenuItemOptions_ibfk_1` FOREIGN KEY (`MenuItemID`) REFERENCES `MenuItems` (`MenuItemID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuItemTranslations`
--

DROP TABLE IF EXISTS `MenuItemTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuItemTranslations` (
  `MenuItemID` int(11) NOT NULL,
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `Name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `Description` text COLLATE utf8_unicode_ci,
  `Allergens` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`MenuItemID`,`LangCode`),
  KEY `ix_mit_lang` (`LangCode`),
  CONSTRAINT `fk_mit_item` FOREIGN KEY (`MenuItemID`) REFERENCES `MenuItems` (`MenuItemID`) ON DELETE CASCADE,
  CONSTRAINT `fk_mit_lang` FOREIGN KEY (`LangCode`) REFERENCES `Languages` (`LangCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `MenuItems`
--

DROP TABLE IF EXISTS `MenuItems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MenuItems` (
  `MenuItemID` int(11) NOT NULL AUTO_INCREMENT,
  `RestaurantID` int(11) NOT NULL,
  `MenuName` varchar(255) NOT NULL,
  `Description` text,
  `Price` decimal(10,2) NOT NULL,
  `SortOrder` int(11) DEFAULT '0',
  `SubCategoryID` int(11) DEFAULT NULL,
  PRIMARY KEY (`MenuItemID`),
  KEY `RestaurantID` (`RestaurantID`),
  KEY `menuitems_ibfk_3` (`SubCategoryID`),
  CONSTRAINT `MenuItems_ibfk_2` FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants` (`RestaurantID`) ON DELETE CASCADE,
  CONSTRAINT `MenuItems_ibfk_3` FOREIGN KEY (`SubCategoryID`) REFERENCES `SubCategories` (`SubCategoryID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `OrderItemOptions`
--

DROP TABLE IF EXISTS `OrderItemOptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OrderItemOptions` (
  `OrderItemOptionID` int(11) NOT NULL AUTO_INCREMENT,
  `OrderItemID` int(11) NOT NULL,
  `OptionID` int(11) NOT NULL,
  `OptionName` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `OptionPrice` decimal(10,2) DEFAULT '0.00',
  `StatusID` int(11) DEFAULT NULL,
  PRIMARY KEY (`OrderItemOptionID`),
  KEY `OrderItemID` (`OrderItemID`),
  KEY `StatusID` (`StatusID`),
  CONSTRAINT `fk_oio_orderitem` FOREIGN KEY (`OrderItemID`) REFERENCES `OrderItems` (`OrderItemID`) ON DELETE CASCADE,
  CONSTRAINT `fk_oio_status` FOREIGN KEY (`StatusID`) REFERENCES `OrderStatuses` (`StatusID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `OrderItems`
--

DROP TABLE IF EXISTS `OrderItems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OrderItems` (
  `OrderItemID` int(11) NOT NULL AUTO_INCREMENT,
  `OrderID` int(11) NOT NULL,
  `OptionID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL DEFAULT '1',
  `BasePrice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `TotalPrice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `StatusID` int(11) DEFAULT NULL,
  `Note` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`OrderItemID`),
  KEY `OrderID` (`OrderID`),
  KEY `MenuItemID` (`OptionID`),
  KEY `StatusID` (`StatusID`),
  CONSTRAINT `fk_orderitems_order` FOREIGN KEY (`OrderID`) REFERENCES `Orders` (`OrderID`) ON DELETE CASCADE,
  CONSTRAINT `fk_orderitems_status` FOREIGN KEY (`StatusID`) REFERENCES `OrderStatuses` (`StatusID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `OrderStatusTranslations`
--

DROP TABLE IF EXISTS `OrderStatusTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OrderStatusTranslations` (
  `StatusID` int(11) NOT NULL,
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `Name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `Description` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`StatusID`,`LangCode`),
  KEY `LangCode` (`LangCode`),
  CONSTRAINT `fk_ost_lang` FOREIGN KEY (`LangCode`) REFERENCES `Languages` (`LangCode`),
  CONSTRAINT `fk_ost_status` FOREIGN KEY (`StatusID`) REFERENCES `OrderStatuses` (`StatusID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `OrderStatuses`
--

DROP TABLE IF EXISTS `OrderStatuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OrderStatuses` (
  `StatusID` int(11) NOT NULL AUTO_INCREMENT,
  `Code` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `SortOrder` int(11) DEFAULT '0',
  `IsActive` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`StatusID`),
  UNIQUE KEY `Code` (`Code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Orders`
--

DROP TABLE IF EXISTS `Orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Orders` (
  `OrderID` int(11) NOT NULL AUTO_INCREMENT,
  `RestaurantID` int(11) NOT NULL,
  `TableID` int(11) DEFAULT NULL,
  `OrderCode` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `StatusID` int(11) DEFAULT NULL,
  `TotalPrice` decimal(10,2) DEFAULT '0.00',
  `Note` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`OrderID`),
  KEY `RestaurantID` (`RestaurantID`),
  KEY `TableID` (`TableID`),
  KEY `StatusID` (`StatusID`),
  CONSTRAINT `fk_orders_restaurant` FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants` (`RestaurantID`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_status` FOREIGN KEY (`StatusID`) REFERENCES `OrderStatuses` (`StatusID`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_table` FOREIGN KEY (`TableID`) REFERENCES `RestaurantTables` (`TableID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RestaurantLanguages`
--

DROP TABLE IF EXISTS `RestaurantLanguages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RestaurantLanguages` (
  `RestaurantID` int(11) NOT NULL,
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `IsDefault` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`RestaurantID`,`LangCode`),
  KEY `fk_rl_lang` (`LangCode`),
  CONSTRAINT `fk_rl_lang` FOREIGN KEY (`LangCode`) REFERENCES `Languages` (`LangCode`),
  CONSTRAINT `fk_rl_rest` FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants` (`RestaurantID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `RestaurantTables`
--

DROP TABLE IF EXISTS `RestaurantTables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RestaurantTables` (
  `TableID` int(11) NOT NULL AUTO_INCREMENT,
  `RestaurantID` int(11) NOT NULL,
  `Name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `Code` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT '1',
  `CreatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`TableID`),
  UNIQUE KEY `uniq_code` (`Code`),
  UNIQUE KEY `uniq_rest_table_name` (`RestaurantID`,`Name`),
  CONSTRAINT `fk_tables_restaurant` FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants` (`RestaurantID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Restaurants`
--

DROP TABLE IF EXISTS `Restaurants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Restaurants` (
  `RestaurantID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `NameHTML` text,
  `Email` varchar(255) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Phone` varchar(50) DEFAULT NULL,
  `Address` varchar(500) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `BackgroundImage` varchar(500) DEFAULT NULL,
  `MainImage` varchar(255) DEFAULT NULL,
  `DefaultLanguage` varchar(5) DEFAULT 'tr',
  `MapUrl` varchar(255) DEFAULT NULL,
  `ThemeMode` varchar(10) DEFAULT 'auto',
  PRIMARY KEY (`RestaurantID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `SubCategories`
--

DROP TABLE IF EXISTS `SubCategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SubCategories` (
  `SubCategoryID` int(11) NOT NULL AUTO_INCREMENT,
  `CategoryID` int(11) NOT NULL,
  `RestaurantID` int(11) NOT NULL,
  `SubCategoryName` varchar(255) NOT NULL,
  `ImageURL` varchar(500) DEFAULT NULL,
  `SortOrder` int(11) DEFAULT '0',
  PRIMARY KEY (`SubCategoryID`),
  KEY `CategoryID` (`CategoryID`),
  KEY `RestaurantID` (`RestaurantID`),
  CONSTRAINT `SubCategories_ibfk_1` FOREIGN KEY (`CategoryID`) REFERENCES `MenuCategories` (`CategoryID`) ON DELETE CASCADE,
  CONSTRAINT `SubCategories_ibfk_2` FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants` (`RestaurantID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `SubCategoryTranslations`
--

DROP TABLE IF EXISTS `SubCategoryTranslations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SubCategoryTranslations` (
  `SubCategoryID` int(11) NOT NULL,
  `LangCode` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `Name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `Description` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`SubCategoryID`,`LangCode`),
  KEY `ix_sct_lang` (`LangCode`),
  CONSTRAINT `fk_sct_lang` FOREIGN KEY (`LangCode`) REFERENCES `Languages` (`LangCode`),
  CONSTRAINT `fk_sct_sub` FOREIGN KEY (`SubCategoryID`) REFERENCES `SubCategories` (`SubCategoryID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-12 12:02:09
