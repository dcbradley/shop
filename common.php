<?php

require_once "user.php";
require_once "user_profile.php";
require_once "auditlog.php";
require_once "get_stock_image.php";
require_once "time_utils.php";
require_once "funds.php";

$self_path = str_replace("/index.php","/",$_SERVER["PHP_SELF"]);
$self_full_url = "https://" . (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : "/") . $self_path;

const DISALLOWED_UPLOAD_FILE_TYPES = array("exe","com","bat","php");

const MAX_UPLOAD_FILE_SIZE = 100000000;

const WORK_ORDER_STATUS_CLOSED = 'C';
const WORK_ORDER_STATUS_CANCELED = 'D';
const WORK_ORDER_STATUS_TIMECODE = 'T';

const OTHER_MATERIAL_ID = 0;
const OTHER_ITEM_ID = -1;

const COST_METHOD_NONE = '';
const COST_METHOD_VOLUME = 'V';
const COST_METHOD_DEFAULT_COST_PER_FOOT = 'D';
const COST_METHOD_CUSTOM_COST_PER_FOOT = 'F';
const COST_METHOD_MANUAL = 'M';

const STANDARD_MARKUP_CODE = 0;
const CUSTOM_MARKUP_CODE = 1;
const NO_MARKUP_CODE = 2;

function echoUpdatePrintTextareaJavascript() {
  ?><script>
  function updatePrintTextarea(textarea) {
    var print_textarea = document.getElementById("print-" + textarea.id);
    print_textarea.innerText = textarea.value;

    updatePrintIfNonEmpty();
  }
  function updatePrintIfNonEmpty() {
    $('.print-if-nonempty').each(function(index) {
      var is_nonempty = false;
      var debug = $(this).hasClass('debug');
      $(this).find('input,select').each(function(index) {
        if( this.type == 'hidden' ) return;
        if( $(this).val() != '' ) {
	  is_nonempty = true;
	  if( debug ) console.log("Found nonempty " + this.tag);
	}
      });
      if( !is_nonempty && $(this).find('textarea:not(:empty)').length ) {
        is_nonempty = true;
	  if( debug ) console.log("Found nonempty " + this.tag);
      }
      if( !is_nonempty && $(this).find('.check-nonempty:not(:empty)').length ) {
        is_nonempty = true;
        if( debug ) console.log("Found nonempty check-nonempty: " + this.tag);
      }

      if( is_nonempty ) {
        $(this).removeClass('noprint');
      } else {
        $(this).addClass('noprint');
      }
    });

  }
  </script><?php
}

function htmlescape($s) {
  return htmlspecialchars($s,ENT_QUOTES|ENT_HTML401);
}

function splitName($name,&$first,&$last) {
  $name = trim($name);
  $comma = strpos($name,",");
  if( $comma === FALSE ) {
    $last_space = strrpos($name," ");
    if( $last_space === FALSE ) {
      $last = $name;
      $first = "";
      return;
    }
    $last = substr($name,$last_space+1);
    $first = trim(substr($name,0,$last_space));
    return;
  }
  $last = trim(substr($name,0,$comma));
  $first = trim(substr($name,$comma+1));
}

function firstLastName($last_first) {
  splitName($last_first,$first,$last);
  return trim($first . " " . $last);
}

function dbNow() {
  return date('Y-m-d H:i:s');
}

function dbNowDate() {
  return date('Y-m-d');
}

function displayDateTime($dt) {
  if( !$dt ) return $dt;
  $parsed = DateTime::createFromFormat("Y-m-d H:i:s",$dt);
  if( !$parsed ) {
    $parsed = DateTime::createFromFormat("Y-m-d H:i",$dt);
  }
  if( !$parsed ) {
    $parsed = DateTime::createFromFormat("Y-m-d",$dt);
  }
  # avoid translation of empty mysql date into -0001-11-30
  if( strncmp($dt,"0000-00-00",10)==0 ) {
    return "0000-00-00 " . date_format($parsed,"H:i");
  }
  return date_format($parsed,"Y-m-d H:i");
}

function displayDate($dt) {
  if( !$dt ) return $dt;
  # avoid translation of empty mysql date into -0001-11-30
  if( strncmp($dt,"0000-00-00",10)==0 ) return "0000-00-00";
  $parsed = DateTime::createFromFormat("Y-m-d H:i:s",$dt);
  if( !$parsed ) {
    $parsed = DateTime::createFromFormat("Y-m-d H:i",$dt);
  }
  if( !$parsed ) {
    $parsed = DateTime::createFromFormat("Y-m-d",$dt);
  }
  return date_format($parsed,"Y-m-d");
}

