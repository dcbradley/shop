<?php

require_once "common.php";
require_once "db.php";
require_once "config.php";

initLogin();

if( !isAdmin() && !isShopWorker() ) {
  echo "not authorized: ",$web_user->netid;
  exit();
}

$loan_id = $_REQUEST["loan_id"];
$admin_notes = $_REQUEST["admin_notes"];

$dbh = connectDB();
$sql = "UPDATE loan SET ADMIN_NOTES = :ADMIN_NOTES WHERE LOAN_ID = :LOAN_ID";
$stmt = $dbh->prepare($sql);
$stmt->bindValue(":ADMIN_NOTES",$admin_notes);
$stmt->bindValue(":LOAN_ID",$loan_id);
$stmt->execute();

echo "SUCCESS\n";
