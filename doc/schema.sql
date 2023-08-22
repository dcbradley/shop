-- MariaDB dump 10.19  Distrib 10.5.19-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: eshop
-- ------------------------------------------------------
-- Server version	10.5.19-MariaDB-0+deb11u2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auditlog`
--

DROP TABLE IF EXISTS `auditlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditlog` (
  `AUDITLOG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ACTING_USER_ID` int(11) DEFAULT NULL,
  `CHANGED_USER_ID` int(11) DEFAULT NULL,
  `CHANGED_GROUP_ID` int(11) DEFAULT NULL,
  `CHANGED_VENDOR_ID` int(11) DEFAULT NULL,
  `CHANGED_PART_ID` int(11) DEFAULT NULL,
  `CHANGED_ORDER_ID` int(11) DEFAULT NULL,
  `CHANGED_WORK_ORDER_ID` int(11) DEFAULT NULL,
  `CHANGED_CONTRACT_ID` int(11) DEFAULT NULL,
  `DATE` datetime NOT NULL,
  `MSG` text NOT NULL DEFAULT '',
  `IPADDR` varchar(50) DEFAULT NULL,
  `LOGIN_METHOD` varchar(10) NOT NULL,
  PRIMARY KEY (`AUDITLOG_ID`),
  KEY `CHANGED_USER_ID` (`CHANGED_USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `back_order`
--