function displayTime($dt) {
  if( !$dt ) return $dt;
  $parsed = DateTime::createFromFormat("Y-m-d H:i:s",$dt);
  $today = date("Y-m-d");
  $dt_day = date_format($parsed,"Y-m-d");

  if( $today == $dt_day ) {
    return date_format($parsed,"H:i");
  }
  # avoid translation of empty mysql date into -0001-11-30
  if( strncmp($dt,"0000-00-00",10)==0 ) {
    return "0000-00-00 " . date_format($parsed,"H:i");
  }
  return date_format($parsed,"Y-m-d H:i");
}


function elapsedHours($start,$stop) {
  if( !$start ) return "";
  if( !$stop ) $stop = dbNow();
  $dt_start = DateTime::createFromFormat("Y-m-d H:i:s",$start);
  $dt_stop = DateTime::createFromFormat("Y-m-d H:i:s",$stop);
  $interval = $dt_start->diff($dt_stop);
  $sign = $interval->invert == 1 ? "-" : "";
  return sprintf("%s%.1f",$sign,$interval->d*24 + $interval->h + $interval->i/60.0 + $interval->s/3600.0);
}

function validDate($datestr) {
  if(!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) +([0-9]+):([0-9]+)(:[0-9]+){0,1}$/',$datestr,$match)) return false;
  $year = (int)$match[1];
  $month = (int)$match[2];
  $day = (int)$match[3];
  $hour = (int)$match[4];
  $minute = (int)$match[5];
  if( !checkdate($month,$day,$year) ) return false;
  if( $hour < 0 || $hour > 24 ) return false;
  if( $minute < 0 || $minute > 59 ) return false;
  return true;
}

function getPartImagePath($part_id,$fname) {
  if( !$fname || $fname == "none" ) return "";
  $hash = substr($part_id,-2);
  return "img/{$hash}/{$part_id}{$fname}";
}

function getPartImageUrl($part_id,$fname,$stock_num,$description) {
  if( $fname == "none" ) return "";
  $url = getPartImagePath($part_id,$fname,$description);
  if( $url ) return $url;

  return getStockImage($part_id,$stock_num,$description);
}

function formatQty($qty) {
  if( preg_match("/^(.*)[.]0*\$/",$qty,$match) ) {
    return $match[1];
  }
  if( preg_match("/^((.*)[.]([0-9]*[1-9]))0*\$/",$qty,$match) ) {
    return $match[1];
  }
  return $qty;
}

function registerLogin(&$user,&$show) {
  if( impersonatingUser() ) return;

  $user->registerLogin();

  if( !isNonNetIDLogin() ) {
    $filled_missing_info = $user->fillMissingDirectoryInfo();

    if( empty($user->created) || !$user->phone || !$user->room || !$user->department || !$user->default_group_id || !$user->default_funding_source_id ) {
      if( $show == "" ) {
        echo "<div class='alert alert-info'>Please fill in all missing information.</div>\n";
        $show = "profile";
      }
    } else if( $filled_missing_info ) {
      if( $show == "" ) {
        echo "<div class='alert alert-info'>Please review the following information.</div>\n";
        $show = "profile";
      }
    }
  }
}

function loadWorkOrder($id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM work_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$id);
  $stmt->execute();
  return $stmt->fetch();
}

function loadWorkOrderBill($bill_id) {
  $dbh = connectDB();
  # include WORK_ORDER_NUM as a convenience for audit log code
  $sql = "SELECT work_order_bill.*,work_order.WORK_ORDER_NUM FROM work_order_bill LEFT JOIN work_order ON work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID WHERE BILL_ID = :BILL_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILL_ID",$bill_id);
  $stmt->execute();
  return $stmt->fetch();
}

