<?php

require_once "shared_auditlog.php";

const MAX_AUDITLOG_MSG = 500;

function auditlogModifyCheckout($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("purchase","CHECKOUT_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyPart($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("part","PART_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_PART_ID = :CHANGED_PART_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_PART_ID",$after_edit ? $after_edit["PART_ID"] : $before_edit["PART_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyVendor($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("vendor","VENDOR_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_VENDOR_ID = :CHANGED_VENDOR_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_VENDOR_ID",$after_edit ? $after_edit["VENDOR_ID"] : $before_edit["VENDOR_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyOrder($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("order","ORDER_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_ORDER_ID = :CHANGED_ORDER_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_ORDER_ID",$after_edit ? $after_edit["ORDER_ID"] : $before_edit["ORDER_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyMaterial($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("stock_material","MATERIAL_ID",$before_edit,$after_edit,"NAME");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_MATERIAL_ID = :CHANGED_MATERIAL_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_MATERIAL_ID",$after_edit ? $after_edit["MATERIAL_ID"] : $before_edit["MATERIAL_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyContract($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("shop_contract","CONTRACT_ID",$before_edit,$after_edit,"CONTRACT_NAME");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_CONTRACT_ID = :CHANGED_CONTRACT_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_CONTRACT_ID",$after_edit ? $after_edit["CONTRACT_ID"] : $before_edit["CONTRACT_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyTimesheet($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("timesheet","TIMESHEET_ID",$before_edit,$after_edit,"DATE");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyWorkOrder($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("work_order","WORK_ORDER_ID",$before_edit,$after_edit,"WORK_ORDER_NUM");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_WORK_ORDER_ID = :CHANGED_WORK_ORDER_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_WORK_ORDER_ID",$after_edit ? $after_edit["WORK_ORDER_ID"] : $before_edit["WORK_ORDER_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyStockOrder($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("stock_order","STOCK_ORDER_ID",$before_edit,$after_edit,"WORK_ORDER_NUM");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_STOCK_ORDER_ID = :CHANGED_STOCK_ORDER_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_STOCK_ORDER_ID",$after_edit ? $after_edit["STOCK_ORDER_ID"] : $before_edit["STOCK_ORDER_ID"]);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyWorkOrderFile($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("work_order_file","WORK_ORDER_FILE_ID",$before_edit,$after_edit,"WORK_ORDER_NUM");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyWorkOrderBill($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("work_order_bill","BILL_ID",$before_edit,$after_edit,"WORK_ORDER_NUM");
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user ? $web_user->user_id : null);
  $stmt->bindValue(":IPADDR",isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "");
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}

function auditlogModifyLoan($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("loan","LOAN_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user ? $web_user->user_id : null);
  $stmt->bindValue(":IPADDR",isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "");
  $stmt->bindValue(":MSG",truncOnOverflow($msg,MAX_AUDITLOG_MSG));
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->execute();
}
