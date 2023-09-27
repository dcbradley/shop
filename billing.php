<?php

const WORK_ORDER_BILL_STATUS_PAID = 'P';

function getWorkOrderLaborCharge($wo_id,$end_date,&$labor_charge,&$hours,&$hourly_rate) {
  $labor_charge = null;
  $hours = null;
  $hourly_rate = null;

  $dbh = connectDB();
  $sql = "SELECT SUM(HOURS) as HOURS FROM timesheet WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  if( $end_date ) {
    $sql .= " AND DATE < :END_DATE";
  }
  $hours_stmt = $dbh->prepare($sql);
  $hours_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  if( $end_date ) {
    $hours_stmt->bindValue(":END_DATE",$end_date);
  }
  $hours_stmt->execute();
  $hours = $hours_stmt->fetch()["HOURS"];
  if( $hours === null ) $hours = 0;

  if( ENABLE_STOCK ) {
    # for quotes, include labor estimates from the stock card
    $sql = "SELECT SUM(HOURS) as HOURS FROM stock_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
    if( $end_date ) {
      $sql .= " AND CREATED < :END_DATE";
    }
    $stock_hours_stmt = $dbh->prepare($sql);
    $stock_hours_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
    if( $end_date ) {
      $stock_hours_stmt->bindValue(":END_DATE",$end_date);
    }
    $stock_hours_stmt->execute();
    $stock_hours = $stock_hours_stmt->fetch()["HOURS"];
    if( $stock_hours === null ) $stock_hours = 0;
    $hours += $stock_hours;
  }

  $sql = "SELECT CONTRACT_HOURLY_RATE FROM work_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  $hourly_rate_stmt = $dbh->prepare($sql);
  $hourly_rate_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $hourly_rate_stmt->execute();
  $row = $hourly_rate_stmt->fetch();
  if( $row ) {
    $hourly_rate = $row["CONTRACT_HOURLY_RATE"];
  }

  if( $hours == 0 ) {
    # no need for hourly rate if hours is 0
    $labor_charge = 0;
  }
  else if( $hourly_rate ) {
    $labor_charge = $hours * $hourly_rate;
  }
  $labor_charge = floor($labor_charge*100)/100.0;
}

function getWorkOrderMaterialsCharge($wo_id,$end_date,&$materials_charge) {
  $materials_charge = null;

  $dbh = connectDB();

  if( ENABLE_STOCK ) {
    $sql = "SELECT SUM(TOTAL_COST) as TOTAL_COST FROM stock_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND CANCELED IS NULL";
    if( $end_date ) {
      $sql .= " AND CREATED < :END_DATE";
    }
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
    if( $end_date ) {
      $stmt->bindValue(":END_DATE",$end_date);
    }
    $stmt->execute();
    $materials_charge = $stmt->fetch()["TOTAL_COST"];
  }

  if( $materials_charge === null ) $materials_charge = 0;

  foreach( getOtherShopsInWorkOrders() as $shop_name => $shop_info ) {
    $items = getShopItemsInWorkOrder($wo_id,$shop_info);
    foreach( $items as $item ) {
      if( !$end_date || $item['DATE'] < $end_date ) {
        $materials_charge += $item['TOTAL'];
      }
    }
  }
  $materials_charge = floor($materials_charge*100)/100.0;
}

function billWorkOrder($wo_id,$end_date,$billing_batch_id) {
  getWorkOrderLaborCharge($wo_id,$end_date,$labor_charge,$hours,$hourly_rate);
  getWorkOrderMaterialsCharge($wo_id,$end_date,$materials_charge);

  $dbh = connectDB();

  $sql = "SELECT
    SUM(MATERIALS_CHARGE) as MATERIALS_CHARGE,
    SUM(LABOR_CHARGE) as LABOR_CHARGE
  FROM
    work_order_bill
  WHERE
    WORK_ORDER_ID = :WORK_ORDER_ID
    AND END_DATE <= :END_DATE";

  $prev_bill_stmt = $dbh->prepare($sql);
  $prev_bill_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $prev_bill_stmt->bindValue(":END_DATE",$end_date);
  $prev_bill_stmt->execute();
  $prev_bill = $prev_bill_stmt->fetch();

  if( $prev_bill ) {
    if( $prev_bill["MATERIALS_CHARGE"] ) {
      $materials_charge -= $prev_bill["MATERIALS_CHARGE"];
    }
    if( $prev_bill["LABOR_CHARGE"] ) {
      $labor_charge -= $prev_bill["LABOR_CHARGE"];
    }
  }

  # After changing getWorkOrderLaborCharge to truncate to the penny, avoid making small adjustments to previous bills.
  if( abs($labor_charge) < 0.02 ) {
    $labor_charge = 0.0;
  }

  # After changing getWorkOrderMaterialsCharge to truncate to the penny, avoid making small adjustments to previous bills.
  if( abs($materials_charge) < 0.02 ) {
    $materials_charge = 0.0;
  }

  if( abs($materials_charge) < 0.01 && abs($labor_charge) < 0.01 ) {
    return true;
  }

  $sql = "SELECT
    GROUP_ID,
    FUNDING_SOURCE_ID
  FROM
    work_order
  WHERE
    WORK_ORDER_ID = :WORK_ORDER_ID";
  $wo_stmt = $dbh->prepare($sql);
  $wo_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $wo_stmt->execute();
  $wo = $wo_stmt->fetch();
  if( !$wo ) {
    return false;
  }

  $sql = "INSERT INTO work_order_bill SET
    WORK_ORDER_ID = :WORK_ORDER_ID,
    BILLING_BATCH_ID = :BILLING_BATCH_ID,
    GROUP_ID = :GROUP_ID,
    FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
    CREATED = NOW(),
    END_DATE = :END_DATE,
    MATERIALS_CHARGE = :MATERIALS_CHARGE,
    LABOR_CHARGE = :LABOR_CHARGE,
    HOURLY_RATE = :HOURLY_RATE";
  $stmt = $dbh->prepare($sql);

  $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->bindValue(":GROUP_ID",$wo["GROUP_ID"]);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$wo["FUNDING_SOURCE_ID"]);
  $stmt->bindValue(":END_DATE",$end_date);
  $stmt->bindValue(":MATERIALS_CHARGE",round($materials_charge,2));
  $stmt->bindValue(":LABOR_CHARGE",round($labor_charge,2));
  $stmt->bindValue(":HOURLY_RATE",$hourly_rate);
  if( $stmt->execute() ) {
    return true;
  }
}

function billWorkOrders($end_date,$billing_batch_id) {
  if( ENABLE_CHECKOUT_BATCH_BILLING ) {
    createCheckoutOrders($end_date,$billing_batch_id);
  }

  $dbh = connectDB();

  # One reason for filtering out work orders that have no admin dates set (reviewed etc.)
  # is to avoid billing student shop access orders that have not been accepted yet.

  $sql = "SELECT WORK_ORDER_ID FROM work_order
    WHERE
      STATUS NOT IN (
        '" . WORK_ORDER_STATUS_TIMECODE . "'
      )
      AND NOT IS_QUOTE
      AND CREATED < :END_DATE
      AND (REVIEWED IS NOT NULL OR QUEUED IS NOT NULL OR CLOSED IS NOT NULL OR COMPLETED IS NOT NULL OR CANCELED IS NOT NULL)";

  # TODO: decide whether to filter out closed orders above
  #        '" . WORK_ORDER_STATUS_CLOSED . "',

  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":END_DATE",$end_date);
  $stmt->execute();

  while( ($row=$stmt->fetch()) ) {
    billWorkOrder($row["WORK_ORDER_ID"],$end_date,$billing_batch_id);
  }
}

function createCheckoutOrders($end_date,$billing_batch_id) {
  $dbh = connectDB();

  $sql_checkout = "DATE < :END_DATE AND DELETED IS NULL AND BILLING_WORK_ORDER_ID IS NULL";
  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    $wo_col = $shop_info['CHECKOUT_WORK_ORDER_ID_COL'];
    $sql_checkout .= " AND $wo_col IS NULL";
  }

  $sql = "SELECT checkout.USER_ID,checkout.GROUP_ID,checkout.FUNDING_SOURCE_ID,user.LAST_NAME,user.FIRST_NAME FROM checkout join user on user.USER_ID = checkout.USER_ID WHERE {$sql_checkout} GROUP BY checkout.USER_ID,checkout.GROUP_ID,checkout.FUNDING_SOURCE_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":END_DATE",$end_date);
  $stmt->execute();

  $sql = "UPDATE checkout SET BILLING_WORK_ORDER_ID = :WORK_ORDER_ID WHERE USER_ID = :USER_ID AND GROUP_ID = :GROUP_ID AND FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID AND {$sql_checkout}";
  $update_checkout_stmt = $dbh->prepare($sql);
  $update_checkout_stmt->bindValue(":END_DATE",$end_date);

  $sql = "
    INSERT INTO work_order SET
    CHECKOUT_ORDER = 1,
    WORK_ORDER_NUM = :WORK_ORDER_NUM,
    ORDERED_BY = :USER_ID,
    GROUP_ID = :GROUP_ID,
    FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
    CREATED = :CREATED,
    COMPLETED = now(),
    CLOSED = now(),
    DESCRIPTION = :DESCRIPTION";
  $insert_stmt = $dbh->prepare($sql);
  $creation_date = getPrevDay($end_date);
  $insert_stmt->bindValue("CREATED",$creation_date);

  while( ($row=$stmt->fetch()) ) {
    $wo_num = getUniqueWorkOrderNum(null,"Chk",$creation_date);
    $insert_stmt->bindValue(":WORK_ORDER_NUM",$wo_num);
    $insert_stmt->bindValue(":USER_ID",$row["USER_ID"]);
    $insert_stmt->bindValue(":GROUP_ID",$row["GROUP_ID"]);
    $insert_stmt->bindValue(":FUNDING_SOURCE_ID",$row["FUNDING_SOURCE_ID"]);
    $user_name = $row["FIRST_NAME"] . " " . $row["LAST_NAME"];
    $insert_stmt->bindValue(":DESCRIPTION","Accumulated " . SHOP_NAME . " checkouts by {$user_name} for period ending on {$end_date}.");
    $insert_stmt->execute();
    $wo_id = $dbh->lastInsertId();

    $update_checkout_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
    $update_checkout_stmt->bindValue(":USER_ID",$row["USER_ID"]);
    $update_checkout_stmt->bindValue(":GROUP_ID",$row["GROUP_ID"]);
    $update_checkout_stmt->bindValue(":FUNDING_SOURCE_ID",$row["FUNDING_SOURCE_ID"]);
    $update_checkout_stmt->execute();
  }
}

