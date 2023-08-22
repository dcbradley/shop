<?php

require_once "db.php";
require_once "common.php";

$part = $_REQUEST["part"];
$stock_num = explode(" ",trim($part))[0];

$dbh = connectDB();
$sql = "SELECT * FROM part WHERE STOCK_NUM = :STOCK_NUM";
$stmt = $dbh->prepare($sql);
$stmt->bindParam(":STOCK_NUM",$stock_num);
$stmt->execute();
$row = $stmt->fetch();

if( $row ) {
  $part_info = array();
  $part_info["FOUND"] = true;
  $vars = array("PART_ID","STOCK_NUM","DESCRIPTION","MANUFACTURER","PRICE","UNITS","LOCATION","QTY_LAST_ORDERED","COST","VENDOR_ID","MARKUP_TYPE","VEND_NUM");
  foreach( $vars as $var ) {
    $part_info[$var] = $row[$var];
  }
  if( !$row["LOCATION"] && ($row["SECTION"] || $row["ROW"] || $row["SHELF"]) ) {
    $part_info["LOCATION"] = getLocationDesc($row);
  }
  $image_url = getPartImageUrl($row["PART_ID"],$row["IMAGE"],$row["STOCK_NUM"],$row["DESCRIPTION"]);
  $part_info["IMAGE"] = $image_url;
  $part_info["FOUND"] = true;

  echo json_encode($part_info);
} else {
  $error_reply = array();
  $error_reply["STOCK_NUM"] = $stock_num;
  $error_reply["FOUND"] = false;
  echo json_encode($error_reply);
}