function loadBillingBatch($billing_batch_id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getStandardHTMLEmailHead() {
  $head = array();
  $head[] = "<html><head><meta charset='UTF-8'>";
  $head[] = "<style>";
  $head[] = "table.records { border-collapse: collapse; }";
  $head[] = ".records, .records th, .records td { border: 1px solid black; }";
  $head[] = "table.records tr.dark { background-color: #E0E0E0; }";
  $head[] = ".centered { text-align: center; }";
  $head[] = ".light-underline { text-decoration-line: underline; text-decoration-color: #D0D0D0; }";
  $head[] = ".currency { text-align: right; font-variant-numeric: tabular-nums; }";
  $head[] = "</style>";
  $head[] = "</head>";
  return $head;
}

function findOrCreateUser($name) {
  splitName($name,$first,$last);

  $db = connectDB();
  if( $first && $last ) {
    $sql = ("SELECT USER_ID FROM user WHERE LAST_NAME = :LAST_NAME AND FIRST_NAME = :FIRST_NAME");
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":FIRST_NAME",$first);
  } else if( $last ) {
    $sql = ("SELECT USER_ID FROM user WHERE LAST_NAME = :LAST_NAME");
    $stmt = $db->prepare($sql);
  } else {
    return null;
  }
  $stmt->bindParam(":LAST_NAME",$last);
  $stmt->execute();

  $row = $stmt->fetch();

  if( !$row && $first ) {
    $sql = ("SELECT USER_ID FROM user WHERE LAST_NAME = :LAST_NAME AND FIRST_NAME LIKE :FIRST_NAME");
    $stmt = $db->prepare($sql);
    $like_first = $first . "%";
    $stmt->bindParam(":FIRST_NAME",$like_first);
    $stmt->bindParam(":LAST_NAME",$last);
    $stmt->execute();
    $row = $stmt->fetch();
  }

  $result = new User;

  if( $row ) {
    if( $result->loadFromUserID($row["USER_ID"]) ) {
      return $result;
    }
    return null;
  }

  $result->first_name = $first;
  $result->last_name = $last;

  $sql = ("INSERT INTO user SET FIRST_NAME = :FIRST_NAME, LAST_NAME = :LAST_NAME, CREATED = now()");
  $stmt = $db->prepare($sql);
  $stmt->bindParam(":FIRST_NAME",$result->first_name);
  $stmt->bindParam(":LAST_NAME",$result->last_name);
  $stmt->execute();

  $result->user_id = $db->lastInsertId();

  return $result;
}

function makeSafeFileName($fname) {
  $fname = preg_replace('/[^0-9A-Za-z._\-]/',"",$fname);
  if( $fname == "." ) return null;
  if( $fname == ".." ) return null;
  return $fname;
}

function arrayToUrlQueryString($a) {
  $qs = "";
  foreach( $a as $key => $value ) {
    if( $qs ) $qs .= "&";
    $qs .= urlencode($key) . "=" . urlencode($value);
  }
  if( $qs ) {
    $qs = "?" . $qs;
  }
  return $qs;
}

function isAllowedDay($date_str,$schedule) {
  $cur_day = date('Y-m-d',strtotime($date_str));
  $date_found = false;
  foreach( $schedule as $entry ) {
    if( array_key_exists('date',$entry) && $entry['date'] == $cur_day ) {
      $date_found = true;
      if( !array_key_exists('start',$entry) ) continue;
      return true;
    }
  }
  if( $date_found ) return false;

  $day_char = getDayChar($date_str);
  foreach( $schedule as $entry ) {
    if( array_key_exists('skip',$entry) ) continue;
    if( array_key_exists('days',$entry) && strpos($entry['days'],$day_char) !== false ) {
      return true;
    }
  }
  return false;
}

function getNextAllowedDay($date_str,$schedule) {
  $d = getNextDay($date_str);
  return getThisAllowedDayOrNext($d,$schedule);
}

function getThisAllowedDayOrNext($date_str,$schedule) {
  $d = $date_str;
  for($i=0; $i<7; $i++) {
    if( isAllowedDay($d,$schedule) ) return $d;
    $d = getNextDay($d);
  }
  return "";
}

function getPrevAllowedDay($date_str,$schedule) {
  $d = getPrevDay($date_str);
  for($i=0; $i<7; $i++) {
    if( isAllowedDay($d,$schedule) ) return $d;
    $d = getPrevDay($d);
  }
  return "";
}

function getNextTimesheetDay($date_str) {
  return getNextAllowedDay($date_str,TIMESHEET_SCHEDULE);
}

function getPrevTimesheetDay($date_str) {
  return getPrevAllowedDay($date_str,TIMESHEET_SCHEDULE);
}

function getLocationDesc($part) {
  if( !($part["SECTION"] || $part["ROW"] || $part["SHELF"]) ) {
    return $part["LOCATION"];
  }

  $location = array();
  if( $part["SECTION"] ) $location[] = "section " . $part["SECTION"];
  if( $part["ROW"] ) $location[] = "row " . $part["ROW"];
  if( $part["SHELF"] ) $location[] = "dwr/shelf " . $part["SHELF"];
  return implode(", ",$location);
}

function getOtherShopsInWorkOrders() {
  $result = OTHER_SHOPS_IN_WORK_ORDERS;

  if( ENABLE_CHECKOUT_BATCH_BILLING ) {
    $result[SHOP_NAME . " Billing"] = array(
      'SHOW_IN_WORK_ORDER_FORM' => false,
      'SHOP_NAME' => SHOP_NAME,
      'CHECKOUT_TABLE' => 'checkout',
      'PART_TABLE' => 'part',
      'CHECKOUT_WORK_ORDER_ID_COL' => 'BILLING_WORK_ORDER_ID',
      'CHECKOUT_URL' => SHOP_URL,
    );
  }
  return $result;
}

