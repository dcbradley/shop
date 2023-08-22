<?php

require_once "config.php";
require_once "post_config.php";
require_once "db.php";

$to = $_SERVER["mail"];
if( !$to ) {
  $sql = "SELECT EMAIL FROM user WHERE NETID = :NETID";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":NETID",$_SERVER["REMOTE_USER"]);
  $stmt->execute();
  $row = $stmt->fetch();
  if( $row ) $to = $row["EMAIL"];
  if( !$to ) {
    echo "FAILED: please save an email address first.";
    exit(1);
  }
}

$msg = array();
$msg[] = "You may update your funding source and other information on the following page:";
$msg[] = "";
$url = SHOP_URL . "?s=profile";
$msg[] = $url;

$msg = implode("\r\n",$msg);

$headers = array();
$headers[] = "From: Physics IT <help@physics.wisc.edu>";
$headers = implode("\r\n",$headers);

$subject = SHOP_NAME . " checkout registration";

if( !mail($to,$subject,$msg,$headers,"-f help@physics.wisc.edu") ) {
  echo "FAILED";
  exit(1);
}
echo "SUCCESS";
