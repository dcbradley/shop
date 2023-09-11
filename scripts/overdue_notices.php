<?php

require "db.php";
require "common.php";
require "config.php";
require "post_config.php";

$sql = "SELECT ITEM_NAME, EXPECTED_RETURN, EMAIL, FIRST_NAME FROM loan, user WHERE user.USER_ID = loan.USER_ID AND loan.RETURNED IS NULL AND loan.EXPECTED_RETURN < date(now())";
$dbh = connectDB();
$stmt = $dbh->prepare($sql);
$stmt->execute();

while( ($row=$stmt->fetch()) ) {
  $msg = array();
  $msg[] = "Dear " . $row["FIRST_NAME"] . ":";
  $msg[] = "";
  $msg[] = "This is a gentle automated reminder that you borrowed " . $row["ITEM_NAME"] . " from the " . SHOP_NAME . " with an expected return date of " . $row["EXPECTED_RETURN"] . ". Do you still need it?";
  $msg[] = "";
  $admin_first_name = explode(" ",SHOP_ADMIN_NAME)[0];
  $msg[] = "Loaned items must be returned to " . $admin_first_name . " personally to be registered as returned. If a loaned item isn't returned within 30 days of the expected return date, your group may be charged the cost of the loaned item.";
  $msg[] = "";
  $msg[] = "Sincerely,";
  $msg[] = SHOP_NAME . " Reminder Daemon";

  $msg = implode("\r\n",$msg);

  $headers = array();
  $headers[] = "From: " . SHOP_NAME . " Reminder <" . SHOP_FROM_EMAIL . ">";
  $headers[] = "Cc: " . SHOP_ADMIN_NAME . " <" . SHOP_ADMIN_EMAIL . ">";
  $headers = implode("\r\n",$headers);

  $to = $row["EMAIL"];
  $subject = "borrowed item: " . $row["ITEM_NAME"];

  if( !mail($to,$subject,$msg,$headers,"-f " . SHOP_FROM_EMAIL) ) {
    echo "Failed to send reminder to $to.";
  }
}
