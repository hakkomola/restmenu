error_reporting(E_ALL);
ini_set('display_errors', 1);
--
-- Table structure for table `Languages`
--

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