function newBillingBatch($end_date) {
  $base_name = date("Y-m",strtotime(getPrevDay($end_date)));
  $dbh = connectDB();
  $sql = "SELECT BILLING_BATCH_ID FROM billing_batch WHERE BATCH_NAME = :BATCH_NAME";
  $exists_stmt = $dbh->prepare($sql);

  $sql = "INSERT INTO billing_batch SET BATCH_NAME = :BATCH_NAME, CREATED = NOW()";
  $insert_stmt = $dbh->prepare($sql);

  $chars = "abcdefghijklmnopqrstuvwxyz";
  $batch_name = "";
  $found = false;
  for($i=0; $i<strlen($chars); $i++) {
    if( $i==0 ) $batch_name = $base_name;
    else $batch_name = $base_name . substr($chars,$i,1);
    $exists_stmt->bindValue(":BATCH_NAME",$batch_name);
    $exists_stmt->execute();
    if( !$exists_stmt->fetch() ) {
      $found = true;
      break;
    }
  }
  if( !$found ) {
    throw new Exception("Failed to allocate new batch name with base name $base_name");
    return null;
  }

  $insert_stmt->bindValue(":BATCH_NAME",$batch_name);
  $insert_stmt->execute();
  $batch_id = $dbh->lastInsertId();

  return $batch_id;
}

function startBillingTransaction() {
  $dbh = connectDB();
  $sql = "LOCK TABLES billing_batch WRITE, work_order_bill WRITE, work_order WRITE, timesheet READ, checkout WRITE, part READ, user READ";

  if( ENABLE_STOCK ) {
    $sql .= ", stock_order READ";
  }

  foreach( getOtherShopsInWorkOrders() as $shop_name => $shop_info ) {
    if( $shop_info['CHECKOUT_TABLE'] == "checkout" ) {
      continue; # already locked our own checkout table
    }
    $sql .= ", " . $shop_info['CHECKOUT_TABLE'] . ' READ';
    $sql .= ", " . $shop_info['PART_TABLE'] . ' READ';
  }

  $dbh->beginTransaction();
  $dbh->exec($sql);
}

function commitBillingTransaction() {
  $dbh = connectDB();
  $dbh->commit();
  $dbh->exec("UNLOCK TABLES");
}

function billLastMonthWorkOrders($billing_batch_id=null) {
  $end_date = date("Y-m-01");
  $transaction_started = false;
  if( !$billing_batch_id ) {
    startBillingTransaction();
    $transaction_started = true;
    $billing_batch_id = newBillingBatch($end_date);
  }
  billWorkOrders($end_date,$billing_batch_id);
  if( $transaction_started ) {
    commitBillingTransaction();
  }
}

function billLastYearWorkOrders() {
  $end_date = date("Y-m-01");
  $this_end_date = $end_date;
  for( $i=0; $i<12; $i++ ) {
    $this_end_date = getPrevMonth($this_end_date);
  }

  startBillingTransaction();
  $billing_batch_id = newBillingBatch($end_date);

  for( ; $this_end_date <= $end_date; $this_end_date = getNextMonth($this_end_date) ) {
    billWorkOrders($this_end_date,$billing_batch_id);
  }
  commitBillingTransaction();
}

function processedBillingBatch($billing_batch_id) {
  startBillingTransaction();

  $dbh = connectDB();
  $sql = "UPDATE billing_batch SET PROCESSED = now() WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID AND PROCESSED IS NULL";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();

  $sql = "UPDATE work_order_bill SET STATUS = :STATUS WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID AND STATUS = ''";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->bindValue(":STATUS",WORK_ORDER_BILL_STATUS_PAID);
  $stmt->execute();

  commitBillingTransaction();
}

