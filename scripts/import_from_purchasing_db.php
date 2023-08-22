<?php

# This script is called periodically to import groups and funding
# sources from the purchasing db into the eshop db.  One reason for
# doing things this way, rather than having the eshop web app read
# directly from the purchasing db is to create some separation between
# the two, so that the eshop app can continue to operate even if the
# purchasing db goes away or is changed in a way that would break
# things.

require_once "db.php";
require_once "people_ldap.php";

$dbh = connectDB();

# Add groups from purchasing db to eshop db.
$sql = "INSERT INTO user_group (GROUP_NAME,PURCHASING_GROUP_ID) SELECT GROUP_NAME, GROUP_ID FROM purchasing.dept_group g1 WHERE g1.GROUP_ID IS NOT NULL AND NOT EXISTS(SELECT GROUP_ID FROM user_group g2 WHERE g2.PURCHASING_GROUP_ID = g1.GROUP_ID)";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Imported $n groups from purchasing.dept_group into eshop.user_group.\n";
}

$sql = "
  UPDATE user_group g1
  INNER JOIN purchasing.dept_group g2 ON g2.group_id = g1.PURCHASING_GROUP_ID
  SET g1.GROUP_NAME = g2.GROUP_NAME,
      g1.DELETED = 0";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Updated $n groups in eshop.user_group from purchasing.dept_group.\n";
}

# Hide groups that were previously imported from purchasing db but which are no longer there.
$sql = "UPDATE user_group SET DELETED=1 WHERE PURCHASING_GROUP_ID IS NOT NULL AND NOT EXISTS( SELECT GROUP_ID FROM purchasing.dept_group g2 WHERE user_group.PURCHASING_GROUP_ID = g2.GROUP_ID )";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Hid $n groups from eshop.user_group that no longer appear in purchasing.dept_group.\n";
}

# Import funding sources.
$sql = "
  INSERT INTO funding_source
    (PURCHASING_FUNDING_SOURCE_ID,
     GROUP_ID,
     FUNDING_DESCRIPTION,
     FUNDING_DEPT,
     FUNDING_PROJECT,
     FUNDING_PROGRAM,
     PI_NAME,
     FUNDING_FUND,
     CREATED,
     FUNDING_START,
     FUNDING_END,
     FUNDING_ACTIVE
    )
    SELECT
     funding_source_id,
     (SELECT GROUP_ID FROM user_group WHERE purchasing_group_id = f1.group_id),
     funding_description,
     funding_dept,
     funding_project,
     funding_program,
     (SELECT CONCAT(pi.user_last,', ',pi.user_first) FROM purchasing.user pi WHERE pi.user_id = f1.funding_pi),
     funding_fund,
     now(),
     funding_start,
     funding_end,
     funding_active
    FROM purchasing.funding_source f1
    WHERE
     f1.funding_active
     AND NOT f1.deleted
     AND NOT EXISTS( SELECT PURCHASING_FUNDING_SOURCE_ID FROM funding_source f2 WHERE f2.PURCHASING_FUNDING_SOURCE_ID = f1.funding_source_id )
  ";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Inserted $n funding sources from purchasing.funding_source into eshop.funding_source.\n";
}

# Update funding sources.
$sql = "
  UPDATE funding_source f1
  INNER JOIN purchasing.funding_source f2 ON f2.funding_source_id = f1.PURCHASING_FUNDING_SOURCE_ID
  INNER JOIN user_group ON user_group.PURCHASING_GROUP_ID = f2.GROUP_ID
  LEFT JOIN purchasing.user pi ON pi.user_id = f2.funding_pi
  SET
     f1.GROUP_ID = user_group.GROUP_ID,
     f1.FUNDING_DESCRIPTION = f2.funding_description,
     f1.FUNDING_DEPT = f2.funding_dept,
     f1.FUNDING_PROJECT = IF(f2.funding_project IS NULL,'',f2.funding_project),
     f1.FUNDING_PROGRAM = f2.funding_program,
     f1.PI_NAME = CONCAT(pi.user_last,', ',pi.user_first),
     f1.FUNDING_FUND = f2.funding_fund,
     f1.DELETED = f2.deleted,
     f1.FUNDING_START = f2.funding_start,
     f1.FUNDING_END = f2.funding_end,
     f1.FUNDING_ACTIVE = f2.funding_active";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Updated $n records in eshop.funding_source from purchasing.funding_source.\n";
}

$sql = "
  UPDATE funding_source f1
  SET f1.DELETED = 1
  WHERE f1.purchasing_funding_source_id IS NOT NULL AND NOT EXISTS( SELECT funding_source_id FROM purchasing.funding_source f2 WHERE f2.funding_source_id = f1.purchasing_funding_source_id )";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Deleted $n records from eshop.funding_source that are no longer in purchasing.funding_source.\n";
}

# Update contact info.
$sql = "
  UPDATE
    funding_source
  JOIN
    web.people
  ON
    CONCAT(people.LAST_NAME,', ',people.FIRST_NAME) = PI_NAME
  SET
    funding_source.PI_EMAIL = people.EMAIL
  WHERE
    PI_NAME <> ''";
$n = $dbh->exec($sql);
if( $n > 0 ) {
  echo "Updated $n PI funding contacts.\n";
}

$sql = "UPDATE funding_source SET PI_EMAIL = :EMAIL WHERE FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID";
$update_stmt = $dbh->prepare($sql);

$sql = "SELECT FUNDING_SOURCE_ID,PI_NAME FROM funding_source WHERE PI_EMAIL = ''";
$stmt = $dbh->prepare($sql);
$stmt->execute();
while( ($row=$stmt->fetch()) ) {
  $name = $row["PI_NAME"];
  $parts = explode(",",$name,2);
  $fn = "";
  $ln = "";
  if( count($parts)==2 ) {
    $fn = explode(" ",trim($parts[1]))[0];
    $ln = trim($parts[0]);
  } else {
    $parts = explode(" ",trim($name));
    if( count($parts)==1 ) {
      $ln = $parts[0];
    } else {
      $fn = $parts[0];
      $ln = $parts[count($parts)-1];
    }
  }
  $ldap_info = getLdapInfo($fn,"",$ln,"","");
  if( $ldap_info && $ldap_info["mail"] ) {
    $update_stmt->bindValue(":FUNDING_SOURCE_ID",$row["FUNDING_SOURCE_ID"]);
    $update_stmt->bindValue(":EMAIL",$ldap_info["mail"]);
    $update_stmt->execute();
  }
}
