<?php

require_once "db.php";
require_once "common.php";
require_once "config.php";
require_once "post_config.php";

function makeAdmin($netid) {
  $dbh = connectDB();
  $sql = "UPDATE user set " . SHOP_ADMIN_COL . " = 1 WHERE NETID = :NETID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":NETID",$netid);
  $stmt->execute();
}

if( count($argv) < 2 ) {
  echo "USAGE: php make_admin.php NETID\n";
}

$netid = $argv[1];

makeAdmin($netid);