function getShopItemsInWorkOrder($wo_id,$shop_info) {
  $checkout_table = $shop_info['CHECKOUT_TABLE'];
  $part_table = $shop_info['PART_TABLE'];
  $wo_col = $shop_info['CHECKOUT_WORK_ORDER_ID_COL'];
  $dbh = connectDB();
  $sql = "
    SELECT
      checkout.*,
      part.STOCK_NUM,
      part.DESCRIPTION
    FROM
      {$checkout_table} checkout
    JOIN
      {$part_table} part
    ON
      part.PART_ID = checkout.PART_ID
    WHERE
      checkout.{$wo_col} = :WORK_ORDER_ID
      AND checkout.DELETED IS NULL
    ORDER BY
      checkout.CHECKOUT_ID
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $stmt->execute();

  $items = array();
  while( ($row=$stmt->fetch()) ) {
    $items[] = $row;
  }
  return $items;
}

function truncZeroCents($n) {
  if( $n === null ) return $n;
  if( preg_match('|([0-9]+)\.00$|',$n,$match) ) {
    return $match[1];
  }
  return $n;
}

function getPartRecord($part_id) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from part WHERE PART_ID = :PART_ID");
  $stmt->bindValue(":PART_ID",$part_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getUniqueWorkOrderNum($id,$wo_type_char,$now=null) {
  if( empty($now) ) {
    $now = time();
  } else {
    $now = strtotime($now);
  }
  $dbh = connectDB();
  $exists_sql = "SELECT WORK_ORDER_NUM FROM work_order WHERE WORK_ORDER_NUM = :WORK_ORDER_NUM";
  if( $id ) {
    $exists_sql .= " AND WORK_ORDER_ID <> :WORK_ORDER_ID";
  }
  $exists_stmt = $dbh->prepare($exists_sql);
  if( $id ) {
    $exists_stmt->bindValue(":WORK_ORDER_ID",$id);
  }

  $wo_num = "";
  if( isAdmin() && isset($_REQUEST["work_order_num"]) ) {
    $wo_num = $_REQUEST["work_order_num"];
    if( $wo_num ) {
      $exists_stmt->bindValue(":WORK_ORDER_NUM",$wo_num);
      $exists_stmt->execute();
      if( $exists_stmt->fetch() ) {
        echo "<div class='alert alert-warning'>WARNING: Work order number '",htmlescape($wo_num),"' already exists, so assigning a unique number instead.</div>\n";
        $wo_num = null;
      }
    }
  }

  if( !$wo_num && $id ) {
    $get_wo_num_sql = "SELECT WORK_ORDER_NUM FROM work_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
    $get_wo_num_stmt = $dbh->prepare($get_wo_num_sql);
    $get_wo_num_stmt->bindValue(":WORK_ORDER_ID",$id);
    $get_wo_num_stmt->execute();
    $r = $get_wo_num_stmt->fetch();
    if( $r ) $wo_num = $r["WORK_ORDER_NUM"];
  }

  if( !$wo_num ) {
    # find a unique work order number (not really a number but a string of the form YY-NNN)

    $yy = date("y",$now);
    $month = (int)date("n",$now);
    if( $month >= 6 ) {
      # new fiscal year starts in June
      $yy = ((int)$yy)+1;
    }
    $wo_prefix = "{$yy}-" . SHOP_WORK_ORDER_CHAR . $wo_type_char;

    $count_sql = "SELECT COUNT(*) as COUNT FROM work_order WHERE WORK_ORDER_NUM REGEXP :WORK_ORDER_NUM_RE";
    $count_stmt = $dbh->prepare($count_sql);
    $count_stmt->bindValue(":WORK_ORDER_NUM_RE","^{$wo_prefix}[0-9]+$");
    $count_stmt->execute();
    $wo_count = $count_stmt->fetch()["COUNT"];

    $wo_num = "";
    for($i=1; $i<100; $i++) {
      $trial_wo_num = $wo_prefix . sprintf('%03d',$i+$wo_count);
      $exists_stmt->bindValue(":WORK_ORDER_NUM",$trial_wo_num);
      $exists_stmt->execute();
      if( !$exists_stmt->fetch() ) {
        $wo_num = $trial_wo_num;
        break;
      }
    }
  }
  return $wo_num;
}

function truncOnOverflow($s,$maxlength) {
  return substr($s,0,$maxlength);
}

function getFinancialYear($now) {
  $yy = date("y",strtotime($now));
  $month = (int)date("n",strtotime($now));
  if( $month >= 6 ) {
    # new fiscal year starts in June
    $yy = ((int)$yy)+1;
  }
  return $yy;
}
