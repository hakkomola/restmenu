
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
  `OrderUse` TINYINT(1) DEFAULT 0,
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




CREATE TABLE `RestaurantRoles` (
  `RoleID` INT AUTO_INCREMENT PRIMARY KEY,
  `RestaurantID` INT NOT NULL,
  `RoleName` VARCHAR(50) NOT NULL,
  `CanManageMenu` TINYINT(1) DEFAULT 0,
  `CanManageOrders` TINYINT(1) DEFAULT 0,
  `CanManageTables` TINYINT(1) DEFAULT 0,
  `CanManageUsers` TINYINT(1) DEFAULT 0,
  `IsAdmin` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants`(`RestaurantID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `RestaurantUsers` (
  `UserID` INT AUTO_INCREMENT PRIMARY KEY,
  `RestaurantID` INT NOT NULL,
  `FullName` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(255) NOT NULL,
  `PasswordHash` VARCHAR(255) NOT NULL,
  `RoleID` INT NOT NULL,
  `IsActive` TINYINT(1) DEFAULT 1,
  `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_rest_email` (`RestaurantID`, `Email`),
  FOREIGN KEY (`RestaurantID`) REFERENCES `Restaurants`(`RestaurantID`) ON DELETE CASCADE,
  FOREIGN KEY (`RoleID`) REFERENCES `RestaurantRoles`(`RoleID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


//****************************************************************************************//
ALTER TABLE Restaurants
  CONVERT TO CHARACTER SET utf8
  COLLATE utf8_unicode_ci;

ALTER TABLE RestaurantUsers
  CONVERT TO CHARACTER SET utf8
  COLLATE utf8_unicode_ci;
  
START TRANSACTION;

-- 1) ŞUBELER
CREATE TABLE IF NOT EXISTS `RestaurantBranches` (
  `BranchID` INT AUTO_INCREMENT PRIMARY KEY,
  `RestaurantID` INT NOT NULL,
  `BranchName` VARCHAR(255) NOT NULL,
  `Address` VARCHAR(500) DEFAULT NULL,
  `Phone` VARCHAR(50) DEFAULT NULL,
  `MapUrl` VARCHAR(255) DEFAULT NULL,
  `IsActive` TINYINT(1) DEFAULT 1,
  `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `rb_rest_fk` FOREIGN KEY (`RestaurantID`)
    REFERENCES `Restaurants`(`RestaurantID`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_rest_branchname` (`RestaurantID`,`BranchName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 2) ROLLER (Restaurant bazlı; global defaultlar için RestaurantID NULL)
CREATE TABLE IF NOT EXISTS `RestaurantRoles` (
  `RoleID` INT AUTO_INCREMENT PRIMARY KEY,
  `RestaurantID` INT NULL,
  `RoleName` VARCHAR(50) NOT NULL,
  `Permissions` JSON DEFAULT NULL,
  `IsSystem` TINYINT(1) DEFAULT 0, -- global defaultlar için 1 kullanılabilir
  CONSTRAINT `rr_rest_fk` FOREIGN KEY (`RestaurantID`)
    REFERENCES `Restaurants`(`RestaurantID`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_rest_rolename` (`RestaurantID`,`RoleName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Global (RestaurantID=NULL) varsayılan roller (idempotent ekleme)
INSERT INTO `RestaurantRoles` (`RestaurantID`,`RoleName`,`Permissions`,`IsSystem`)
SELECT * FROM (
  SELECT NULL, 'Admin',   JSON_OBJECT('menu', true, 'orders', true, 'tables', true, 'users', true, 'branches', true), 1 UNION ALL
  SELECT NULL, 'Garson',  JSON_OBJECT('menu', false,'orders', true, 'tables', true, 'users', false,'branches', false), 1 UNION ALL
  SELECT NULL, 'Mutfak',  JSON_OBJECT('menu', false,'orders', true, 'tables', false,'users', false,'branches', false), 1 UNION ALL
  SELECT NULL, 'Kasiyer', JSON_OBJECT('menu', false,'orders', true, 'tables', false,'users', false,'branches', false), 1
) AS t
WHERE NOT EXISTS (SELECT 1 FROM RestaurantRoles r WHERE r.RestaurantID IS NULL);

-- 3) KULLANICILAR (artık restoran içinde çok kullanıcı)
CREATE TABLE IF NOT EXISTS `RestaurantUsers` (
  `UserID` INT AUTO_INCREMENT PRIMARY KEY,
  `RestaurantID` INT NOT NULL,
  `FullName` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(255) NOT NULL,
  `PasswordHash` VARCHAR(255) NOT NULL,
  `IsActive` TINYINT(1) DEFAULT 1,
  `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `ru_rest_fk` FOREIGN KEY (`RestaurantID`)
    REFERENCES `Restaurants`(`RestaurantID`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_rest_email` (`RestaurantID`,`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 4) KULLANICI–ROL (çoklu rol ataması)
CREATE TABLE IF NOT EXISTS `RestaurantUserRoles` (
  `UserID` INT NOT NULL,
  `RoleID` INT NOT NULL,
  PRIMARY KEY (`UserID`,`RoleID`),
  CONSTRAINT `rur_user_fk` FOREIGN KEY (`UserID`)
    REFERENCES `RestaurantUsers`(`UserID`) ON DELETE CASCADE,
  CONSTRAINT `rur_role_fk` FOREIGN KEY (`RoleID`)
    REFERENCES `RestaurantRoles`(`RoleID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 5) KULLANICI–ŞUBE (hangi şubelerde yetkili?)
CREATE TABLE IF NOT EXISTS `RestaurantBranchUsers` (
  `UserID` INT NOT NULL,
  `BranchID` INT NOT NULL,
  PRIMARY KEY (`UserID`,`BranchID`),
  CONSTRAINT `rbu_user_fk` FOREIGN KEY (`UserID`)
    REFERENCES `RestaurantUsers`(`UserID`) ON DELETE CASCADE,
  CONSTRAINT `rbu_branch_fk` FOREIGN KEY (`BranchID`)
    REFERENCES `RestaurantBranches`(`BranchID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 6) OPERASYONEL TABLOLARA BranchID EKLE (mevcut veriyi korur)
ALTER TABLE `MenuCategories`     ADD COLUMN `BranchID` INT NULL AFTER `RestaurantID`;
ALTER TABLE `SubCategories`      ADD COLUMN `BranchID` INT NULL AFTER `RestaurantID`;
ALTER TABLE `MenuItems`          ADD COLUMN `BranchID` INT NULL AFTER `RestaurantID`;
ALTER TABLE `Orders`             ADD COLUMN `BranchID` INT NULL AFTER `RestaurantID`;
ALTER TABLE `RestaurantTables`   ADD COLUMN `BranchID` INT NULL AFTER `RestaurantID`;

ALTER TABLE `MenuCategories`     ADD CONSTRAINT `mc_branch_fk` FOREIGN KEY (`BranchID`) REFERENCES `RestaurantBranches`(`BranchID`) ON DELETE SET NULL;
ALTER TABLE `SubCategories`      ADD CONSTRAINT `sc_branch_fk` FOREIGN KEY (`BranchID`) REFERENCES `RestaurantBranches`(`BranchID`) ON DELETE SET NULL;
ALTER TABLE `MenuItems`          ADD CONSTRAINT `mi_branch_fk` FOREIGN KEY (`BranchID`) REFERENCES `RestaurantBranches`(`BranchID`) ON DELETE SET NULL;
ALTER TABLE `Orders`             ADD CONSTRAINT `o_branch_fk`  FOREIGN KEY (`BranchID`) REFERENCES `RestaurantBranches`(`BranchID`) ON DELETE SET NULL;
ALTER TABLE `RestaurantTables`   ADD CONSTRAINT `rt_branch_fk` FOREIGN KEY (`BranchID`) REFERENCES `RestaurantBranches`(`BranchID`) ON DELETE SET NULL;

-- 7) PERFORMANS İNDEKSLERİ (sık sorgu kombinasyonları)
CREATE INDEX `ix_mc_rest_branch` ON `MenuCategories` (`RestaurantID`,`BranchID`);
CREATE INDEX `ix_sc_rest_branch` ON `SubCategories`  (`RestaurantID`,`BranchID`);
CREATE INDEX `ix_mi_rest_branch` ON `MenuItems`      (`RestaurantID`,`BranchID`);
CREATE INDEX `ix_o_rest_branch`  ON `Orders`         (`RestaurantID`,`BranchID`);
CREATE INDEX `ix_rt_rest_branch` ON `RestaurantTables`(`RestaurantID`,`BranchID`);

COMMIT;


//***********************************************************************************************//


START TRANSACTION;

-- A) Her restoran için varsayılan bir şube oluştur (adı: 'Merkez Şube')
INSERT INTO RestaurantBranches (RestaurantID, BranchName, Address, IsActive)
SELECT r.RestaurantID, 'Merkez Şube', r.Address, 1
FROM Restaurants r
WHERE NOT EXISTS (
  SELECT 1 FROM RestaurantBranches b WHERE b.RestaurantID = r.RestaurantID
);

-- B) Her restoran için global default rollerden kopya oluştur (Admin, Garson, Mutfak, Kasiyer)
INSERT INTO RestaurantRoles (RestaurantID, RoleName, Permissions, IsSystem)
SELECT r.RestaurantID, g.RoleName, g.Permissions, 0
FROM Restaurants r
JOIN RestaurantRoles g ON g.RestaurantID IS NULL
WHERE NOT EXISTS (
  SELECT 1 FROM RestaurantRoles rr WHERE rr.RestaurantID = r.RestaurantID
);

-- C) Eski tek-hesap kullanıcıyı yeni sisteme taşı (Restaurants tablosundaki Email/PasswordHash)
--    FullName olarak restoran adını kullanıyoruz.
INSERT INTO RestaurantUsers (RestaurantID, FullName, Email, PasswordHash, IsActive)
SELECT r.RestaurantID, COALESCE(NULLIF(r.Name,''), CONCAT('Restoran #', r.RestaurantID)),
       r.Email, r.PasswordHash, 1
FROM Restaurants r
WHERE r.Email IS NOT NULL AND r.Email <> ''
  AND NOT EXISTS (
    SELECT 1 FROM RestaurantUsers u WHERE u.RestaurantID = r.RestaurantID AND u.Email = r.Email
  );

-- D) Bu kullanıcıyı 'Admin' rolüne ata (restoranın kopyalanmış Admin rolü)
INSERT INTO RestaurantUserRoles (UserID, RoleID)
SELECT u.UserID, rr.RoleID
FROM RestaurantUsers u
JOIN RestaurantRoles rr
  ON rr.RestaurantID = u.RestaurantID AND rr.RoleName = 'Admin'
WHERE NOT EXISTS (
  SELECT 1 FROM RestaurantUserRoles x WHERE x.UserID = u.UserID AND x.RoleID = rr.RoleID
);

-- E) Kullanıcıya "Merkez Şube" yetkisi ver
INSERT INTO RestaurantBranchUsers (UserID, BranchID)
SELECT u.UserID, b.BranchID
FROM RestaurantUsers u
JOIN RestaurantBranches b ON b.RestaurantID = u.RestaurantID AND b.BranchName = 'Merkez Şube'
WHERE NOT EXISTS (
  SELECT 1 FROM RestaurantBranchUsers x WHERE x.UserID = u.UserID AND x.BranchID = b.BranchID
);

-- F) Operasyonel verileri varsayılan şubeye bağla
--    NOT: Bir restoranda birden fazla Branch varsa (manuel eklediysen), burada "Merkez Şube" tercih edilecek.
UPDATE MenuCategories mc
JOIN RestaurantBranches b ON b.RestaurantID = mc.RestaurantID AND b.BranchName = 'Merkez Şube'
SET mc.BranchID = COALESCE(mc.BranchID, b.BranchID)
WHERE mc.BranchID IS NULL;

UPDATE SubCategories sc
JOIN RestaurantBranches b ON b.RestaurantID = sc.RestaurantID AND b.BranchName = 'Merkez Şube'
SET sc.BranchID = COALESCE(sc.BranchID, b.BranchID)
WHERE sc.BranchID IS NULL;

UPDATE MenuItems mi
JOIN RestaurantBranches b ON b.RestaurantID = mi.RestaurantID AND b.BranchName = 'Merkez Şube'
SET mi.BranchID = COALESCE(mi.BranchID, b.BranchID)
WHERE mi.BranchID IS NULL;

UPDATE RestaurantTables rt
JOIN RestaurantBranches b ON b.RestaurantID = rt.RestaurantID AND b.BranchName = 'Merkez Şube'
SET rt.BranchID = COALESCE(rt.BranchID, b.BranchID)
WHERE rt.BranchID IS NULL;

UPDATE Orders o
JOIN RestaurantBranches b ON b.RestaurantID = o.RestaurantID AND b.BranchName = 'Merkez Şube'
SET o.BranchID = COALESCE(o.BranchID, b.BranchID)
WHERE o.BranchID IS NULL;

COMMIT;
//***************************************************//