function showBillingBatches() {

  if( isset($_REQUEST["id"]) ) {
    return showBillingBatch($_REQUEST["id"]);
  }

  $page_limit = 200;
  $page = isset($_REQUEST["page"]) ? (int)$_REQUEST["page"] : 0;
  $offset = $page*$page_limit;

  $dbh = connectDB();
  $sql = "
    SELECT
      *,
      (SELECT SUM(MATERIALS_CHARGE+LABOR_CHARGE) FROM work_order_bill WHERE work_order_bill.BILLING_BATCH_ID = billing_batch.BILLING_BATCH_ID) as TOTAL,
      (SELECT MIN(STATEMENT_SENT IS NOT NULL) FROM work_order_bill WHERE work_order_bill.BILLING_BATCH_ID = billing_batch.BILLING_BATCH_ID) as STATEMENTS_SENT
    FROM
      billing_batch
    ORDER BY BILLING_BATCH_ID DESC
    LIMIT " . ($page_limit+1) . " OFFSET {$offset}";

  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  echo "<table class='records clicksort'><thead><tr><th>Billing Batch</th><th>Status</th><th>Statements<br>Sent</th><th>Files<br>Saved</th><th>Total</th></tr></thead><tbody>";
  $item_num = 0;
  while( ($row=$stmt->fetch()) ) {
    $item_num += 1;
    if( $item_num > $page_limit ) {
      break;
    }
    echo "<tr>";
    $url = "?s=billing&id=" . $row["BILLING_BATCH_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["BATCH_NAME"]),"</a></td>";
    $status = "";
    if( $row["PROCESSED"] ) $status = "Processed";
    echo "<td>",htmlescape($status),"</td>";
    $statements_sent = $row["STATEMENTS_SENT"] ? "<i class='fas fa-check text-success'></i>" : '';
    echo "<td class='centered'>$statements_sent</td>";
    $files_saved = $row["ARCHIVED"] ? "<i class='fas fa-check text-success'></i>" : '';
    echo "<td class='centered'>$files_saved</td>";
    echo "<td class='currency'>",htmlescape($row["TOTAL"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";

  $url_params = array();
  if( isset($_REQUEST["s"]) ) $url_params["s"] = $_REQUEST["s"];

  echo "<p>";
  if( $page ) {
    if( $page-1 ) {
      $url = arrayToUrlQueryString(array_merge($url_params,array("page" => $page-1)));
    } else {
      $url = arrayToUrlQueryString($url_params);
      if( !$url ) $url = $self_full_url;
    }
    echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Newer Billing Batches</a>";
  }
  if( $item_num > $page_limit ) {
    $url = arrayToUrlQueryString(array_merge($url_params,array("page" => $page+1)));
    echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Older Billing Batches</a>";
  }
  echo "</p>\n";
}

function showBillingBatch($billing_batch_id) {
  $dbh = connectDB();
  $bb_row = loadBillingBatch($billing_batch_id);
  if( !$bb_row ) {
    echo "<div class='alert alert-danger'>No billing batch with id ",htmlescape($billing_batch_id)," found.</div>\n";
    return;
  }
  $batch_name = $bb_row["BATCH_NAME"];

  echo "<h2>Billing Batch ",htmlescape($batch_name),"</h2>\n";

  echo "<div class='card'><div class='card-body'>\n";

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='billing_batch'/>\n";
  echo "<input type='hidden' name='id' value='",htmlescape($billing_batch_id),"'/>\n";

  echo "<div class='container-fluid'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  if( $bb_row["PROCESSED"] ) {
    echo "<div class='$rowclass'><div class='$col1'>Billing Processed</div><div class='col'>",displayDate($bb_row["PROCESSED"]),"</div></div>\n";
  }

  echo "<div class='$rowclass'><div class='$col1'>Billing Batch</div><div class='col'><input name='batch_name' value='",htmlescape($bb_row["BATCH_NAME"]),"'/></div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='admin_notes'>Admin Notes</label></div><div class='col'>";
  echo "<textarea name='admin_notes' id='admin_notes' rows='5' cols='40'>",htmlescape($bb_row["ADMIN_NOTES"]),"</textarea>";
  echo "</div></div>\n";

  echo "</div>\n"; # end of container

  echo "<p>";
  if( $bb_row["PROCESSED"] ) {
    echo "<input type='submit' name='submit' value='Submit'/>\n";
  } else {
    echo "<input type='submit' name='submit' value='Save'>\n";
    echo "<input type='submit' name='submit' value='Save &amp; Mark as Done'>\n";
  }
  echo "</p>\n";
  echo "</form>\n";

  echo "</div></div>"; #end of card

  echo "<p>&nbsp;<br>";
  $url = "?s=export_billing_batch_xfer&id=" . $billing_batch_id;
  echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Download Pending Transfers</a>";
  $url .= "&done";
  echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Download Done Transfers</a>";
  $url = "?s=export_billing_batch_raw_data&id=" . $billing_batch_id;
  echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Download Raw Data</a>";
  $url = "?s=billing_batch_statements&id=" . $billing_batch_id;
  echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Statements</a>";
  $url = "?s=billing_batch_receipts_pdf&id=" . $billing_batch_id;
  echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Receipts</a>";
  if( STORE_BILLING_FILES_SUPPORTED ) {
    $url = "?s=billing_batch_store_files&id=" . $billing_batch_id;
    echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Store Files</a>";
  }
  echo "</p>\n";

  if( $bb_row["ARCHIVED"] ) {
    echo "<p>Last stored transfers and receipts on ",displayDate($bb_row["ARCHIVED"]),".</p>\n";
  }

  $sql = "
    SELECT
      work_order_bill.*,
      work_order.WORK_ORDER_NUM,
      work_order.ORDERED_BY,
      user.FIRST_NAME,
      user.LAST_NAME,
      user_group.GROUP_NAME,
      funding_source.PI_NAME,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    FROM
      work_order_bill
    LEFT JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      user_group
    ON
      user_group.GROUP_ID = work_order_bill.GROUP_ID
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    WHERE
      BILLING_BATCH_ID = :BILLING_BATCH_ID
    ORDER BY
      work_order_bill.END_DATE,WORK_ORDER_NUM
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();

  $bills_by_pi_user = array();
  while( ($row=$stmt->fetch()) ) {
    $user_name = $row["LAST_NAME"] . ", " . $row["FIRST_NAME"];
    $pi = $row["PI_NAME"];
    if( !$pi || $pi == "unknown, unknown" ) {
      $pi = $user_name;
    }
    if( !isset($bills_by_pi_user[$pi]) ) {
      $bills_by_pi_user[$pi] = array();
    }
    if( !isset($bills_by_pi_user[$pi][$user_name]) ) {
      $bills_by_pi_user[$pi][$user_name] = array();
    }
    $bills_by_pi_user[$pi][$user_name][] = $row;
  }

  $date_fmt = "M";
  $same_year = "";
  foreach( $bills_by_pi_user as $pi => $bills_by_user ) {
    foreach( $bills_by_user as $user_name => $user_bills ) {
      foreach( $user_bills as $bill ) {
        $year = date("Y",strtotime($bill["END_DATE"]));
	if( $same_year === "" ) $same_year = $year;
	if( $same_year !== $year ) $same_year = null;
      }
    }
  }
  if( $same_year === null ) $date_fmt = "M Y";


  echo "<table class='records' style='border: none'>";
  ksort( $bills_by_pi_user );
  foreach( $bills_by_pi_user as $pi => $bills_by_user ) {

    echo "<tr><th style='border: none; padding-top: 1em' colspan='11'></th></tr>\n";
    echo "<tr class='centered dark' ><th colspan='11'>",htmlescape($pi),"</th></tr>\n";
    echo "<tr class='centered dark'><th></th><th>Customer</th><th>Order</th><th>Date</th><th>Materials</th><th>Labor</th><th>Rate</th><th>Fund Group</th><th>Fund Description</th><th>Funding String</th><th>Notes</th></tr>\n";

    $pi_total_labor = 0;
    $pi_total_materials = 0;

    ksort($bills_by_user);
    foreach( $bills_by_user as $user_name => $user_bills ) {

      $user_total_labor = 0;
      $user_total_materials = 0;
      foreach( $user_bills as $bill ) {
        $user_total_labor += $bill["LABOR_CHARGE"];
        $user_total_materials += $bill["MATERIALS_CHARGE"];
        echo "<tr>";
        $url = "?s=work_order_bill&id=" . $bill["BILL_ID"];
        echo "<td><a href='",htmlescape($url),"' class='icon'><i class='far fa-edit'></i></a>",getWorkOrderBillStatusIcon($bill),"</td>";
        echo "<td>",htmlescape($user_name),"</td>";
        $url = "?s=work_order&id=" . $bill["WORK_ORDER_ID"];
        echo "<td><a href='",htmlescape($url),"'>",htmlescape($bill["WORK_ORDER_NUM"]),"</a></td>";
        echo "<td class='white-space: nowrap'>",date($date_fmt,strtotime(getPrevDay($bill["END_DATE"]))),"</td>";
        echo "<td class='currency'>",htmlescape($bill["MATERIALS_CHARGE"]),"</td>";
        echo "<td class='currency'>",htmlescape($bill["LABOR_CHARGE"]),"</td>";
        echo "<td class='currency'>",htmlescape($bill["HOURLY_RATE"]),"</td>";
        echo "<td>",htmlescape($bill["GROUP_NAME"]),"</td>";
        echo "<td>",htmlescape($bill["FUNDING_DESCRIPTION"]),"</td>";
        $funding_string = "<span class='light-underline'>" . trim($bill["FUNDING_FUND"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_PROJECT"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_DEPT"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_PROGRAM"]) . "</span>";
        echo "<td>",$funding_string,"</td>";
        echo "<td>",htmlescape($bill["ADMIN_NOTES"]),"</td>";
        echo "</tr>\n";
      }
      if( count($bills_by_user)>1 ) {
        echo "<tr><td></td><td></td><th>Total</th><td></td>";
        echo "<td class='currency'>",sprintf("%.2f",$user_total_materials),"</td>";
        echo "<td class='currency'>",sprintf("%.2f",$user_total_labor),"</td>";
        echo "<td></td><td></td><td></td><td></td><td></td></tr>\n";
      }
      $pi_total_labor += $user_total_labor;
      $pi_total_materials += $user_total_materials;
    }
    echo "<tr><td></td><th>Total</th><td></td><td></td>";
    echo "<td class='currency'>",sprintf("%.2f",$pi_total_materials),"</td>";
    echo "<td class='currency'>",sprintf("%.2f",$pi_total_labor),"</td>";
    echo "<td></td><td></td><th style='text-align: right'>Grand Total</th><th class='currency'>",sprintf("%.2f",$pi_total_materials+$pi_total_labor),"</th><td></td></tr>\n";
  }
  echo "</table>\n";
  echo "<p>&nbsp;</p>\n";
}

function getWorkOrderBillStatusIcon($bill) {
  if( $bill["STATUS"] == WORK_ORDER_BILL_STATUS_PAID ) {
    return "<i class='fas fa-check text-success'></i>";
  }
  return "";
}

function mergeBillingBatches() {
  if( !isAdmin() ) {
    return;
  }
  startBillingTransaction();

  $dbh = connectDB();
  $sql = "UPDATE work_order_bill SET BILLING_BATCH_ID = :NEW_BILLING_BATCH_ID WHERE BILLING_BATCH_ID = :OLD_BILLING_BATCH_ID";
  $update_work_order_bills_stmt = $dbh->prepare($sql);
  $update_work_order_bills_stmt->bindValue(":NEW_BILLING_BATCH_ID",$_POST["dest_batch_id"]);

  $sql = "DELETE FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $delete_merged_batch_stmt = $dbh->prepare($sql);

  $update_work_order_bills_stmt->bindValue(":OLD_BILLING_BATCH_ID",$_POST["src_batch_id"]);
  $update_work_order_bills_stmt->execute();

  $delete_merged_batch_stmt->bindValue(":BILLING_BATCH_ID",$_POST["src_batch_id"]);
  $delete_merged_batch_stmt->execute();

  commitBillingTransaction();

  echo "<div class='alert alert-success'>Merged.</div>\n";
}

function saveBillingBatch() {
  global $self_path;

  if( !isAdmin() ) {
    return;
  }
  $billing_batch_id = $_POST["id"];

  $bb_row = loadBillingBatch($billing_batch_id);

  if( isset($_POST["submit"]) && strstr($_POST["submit"],"Mark as Done") !== false ) {
    processedBillingBatch($billing_batch_id);
  }

  $new_batch_name = $_POST["batch_name"];

  $dbh = connectDB();
  $sql = "SELECT BILLING_BATCH_ID FROM billing_batch WHERE BATCH_NAME = :BATCH_NAME AND BILLING_BATCH_ID <> :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BATCH_NAME",$new_batch_name);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  $same_name = $stmt->fetch();
  if( $same_name ) {
    # for now, keep the existing name to avoid a conflict
    $new_batch_name = $bb_row["BATCH_NAME"];

    echo "<div class='alert alert-info'>There are is another billing batch with the name '",htmlescape($bb_row["BATCH_NAME"]),"'.  Would you like to merge them into one?  If so, click ";
    echo "<form enctype='multipart/form-data' method='POST' autocomplete='off' action='$self_path'>\n";
    echo "<input type='hidden' name='form' value='merge_batches'/>";
    echo "<input type='hidden' name='dest_batch_id' value='",htmlescape($same_name["BILLING_BATCH_ID"]),"'/>";
    echo "<input type='hidden' name='src_batch_id' value='",htmlescape($billing_batch_id),"'/>";
    echo "<input type='hidden' name='s' value='billing'/>";
    echo "<input type='hidden' id='",htmlescape($same_name["BILLING_BATCH_ID"]),"'/>";
    echo "<input type='submit' value='Merge'/>";
    echo "</form>";
    echo "</div>\n";
  }

  $dbh = connectDB();
  $sql = "UPDATE billing_batch SET BATCH_NAME = :BATCH_NAME, ADMIN_NOTES = :ADMIN_NOTES WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->bindValue(":BATCH_NAME",$new_batch_name);
  $stmt->bindValue(":ADMIN_NOTES",$_POST["admin_notes"]);
  $stmt->execute();
  echo "<div class='alert alert-success'>Saved</div>\n";
}

function exportBillingBatchRawData() {
  $billing_batch_id = $_REQUEST["id"];

  $dbh = connectDB();
  $sql = "SELECT * FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  $bb_row = $stmt->fetch();
  if( !$bb_row ) {
    return;
  }

  $filename = makeSafeFileName(SHOP_NAME . '_BillingBatch_' . $bb_row["BATCH_NAME"]) . ".csv";

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  $sql = "
    SELECT
      work_order_bill.MATERIALS_CHARGE,
      work_order_bill.LABOR_CHARGE,
      work_order_bill.HOURLY_RATE,
      work_order_bill.END_DATE,
      work_order_bill.STATUS,
      work_order.WORK_ORDER_NUM,
      user.FIRST_NAME,
      user.LAST_NAME,
      user.EMAIL,
      user_group.GROUP_NAME,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND,
      funding_source.PI_NAME,
      funding_source.PI_EMAIL,
      funding_source.BILLING_CONTACT_NAME,
      funding_source.BILLING_CONTACT_EMAIL
    FROM
      work_order_bill
    LEFT JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      user_group
    ON
      user_group.GROUP_ID = work_order_bill.GROUP_ID
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    WHERE
      BILLING_BATCH_ID = :BILLING_BATCH_ID
    ORDER BY
      work_order.WORK_ORDER_NUM
    ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();

  $csv = array("Order","Date","Materials","Labor","Hourly Rate","Paid","Fund Group","Fund Description","Department","Fund","Program","Project","Customer First","Customer Last","Customer Email","Fund PI","PI Email","Billing Contact","Billing Contact Email");
  fputcsv_excel($F,$csv);

  while( ($row=$stmt->fetch()) ) {
    $csv = array();
    $csv[] = $row["WORK_ORDER_NUM"];
    $csv[] = date("Y-m",strtotime(getPrevDay($row["END_DATE"])));
    $csv[] = $row["MATERIALS_CHARGE"];
    $csv[] = $row["LABOR_CHARGE"];
    $csv[] = $row["HOURLY_RATE"];
    $csv[] = $row["STATUS"] == WORK_ORDER_BILL_STATUS_PAID ? "Paid" : "";
    $csv[] = $row["GROUP_NAME"];
    $csv[] = $row["FUNDING_DESCRIPTION"];
    $department = $row["FUNDING_DEPT"];
    if( substr($department,0,1) == "A" ) {
      $department = substr($department,1);
    }
    $csv[] = $department;
    $csv[] = $row["FUNDING_FUND"];
    $csv[] = $row["FUNDING_PROGRAM"];
    $csv[] = $row["FUNDING_PROJECT"];
    $csv[] = $row["FIRST_NAME"];
    $csv[] = $row["LAST_NAME"];
    $csv[] = $row["EMAIL"];
    $csv[] = $row["PI_NAME"];
    $csv[] = $row["PI_EMAIL"];
    $csv[] = $row["BILLING_CONTACT_NAME"];
    $csv[] = $row["BILLING_CONTACT_EMAIL"];

    fputcsv_excel($F,$csv);
  }

  fclose($F);
}

function getBillingBatchTransfersFname($billing_batch_id,$ext) {
  $dbh = connectDB();
  $sql = "SELECT * FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  $bb_row = $stmt->fetch();
  if( !$bb_row ) {
    return;
  }

  $filename = makeSafeFileName(formatBatchFileName($bb_row["BATCH_NAME"]) . '_' . SHOP_NAME . '_xfers') . $ext;
  return $filename;
}

function exportBillingBatchTransfersCSV() {
  $billing_batch_id = $_REQUEST["id"];

  $filename = getBillingBatchTransfersFname($billing_batch_id,".csv");

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  if( isset($_REQUEST["done"]) ) {
    $status = WORK_ORDER_BILL_STATUS_PAID;
  } else {
    $status = ''; # not processed
  }

  writeBillingBatchTransfersCSV($F,$billing_batch_id,$status);
  fclose($F);
}

function exportBillingBatchTransfersExcel() {
  $billing_batch_id = $_REQUEST["id"];

  $filename = getBillingBatchTransfersFname($billing_batch_id,".xlsx");

  header("Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  if( isset($_REQUEST["done"]) ) {
    $status = WORK_ORDER_BILL_STATUS_PAID;
  } else {
    $status = ''; # not processed
  }

  writeBillingBatchTransfersExcel($F,$billing_batch_id,$status);
  fclose($F);
}

function writeBillingBatchTransfersCSV($F,$billing_batch_id,$status) {
  fputcsv_excel($F,array("NSCT"));
  $csv = array("Department","Fund","Program","Project","Activity ID","Account","Class","Amount","Description (30)","Jnl_Ln_Ref (10)","Reference (10)","Voucher No (10)","Invoice No (12)");
  fputcsv_excel($F,$csv);

  $bb_row = loadBillingBatch($billing_batch_id);

  $status_sql = '';
  if( $status != 'all' ) {
    $status_sql = "AND work_order_bill.STATUS = :BILL_STATUS";
  }

  $dbh = connectDB();
  $sql = "
    SELECT
      SUM(work_order_bill.MATERIALS_CHARGE) as MATERIALS_CHARGE,
      SUM(work_order_bill.LABOR_CHARGE) as LABOR_CHARGE,
      work_order.WORK_ORDER_NUM,
      work_order.INVENTORY_NUM,
      work_order.STOCK_ORDER,
      user.FIRST_NAME,
      user.LAST_NAME,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    FROM
      work_order_bill
    LEFT JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      user_group
    ON
      user_group.GROUP_ID = work_order_bill.GROUP_ID
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    WHERE
      BILLING_BATCH_ID = :BILLING_BATCH_ID
      $status_sql
    GROUP BY
      work_order.WORK_ORDER_NUM,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    ORDER BY
      work_order.WORK_ORDER_NUM
    ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  if( $status_sql ) {
    $stmt->bindValue(":BILL_STATUS",$status);
  }
  $stmt->execute();

  $amount_sum = 0;
  while( ($row=$stmt->fetch()) ) {
    $csv = array();
    $department = $row["FUNDING_DEPT"];
    if( substr($department,0,1) == "A" ) {
      $department = substr($department,1);
    }
    $name = $row["LAST_NAME"];
    if( $name == "Group" ) {
      $name = $row["FIRST_NAME"];
    }
    $csv[] = $department;
    $csv[] = $row["FUNDING_FUND"];
    $csv[] = $row["FUNDING_PROGRAM"];
    $csv[] = $row["FUNDING_PROJECT"];
    $csv[] = "";
    $account = BILLING_ACCOUNT($row);
    $csv[] = $account;
    $csv[] = "";
    $amount = $row["MATERIALS_CHARGE"] + $row["LABOR_CHARGE"];
    $csv[] = $amount;
    $amount_sum += $amount;
    $csv[] = BILLING_TRANSFER_SHOP_NAME;

    if( BILLING_JNL_LN_REF == "WORK_ORDER_NUM" ) {
      # in shops like ishop where the work order number is known to the customer, put that in this column, which is easier for people to see
      $csv[] = $row["WORK_ORDER_NUM"];
    } else {
      # otherwise, put the name of the person associated with the purchase
      $csv[] = $name;
    }
    $csv[] = $row["INVENTORY_NUM"];
    $csv[] = "";

    # whichever was chosen for "Jnl Ln Ref" column, choose the opposite here, which is harder for people to look up but still hopefully accessible
    if( BILLING_JNL_LN_REF == "WORK_ORDER_NUM" ) {
      $csv[] = $name;
    } else {
      $csv[] = $row["WORK_ORDER_NUM"];
    }

    if( fputcsv_excel($F,$csv) === false ) {
      return false;
    }
  }
  $csv = array('','','','','','','','','','','','');
  $csv[7] = -$amount_sum;
  $csv[8] = BILLING_TRANSFER_SHOP_NAME . " " . $bb_row["BATCH_NAME"];
  UPDATE_BILLING_DEST_ACCOUNT_LINE($csv);
  if( fputcsv_excel($F,$csv) === false ) {
    return false;
  }

  return true;
}

function writeBillingBatchTransfersExcel($F,$billing_batch_id,$status) {
  require_once "vendor/autoload.php";

  // instantiate the class
  $doc = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $doc->setActiveSheetIndex(0);
  $sheet = $doc->getActiveSheet();

  # Store all data as text.  This avoids leading zeros from being stripped.
  \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder() );

  $sheet->fromArray(array("NSCT"),null,'A1');
  $sheet->fromArray(array("Department","Fund","Program","Project","Activity ID","Account","Class","Amount","Description (30)","Jnl_Ln_Ref (10)","Reference (10)","Voucher No (10)","Invoice No (12)"),null,'A2');
  $row_num = 3;

  $bb_row = loadBillingBatch($billing_batch_id);

  $status_sql = '';
  if( $status != 'all' ) {
    $status_sql = "AND work_order_bill.STATUS = :BILL_STATUS";
  }

  $dbh = connectDB();
  $sql = "
    SELECT
      SUM(work_order_bill.MATERIALS_CHARGE) as MATERIALS_CHARGE,
      SUM(work_order_bill.LABOR_CHARGE) as LABOR_CHARGE,
      work_order.WORK_ORDER_NUM,
      work_order.INVENTORY_NUM,
      work_order.STOCK_ORDER,
      user.FIRST_NAME,
      user.LAST_NAME,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    FROM
      work_order_bill
    LEFT JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      user_group
    ON
      user_group.GROUP_ID = work_order_bill.GROUP_ID
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    WHERE
      BILLING_BATCH_ID = :BILLING_BATCH_ID
      $status_sql
    GROUP BY
      work_order.WORK_ORDER_NUM,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    ORDER BY
      work_order.WORK_ORDER_NUM
    ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  if( $status_sql ) {
    $stmt->bindValue(":BILL_STATUS",$status);
  }
  $stmt->execute();

  $amount_sum = 0;
  while( ($row=$stmt->fetch()) ) {
    $csv = array();
    $department = $row["FUNDING_DEPT"];
    if( substr($department,0,1) == "A" ) {
      $department = substr($department,1);
    }
    $name = $row["LAST_NAME"];
    if( $name == "Group" ) {
      $name = $row["FIRST_NAME"];
    }
    $csv[] = $department;
    $csv[] = $row["FUNDING_FUND"];
    $csv[] = $row["FUNDING_PROGRAM"];
    $csv[] = $row["FUNDING_PROJECT"];
    $csv[] = "";
    $account = BILLING_ACCOUNT($row);
    $csv[] = $account;
    $csv[] = "";
    $amount = $row["MATERIALS_CHARGE"] + $row["LABOR_CHARGE"];
    $csv[] = $amount;
    $amount_sum += $amount;
    $csv[] = BILLING_TRANSFER_SHOP_NAME;

    if( BILLING_JNL_LN_REF == "WORK_ORDER_NUM" ) {
      # in shops like ishop where the work order number is known to the customer, put that in this column, which is easier for people to see
      $csv[] = $row["WORK_ORDER_NUM"];
    } else {
      # otherwise, put the name of the person associated with the purchase
      $csv[] = $name;
    }
    $csv[] = $row["INVENTORY_NUM"];
    $csv[] = "";

    # whichever was chosen for "Jnl Ln Ref" column, choose the opposite here, which is harder for people to look up but still hopefully accessible
    if( BILLING_JNL_LN_REF == "WORK_ORDER_NUM" ) {
      $csv[] = $name;
    } else {
      $csv[] = $row["WORK_ORDER_NUM"];
    }

    $sheet->fromArray($csv,null,'A' . $row_num++);
  }
  $csv = array('','','','','','','','','','','','');
  $csv[7] = -$amount_sum;
  $csv[8] = BILLING_TRANSFER_SHOP_NAME . " " . $bb_row["BATCH_NAME"];
  UPDATE_BILLING_DEST_ACCOUNT_LINE($csv);
  $sheet->fromArray($csv,null,'A' . $row_num++);

  $last_column = $doc->getActiveSheet()->getHighestColumn();
  $last_row = $doc->getActiveSheet()->getHighestRow();

  // autosize all columns to content width
  for ($i = 'A'; $i <= $last_column; $i++) {
      $sheet->getColumnDimension($i)->setAutoSize(TRUE);
  }
  // store transaction amounts as numeric values, so it is easier to manipulate them in Excel if necessary
  for( $i=3; $i <= $last_row; $i++ ) {
    $value = $sheet->getCell("H$i")->getValue();
    $sheet->setCellValueExplicit("H$i",$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  }

  // write and save the file
  $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($doc);
  $writer->save($F);

  return true;
}

function showWorkOrderBill($web_user) {
  $bill_id = $_REQUEST["id"];

  $dbh = connectDB();
  $sql = "
    SELECT
      work_order_bill.*,
      work_order.WORK_ORDER_NUM,
      billing_batch.BATCH_NAME,
      work_order.ORDERED_BY
    FROM
      work_order_bill
    LEFT JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    LEFT JOIN
      billing_batch
    ON
      billing_batch.BILLING_BATCH_ID = work_order_bill.BILLING_BATCH_ID
    WHERE
      BILL_ID = :BILL_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILL_ID",$bill_id);
  $stmt->execute();
  $row = $stmt->fetch();

  echo "<h2>Billing for ",htmlescape($row["WORK_ORDER_NUM"])," in ",date("F",strtotime(getPrevDay($row["END_DATE"]))),"</h2>\n";

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='work_order_bill'/>\n";
  echo "<input type='hidden' name='id' value='",htmlescape($row["BILL_ID"]),"'/>\n";
  echo "<input type='hidden' name='orig_batch_name' value='",htmlescape($row["BATCH_NAME"]),"'/>\n";
  echo "<input type='hidden' name='orig_batch_id' value='",htmlescape($row["BILLING_BATCH_ID"]),"'/>\n";

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  $url = "?s=work_order&id=" . $row["WORK_ORDER_ID"];
  echo "<div class='$rowclass'><div class='$col1'>Order #</div><div class='col'><a href='",htmlescape($url),"'>",$row["WORK_ORDER_NUM"],"</a></div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Billing Batch</div><div class='col'><input name='batch_name' value='",htmlescape($row["BATCH_NAME"]),"'/></div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='group_id'>Fund Group</label></div><div class='col'>";
  echoSelectUserGroup($web_user,$row["GROUP_ID"],'');
  if( $row["GROUP_ID"] ) {
    $url = '?s=edit_group&group_id=' . $row["GROUP_ID"];
  }
  echo " <a class='btn btn-secondary btn-sm' href='",htmlescape($url),"'>Edit</a>\n";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='funding_source'>Fund</label></div><div class='col'>";
  $order_user = loadUserFromUserID($row["ORDERED_BY"]);
  $show_details = true;
  $offer_to_set_default = $order_user ? true : false;
  echoSelectFundingSource($order_user,$row["FUNDING_SOURCE_ID"],'',$show_details,$offer_to_set_default);
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Materials</div><div class='col'>",htmlescape($row["MATERIALS_CHARGE"]),"</div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'>Labor</div><div class='col'>",htmlescape($row["LABOR_CHARGE"]),"</div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'>Hourly Rate</div><div class='col'>",htmlescape($row["HOURLY_RATE"]),"</div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'>Total</div><div class='col'>",htmlescape($row["MATERIALS_CHARGE"] + $row["LABOR_CHARGE"]),"</div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'>Calculated</div><div class='col'>",htmlescape($row["CREATED"]),"</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='admin_notes'>Admin Notes</label></div><div class='col'>";
  echo "<textarea name='admin_notes' id='admin_notes' rows='5' cols='40'>",htmlescape($row["ADMIN_NOTES"]),"</textarea>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Payment Processed</div><div class='col'>";
  $checked = $row["STATUS"] == WORK_ORDER_BILL_STATUS_PAID ? "checked" : "";
  echo "<input type='radio' name='status' value='",WORK_ORDER_BILL_STATUS_PAID,"' $checked/> Yes ";
  $checked = $row["STATUS"] != WORK_ORDER_BILL_STATUS_PAID ? "checked" : "";
  echo "<input type='radio' name='status' value='' $checked/> No ";
  echo "</div></div>\n";

  echo "</div>\n"; # end of container

  echo "<p><input type='submit' value='Submit'/></p>\n";
  echo "</form>\n";

}

function saveWorkOrderBill($web_user) {
  if( !isAdmin() ) {
    echo "<div class='alert alert-danger'>Only admins can modify work order bills.</div>\n";
    return;
  }

  startBillingTransaction();

  $before_edit = loadWorkOrderBill($_POST["id"]);

  $dbh = connectDB();
  $sql = "UPDATE work_order_bill SET STATUS = :STATUS, GROUP_ID = :GROUP_ID, FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID, ADMIN_NOTES = :ADMIN_NOTES WHERE BILL_ID = :BILL_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILL_ID",$_POST["id"]);
  $stmt->bindValue(":GROUP_ID",$_POST["group_id"]);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$_POST["funding_source"]);
  $stmt->bindValue(":ADMIN_NOTES",$_POST["admin_notes"]);
  $stmt->bindValue(":STATUS",isset($_POST["status"]) ? $_POST["status"] : "");
  $stmt->execute();

  $new_batch_id = null;
  $new_batch_name = null;
  if( $_POST["orig_batch_name"] != $_POST["batch_name"] && trim($_POST["batch_name"]) ) {
    $new_batch_name = trim($_POST["batch_name"]);
    $sql = "SELECT BILLING_BATCH_ID from billing_batch WHERE BATCH_NAME = :BATCH_NAME";
    $batch_stmt = $dbh->prepare($sql);
    $batch_stmt->bindValue(":BATCH_NAME",$new_batch_name);
    $batch_stmt->execute();
    $batch_row = $batch_stmt->fetch();
    $new_batch_id = null;
    if( $batch_row ) {
      $new_batch_id = $batch_row["BILLING_BATCH_ID"];
    } else {
      $sql = "INSERT INTO billing_batch SET BATCH_NAME = :BATCH_NAME, CREATED = now()";
      $create_batch_stmt = $dbh->prepare($sql);
      $create_batch_stmt->bindValue(":BATCH_NAME",$new_batch_name);
      $create_batch_stmt->execute();
      $new_batch_id = $dbh->lastInsertId();
    }
    $sql = "UPDATE work_order_bill SET BILLING_BATCH_ID = :BILLING_BATCH_ID WHERE BILL_ID = :BILL_ID";
    $update_batch_stmt = $dbh->prepare($sql);
    $update_batch_stmt->bindValue(":BILLING_BATCH_ID",$new_batch_id);
    $update_batch_stmt->bindValue(":BILL_ID",$_POST["id"]);
    $update_batch_stmt->execute();
  }

  $after_edit = loadWorkOrderBill($_POST["id"]);

  commitBillingTransaction();

  auditlogModifyWorkOrderBill($web_user,$before_edit,$after_edit);

  $wo = getWorkOrder($after_edit['WORK_ORDER_ID']);
  $order_user = loadUserFromUserID($wo['ORDERED_BY']);
  handleDefaultFundingSourceUpdateForm($order_user);

  echo "<div class='alert alert-success noprint'>Saved.";
  if( isset($_POST["orig_batch_id"]) ) {
    $url = "?s=billing&id=" . $_POST["orig_batch_id"];
    echo "  To return to the billing batch, click here: <a class='btn btn-sm btn-secondary' href='",htmlescape($url),"'>",htmlescape($_POST["orig_batch_name"]),"</a>";
    if( $new_batch_id ) {
      $url = "?s=billing&id=" . $new_batch_id;
      echo " or <a class='btn btn-sm btn-secondary' href='",htmlescape($url),"'>",htmlescape($new_batch_name),"</a>";
    }
  }
  echo "</div>\n";
}

function showWorkOrderBills($wo_id,$header="") {

  $dbh = connectDB();
  $sql = "
    SELECT
      work_order_bill.*,
      work_order.WORK_ORDER_NUM,
      billing_batch.BILLING_BATCH_ID,
      billing_batch.BATCH_NAME,
      user_group.GROUP_NAME,
      funding_source.PI_NAME,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    FROM
      work_order_bill
    JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    JOIN
      billing_batch
    ON
      billing_batch.BILLING_BATCH_ID = work_order_bill.BILLING_BATCH_ID
    LEFT JOIN
      user_group
    ON
      user_group.GROUP_ID = work_order_bill.GROUP_ID
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    WHERE
      work_order_bill.WORK_ORDER_ID = :WORK_ORDER_ID
    ORDER BY
      END_DATE";
  $bill_stmt = $dbh->prepare($sql);
  $bill_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $bill_stmt->execute();

  $header_done = false;
  $total_materials = 0;
  $total_labor = 0;
  while( ($bill=$bill_stmt->fetch()) ) {
    if( !$header_done ) {
      $header_done = true;
      if( $header ) echo $header;
      echo "<table class='records clicksort'>\n";
      echo "<caption>Billing for ",htmlescape($bill["WORK_ORDER_NUM"]),"</caption>\n";
      echo "<thead><tr>";
      if( isAdmin() ) {
        echo "<td></td>";
      }
      echo "<th>Batch</th><th>End Date</th><th>Materials</th><th>Labor</th><th>Rate</th><th>Fund Group</th><th>Fund Description</th><th>Funding String</th></tr></thead><tbody>\n";
    }
    echo "<tr class='record'>";
    if( isAdmin() ) {
      $url = "?s=work_order_bill&id=" . $bill["BILL_ID"];
      echo "<td><a href='",htmlescape($url),"' class='icon'><i class='far fa-edit'></i></a>",getWorkOrderBillStatusIcon($bill),"</td>";
      $url = '?s=billing&id=' . $bill["BILLING_BATCH_ID"];
      echo "<td><a href='",htmlescape($url),"'>",htmlescape($bill["BATCH_NAME"]),"</a></td>";
    } else {
      echo "<td>",htmlescape($bill["BATCH_NAME"]),"</td>";
    }
    echo "<td>",htmlescape(getPrevDay($bill["END_DATE"])),"</td>";
    echo "<td class='currency'>",htmlescape($bill["MATERIALS_CHARGE"]),"</td>";
    echo "<td class='currency'>",htmlescape($bill["LABOR_CHARGE"]),"</td>";
    echo "<td class='currency'>",htmlescape($bill["HOURLY_RATE"]),"</td>";
    $total_materials += $bill["MATERIALS_CHARGE"];
    $total_labor += $bill["LABOR_CHARGE"];
    echo "<td>",htmlescape($bill["GROUP_NAME"]),"</td>";
    echo "<td>",htmlescape($bill["FUNDING_DESCRIPTION"]),"</td>";
    $funding_string = "<span class='light-underline'>" . trim($bill["FUNDING_FUND"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_PROJECT"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_DEPT"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_PROGRAM"]) . "</span>";
    echo "<td>",$funding_string,"</td>";
    echo "</tr>\n";
  }
  if( $header_done ) {
    echo "</tbody>\n";
    echo "<tfoot><tr>";
    if( isAdmin() ) {
      echo "<td></td>";
    }
    echo "<th>Total</th><td></td><td class='currency'>",sprintf("%.2f",$total_materials),"</td><td class='currency'>",sprintf("%.2f",$total_labor),"</td><td></td><td></td><th style='text-align: right'>Grand Total</th><th class='currency'>",sprintf("%.2f",$total_materials+$total_labor),"</th></tr></tfoot>\n";
    echo "</table>\n";
  }
}

function addNonEmptyUniqueItemToList($item,&$list) {
  if( $item && !in_array($item,$list) ) {
    $list[] = $item;
  }
}

function loadBillingBatchStatements($billing_batch_id,&$bb_info,&$statements,$include_job_descriptions) {
  global $self_full_url;

  $self_pub_url = preg_replace('{genpdf.php}','',$self_full_url);

  $dbh = connectDB();
  $sql = "SELECT * FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  $bb_info = $stmt->fetch();
  if( !$bb_info ) {
    echo "<div class='alert alert-danger'>No billing batch with id ",htmlescape($billing_batch_id)," found.</div>\n";
    return;
  }

  $sql = "
    SELECT
      work_order_bill.*,
      work_order.WORK_ORDER_NUM,
      work_order.ORDERED_BY,
      work_order.DESCRIPTION,
      user.FIRST_NAME,
      user.LAST_NAME,
      user.EMAIL,
      user_group.GROUP_NAME,
      funding_source.PI_NAME,
      funding_source.PI_EMAIL,
      funding_source.BILLING_CONTACT_NAME,
      funding_source.BILLING_CONTACT_EMAIL,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    FROM
      work_order_bill
    LEFT JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      user_group
    ON
      user_group.GROUP_ID = work_order_bill.GROUP_ID
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    WHERE
      BILLING_BATCH_ID = :BILLING_BATCH_ID
    ORDER BY
      work_order_bill.END_DATE,WORK_ORDER_NUM
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();

  $bills_by_pi_user = array();
  $latest_end_date = null;
  while( ($row=$stmt->fetch()) ) {
    $user_name = $row["LAST_NAME"] . ", " . $row["FIRST_NAME"];
    $pi = $row["PI_NAME"];
    if( !$pi || $pi == "unknown, unknown" ) {
      $pi = $user_name;
    }
    if( !isset($bills_by_pi_user[$pi]) ) {
      $bills_by_pi_user[$pi] = array();
    }
    if( !isset($bills_by_pi_user[$pi][$user_name]) ) {
      $bills_by_pi_user[$pi][$user_name] = array();
    }
    $bills_by_pi_user[$pi][$user_name][] = $row;

    if( !$latest_end_date || $latest_end_date < $row["END_DATE"] ) {
      $latest_end_date = $row["END_DATE"];
    }
  }

  if( $include_job_descriptions ) {
    if( ENABLE_STOCK ) {
      $stock_materials = getStockMaterials();
      $stock_shapes = getStockShapes();
      $stock_sql = "SELECT * FROM stock_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND CREATED < :END_DATE ORDER BY STOCK_ORDER_ID";
      $stock_stmt = $dbh->prepare($stock_sql);
    }

    $old_bill_sql = "SELECT SUM(LABOR_CHARGE+MATERIALS_CHARGE) AS TOTAL_CHARGE FROM work_order_bill WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND END_DATE < :END_DATE";
    $old_bill_stmt = $dbh->prepare($old_bill_sql);
  }

  ksort( $bills_by_pi_user );
  $statements = array();
  foreach( $bills_by_pi_user as $pi => $bills_by_user ) {
    $msg = array();
    $recipients = array();

    $msg[] = "<table class='records' style='border: none'>";
    $msg[] = "<tr><th style='border: none; padding-top: 1em' colspan='10'></th></tr>\n";
    $msg[] = "<tr class='centered dark'><th>Customer</th><th>Order</th><th>Date</th><th>Materials</th><th>Labor</th><th>Rate</th><th>Group</th><th>Fund Description</th><th>Funding String</th></tr>\n";

    $pi_total_labor = 0;
    $pi_total_materials = 0;
    $statement_sent = true;
    $statement_sent_date = null;
    $work_order_bill_ids = array();
    $job_descriptions = array();

    $date_fmt = "M";
    $same_year = "";
    foreach( $bills_by_user as $user_name => $user_bills ) {
      foreach( $user_bills as $bill ) {
        $year = date("Y",strtotime($bill["END_DATE"]));
	if( $same_year === "" ) $same_year = $year;
	if( $same_year !== $year ) $same_year = null;
      }
    }
    if( $same_year === null ) $date_fmt = "M Y";

    ksort($bills_by_user);
    foreach( $bills_by_user as $user_name => $user_bills ) {

      $user_total_labor = 0;
      $user_total_materials = 0;
      $credit_card_total = 0;
      $credit_card_invoice_num = "";
      foreach( $user_bills as $bill ) {
        addNonEmptyUniqueItemToList($bill["EMAIL"],$recipients);
        addNonEmptyUniqueItemToList($bill["PI_EMAIL"],$recipients);
        addNonEmptyUniqueItemToList($bill["BILLING_CONTACT_EMAIL"],$recipients);

	$work_order_bill_ids[] = $bill["BILL_ID"];

        # get the oldest bill in this batch for each work order, so the "previous charges" calculation is correct
        if( $include_job_descriptions && (!isset($job_descriptions[$bill["WORK_ORDER_NUM"]]) || $job_descriptions[$bill["WORK_ORDER_NUM"]]["END_DATE"] > $bill)) {
          $job_descriptions[$bill["WORK_ORDER_NUM"]] = $bill;
	}

	if( !$bill["STATEMENT_SENT"] ) {
	  $statement_sent = false;
	} else {
	  $statement_sent_date = $bill["STATEMENT_SENT"];
	}

        $user_total_labor += $bill["LABOR_CHARGE"];
        $user_total_materials += $bill["MATERIALS_CHARGE"];
        $msg[] = "<tr>";
        $msg[] = "<td>" . htmlescape($user_name) . "</td>";
        $url = "$self_pub_url?s=work_order&id=" . $bill["WORK_ORDER_ID"];
        $msg[] = "<td><a href='" . htmlescape($url) . "'>" . htmlescape($bill["WORK_ORDER_NUM"]) . "</a></td>";
        $msg[] = "<td>" . date($date_fmt,strtotime(getPrevDay($bill["END_DATE"]))) . "</td>";
        $msg[] = "<td class='currency'>" . htmlescape($bill["MATERIALS_CHARGE"]) . "</td>";
        $msg[] = "<td class='currency'>" . htmlescape($bill["LABOR_CHARGE"]) . "</td>";
        $msg[] = "<td class='currency'>" . htmlescape($bill["HOURLY_RATE"]) . "</td>";
        $msg[] = "<td>" . htmlescape($bill["GROUP_NAME"]) . "</td>";
        $msg[] = "<td>" . htmlescape($bill["FUNDING_DESCRIPTION"]) . "</td>";
        $funding_string = "<span class='light-underline'>" . trim($bill["FUNDING_FUND"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_PROJECT"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_DEPT"]) . "</span> <span class='light-underline'>" . trim($bill["FUNDING_PROGRAM"]) . "</span>";
        $msg[] = "<td>" . $funding_string . "</td>";
        $msg[] = "</tr>\n";

	if( $bill["FUNDING_FUND"] == "credit card" ) {
	  $credit_card_total += $bill["MATERIALS_CHARGE"] + $bill["LABOR_CHARGE"];
	  if( $credit_card_invoice_num == "" && $credit_card_total > 0 ) {
	    # use the first positive work order number as the invoice number
	    $credit_card_invoice_num = $bill["WORK_ORDER_NUM"];
	  }
	}
      }
      if( count($bills_by_user)>1 ) {
        $msg[] = "<tr><td></td><th>Total</th><td></td>";
        $msg[] = "<td class='currency'>" . sprintf("%.2f",$user_total_materials) . "</td>";
        $msg[] = "<td class='currency'>" . sprintf("%.2f",$user_total_labor) . "</td>";
        $msg[] = "<td></td><td></td><td></td><td></td></tr>\n";
      }
      $pi_total_labor += $user_total_labor;
      $pi_total_materials += $user_total_materials;
    }
    $msg[] = "<tr><th>Total</th><td></td><td></td>";
    $msg[] = "<td class='currency'>" . sprintf("%.2f",$pi_total_materials) . "</td>";
    $msg[] = "<td class='currency'>" . sprintf("%.2f",$pi_total_labor) . "</td>";
    $msg[] = "<td></td><td></td><th style='text-align: right'>Grand Total</th><th class='currency'>" . sprintf("%.2f",$pi_total_materials+$pi_total_labor) . "</th></tr>\n";
    $msg[] = "</table>\n";
    $msg[] = "<p>&nbsp;</p>\n";

    if( $credit_card_total > 0 && CREDIT_CARD_URL ) {
      $msg[] = "<p>To pay the total of \$" . sprintf("%.2f",$credit_card_total) . " by credit card, visit the payment page: <a href='" . htmlescape(CREDIT_CARD_URL) . "'>" . htmlescape(CREDIT_CARD_URL) . "</a>.  Specify invoice number " . htmlescape($credit_card_invoice_num) . ".</p>\n";
    }

    $period_end = date("M j, Y",strtotime(getPrevDay($latest_end_date)));
    $pi_first_last = firstLastName($pi);
    $subject = SHOP_NAME . " statement for $pi_first_last, period ending {$period_end}";

    if( $include_job_descriptions && count($job_descriptions) ) {
      ksort($job_descriptions);
      $msg[] = "<h4>Order Descriptions</h4>\n";
      foreach( $job_descriptions as $bill ) {
        $url = "$self_pub_url?s=work_order&id=" . $bill["WORK_ORDER_ID"];
        $msg[] = "<h5 style='margin-top: 0.5em'><a href='" . htmlescape($url) . "'>" . htmlescape($bill["WORK_ORDER_NUM"]) . "</a></h5>\n";

        $msg[] = "<blockquote>" . htmlescape($bill["DESCRIPTION"]) . "</blockquote>\n";

        $old_bill_stmt->bindValue(":WORK_ORDER_ID",$bill["WORK_ORDER_ID"]);
        $old_bill_stmt->bindValue(":END_DATE",$bill["END_DATE"]);
        $old_bill_stmt->execute();
        $old_bill = $old_bill_stmt->fetch();
        if( $old_bill && $old_bill["TOTAL_CHARGE"] ) {
          $msg[] = "<p>Charges to this work order from previous billing periods: \$" . sprintf("%.2f",$old_bill["TOTAL_CHARGE"]) . ".</p>\n";
        }

        if( ENABLE_STOCK ) {
          $stock_stmt->bindValue(":WORK_ORDER_ID",$bill["WORK_ORDER_ID"]);
          $stock_stmt->bindValue(":END_DATE",$bill["END_DATE"]);
          $stock_stmt->execute();
          $stock_header_done = false;
          $stock_total = 0;
          while( ($stock=$stock_stmt->fetch()) ) {
            if( !$stock_header_done ) {
              $stock_header_done = true;
              $msg[] = "<table class='records'>";
              $msg[] = "<thead><tr><th>Material</th><th>Shape</th><th><small>Thickness</small><br><small>(inches)</small></th><th><small>Height</small><br><small>(inches)</small></th><th><small>Width/</small><small>Dia</small><br><small>(inches)</small></th><th><small>Length</small><br><small>(inches)</small></th><th>Qty</th><th>Cost</th></tr></thead><tbody>\n";
            }
            $style = $stock["CANCELED"] ? "style='background-color: #f0f0f0'" : "";
            $msg[] = "<tr $style>";
            $style = $stock["CANCELED"] ? "style='text-decoration: line-through'" : "";
            $msg[] = "<td $style>";
            if( isset($stock_materials[$stock["MATERIAL_ID"]]) ) {
               $material = $stock_materials[$stock["MATERIAL_ID"]];
               $msg[] = htmlescape($material["NAME"]);
            } else if( $stock["MATERIAL_ID"] == OTHER_MATERIAL_ID || $stock["MATERIAL_ID"] == OTHER_ITEM_ID ) {
              $msg[] = htmlescape($stock["OTHER_MATERIAL"]);
            }
            $msg[] = "</td>";
            $msg[] = "<td $style>";
            if( isset($stock_shapes[$stock["SHAPE_ID"]]) ) {
              $shape = $stock_shapes[$stock["SHAPE_ID"]];
              $msg[] = htmlescape($shape["NAME"]);
            }
            $msg[] = "</td>";
            $msg[] = "<td $style>" . htmlescape($stock["THICKNESS"]) . "</td>";
            $msg[] = "<td $style>" . htmlescape($stock["HEIGHT"]) . "</td>";
            $msg[] = "<td $style>" . htmlescape($stock["WIDTH"]) . "</td>";
            $msg[] = "<td $style>" . htmlescape($stock["LENGTH"]) . "</td>";
            $msg[] = "<td $style>" . htmlescape($stock["QUANTITY"]) . "</td>";

            if( $stock["TOTAL_COST"] === null ) {
              if( !$stock["CANCELED"] ) {
                $stock_total = null;
              }
            } else if( $stock_total !== null ) {
              $stock_total += $stock["TOTAL_COST"];
            }
            $msg[] = "<td class='currency'>" . htmlescape($stock["TOTAL_COST"]) . "</td>";

            $msg[] = "</tr>\n";
          }
          if( $stock_header_done ) {
            $msg[] = "<tbody></table><p>&nbsp;</p>\n";
          }
	}

        # For work orders created to aggregate all the items checked
        # in the billing period, display the date of purchase for
        # each item.
        $include_checkout_date = preg_match("{^Accumulated}",$bill["DESCRIPTION"]);

        $parts_header_done = false;
        foreach( getOtherShopsInWorkOrders() as $shop_name => $shop_info ) {
          if( isset($shop_info['SHOP_NAME']) ) {
            $shop_name = $shop_info['SHOP_NAME'];
          }
          $items = getShopItemsInWorkOrder($bill["WORK_ORDER_ID"],$shop_info);
          foreach( $items as $item ) {
            if( $item['DATE'] >= $bill["END_DATE"] ) {
              continue;
            }
            if( !$parts_header_done ) {
	      $parts_header_done = true;
              $msg[] = "<table class='records'>";
              $head = "<thead><tr>";
	      if( $include_checkout_date ) $head .= "<th>Date</th>";
	      $head .= "<th>Part</th><th>Qty</th><th>Cost/<br>Piece</th><th>Total</th></tr></thead><tbody>\n";
	      $msg[] = $head;
            }
            $msg[] = "<tr>";
	    if( $include_checkout_date ) $msg[] = "<td>" . date("Y-m-d",strtotime($item["DATE"])) . "</td>";
            $part_desc = $item['STOCK_NUM'] . " - " . $item['DESCRIPTION'];
            $msg[] = "<td>" . htmlescape($shop_name) . " part " . htmlescape($part_desc) . "</td>";
            $qty = $item['QTY'] . " " . $item['UNITS'];
            $msg[] = "<td>" . htmlescape($qty) . "</td>";
            $msg[] = "<td class='currency'>" . $item['PRICE'] . "</td>";
            $msg[] = "<td class='currency'>" . $item['TOTAL'] . "</td>";
            $msg[] = "</tr>\n";
          }
        }
        if( $parts_header_done ) {
          $msg[] = "</tbody></table>\n";
        }
      }
    }

    $statements[] = array(
      "pi" => $pi,
      "msg" => $msg,
      "subject" => $subject,
      "recipients" => $recipients,
      "sent" => $statement_sent,
      "sent_date" => $statement_sent_date,
      "work_order_bill_ids" => $work_order_bill_ids,
    );
  }
  return true;
}

function formatBatchFileName($name) {
  # convert from YYYY-MM to the format Aimee prefers for filing: YYYYMMDD where DD is the last day of the month
  if( preg_match('{^[0-9][0-9][0-9][0-9]-[0-9][0-9]$}',$name) ) {
    $dt = date("Y-m-d",strtotime($name . "-01"));
    $dt = getNextMonth($dt);
    $dt = getPrevDay($dt);
    $name = str_replace("-","",$dt);
  }
  return $name;
}

function getBillingBatchReceiptsPDFUrl($billing_batch_id) {
  return SHOP_URL . '/workorder/genpdf.php?s=billing_batch_receipts&id=' . htmlescape($billing_batch_id);
}

function getBillingBatchReceiptsPDFFname($billing_batch_id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  $bb_info = $stmt->fetch();
  if( !$bb_info ) return;
  return makeSafeFileName(formatBatchFileName($bb_info["BATCH_NAME"]) . '_' . SHOP_NAME . "_Receipts") . ".pdf";
}

function getBillingBatchFinancialYear($billing_batch_id) {
  $dbh = connectDB();
  $sql = "SELECT CREATED FROM billing_batch WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
  $stmt->execute();
  $bb_info = $stmt->fetch();
  if( !$bb_info ) return;
  return getFinancialYear($bb_info["CREATED"]);
}

function exportBillingBatchReceiptsPDF() {
  $billing_batch_id = $_REQUEST["id"];
  $url = getBillingBatchReceiptsPDFUrl($billing_batch_id);
  $fname = getBillingBatchReceiptsPDFFname($billing_batch_id);

  if( !$url || !$fname ) {
    echo "<div class='alert alert-danger'>No billing batch with id ",htmlescape($billing_batch_id)," found.</div>\n";
    return;
  }

  header("Content-type: application/pdf");
  header("Content-Disposition: attachment; filename=\"$fname\"");

  $command = "wkhtmltopdf -q --print-media-type '$url' -";
  passthru($command,$rc);
  if( $rc != 0 ) {
    return False;
  }
}

function showBillingBatchReceipts() {
  # Billing receipts are details statements that are uploaded into the UW fund transfer system as justification of the charges.

  $billing_batch_id = $_REQUEST["id"];
  if( !loadBillingBatchStatements($billing_batch_id,$bb_info,$statements,true) ) {
    return;
  }
  $batch_name = $bb_info["BATCH_NAME"];
  echo "<h1>",htmlescape(SHOP_NAME)," Billing Batch ",htmlescape($batch_name)," Receipts</h1>\n";

  foreach( $statements as $statement ) {
    $pi = $statement["pi"];
    $msg = $statement["msg"];
    $recipients = $statement["recipients"];
    $subject = $statement["subject"];
    $statement_sent = $statement["sent"];
    $statement_sent_date = $statement["sent_date"];

    echo "<div class='card'><div class='card-body'>\n";

    echo "<h3>",htmlescape($subject),"</h3>\n";

    $msg = implode("",$msg);
    echo $msg;

    echo "</div></div>\n";
    echo "<p style='page-break-after: always;'>&nbsp;</p>\n";
  }
}

function showBillingBatchStatements() {

  $billing_batch_id = $_REQUEST["id"];
  if( !loadBillingBatchStatements($billing_batch_id,$bb_info,$statements,true) ) {
    return;
  }
  $batch_name = $bb_info["BATCH_NAME"];
  echo "<h2>Billing Batch ",htmlescape($batch_name)," Statements</h2>\n";

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='billing_batch_statements'/>\n";
  echo "<input type='hidden' name='id' value='",htmlescape($billing_batch_id),"'/>\n";

  echo "<div class='noprint'>\n";

  echo "<p>";
  echo "<input type='submit' name='submit' value='Send Selected Statements'>\n";
  echo "</p>\n";

  echo "<p><textarea name='top_blurb' rows='2' style='width: 100%' placeholder='Extra blurb to include at the top of the email (interpreted as html).'>",DEFAULT_BILLING_STATEMENT_HEADER,"</textarea></p>\n";

  echo "<label><input type='checkbox' value='1' name='toggle_all' id='toggle_all' onchange='toggleAll()' checked/> toggle all</label><br>\n";

  echo "</div>\n";

  ?><script>
  function toggleAll() {
    if( $('#toggle_all:checked').length ) {
      $('.send_checkbox').prop('checked',true);
    } else {
      $('.send_checkbox').prop('checked',false);
    }
  }
  </script><?php

  foreach( $statements as $statement ) {
    $pi = $statement["pi"];
    $msg = $statement["msg"];
    $recipients = $statement["recipients"];
    $subject = $statement["subject"];
    $statement_sent = $statement["sent"];
    $statement_sent_date = $statement["sent_date"];

    echo "<div class='card'><div class='card-body'>\n";

    echo "<p>";

    $checked = $statement_sent ? "" : "checked";
    echo "<label class='noprint'><input class='send_checkbox' type='checkbox' name='send_pi_statement[]' value='",htmlescape($pi),"' $checked/> Send</label>";
    if( $statement_sent_date ) {
      $some = $statement_sent ? "" : " some but not all lines";
      echo " (sent{$some} on ",date("M, j Y",strtotime($statement_sent_date))," at ",date("g:ma",strtotime($statement_sent_date)),")";
    }
    echo "<br>";

    $recipients = implode(", ",$recipients);
    echo "To: <tt>",htmlescape($recipients),"</tt><br>\n";
    echo "Subject: <tt>",htmlescape($subject),"</tt><br>\n";
    echo "</p>\n";

    $msg = implode("",$msg);
    echo $msg;

    echo "</div></div>\n";
    echo "<p style='page-break-after: always;'>&nbsp;</p>\n";
  }

  echo "</form>\n";
}

function sendBillingBatchStatements() {
  if( !isAdmin() ) {
    echo "<div class='alert alert-danger'>Only administrators can send billing statements.</div>\n";
    return;
  }
  $billing_batch_id = $_REQUEST["id"];
  if( !loadBillingBatchStatements($billing_batch_id,$bb_info,$statements,true) ) {
    echo "<div class='alert alert-danger'>No statements sent.</div>\n";
    return;
  }

  $dbh = connectDB();
  $sql = "UPDATE work_order_bill SET STATEMENT_SENT = now() WHERE BILL_ID = :BILL_ID";
  $wo_statement_sent_stmt = $dbh->prepare($sql);

  $selected_statements = isset($_REQUEST["send_pi_statement"]) ? $_REQUEST["send_pi_statement"] : array();
  $messages_sent = 0;
  foreach( $statements as $statement ) {
    $pi = $statement["pi"];
    $msg = $statement["msg"];
    $recipients = $statement["recipients"];
    $subject = $statement["subject"];
    $statement_sent = $statement["sent"];
    $statement_sent_date = $statement["sent_date"];
    $work_order_bill_ids = $statement["work_order_bill_ids"];

    if( !in_array($pi,$selected_statements) ) continue;

    $to = implode(",",$recipients);

    $head = getStandardHTMLEmailHead();
    $head[] = "<body>";

    if( isset($_REQUEST["top_blurb"]) && $_REQUEST["top_blurb"] != "" ) {
      $head[] = $_REQUEST["top_blurb"];
    }

    $foot = array();
    $foot[] = "</body></html>";

    $body = implode("\r\n",array_merge($head,$msg,$foot));

    $headers = array();
    $headers[] = "From: " . BILLING_EMAIL_NAME . " <" . BILLING_EMAIL . ">";
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';

    $headers = implode("\r\n",$headers);

    if( !mail($to,$subject,$body,$headers,"-f " . BILLING_EMAIL) ) {
      echo "<div class='alert alert-danger'>Failed to send email to ",htmlescape($to),".</div>\n";
    } else {
      $messages_sent += 1;
      foreach( $work_order_bill_ids as $bill_id ) {
        $wo_statement_sent_stmt->bindValue(":BILL_ID",$bill_id);
	$wo_statement_sent_stmt->execute();
      }
    }
  }
  if( $messages_sent ) {
    echo "<div class='alert alert-success'>Sent {$messages_sent} statements.</div>\n";
  }
  else if( count($selected_statements)==0 ) {
    echo "<div class='alert alert-warning'>No statements were selected, so none were sent.</div>\n";
  }
}

function storeBillingFiles() {
  $billing_batch_id = $_REQUEST["id"];
  $url = getBillingBatchReceiptsPDFUrl($billing_batch_id);
  $fname = getBillingBatchReceiptsPDFFname($billing_batch_id);
  $FY = getBillingBatchFinancialYear($billing_batch_id);

  if( $url === null || $fname === null || $FY === null ) {
    echo "<div class='alert alert-danger'>No billing batch with id ",htmlescape($billing_batch_id)," found.</div>\n";
    return;
  }
  $FY = "FY$FY";

  $command = "wkhtmltopdf -q --print-media-type '$url' '/tmp/$fname' 2>&1";
  $output = array();
  $result_code = null;
  $saved_receipts = false;
  if( exec($command,$output,$result_code) === false || $result_code !== 0 ) {
    echo "<div class='alert alert-danger'>Failed to generate ",htmlescape($fname),": ",htmlescape(implode("\n",$output)),"</div>\n";
  }
  else if( archiveShopFile("/tmp/$fname",$FY) ) {
    $saved_receipts = true;
  }

  $fname = getBillingBatchTransfersFname($billing_batch_id,".xlsx");
  $F = fopen("/tmp/$fname","w");
  $saved_xfr = false;
  if( !$F || !writeBillingBatchTransfersExcel($F,$billing_batch_id,'all') || !fclose($F) ) {
    echo "<div class='alert alert-danger'>Failed to generate ",htmlescape($fname),".</div>\n";
  }
  else if( archiveShopFile("/tmp/$fname",$FY) ) {
    $saved_xfr = true;
  }

  if( $saved_receipts && $saved_xfr ) {
    $dbh = connectDB();
    $sql = "UPDATE billing_batch SET archived = now() WHERE BILLING_BATCH_ID = :BILLING_BATCH_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":BILLING_BATCH_ID",$billing_batch_id);
    $stmt->execute();
  }
}

function archiveShopFile($fname,$FY) {
  $command = "/usr/local/bin/archive-shop-file '" . SHOP_NAME . "' '$FY' '$fname' 2>&1";
  $output = array();
  $result_code = null;
  if( exec($command,$output,$result_code) === false || $result_code !== 0 ) {
    echo "<div class='alert alert-danger'>Failed to store ",htmlescape($fname),": ",htmlescape(implode("\n",$output)),"</div>\n";
  }
  else {
    echo "<div class='alert alert-success'>",htmlescape(implode("\n",$output)),"</div>\n";
    unlink($fname);
    return true;
  }
}

function doBillingFileExports($show) {
  switch( $show ) {
  case "export_billing_batch_xfer":
    exportBillingBatchTransfersExcel();
    exit;
  case "export_billing_batch_raw_data":
    exportBillingBatchRawData();
    exit;
  case "billing_batch_receipts_pdf":
    exportBillingBatchReceiptsPDF();
    exit;
  }
}

function doBillingFormPosts($form) {
  if( !isAdmin() ) return;
  switch( $form ) {
    case "billing_batch":
      saveBillingBatch();
      break;
    case "billing_batch_statements":
      sendBillingBatchStatements();
      break;
    case "merge_batches":
      mergeBillingBatches();
      break;
  }
}

function showBillingPage($show) {
  global $web_user;

  switch( $show ) {
    case "billing":
      if( isAdmin() ) {
        showBillingBatches();
      }
      return true;
    case "work_order_bill":
      if( isAdmin() ) {
        showWorkOrderBill($web_user);
      }
      return true;
    case "billing_batch_statements":
      if( isAdmin() ) {
        showBillingBatchStatements();
      }
      return true;
    case "billing_batch_receipts":
      if( isAdmin() || isInternalLogin() ) {
        showBillingBatchReceipts();
      }
      return true;
    case "billing_batch_store_files":
      if( isAdmin() ) {
        storeBillingFiles();
      }
      return true;
  }
}

function adjustBillingPageClass($show,&$page_class) {
  switch( $show ) {
    case "billing":
      if( isset($_REQUEST["id"]) ) {
        $page_class = "container-fluid";
      }
      break;
  }
}
