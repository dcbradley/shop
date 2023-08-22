<?php

function auditlogModifyUser($old_user,$new_user) {
  global $web_user;

  $old_vars = get_object_vars($old_user);
  $new_vars = get_object_vars($new_user);

  $varchanges = array();
  foreach( $old_vars as $varname => $value ) {
    if( $new_vars[$varname] !== $value ) {
      $varchanges[] = "$varname from '$value' to '" . $new_vars[$varname] . "'";
    }
  }
  if( count($varchanges) == 0 ) return;
  $msg = "Changed " . implode(", ",$varchanges) . ".";

  $db = connectDB();
  $sql = "INSERT INTO shared_auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_USER_ID = :CHANGED_USER_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD, SHOP = :SHOP";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(":ACTING_USER_ID",$web_user->user_id);
  $ipaddr = $_SERVER['REMOTE_ADDR'];
  $stmt->bindParam(":IPADDR",$ipaddr);
  $stmt->bindParam(":CHANGED_USER_ID",$new_user->user_id);
  $stmt->bindParam(":MSG",$msg);
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->bindValue(":SHOP",SHOP_NAME);
  $stmt->execute();
}

function describeDBRowChanges($record_type,$id_col,$before_edit,$after_edit,$desc_col=null) {
  $msg = array();
  if( $after_edit ) foreach( $after_edit as $key => $value ) {
    if( is_int($key) ) continue; # only process column names, not column indexes
    if( !$before_edit ) {
      $msg[] = "$key is '$value'.";
    } else {
      $oldval = $before_edit[$key];
      if( $value !== $oldval ) {
        $msg[] = "Changed $key from '$oldval' to '$value'.";
      }
    }
  }
  else if( $before_edit ) foreach( $before_edit as $key => $value ) {
    if( is_int($key) ) continue; # only process column names, not column indexes
    if( $value !== "" && $value !== null ) {
      $msg[] = "$key was '$value'.";
    }
  }

  $msg = implode(" ",$msg);
  if( !$msg ) return $msg;

  $id = "";
  if( $after_edit ) $id = " " . $after_edit[$id_col];
  else if( $before_edit ) $id = " " . $before_edit[$id_col];

  $desc = "";
  if( $desc_col ) {
    if( $after_edit ) $desc = $after_edit[$desc_col];
    else if( $before_edit ) $desc = $before_edit[$desc_col];
  }
  if( $desc ) $desc = " (" . $desc . ")";

  if( !$after_edit ) {
    $msg = "Deleted {$record_type}{$id}{$desc}: " . $msg;
  }
  else if( $before_edit ) {
    $msg = "Updated {$record_type}{$id}{$desc}: " . $msg;
  } else {
    $msg = "Created {$record_type}{$id}{$desc}: " . $msg;
  }

  return $msg;
}

