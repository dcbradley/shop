<?php
ini_set('display_errors', 'On');

require_once "db.php";
require_once "common.php";
require_once "config.php";
require_once "post_config.php";

function exec_sql_ignore_already_exists($sql,$quiet=false) {
  $dbh = connectDB();
  try {
    $dbh->exec($sql);
  }
  catch( PDOException $Exception ) {
    $code = $Exception->getCode();
    if( $code == '42S01' || $code == '42S21' ) { # already exists
      if( !$quiet ) {
        echo $Exception->getMessage(),"\n";
        echo "Ignoring and continuing.\n\n";
      }
      return false;
    }
    else {
      echo "Unexpected error.  Aborting.\n";
      throw $Exception;
    }
  }
  return true;
}

function exec_sql($sql) {
  $dbh = connectDB();
  $dbh->exec($sql);
  return true;
}

function getSchemaVersion() {
  $dbh = connectDB();
  $sql = "SELECT DB_SCHEMA_VERSION FROM shop_config WHERE CONFIG_ID = 1";
  $stmt = $dbh->prepare($sql);
  try {
    $stmt->execute();
  }
  catch( PDOException $Exception ) {
    $code = $Exception->getCode();
    if( $code == '42S02' ) { # table not found
      return 0;
    }
    else {
      echo "Unexpected error.  Aborting.\n";
      throw $Exception;
    }
  }
  $row = $stmt->fetch();
  if( !$row ) return 0;
  return $row["DB_SCHEMA_VERSION"];
}

function setSchemaVersion($version) {
  $dbh = connectDB();
  $sql = "UPDATE shop_config SET DB_SCHEMA_VERSION = :VERSION WHERE CONFIG_ID = 1 AND DB_SCHEMA_VERSION < :VERSION";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":VERSION",$version);
  $stmt->execute();
  echo "Updated to schema version $version.\n";
}

function create_schema_v1() {

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
CREATE TABLE `billing_batch` (
  `BILLING_BATCH_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BATCH_NAME` varchar(60) NOT NULL,
  `CREATED` datetime NOT NULL,
  `PROCESSED` datetime DEFAULT NULL,
  `ADMIN_NOTES` varchar(200) NOT NULL DEFAULT '',
  `ARCHIVED` datetime DEFAULT NULL,
  PRIMARY KEY (`BILLING_BATCH_ID`),
  UNIQUE KEY `BATCH_NAME` (`BATCH_NAME`)
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
CREATE TABLE `shop_contract` (
  `CONTRACT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CONTRACT_NAME` varchar(20) NOT NULL,
  `CONTRACT_HOURLY_RATE` decimal(7,2) NOT NULL,
  `CONTRACT_HOURLY_RATE_UPDATED` datetime NOT NULL,
  `HIDE` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`CONTRACT_ID`)
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
CREATE TABLE `user_group` (
  `GROUP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `GROUP_NAME` varchar(100) NOT NULL,
  `PURCHASING_GROUP_ID` int(11) DEFAULT NULL,
  `DELETED` tinyint(2) NOT NULL DEFAULT 0,
  `USER_NETIDS` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`GROUP_ID`)
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
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
);
");

  exec_sql_ignore_already_exists("
CREATE TABLE `shop_config` (
  `CONFIG_ID` int(11) NOT NULL,
  `DB_SCHEMA_VERSION` int(11) NOT NULL,
  `CREATED` datetime NOT NULL,
  PRIMARY KEY (`CONFIG_ID`)
);
");

  exec_sql("
INSERT INTO shop_config
SET CONFIG_ID = 1, DB_SCHEMA_VERSION = 1, CREATED = NOW()
");

  echo "Created database schema version 1.\n";
}

function create_custom_columns() {
  $custom_columns = array(
    array("user",SHOP_ADMIN_COL,"tinyint(1) NOT NULL DEFAULT 0"),
    array("user",SHOP_WORKER_COL,"tinyint(1) NOT NULL DEFAULT 0"),
    array("user",SHOP_LAST_LOGIN_COL,"datetime DEFAULT NULL"),
    array("user",SHOP_USER_CREATED_COL,"tinyint(1) NOT NULL DEFAULT 0"),
  );

  if( OTHER_SHOPS_IN_CHECKOUT ) foreach( OTHER_SHOPS_IN_CHECKOUT as $shop ) {
    $custom_columns[] = array("checkout",$shop['CHECKOUT_WORK_ORDER_ID_COL'],"INT NULL DEFAULT NULL");
  }

  foreach( $custom_columns as $col ) {
    $table = $col[0];
    $column = $col[1];
    $def = $col[2];

    $sql = "ALTER TABLE $table add column $column $def";
    $quiet = true;
    if( exec_sql_ignore_already_exists($sql,$quiet) ) {
      echo "Added $table.$column\n";
    }
  }
}

$schema_version = getSchemaVersion();

if( $schema_version < 1 ) {
  create_schema_v1();
}

create_custom_columns();