DROP TABLE IF EXISTS `back_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `back_order` (
  `WORK_ORDER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `WORK_ORDER_NUM` varchar(10) NOT NULL,
  `CHECKOUT_ORDER` tinyint(1) NOT NULL DEFAULT 0,
  `STOCK_ORDER` tinyint(1) NOT NULL DEFAULT 0,
  `IS_QUOTE` tinyint(1) NOT NULL DEFAULT 0,
  `ORDERED_BY` int(11) NOT NULL,
  `GROUP_ID` int(11) NOT NULL,
  `FUNDING_SOURCE_ID` int(11) NOT NULL,
  `STATUS` char(1) NOT NULL DEFAULT '',
  `CREATED` datetime NOT NULL,
  `REVIEWED` datetime DEFAULT NULL,
  `QUEUED` date DEFAULT NULL,
  `COMPLETED` date DEFAULT NULL,
  `CANCELED` date DEFAULT NULL,
  `BILLED` date DEFAULT NULL,
  `CLOSED` date DEFAULT NULL,
  `CONTRACT_ID` int(11) DEFAULT NULL,
  `CONTRACT_HOURLY_RATE` decimal(7,2) DEFAULT NULL,
  `QUOTE` decimal(7,2) DEFAULT NULL,
  `INVENTORY_NUM` varchar(20) NOT NULL DEFAULT '',
  `HEALTH_HAZARD` char(1) NOT NULL DEFAULT '',
  `DESCRIPTION` text NOT NULL,
  `ADMIN_NOTES` text DEFAULT NULL,
  PRIMARY KEY (`WORK_ORDER_ID`),
  KEY `STATUS` (`STATUS`),
  KEY `WORK_ORDER_NUM` (`WORK_ORDER_NUM`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_batch`
--

DROP TABLE IF EXISTS `billing_batch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_batch` (
  `BILLING_BATCH_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BATCH_NAME` varchar(60) NOT NULL,
  `CREATED` datetime NOT NULL,
  `PROCESSED` datetime DEFAULT NULL,
  `ADMIN_NOTES` varchar(200) NOT NULL DEFAULT '',
  `ARCHIVED` datetime DEFAULT NULL,
  PRIMARY KEY (`BILLING_BATCH_ID`),
  UNIQUE KEY `BATCH_NAME` (`BATCH_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checkout`
--

DROP TABLE IF EXISTS `checkout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checkout` (
  `CHECKOUT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) NOT NULL,
  `GROUP_ID` int(11) NOT NULL,
  `FUNDING_SOURCE_ID` int(11) NOT NULL,
  `BILLING_WORK_ORDER_ID` int(11) DEFAULT NULL,
  `PART_ID` int(11) NOT NULL,
  `QTY` decimal(7,2) NOT NULL,
  `UNITS` varchar(10) NOT NULL,
  `PRICE` decimal(7,2) NOT NULL,
  `TOTAL` decimal(7,2) NOT NULL,
  `DATE` datetime NOT NULL,
  `DELETED` datetime DEFAULT NULL,
  `LOGIN_METHOD` varchar(10) NOT NULL,
  `IPADDR` varchar(50) NOT NULL,
  PRIMARY KEY (`CHECKOUT_ID`),
  KEY `USER_ID` (`USER_ID`),
  KEY `DATE` (`DATE`),
  KEY `PART_ID` (`PART_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `funding_source`
--

DROP TABLE IF EXISTS `funding_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `funding_source` (
  `FUNDING_SOURCE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PURCHASING_FUNDING_SOURCE_ID` int(11) DEFAULT NULL,
  `GROUP_ID` int(11) NOT NULL,
  `FUNDING_DESCRIPTION` varchar(256) NOT NULL,
  `FUNDING_DEPT` varchar(20) NOT NULL,
  `FUNDING_PROJECT` varchar(20) NOT NULL,
  `FUNDING_PROGRAM` varchar(20) NOT NULL,
  `PI_NAME` varchar(128) NOT NULL,
  `PI_EMAIL` varchar(60) NOT NULL DEFAULT '',
  `PI_PHONE` varchar(30) NOT NULL DEFAULT '',
  `BILLING_CONTACT_NAME` varchar(128) NOT NULL DEFAULT '',
  `BILLING_CONTACT_EMAIL` varchar(60) NOT NULL DEFAULT '',
  `BILLING_CONTACT_PHONE` varchar(30) NOT NULL DEFAULT '',
  `FUNDING_FUND` varchar(20) NOT NULL,
  `CREATED` datetime NOT NULL,
  `DELETED` tinyint(2) NOT NULL DEFAULT 0,
  `FUNDING_ACTIVE` tinyint(2) NOT NULL DEFAULT 1,
  `FUNDING_START` date DEFAULT NULL,
  `FUNDING_END` date DEFAULT NULL,
  PRIMARY KEY (`FUNDING_SOURCE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `loan`
--

DROP TABLE IF EXISTS `loan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan` (
  `LOAN_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) NOT NULL,
  `START` datetime NOT NULL,
  `RETURNED` datetime DEFAULT NULL,
  `ITEM_NAME` varchar(60) NOT NULL,
  `EXPECTED_RETURN` datetime DEFAULT NULL,
  `ADMIN_NOTES` varchar(1024) NOT NULL DEFAULT '',
  `REMINDED` date DEFAULT NULL,
  PRIMARY KEY (`LOAN_ID`),
  KEY `USER_ID` (`USER_ID`),
  KEY `STOP` (`RETURNED`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `part`
--

DROP TABLE IF EXISTS `part`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part` (
  `PART_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STOCK_NUM` varchar(20) NOT NULL,
  `STATUS` varchar(20) NOT NULL DEFAULT '',
  `MANUFACTURER` varchar(40) NOT NULL DEFAULT '',
  `MAN_NUM` varchar(20) NOT NULL DEFAULT '',
  `DESCRIPTION` varchar(60) NOT NULL DEFAULT '',
  `VENDOR_ID` int(11) DEFAULT NULL,
  `VEND_NUM` varchar(20) NOT NULL DEFAULT '',
  `CREATED` date NOT NULL,
  `UPDATED` date DEFAULT NULL,
  `QTY` int(11) NOT NULL DEFAULT 0,
  `QTY_CORRECT` tinyint(1) NOT NULL DEFAULT 0,
  `QTY_CORRECT_DATE` date DEFAULT NULL,
  `QTY_LAST_ORDERED` int(11) NOT NULL DEFAULT 0,
  `MIN_QTY` int(11) NOT NULL DEFAULT 0,
  `PRICE` decimal(7,2) NOT NULL,
  `COST` decimal(7,2) NOT NULL,
  `MARKUP_TYPE` tinyint(2) NOT NULL,
  `UNITS` varchar(10) NOT NULL DEFAULT '',
  `NO_RECOVER` tinyint(1) NOT NULL DEFAULT 0,
  `LOCATION` varchar(30) NOT NULL DEFAULT '',
  `SECTION` varchar(15) NOT NULL DEFAULT '',
  `ROW` varchar(15) NOT NULL DEFAULT '',
  `SHELF` varchar(15) NOT NULL DEFAULT '',
  `IMAGE` varchar(30) NOT NULL DEFAULT '',
  `INACTIVE` tinyint(1) NOT NULL,
  `BACKUP_QTY` int(11) NOT NULL DEFAULT 0,
  `BACKUP_LOCATION` varchar(30) NOT NULL DEFAULT '',
  `BACKUP_SECTION` varchar(15) NOT NULL DEFAULT '',
  `BACKUP_ROW` varchar(15) NOT NULL DEFAULT '',
  `BACKUP_SHELF` varchar(15) NOT NULL DEFAULT '',
  `NOTES` text DEFAULT NULL,
  PRIMARY KEY (`PART_ID`),
  UNIQUE KEY `STOCK_NUM` (`STOCK_NUM`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `part_order`
--

DROP TABLE IF EXISTS `part_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part_order` (
  `ORDER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PART_ID` int(11) NOT NULL,
  `VENDOR_ID` int(11) NOT NULL,
  `ORDERED` date NOT NULL,
  `CLOSED` date DEFAULT NULL,
  `COST` decimal(7,2) NOT NULL,
  `MARKUP_TYPE` tinyint(2) NOT NULL,
  `PRICE` decimal(7,2) NOT NULL,
  `UNITS` varchar(10) NOT NULL,
  `QTY` int(11) NOT NULL,
  `RECEIVED` int(11) DEFAULT NULL,
  `SHIP_TO` varchar(60) NOT NULL,
  `MANUFACTURER` varchar(40) NOT NULL,
  `MAN_NUM` varchar(20) NOT NULL,
  `VEND_NUM` varchar(20) NOT NULL,
  `PO_ID` varchar(15) NOT NULL,
  PRIMARY KEY (`ORDER_ID`),
  KEY `ORDERED` (`ORDERED`),
  KEY `PART_ID` (`PART_ID`),
  KEY `VENDOR_ID` (`VENDOR_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shared_auditlog`
--

DROP TABLE IF EXISTS `shared_auditlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shared_auditlog` (
  `AUDITLOG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ACTING_USER_ID` int(11) DEFAULT NULL,
  `CHANGED_USER_ID` int(11) DEFAULT NULL,
  `CHANGED_GROUP_ID` int(11) DEFAULT NULL,
  `CHANGED_VENDOR_ID` int(11) DEFAULT NULL,
  `CHANGED_PART_ID` int(11) DEFAULT NULL,
  `CHANGED_ORDER_ID` int(11) DEFAULT NULL,
  `DATE` datetime NOT NULL,
  `MSG` text NOT NULL DEFAULT '',
  `IPADDR` varchar(50) DEFAULT NULL,
  `LOGIN_METHOD` varchar(10) NOT NULL,
  `SHOP` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`AUDITLOG_ID`),
  KEY `CHANGED_USER_ID` (`CHANGED_USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_contract`
--

DROP TABLE IF EXISTS `shop_contract`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_contract` (
  `CONTRACT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CONTRACT_NAME` varchar(20) NOT NULL,
  `CONTRACT_HOURLY_RATE` decimal(7,2) NOT NULL,
  `CONTRACT_HOURLY_RATE_UPDATED` datetime NOT NULL,
  `HIDE` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`CONTRACT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timesheet`
--

DROP TABLE IF EXISTS `timesheet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timesheet` (
  `TIMESHEET_ID` int(11) NOT NULL AUTO_INCREMENT,
  `WORK_ORDER_ID` int(11) NOT NULL,
  `USER_ID` int(11) NOT NULL,
  `DATE` date NOT NULL,
  `HOURS` decimal(4,2) NOT NULL,
  `NOTES` varchar(100) NOT NULL,
  PRIMARY KEY (`TIMESHEET_ID`),
  KEY `WORK_ORDER_ID` (`WORK_ORDER_ID`),
  KEY `USER_ID` (`USER_ID`,`DATE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `USER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NETID` varchar(60) DEFAULT NULL,
  `BLOCK_LOGIN` tinyint(1) NOT NULL DEFAULT 0,
  `LOCAL_LOGIN` varchar(30) DEFAULT NULL,
  `LAST_NAME` varchar(30) NOT NULL DEFAULT '',
  `FIRST_NAME` varchar(30) NOT NULL DEFAULT '',
  `EMAIL` varchar(60) NOT NULL DEFAULT '',
  `PHONE` varchar(20) NOT NULL DEFAULT '',
  `ROOM` varchar(40) NOT NULL DEFAULT '',
  `DEPARTMENT` varchar(30) NOT NULL DEFAULT '',
  `EMPLOYEE_TYPE` varchar(20) NOT NULL DEFAULT '',
  `DEFAULT_GROUP_ID` int(11) DEFAULT NULL,
  `DEFAULT_FUNDING_SOURCE_ID` int(11) DEFAULT NULL,
  `CREATED` datetime NOT NULL,
  `LAST_LOGIN` datetime DEFAULT NULL,
  `LAST_LDAP_LOOKUP` datetime DEFAULT NULL,
  `SHOP_LAST_LOGIN` datetime DEFAULT NULL,
  `SHOP_ADMIN` tinyint(1) NOT NULL DEFAULT 0,
  `SHOP_WORKER` tinyint(1) NOT NULL DEFAULT 0,
  `LEADER_ID` int(11) DEFAULT NULL,
  `ADDED_VIA_SHOP` tinyint(1) NOT NULL DEFAULT 0,
  `IS_MEMBER_OF` varchar(2048) DEFAULT NULL,
  `AUTO_SET_DEPARTMENTS` varchar(60) DEFAULT NULL,
  `ADMIN_SET_DEPARTMENTS` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `local_login` (`LOCAL_LOGIN`),
  UNIQUE KEY `netid` (`NETID`),
  KEY `GROUPID` (`DEFAULT_GROUP_ID`),
  KEY `GROUP_ID` (`LEADER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_group`
--

DROP TABLE IF EXISTS `user_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_group` (
  `GROUP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `GROUP_NAME` varchar(100) NOT NULL,
  `PURCHASING_GROUP_ID` int(11) DEFAULT NULL,
  `DELETED` tinyint(2) NOT NULL DEFAULT 0,
  `USER_NETIDS` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`GROUP_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor`
--

DROP TABLE IF EXISTS `vendor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor` (
  `VENDOR_ID` int(11) NOT NULL AUTO_INCREMENT,
  `UW_NUMBER` varchar(20) NOT NULL,
  `NAME` varchar(60) NOT NULL,
  `ADDRESS1` varchar(60) NOT NULL,
  `ADDRESS2` varchar(60) NOT NULL,
  `CITY_STATE_ZIP` varchar(60) NOT NULL,
  `PHONE` varchar(20) NOT NULL,
  `PHONE_EXT` varchar(20) NOT NULL,
  `FAX` varchar(20) NOT NULL,
  `REP` varchar(60) NOT NULL,
  `DISCOUNT` decimal(5,2) DEFAULT NULL,
  `BLANKET` varchar(60) NOT NULL,
  `EMAIL` varchar(60) NOT NULL,
  `WWWURL` varchar(60) NOT NULL,
  `UPDATED` date DEFAULT NULL,
  `CREATED` date DEFAULT NULL,
  `INACTIVE` tinyint(1) NOT NULL,
  `NOTES` text DEFAULT NULL,
  PRIMARY KEY (`VENDOR_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_order`
--

DROP TABLE IF EXISTS `work_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order` (
  `WORK_ORDER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `WORK_ORDER_NUM` varchar(10) NOT NULL,
  `CHECKOUT_ORDER` tinyint(1) NOT NULL DEFAULT 0,
  `STOCK_ORDER` tinyint(1) NOT NULL DEFAULT 0,
  `IS_QUOTE` tinyint(1) NOT NULL DEFAULT 0,
  `ORDERED_BY` int(11) NOT NULL,
  `GROUP_ID` int(11) NOT NULL,
  `FUNDING_SOURCE_ID` int(11) NOT NULL,
  `STATUS` char(1) NOT NULL DEFAULT '',
  `CREATED` datetime NOT NULL,
  `REVIEWED` datetime DEFAULT NULL,
  `QUEUED` date DEFAULT NULL,
  `COMPLETED` date DEFAULT NULL,
  `CANCELED` date DEFAULT NULL,
  `BILLED` date DEFAULT NULL,
  `CLOSED` date DEFAULT NULL,
  `CONTRACT_ID` int(11) DEFAULT NULL,
  `CONTRACT_HOURLY_RATE` decimal(7,2) DEFAULT NULL,
  `QUOTE` decimal(7,2) DEFAULT NULL,
  `INVENTORY_NUM` varchar(20) NOT NULL DEFAULT '',
  `HEALTH_HAZARD` char(1) NOT NULL DEFAULT '',
  `DESCRIPTION` text NOT NULL,
  `ADMIN_NOTES` text DEFAULT NULL,
  `COMPLETION_EMAIL_SENT` date DEFAULT NULL,
  `PICKED_UP_BY` int(11) DEFAULT NULL,
  `PICKED_UP_DATE` datetime DEFAULT NULL,
  `ASSIGNED_TO` int(11) DEFAULT NULL,
  PRIMARY KEY (`WORK_ORDER_ID`),
  KEY `STATUS` (`STATUS`),
  KEY `WORK_ORDER_NUM` (`WORK_ORDER_NUM`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_order_bill`
--

DROP TABLE IF EXISTS `work_order_bill`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_bill` (
  `BILL_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BILLING_BATCH_ID` int(11) NOT NULL,
  `WORK_ORDER_ID` int(11) NOT NULL,
  `GROUP_ID` int(11) DEFAULT NULL,
  `FUNDING_SOURCE_ID` int(11) DEFAULT NULL,
  `CREATED` datetime NOT NULL,
  `END_DATE` date NOT NULL,
  `MATERIALS_CHARGE` decimal(7,2) NOT NULL,
  `LABOR_CHARGE` decimal(7,2) NOT NULL,
  `HOURLY_RATE` decimal(7,2) DEFAULT NULL,
  `STATUS` char(1) NOT NULL DEFAULT '',
  `ADMIN_NOTES` varchar(200) NOT NULL DEFAULT '',
  `STATEMENT_SENT` datetime DEFAULT NULL,
  PRIMARY KEY (`BILL_ID`),
  KEY `WORK_ORDER_ID` (`WORK_ORDER_ID`),
  KEY `BILLING_BATCH_ID` (`BILLING_BATCH_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_order_file`
--

DROP TABLE IF EXISTS `work_order_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_file` (
  `WORK_ORDER_FILE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `WORK_ORDER_ID` int(11) NOT NULL,
  `USER_READ_PERM` char(1) NOT NULL DEFAULT '',
  `USER_WRITE_PERM` char(1) NOT NULL DEFAULT '',
  `CREATED` datetime NOT NULL,
  `CREATED_BY` int(11) NOT NULL,
  `FILENAME` varchar(100) NOT NULL,
  PRIMARY KEY (`WORK_ORDER_FILE_ID`),
  KEY `WORK_ORDER_ID` (`WORK_ORDER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-08-22 15:34:53