function auditlogModifyUserGroup($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("group","GROUP_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO shared_auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_GROUP_ID = :CHANGED_GROUP_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD, SHOP = :SHOP";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_GROUP_ID",$after_edit["GROUP_ID"]);
  $stmt->bindValue(":MSG",$msg);
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->bindValue(":SHOP",SHOP_NAME);
  $stmt->execute();
}

function auditlogModifyFundingSource($web_user,$before_edit,$after_edit) {
  $msg = describeDBRowChanges("funding source","FUNDING_SOURCE_ID",$before_edit,$after_edit);
  if( !$msg ) return; # no change

  $sql = "INSERT INTO shared_auditlog SET DATE=now(), ACTING_USER_ID = :ACTING_USER_ID, IPADDR = :IPADDR, CHANGED_GROUP_ID = :CHANGED_GROUP_ID, MSG = :MSG, LOGIN_METHOD = :LOGIN_METHOD, SHOP = :SHOP";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ACTING_USER_ID",$web_user->user_id);
  $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  $stmt->bindValue(":CHANGED_GROUP_ID",$after_edit["GROUP_ID"]);
  $stmt->bindValue(":MSG",$msg);
  $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
  $stmt->bindValue(":SHOP",SHOP_NAME);
  $stmt->execute();
}

function showAuditLog($changed_user=null) {

  if( !$changed_user && isset($_REQUEST["changed_user_id"]) ) {
    $changed_user = new User;
    if( !$changed_user->loadFromUserID($_REQUEST["changed_user_id"]) ) {
      $changed_user = null;
    }
  }

  $where_user = "";
  if( $changed_user ) {
    $where_user = "AND auditlog.CHANGED_USER_ID = :CHANGED_USER_ID";
  }

  if( isset($_REQUEST["start"]) ) {
    $start = $_REQUEST["start"];
  } else {
    $start = date("Y-01-01");
  }

  if( isset($_REQUEST["end"]) ) {
    $end = $_REQUEST["end"];
  } else {
    $end = date("Y-m-d",strtotime($start . " +1 year"));
  }

  $next = $end;

  $now = date("Y-m-d");
  if( $next > $now ) $next = "";

  $prev = date("Y-m-d",strtotime($start . " -1 year"));

  $db = connectDB();
  $sql_base = "
    SELECT
      DATE,
      AUDITLOG_ID,
      MSG,
      _SHOP_ as SHOP,
      ACTING_USER_ID,
      auser.FIRST_NAME as AUSER_FIRST_NAME,
      auser.LAST_NAME as AUSER_LAST_NAME,
      IPADDR,
      CHANGED_USER_ID,
      cuser.FIRST_NAME as CUSER_FIRST_NAME,
      cuser.LAST_NAME as CUSER_LAST_NAME,
      CHANGED_GROUP_ID,
      cgroup.GROUP_NAME,
      cpart.PART_ID as CHANGED_PART_ID,
      cpart.STOCK_NUM,
      cpart.DESCRIPTION as PART_NAME,
      cvendor.VENDOR_ID as CHANGED_VENDOR_ID,
      cvendor.NAME as VENDOR_NAME,
      CHANGED_ORDER_ID,
      po_part.STOCK_NUM as PO_STOCK_NUM,
      po_part.DESCRIPTION as PO_PART_NAME
    FROM
      _AUDITLOG_ auditlog
      LEFT JOIN user as auser ON auser.USER_ID = auditlog.ACTING_USER_ID
      LEFT JOIN user as cuser ON cuser.USER_ID = auditlog.CHANGED_USER_ID
      LEFT JOIN user_group as cgroup ON cgroup.GROUP_ID = auditlog.CHANGED_GROUP_ID
      LEFT JOIN part as cpart ON cpart.PART_ID = auditlog.CHANGED_PART_ID
      LEFT JOIN vendor as cvendor ON cvendor.VENDOR_ID = auditlog.CHANGED_VENDOR_ID
      LEFT JOIN part_order as po ON po.ORDER_ID = auditlog.CHANGED_ORDER_ID
      LEFT JOIN part as po_part on po_part.PART_ID = po.PART_ID
    WHERE
      auditlog.DATE >= :START AND auditlog.DATE < :END
      $where_user
  ";

  $sql1 = str_replace("_AUDITLOG_","shared_auditlog",$sql_base);
  $sql1 = str_replace("_SHOP_","SHOP",$sql1);
  $sql2 = str_replace("_AUDITLOG_","auditlog",$sql_base);
  $sql2 = str_replace("_SHOP_",":DEFAULT_SHOP",$sql2);
  $sql = "SELECT * FROM ($sql1 UNION $sql2) logs ORDER BY DATE DESC, AUDITLOG_ID DESC";

  $stmt = $db->prepare($sql);
  $stmt->bindValue(":START",$start);
  $stmt->bindValue(":END",$end);
  $stmt->bindValue(":DEFAULT_SHOP",SHOP_NAME);
  if( $changed_user ) {
    $stmt->bindParam(":CHANGED_USER_ID",$changed_user->user_id);
  }
  $stmt->execute();

  if( $start == date("Y-01-01",strtotime($start)) && $end == date("Y-m-d",strtotime($start . " +1 year")) ) {
    $time_title = date("Y",strtotime($start));
  } else {
    $time_title = "$start to $end";
  }

  if( $changed_user ) {
    echo "<h2>",htmlescape($changed_user->displayName()),"'s Profile History for ",htmlescape($time_title),"</h2>\n";
  } else {
    echo "<h2>Audit Log for ",htmlescape($time_title),"</h2>\n";
  }

  $base_url = "?s=auditlog";
  if( $changed_user ) $base_url .= "&changed_user_id=" . $changed_user->user_id;

  $url = "{$base_url}&start=$prev";
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  if( $next ) {
    $url = "{$base_url}&start=$next";
    echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-right'></i></a>\n";
  }

  echo "<br><table class='records clicksort'>\n";
  echo "<thead><tr><th>Date</th><th>Shop</th><th>Who</th><th>Changed</th><th>Log</th><th>IP</th></tr></thead>\n";
  echo "<tbody>\n";
  $count = 0;
  while( ($row = $stmt->fetch()) ) {
    echo "<tr class='record'>";
    echo "<td>",htmlescape(displayDateTime($row["DATE"])),"</td>";

    $shop = preg_replace('| Shop$|','',$row["SHOP"]);
    echo "<td>",htmlescape($shop),"</td>";

    if( $row["ACTING_USER_ID"] ) {
      $who = $row["AUSER_LAST_NAME"] . ", " . $row["AUSER_FIRST_NAME"];
      echo "<td><a href='?s=edit_user&amp;user_id=",htmlescape($row["ACTING_USER_ID"]),"'>",htmlescape($who),"</a></td>";
    } else {
      echo "<td></td>";
    }

    if( $row["CHANGED_USER_ID"] ) {
      $cuser_name = $row["CUSER_LAST_NAME"] . ", " . $row["CUSER_FIRST_NAME"];
      echo "<td><a href='?s=edit_user&amp;user_id=",htmlescape($row["CHANGED_USER_ID"]),"'>",htmlescape($cuser_name),"</a></td>";
    } else if( $row["CHANGED_GROUP_ID"] ) {
      $name = $row["GROUP_NAME"];
      $url = "?s=edit_group&group_id=" . $row["CHANGED_GROUP_ID"];
      echo "<td><a href='",htmlescape($url),"'>",htmlescape($name),"</a></td>";
    } else if( $row["CHANGED_PART_ID"] ) {
      $url = "?s=part&part_id=" . $row["CHANGED_PART_ID"];
      echo "<td><a href='",htmlescape($url),"'><tt>",htmlescape($row["STOCK_NUM"]),"</tt></a> - ",htmlescape($row["PART_NAME"]),"</td>";
    } else if( $row["CHANGED_VENDOR_ID"] ) {
      $name = $row["VENDOR_NAME"];
      $url = "?s=vendor&vendor_id=" . $row["CHANGED_VENDOR_ID"];
      echo "<td><a href='",htmlescape($url),"'>",htmlescape($name),"</a></td>";
    } else if( $row["CHANGED_ORDER_ID"] ) {
      $url = "?s=order&order_id=" . $row["CHANGED_ORDER_ID"];
      echo "<td><a href='",htmlescape($url),"'><tt>",htmlescape($row["PO_STOCK_NUM"]),"</tt></a> - ",htmlescape($row["PO_PART_NAME"]),"</td>";
    } else {
      echo "<td></td>";
    }
    echo "<td>",htmlescape($row["MSG"]),"</td>";
    echo "<td>",htmlescape($row["IPADDR"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";
  echo "<p>&nbsp;</p>\n";
}
