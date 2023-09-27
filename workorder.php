<?php
ini_set('display_errors', 'On');

const WEBAPP_TOP = "../";

require_once "../db.php";
require_once "../common.php";
require_once "../config.php";
require_once "../post_config.php";
require_once "libphp/excelcsv.php";
require_once "../loans.php";
require_once "billing.php";

initLogin();

if( preg_match("/\/uploads\/[0-9]+\/([0-9]+)\/(.*)$/",$_SERVER["REQUEST_URI"],$match) ) {
  downloadAttachment($web_user,(int)$match[1],urldecode($match[2]));
  exit;
}

$show = isset($_REQUEST["s"]) ? $_REQUEST["s"] : "";

if( isAdmin() ) {
  switch( $show ) {
  case "export_workorders":
    exportWorkOrders();
    exit;
  case "export_timesheets":
    exportTimesheets();
    exit;
  }
  doBillingFileExports($show);
}

if( isAdmin() || isShopWorker() ) {
  switch( $show ) {
  case "send_completion_email":
    sendCompletionEmail();
    exit;
  }
}

switch( $show ) {
  case "stock_cost_query":
    calculateStockCostsAjax();
    exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
  <link href="<?php echo WEBAPP_TOP ?>style.css" rel="stylesheet" type="text/css"/>
  <link href="<?php echo WEBAPP_TOP ?>print.css" rel="stylesheet" type="text/css" media="print"/>
  <title><?php echo htmlescape(SHOP_NAME) ?> App</title>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
  <script src="<?php echo LIBJS ?>autocomplete.js"></script>
  <script src="<?php echo LIBJS ?>clicksort.js"></script>
  <link rel="stylesheet" href="<?php echo LIBJS ?>clicksort.css" type="text/css"/>
  <script src="<?php echo WEBAPP_TOP ?>cleardate.js"></script>
  <?php echoUpdatePrintTextareaJavascript(); ?>
  <script>
    function arrayToEnglishList(a) {
      if( a.length == 0 ) return "";
      if( a.length == 1 ) return a[0];
      var result = a[0];
      for( var i=1; i<a.length-1; i++ ) {
        result += ", " + a[i];
      }
      result += " and " + a[a.length-1];
      return result;
    }
  </script>

</head>
<body>

<?php

if( !$web_user ) {
  ?>
    <main role="main" class="container">
    <div class="bg-light p-5 rounded text-center">
      <h1>Welcome to the <?php echo htmlescape(SHOP_FULL_NAME)  ?></h1>
      <p class="lead">Please use this form to create work orders.</p>
      <p><button class="btn btn-lg btn-primary" role="button" onclick='login()'>Log in with NetID &raquo;</button></p>
      <?php if( allowNonNetIDLogins() ) { ?>
      <?php if( $login_error_msg ) echo "<div class='alert alert-danger'>",$login_error_msg,"</div>\n"; ?>
      <p>Or use a group account:</p>
         <form enctype='multipart/form-data' method='POST' autocomplete='off'>
         <?php /* use name 'group_lgn', because 'group_login' causes some browsers to provide login name autocompletion */ ?>
         <div class='row justify-content-md-center'>
         <div class='form-group col-md-4 col-lg-3'>
         <div class='input-group'>
         <input name='group_lgn' class='form-control' placeholder='group name'/>
         <div class='input-group-append'><input type='submit' class='form-control btn btn-secondary' value='Go'/></div>
         </div>
         </div>
         </div>
         </form>
      <p>(To set up a group account, contact <?php echo ACCOUNT_HELP_EMAIL ?> or enable group access in <a href='#' onclick='login("profile")'>your profile</a>.)</p>
      <?php } ?>
      <noscript>ERROR: javascript is disabled and is required by this web app.</noscript>
      <?php echo SHOP_WORKORDER_LOGIN_NOTICE ?>
    </div>
    </main>
  <?php
} else {

  $default_show = "work_order";
  if( isAdmin() ) {
    $default_show = "all_work_orders";
  }
  else if( isShopWorker() ) {
    $default_show = "timesheet";
  }
  if( $show == "" ) $show = $default_show;

  showNavbar($web_user,$show,$default_show);

  if( isset($_POST["form"]) ) {
    $form = $_POST["form"];
    switch($form) {
      case "edit_group":
        saveGroup($web_user,$show);
        break;
      case "user_profile":
        saveUserProfile($web_user,$show);
        break;
      case "work_order":
        saveWorkOrder($web_user,$show);
        break;
      case "student_shop_access_order":
        saveStudentShopAccessOrder($web_user);
        break;
      case "student_access_fee":
        saveStudentShopAccessFeeConfig($web_user);
	break;
      case "timesheet":
        saveTimesheet($web_user);
        break;
      case "materials":
        saveMaterials($web_user);
	break;
      case "shop_rates":
        saveContracts($web_user);
        break;
      case "work_order_bill":
        saveWorkOrderBill($web_user);
        break;
      case "loan":
        saveLoan($web_user);
        break;
      case "returned":
        returnLoan();
        break;
      case "pickup":
        pickUpWorkOrder($web_user,$show);
        break;
      default:
        doBillingFormPosts($form);
        break;
    }
  }

  $page_class = "container";
  switch($show) {
    case "users":
    case "auditlog":
    case "materials":
      # get rid of left margin on these pages with wide tables
      $page_class = "container-fluid";
      break;
    case "work_order":
    case "all_work_orders":
    case "all_stock_orders":
    case "timecodes":
      if( !isset($_REQUEST["id"]) ) {
        $page_class = "container-fluid";
      }
      break;
    default:
      adjustBillingPageClass($show,$page_class);
      break;
  }

  echo "<main role='main' class='{$page_class}'>\n";

  # this must happen after saveUserProfile()
  registerLogin($web_user,$show);

  if( getBackToAppInfo($back_to_app_url,$back_to_app_name) ) {
    echo "<div class='alert alert-success noprint'>To go back, click here: <a class='btn btn-sm btn-secondary' href='",htmlescape($back_to_app_url),"' onclick='unset_back_to_app_cookies(); return true;'>Back to ",htmlescape($back_to_app_name),"</a></div>\n";
  }

  switch( $show ) {
    case "auditlog":
      if( isAdmin() ) {
        showAuditLog();
      }
      break;
    case "edit_group":
      showEditGroup($web_user);
      break;
    case "search_replace_fund":
      if( isAdmin() ) {
        showSearchReplaceFund();
      }
      break;
    case "profile":
      showUserProfile($web_user);
      break;
    case "timesheet":
      showTimesheet($web_user);
      break;
    case "timesheets":
      showTimesheets();
      break;
    case "all_stock_orders":
    case "quotes":
    case "timecodes":
    case "all_work_orders":
      showWorkOrders($web_user);
      break;
    case "student_shop_access_orders":
      showStudentShopAccessOrders();
      break;
    case "work_order":
      showMyWorkOrders($web_user);
      break;
    case "users":
      if( isAdmin() ) {
        showUsers();
      }
      break;
    case "edit_user":
      if( isAdmin() ) {
        showEditUser();
      }
      break;
    case "groups":
      showGroups();
      break;
    case "materials":
      showMaterials();
      break;
    case "shop_rates":
      showShopRates();
      break;
    case "work_history":
      showWorkHistory($web_user);
      break;
    case "all_work_history":
      if( isAdmin() ) {
        showWorkHistoryOfAll($web_user);
      }
      break;
    case "student_access_fee":
      if( isAdmin() ) {
        showStudentShopAccessFeeConfig();
      }
      break;
    case "loans":
      if( isLoanAdmin() ) {
        showLoans();
      }
      break;
    case "pickup":
      showPickUp($web_user);
      break;
    default:
      showBillingPage($show);
      break;
  }

  echo "</main>\n";
}

echoShopLoginJavascript();

?>

</body>
</html>

<?php

function showShopWorkerAppsMenu() {
  echo "<li class='nav-item admin-only dropdown'>\n";
  echo "<a class='nav-link dropdown-toggle' href='#' id='navbarApps' role='button' data-bs-toggle='dropdown' aria-haspopup='true' aria-expanded='false'> Apps </a>\n";
  echo "<div class='dropdown-menu' aria-labelledby='navbarApps'>\n";
  foreach( SHOP_WORKER_APPS as $label => $url ) {
    echo "<a class='dropdown-item' href='",htmlescape($url),"' target='_blank'>",htmlescape($label),"</a>\n";
  }
  echo "</div></li>\n";
}

function showNavbar($user,$show,$default_show) {
?>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
      <span class="navbar-brand" href="#">
      <?php if( SMALL_LOGO ) echo '<img src="' . WEBAPP_TOP . SMALL_LOGO . '" height="30" class="d-inline-block align-top" alt="UW">' ?>
      <?php echo htmlescape(SHOP_FULL_NAME) ?></span>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav me-auto">
	  <?php if( !isShopWorker() ) { ?>
            <li class="nav-item <?php echo $show=="work_order" ? "active" : ""  ?>">
              <a class="nav-link" href="<?php echo $default_show == "work_order" ? "." : "?s=work_order" ?>">Work Order</a>
            </li>
	  <?php } ?>
	  <?php if( isShopWorker() ) { ?>
          <li class="nav-item <?php echo $show=="timesheet" ? "active" : "" ?>">
            <a class="nav-link" href="<?php echo $default_show == "timesheet" ? "." : "?s=timesheet" ?>">Timesheet</a>
          </li>
	  <?php } ?>
	  <?php if( isShopWorker() && !isAdmin() ) { ?>
            <li class="nav-item <?php echo $show=="all_work_orders" ? "active" : "" ?>">
              <a class="nav-link" href="<?php echo $default_show == "all_work_orders" ? "." : "?s=all_work_orders" ?>">Work Orders</a>
            </li>
            <?php if( ENABLE_STOCK ) { ?>
              <li class="nav-item <?php echo $show=="all_stock_orders" ? "active" : "" ?>">
                <a class="nav-link" href="<?php echo $default_show == "all_stock_orders" ? "." : "?s=all_stock_orders" ?>">Stock Orders</a>
              </li>
            <?php } ?>
            <?php if(SHOP_SUPPORTS_LOANS) { ?>
            <li class="nav-item <?php echo $show=="loans" ? "active" : "" ?>">
              <a class="nav-link" href="?s=loans">Loans</a>
            </li>
            <?php } ?>
            <?php if( defined('SHOP_WORKER_APPS') ) {
	      showShopWorkerAppsMenu();
            } ?>
          <?php } ?>
          <li class="nav-item <?php echo $show=="profile" ? "active" : "" ?>">
            <a class="nav-link" href="?s=profile">Profile</a>
          </li>
          <?php if(isAdmin()) { ?>
            <li class="navbar-text admin-only">&nbsp;&nbsp;<small>Admin:</small></li>
            <li class="nav-item admin-only <?php echo $show=="users" ? "active" : "" ?>">
              <a class="nav-link" href="?s=users">Users</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="groups" ? "active" : "" ?>">
              <a class="nav-link" href="?s=groups">Funds</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="all_work_orders" ? "active" : "" ?>">
              <a class="nav-link" href="<?php echo $default_show == "all_work_orders" ? "." : "?s=all_work_orders" ?>">Work Orders</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="quotes" ? "active" : "" ?>">
              <a class="nav-link" href="<?php echo $default_show == "quotes" ? "." : "?s=quotes" ?>">Quotes</a>
            </li>
            <?php if( ENABLE_STOCK ) { ?>
              <li class="nav-item admin-only <?php echo $show=="all_stock_orders" ? "active" : "" ?>">
                <a class="nav-link" href="<?php echo $default_show == "all_stock_orders" ? "." : "?s=all_stock_orders" ?>">Stock Orders</a>
              </li>
            <?php } ?>
            <?php if( ENABLE_STUDENT_SHOP_ACCESS_ORDERS ) { ?>
              <li class="nav-item admin-only <?php echo $show=="student_shop_access_orders" ? "active" : "" ?>">
                <a class="nav-link" href="<?php echo $default_show == "student_shop_access_orders" ? "." : "?s=student_shop_access_orders" ?>">Stdnt Orders</a>
              </li>
            <?php } ?>
            <li class="nav-item admin-only <?php echo $show=="timesheets" ? "active" : "" ?>">
              <a class="nav-link" href="?s=timesheets">Timesheets</a>
            </li>
            <?php if(SHOP_SUPPORTS_LOANS) { ?>
            <li class="nav-item admin-only <?php echo $show=="loans" ? "active" : "" ?>">
              <a class="nav-link" href="?s=loans">Loans</a>
            </li>
            <?php } ?>
            <li class="nav-item admin-only <?php echo $show=="billing" ? "active" : "" ?>">
              <a class="nav-link" href="?s=billing">Billing</a>
            </li>
            <li class="nav-item admin-only dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="navbarConfig" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> Config </a>
                <div class="dropdown-menu" aria-labelledby="navbarConfig">
                  <?php if( ENABLE_STOCK ) { ?>
                    <a class="dropdown-item <?php echo $show=="materials" ? "active" : "" ?>" href="?s=materials">Materials</a>
                  <?php } ?>
                  <a class="dropdown-item <?php echo $show=="timecodes" ? "active" : "" ?>" href="?s=timecodes">Time Codes</a>
                  <a class="dropdown-item <?php echo $show=="shop_rates" ? "active" : "" ?>" href="?s=shop_rates">Shop Rates</a>
                  <?php if( ENABLE_STUDENT_SHOP_ACCESS_ORDERS ) { ?>
                    <a class="dropdown-item <?php echo $show=="student_access_fee" ? "active" : "" ?>" href="?s=student_access_fee">Student Shop Fee</a>
                  <?php } ?>
                </div>
            </li>
            <?php if( defined('SHOP_WORKER_APPS') ) {
	      showShopWorkerAppsMenu();
            } ?>
            <li class="nav-item admin-only <?php echo $show=="auditlog" ? "active" : "" ?>">
              <a class="nav-link" href="?s=auditlog">Audit Log</a>
            </li>
          <?php } ?>
        </ul>
        <span class="navbar-text" style='color: rgb(255,0,255)'><?php echo htmlescape($user->displayName()) ?></span>&nbsp;
        <?php
          if( $user->is_admin && !isAdmin() ) {
            echo " <span class='navbar-text'>(non-admin mode)</span>&nbsp;";
          }
	  $logout = "Log Out";
	  if( getBackToAppInfo($back_to_app_url,$back_to_app_name) ) {
	    $logout = "Back to " . $back_to_app_name;
	  }
        ?>
	<?php if( showLogoutButton() ) { ?>
        <button class="btn btn-secondary my-2 my-sm-0" onclick='logout(); return false;'><?php echo htmlescape($logout) ?></button>
	<?php } ?>
      </div>
    </nav>
<?php
}

function getWorkOrderDisplayStatus($row) {
  switch( $row["STATUS"] ) {
  case WORK_ORDER_STATUS_CLOSED:
    return "Closed";
  case WORK_ORDER_STATUS_CANCELED:
    return "Canceled";
  }
  if( $row["COMPLETED"] ) return "Completed";
  if( isset($row["WORK_STARTED"]) && $row["WORK_STARTED"] ) return "Started";
  if( $row["QUEUED"] ) return "Queued";
  if( $row["REVIEWED"] ) return "Reviewed";
  return "";
}

function showMyWorkOrders($web_user) {
  return showWorkOrders($web_user,true);
}

function showWorkOrders($web_user,$my_work_orders=false) {
  global $self_full_url;

  if( !isAdmin() && !isShopWorker() ) {
    $my_work_orders = true;
  }
  if( isset($_REQUEST["id"]) ) {
    return showWorkOrder($web_user);
  }

  $is_timecode_view = isset($_REQUEST["s"]) && $_REQUEST["s"] == "timecodes";
  $is_quote_view = isset($_REQUEST["s"]) && $_REQUEST["s"] == "quotes";
  $Work = $is_quote_view ? "Quote" : "Work";

  if( SHOP_SUPPORTS_LOANS ) {
    echo "<div id='loan_form' style='display: none'>\n";
    $outstanding_loans = showCurrentLoans($web_user);
    echo "<hr/>\n";
    echo "</div>\n";

    if( $outstanding_loans ) {
      echo "<script>\$(document).ready(function() {\$('#loan_form').show();});</script>\n";
    }
  }

  if( $is_timecode_view ) {
    echo "<p class='noprint'>";
    echo "<a class='btn btn-primary' href='",htmlescape("?s=timecodes&id=new"),"'>New Timesheet Code</a>\n";
    echo "</p>\n";
  } else if( !isShopWorker() || isAdmin() ) {
    echo "<p class='noprint'>";
    echo "<a class='btn btn-primary' href='?id=new'>New Work Order</a>\n";
    if( ENABLE_STOCK ) {
      echo "<a class='btn btn-primary' href='?id=new_stock'>New Stock Order</a>\n";
    }
    if( $my_work_orders ) {
      echo "<a class='btn btn-primary' href='?s=pickup'>Pick Up Order</a>\n";
    }
    if( isAdmin() ) {
      echo "<a class='btn btn-primary' href='?id=new_quote'>New Quote</a>\n";
    }
    if( ENABLE_STUDENT_SHOP_ACCESS_ORDERS ) {
      if( $web_user->employee_type == "Student" && strtolower($web_user->department) != strtolower(STUDENT_SHOP_DEPARTMENT) ) {
        echo "<a class='btn btn-primary' href='?id=new_student_shop'>Student Shop Access</a>\n";
      }
    }
    if( SHOP_SUPPORTS_LOANS && !$outstanding_loans ) {
      echo "<button class='btn btn-primary' onclick='showLoanForm(); return false;'>Borrow a Tool</button>\n";
      echo "<script>function showLoanForm() {\$('#loan_form').show();}</script>\n";
    }
    if( !$my_work_orders ) {
      echo "<button class='btn btn-primary' onclick='showExportOptions()'>Export ...</button>\n";
    }
    echo "</p>\n";

    if( !$my_work_orders ) {
      echo "<div class='card' id='export_form' style='display: none'><div class='card-body'>\n";
      echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
      echo "<input type='hidden' name='s' value='export_workorders'/>\n";
      echo "<label for='start_date'>Created On or After</label>: <input type='date' name='start_date' id='start_date'/><br>\n";
      echo "<label for='start_date'>Created Before</label>: <input type='date' name='end_date' id='end_date'/><br>\n";
      echo "<label><input type='radio' value='open' name='order_status' checked/> only open orders</label><br>\n";
      echo "<label><input type='radio' value='closed' name='order_status'/> only closed orders</label><br>\n";
      echo "<label><input type='radio' value='all' name='order_status'/> all orders</label><br>\n";
      echo "<input type='submit' value='Export'/>\n";
      echo "</form></div></div>\n";
      ?><script>
      function showExportOptions() {
        if( $('#export_form').is(":visible") ) {
          $('#export_form').hide();
        } else {
          $('#export_form').show();
        }
      }
      </script><?php
    }
  } else if( isShopWorker() ) {
    echo "<p class='noprint'>\n";
    if( SHOP_SUPPORTS_LOANS && !$outstanding_loans ) {
      echo "<button class='btn btn-primary' onclick='showLoanForm(); return false;'>Borrow a Tool</button>\n";
      echo "<script>function showLoanForm() {\$('#loan_form').show();}</script>\n";
    }
    echo "</p>\n";
  }

  $my_work_order_sql = "";
  if( $my_work_orders ) {
    $my_work_order_sql = getMyWorkOrderSQL();
  }

  if( isset($_REQUEST["s"]) && $_REQUEST["s"] == "all_stock_orders" ) {
    $order_type_sql = "AND STOCK_ORDER AND STATUS <> '" . WORK_ORDER_STATUS_TIMECODE . "'";
  } else if( $is_timecode_view ) {
    $order_type_sql = "AND STATUS = '" . WORK_ORDER_STATUS_TIMECODE . "'";
  } else if( $is_quote_view ) {
    $order_type_sql = "AND IS_QUOTE";
  } else if( $my_work_orders ) {
    $order_type_sql = "AND STATUS <> '" . WORK_ORDER_STATUS_TIMECODE . "'";
  } else {
    $order_type_sql = "AND STATUS <> '" . WORK_ORDER_STATUS_TIMECODE . "'";
    $order_type_sql .= " AND NOT IS_QUOTE";
    if( ENABLE_STOCK ) {
      $order_type_sql .= " AND NOT STOCK_ORDER";
    }
    if( ENABLE_CHECKOUT_BATCH_BILLING ) {
      $order_type_sql .= " AND NOT CHECKOUT_ORDER";
    }
  }
  if( ENABLE_STUDENT_SHOP_ACCESS_ORDERS && !$my_work_orders ) {
    $order_type_sql .= " AND NOT STUDENT_SHOP_ACCESS";
  }

  $page_limit = 200;
  $page = isset($_REQUEST["page"]) ? (int)$_REQUEST["page"] : 0;
  $offset = $page*$page_limit;

  $dbh = connectDB();
  $sql = "
    SELECT
      work_order.*,
      (SELECT MIN(timesheet.DATE) FROM timesheet WHERE timesheet.WORK_ORDER_ID = work_order.WORK_ORDER_ID) as WORK_STARTED,
      user.FIRST_NAME,
      user.LAST_NAME,
      user.EMAIL,
      user.PHONE,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.PI_NAME,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND,
      worker.FIRST_NAME as WORKER_FIRST_NAME,
      worker.LAST_NAME as WORKER_LAST_NAME
    FROM
      work_order
    JOIN user
    ON user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order.FUNDING_SOURCE_ID
    LEFT JOIN
      user worker
    ON
      worker.USER_ID = work_order.ASSIGNED_TO
    WHERE
      TRUE
      {$my_work_order_sql}
      {$order_type_sql}
    ORDER BY
      WORK_ORDER_ID DESC
    LIMIT " . ($page_limit+1) . " OFFSET {$offset}";

  $stmt = $dbh->prepare($sql);
  if( $my_work_order_sql ) {
    $stmt->bindValue(":ORDERED_BY",$web_user->user_id);
    $stmt->bindValue(":WEB_USER_EMAIL",$web_user->email);
  }
  $stmt->execute();

  echo "<table class='records sticky clicksort'><thead>";
  echo "<tr><th>WO #</th>";
  if( !$my_work_orders && !$is_timecode_view ) {
    if( !$is_quote_view ) {
      echo "<th><small>Rvwd</small></th>";
    }
    echo "<th><small>$Work<br>Done</small></th>";
  }
  if( !$is_timecode_view ) {
    echo "<th id='customer_col'>Customer</th>";
  }
  if( !$my_work_orders && !$is_timecode_view ) {
    echo "<th>Email</th>";
    echo "<th>Phone</th>";
  }
  echo "<th>Created</th>";
  if( !$is_timecode_view ) {
    echo "<th>Queued</th>";
    if( !$my_work_orders ) {
      echo "<th><small>Assnd</small></th>";
    }
    echo "<th>Started</th><th>Status</th>";
  }
  if( $my_work_orders ) {
    echo "<th>Fund</th>";
  }
  echo "<th>Description</th></tr></thead><tbody>\n";
  $item_num = 0;
  $only_customer_is_me = true;
  while( ($row=$stmt->fetch()) ) {
    $item_num += 1;
    if( $item_num > $page_limit ) {
      break;
    }
    echo "<tr class='record'>";
    $url = '?s=work_order&id=' . $row['WORK_ORDER_ID'];
    $wo_name = $row['WORK_ORDER_NUM'] <> "" ? $row['WORK_ORDER_NUM'] : ('#' . $row['WORK_ORDER_ID']);
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($wo_name),"</a></td>";
    if( !$my_work_orders && !$is_timecode_view ) {
      if( !$is_quote_view ) {
        echo "<td>", ($row["REVIEWED"] ? "<i class='fas fa-check text-success'></i><span class='clicksort_data'>Y</span>" : "") ,"</td>";
      }
      $emailed = $row["COMPLETION_EMAIL_SENT"] ? " <i class='far fa-envelope'></i>" : "";
      echo "<td>", ($row["COMPLETED"] ? "<i class='fas fa-check text-success'></i><span class='clicksort_data'>Y</span>" : "") ,"$emailed</td>";
    }

    if( !$is_timecode_view ) {
      if( $row["ORDERED_BY"] != $web_user->user_id ) {
        $only_customer_is_me = false;
      }
      $url = '?s=edit_user&user_id=' . $row["ORDERED_BY"];
      if( !isAdmin() ) { # shop worker does not have access to user profile
        $url = 'mailto:' . $row["EMAIL"];
      }
      echo "<td class='customer_col'><a href='",htmlescape($url),"'>",htmlescape($row["LAST_NAME"] . ", " . $row["FIRST_NAME"]),"</a></td>";
    }

    if( !$my_work_orders && !$is_timecode_view ) {
      echo "<td>";
      if( $row["EMAIL"] ) {
        echo "<a href='mailto:",htmlescape($row["EMAIL"]),"'>";
      }
      echo htmlescape($row["EMAIL"]);
      if( $row["EMAIL"] ) {
        echo "</a>";
      }
      echo "</td>";
      echo "<td>",htmlescape($row["PHONE"]),"</td>";
    }
    echo "<td>",htmlescape(displayDate($row["CREATED"])),"</td>";
    if( !$is_timecode_view ) {
      echo "<td>",htmlescape(displayDate($row["QUEUED"])),"</td>";

      if( !$my_work_orders ) {
        $worker_initials = "";
        $worker_initials .= $row["WORKER_FIRST_NAME"] ? substr($row["WORKER_FIRST_NAME"],0,1) : "";
        $worker_initials .= $row["WORKER_LAST_NAME"] ? substr($row["WORKER_LAST_NAME"],0,1) : "";
        echo "<td>",htmlescape($worker_initials),"</td>";
      }

      echo "<td>",htmlescape(displayDate($row["WORK_STARTED"])),"</td>";
      $status = getWorkOrderDisplayStatus($row);
      echo "<td>",htmlescape($status),"</td>";
    }
    if( $my_work_orders ) {
      $funding_string = getFundingSourceStringHTML($row);
      echo "<td>",$funding_string,"</td>";
    }
    echo "<td>",htmlescape($row["DESCRIPTION"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";

  if( $my_work_orders && $only_customer_is_me ) {
    ?><script>
      $(document).ready(function() {
        $('#customer_col,.customer_col').hide();
      });
    </script><?php
  }

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
    echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Newer Work Orders</a>";
  }
  if( $item_num > $page_limit ) {
    $url = arrayToUrlQueryString(array_merge($url_params,array("page" => $page+1)));
    echo " <a class='btn btn-primary' href='",htmlescape($url),"'>Older Work Orders</a>";
  }
  echo "</p>\n";

  if( !$page && $_SERVER["REQUEST_METHOD"] != 'POST' ) {
    # automatically reload the work/stock orders page every minute
    ?><script>
    setTimeout(function() {
      if( $('#loan_form:visible').length == 0 ) {
        location.reload();
      }
    },60000);
    </script><?php
  }
}

function getStockMaterials() {
  $dbh = connectDB();
  $sql = "SELECT * FROM stock_material ORDER BY NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();
  $results = array();
  while( ($row=$stmt->fetch()) ) {
    $results[$row["MATERIAL_ID"]] = $row;
  }
  return $results;
}

function getStockMaterial($material_id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM stock_material WHERE MATERIAL_ID = :MATERIAL_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":MATERIAL_ID",$material_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getStockShapes() {
  $dbh = connectDB();
  $sql = "SELECT * FROM stock_shape ORDER BY NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();
  $results = array();
  while( ($row=$stmt->fetch()) ) {
    $results[$row["SHAPE_ID"]] = $row;
  }
  return $results;
}

function getContracts() {
  $dbh = connectDB();
  $sql = "SELECT * FROM shop_contract ORDER BY CONTRACT_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();
  $results = array();
  while( ($row=$stmt->fetch()) ) {
    $results[$row["CONTRACT_ID"]] = $row;
  }
  return $results;
}

function getContract($contract_id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM shop_contract WHERE CONTRACT_ID = :CONTRACT_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":CONTRACT_ID",$contract_id);
  $stmt->execute();
  return $stmt->fetch();
}

function isWorkOrderEditor($user,$wo_row) {
  if( $user->user_id == $wo_row["ORDERED_BY"] ) return true;

  # Note that the email string comparisons are done in SQL here to
  # match those done in showWorkOrders().  They are case insensitive.

  $dbh = connectDB();
  $sql = "
    SELECT
      PI_EMAIL,
      BILLING_CONTACT_EMAIL
    FROM
      funding_source
    WHERE
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID
      AND (PI_EMAIL = :WEB_USER_EMAIL
      OR   BILLING_CONTACT_EMAIL = :WEB_USER_EMAIL)
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$wo_row["FUNDING_SOURCE_ID"]);
  $stmt->bindValue(":WEB_USER_EMAIL",$user->email);
  $stmt->execute();
  $fs_row = $stmt->fetch();

  if( $fs_row ) {
    return true;
  }
  return false;
}

function getWorkOrderShopCheckoutUrl($wo_id,$shop_info) {
  $url = $shop_info['CHECKOUT_URL'];
  if( strstr($url,"?") === FALSE ) $url .= "?";
  else $url .= "&";
  $url .= urlencode($shop_info['CHECKOUT_WORK_ORDER_ID_COL']) . '=' . urlencode($wo_id);
  return $url;
}

function showWorkOrder($web_user) {

  $dbh = connectDB();

  $editing = null;
  if( isset($_REQUEST["id"]) && strncmp($_REQUEST["id"],"new",3)!=0 ) {
    $editing = loadWorkOrder($_REQUEST["id"]);
  }

  if( $editing ) {
    $wo_desc = $editing["WORK_ORDER_NUM"];
    if( !$wo_desc ) $wo_desc = 'ID ' . $editing["WORK_ORDER_ID"];
  }

  $is_stock_order = $editing ? $editing["STOCK_ORDER"] : (isset($_REQUEST["id"]) && $_REQUEST["id"] == "new_stock");
  $is_quote = $editing ? $editing["IS_QUOTE"] : (isset($_REQUEST["id"]) && $_REQUEST["id"] == "new_quote");
  $is_timecode = (isset($_REQUEST['s']) && $_REQUEST['s'] == 'timecodes') || ($editing && $editing['STATUS'] == WORK_ORDER_STATUS_TIMECODE);
  $is_student_shop_access_order = ENABLE_STUDENT_SHOP_ACCESS_ORDERS && $editing ? $editing["STUDENT_SHOP_ACCESS"] : (isset($_REQUEST["id"]) && $_REQUEST["id"] == "new_student_shop");
  if( $is_student_shop_access_order ) {
    return showStudentShopAccessOrder($web_user);
  }
  $is_checkout_order = $editing && $editing["CHECKOUT_ORDER"];

  $Work_Order = "Work Order";
  if( $is_timecode ) $Work_Order = "Time Code";
  else if( $is_stock_order ) $Work_Order = "Stock Order";
  else if( $is_student_shop_access_order ) $Work_Order = "Student Shop Access Order";
  else if( $is_quote ) $Work_Order = "Quote";
  else if( $is_checkout_order ) $Work_Order = "Accumulated Checkout Order";

  if( preg_match('{.* Order$}',$Work_Order) ) {
    $Order = "Order";
  } else {
    $Order = $Work_Order;
  }

  $work_order = strtolower($Work_Order);
  $Work = $is_quote ? "Quote" : "Work";

  $shopworker_editing = "";
  $inprog_disabled = "";
  $closed_disabled = "";
  $is_owner = false;

  if( $editing && !isAdmin() && !isShopWorker() ) {
    if( !isWorkOrderEditor($web_user,$editing) ) {

      # As of 2021-12-23, shopworker_editing is no longer used,
      # because Doug Dummer requested that shop workers have full
      # admin access to work orders.  We no longer get here for
      # shop workers.

      if( isShopWorker() ) {
        $shopworker_editing = "disabled";
      } else {
        echo "<div class='alert alert-warning'>$Work_Order ",htmlescape($wo_desc)," does not belong to you.</div>\n";
        return;
      }
    } else if( $editing["CLOSED"] ) {
      echo "<div class='alert alert-success'>This $work_order has been completed.  Any questions?  Contact <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      $closed_disabled = "disabled";
      $inprog_disabled = "disabled";
      $is_owner = true;
    } else if( $editing["CANCELED"] ) {
      echo "<div class='alert alert-success'>This $work_order has been canceled.  Any questions?  Contact <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      $closed_disabled = "disabled";
      $inprog_disabled = "disabled";
      $is_owner = true;
    } else if( $editing["QUEUED"] ) {
      echo "<div class='alert alert-success'>This $work_order is already queued for work to begin, so any changes other than funding information must be made by contacting <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      $inprog_disabled = "disabled";
      $is_owner = true;
    }
  }

  if( $editing ) {
    echo "<h2>$Work_Order ",htmlescape($wo_desc),"</h2>\n";
  } else {
    echo "<h2>New $Work_Order</h2>\n";
  }

  if( $is_stock_order ) {
    if( !getBackToAppInfo($back_to_app_url,$back_to_app_name) || $back_to_app_name != "Student Shop App" ) {
      if( !$editing || intval(date("Y",strtotime($editing["CREATED"]))) >= 2023 ) {
        echo "<div class='alert alert-info'>As of 2023, there will be a labor charge for time spent cutting stock in addition to the cost of material. The same hourly rates will be used as those applied to work orders.</div>\n";
      }
    }
    echo "<div class='alert alert-warning'>This form is for rough dimensioned stock &plusmn;&#x215B;\". Close tolerance stock must instead be submitted as a <a href='?id=new'>work order</a>.</div>\n";
  }

  echo "<form id='work_order_form' enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateWorkOrderForm();'>\n";
  echo "<input type='hidden' name='form' value='work_order'/>\n";
  if( $editing ) {
    echo "<input type='hidden' name='id' value='",htmlescape($editing["WORK_ORDER_ID"]),"'/>\n";
  } else if( $is_stock_order ) {
    echo "<input type='hidden' name='id' value='new_stock'/>\n";
  }

  echo "<div class='container'>\n";
  $rowclass = "row mt-2 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  if( isAdmin() || isShopWorker() ) {
    echo "<div class='$rowclass noprint'><div class='$col1'><label for='work_order_num'>$Order #</label></div><div class='col'><input type='text' name='work_order_num' id='work_order_num' size='10' maxlength='60' aria-label='work order number' value='",htmlescape($editing ? $editing["WORK_ORDER_NUM"] : ""),"'/></div></div>\n";

    if( !$is_timecode && !$is_quote ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='reviewed'>Reviewed by Shop Supervisor</label></div><div class='col'>";
      $checked = $editing && $editing["REVIEWED"] ? "" : "checked";
      echo "<label><input type='radio' name='reviewed' id='reviewed' value='N' $checked/> No</label> ";
      $checked = $editing && $editing["REVIEWED"] ? "checked" : "";
      echo "<label><input type='radio' name='reviewed' id='reviewed' value='Y' $checked/> Yes</label> ";
      if( $editing && $editing["REVIEWED"] ) {
        echo " (",htmlescape(displayDateTime($editing["REVIEWED"])),")";
      }
      echo "</div></div>\n";
    }
  }

  if( $editing ) {
    $creator = new User;
    $creator->loadFromUserID($editing["ORDERED_BY"]);
    echo "<div class='$rowclass'><div class='$col1'>Ordered By</div><div class='col'><b>",htmlescape($creator->displayName()),"</b> on ",htmlescape(displayDateTime($editing["CREATED"]));
    if( isAdmin() || isShopWorker() ) {
      echo "<br><select name='new_owner' class='noprint'><option value=''>change owner</option>\n";
      $users = getUsers();
      foreach( $users as $u ) {
        echo "<option value='",htmlescape($u->user_id),"'>",htmlescape($u->lastFirst()),"</option>\n";
      }
      echo "</select>\n";
    }
    echo "</div></div>\n";
    echo "<div class='$rowclass'><div class='$col1'>Email</div><div class='col'><a href='mailto:",htmlescape($creator->email),"'>",htmlescape($creator->email),"</a></div></div>\n";
    echo "<div class='$rowclass'><div class='$col1'>Phone</div><div class='col'>",htmlescape($creator->phone),"</div></div>\n";
  }

  if( !$shopworker_editing && !$is_timecode && !$is_quote ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='group_id'>Fund Group</label></div><div class='col'>";
    echoSelectUserGroup($web_user,$editing ? $editing["GROUP_ID"] : null,$closed_disabled);
    echo "</div></div>\n";

    echo "<div class='$rowclass'><div class='$col1'><label for='funding_source'>Fund</label></div><div class='col'>";
    $order_user = $editing ? loadUserFromUserID($editing["ORDERED_BY"]) : $web_user;
    $show_details = true;
    $offer_to_set_default = true;
    echoSelectFundingSource($order_user,$editing ? $editing["FUNDING_SOURCE_ID"] : null,$closed_disabled,$show_details,$offer_to_set_default);
    echo "</div></div>\n";
  }

  if( !$is_timecode && !$is_stock_order ) {
    if( isAdmin() || isShopWorker() ) {
      echo "<div class='$rowclass'><div class='$col1'>Shop Contract</div><div class='col'>";
      $contracts = getContracts();
      foreach( $contracts as $contract ) {
        $checked = $editing && $editing["CONTRACT_ID"] == $contract["CONTRACT_ID"] ? "checked" : "";
        if( !$editing && count($contracts)==1 ) $checked = "checked";
        if( !$checked && $contract["HIDE"] ) continue;
        echo " <label><input type='radio' name='contract' value='",htmlescape($contract["CONTRACT_ID"]),"' $checked {$shopworker_editing} {$inprog_disabled}/> ",htmlescape($contract["CONTRACT_NAME"]),"</label>";
      }
      echo "</div></div>\n";
    } else if( $editing ) {
      $contract = getContract($editing["CONTRACT_ID"]);
      if( $contract ) {
        echo "<div class='$rowclass'><div class='$col1'>Shop Contract</div><div class='col'>",htmlescape($contract["CONTRACT_NAME"]),"</div></div>\n";
      }
    }
  }

  if( !$shopworker_editing && !$is_timecode && (!$editing || $editing["INVENTORY_NUM"] || !$closed_disabled)) {
    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='inventory_num'>Inventory #</label></div><div class='col'><input type='text' name='inventory_num' id='inventory_num' size='10' maxlength='60' aria-label='inventory number' value='",htmlescape($editing ? $editing["INVENTORY_NUM"] : ""),"' {$inprog_disabled}/> (if part of a capital item)</div></div>\n";
  }

  echo "<div id='stock_placeholder'></div>\n";

  if( $is_stock_order ) {
    $desc_label = "Notes";
  } else {
    $desc_label = "Job Description";
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='description'>$desc_label</label></div><div class='col'>";
  echo "<textarea name='description' id='description' rows='5' cols='40' class='noprint' oninput='updatePrintTextarea(this)' {$shopworker_editing} {$inprog_disabled}>",htmlescape($editing ? $editing["DESCRIPTION"] : ""),"</textarea>";
  echo "<div id='print-description' class='printonly print-textarea'>",htmlescape($editing ? $editing["DESCRIPTION"] : ""),"</div>";
  echo "</div></div>\n";

  if( isAdmin() || isShopWorker() ) {
    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='admin_notes'>Shop Notes<br>(not visible to customer)</label></div><div class='col'>";
    echo "<textarea name='admin_notes' id='admin_notes' rows='3' cols='40' class='noprint' oninput='updatePrintTextarea(this)' {$shopworker_editing} {$inprog_disabled}>",htmlescape($editing ? $editing["ADMIN_NOTES"] : ""),"</textarea>";
    echo "<div id='print-admin_notes' class='printonly print-textarea'>",htmlescape($editing ? $editing["ADMIN_NOTES"] : ""),"</div>";
    echo "</div></div>\n";
  }

  echo "<div class='$rowclass print-if-nonempty'><div class='$col1'>Attachments</div><div class='col'>";
  if( $editing ) {
    $perm_sql = "";
    if( !isAdmin() && !isShopWorker() ) {
      $perm_sql .= "AND USER_READ_PERM = 'Y'";
    }
    $sql = "SELECT * FROM work_order_file WHERE WORK_ORDER_ID = :WORK_ORDER_ID {$perm_sql} ORDER BY WORK_ORDER_FILE_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":WORK_ORDER_ID",$editing["WORK_ORDER_ID"]);
    $stmt->execute();
    echo "<div class='check-nonempty'>";
    while( ($row=$stmt->fetch()) ) {
      $url = getWorkOrderFileUrl($row['WORK_ORDER_ID'],$row['FILENAME']);
      echo "<a href='",htmlescape($url),"'>",htmlescape($row["FILENAME"]),"</a>";
      if( isAdmin() || isShopWorker() || $is_owner && $row["USER_WRITE_PERM"] == 'Y' ) {
        echo " <label class='noprint'><input type='checkbox' name='delete_attachment[]' value='",htmlescape($row['WORK_ORDER_FILE_ID']),"' {$inprog_disabled}/> delete</label>";
      }
      echo "<br>\n";
    }
    echo "</div>\n";
  }
  echo "<div id='attachments' class='check-nonempty'></div>\n";
  if( !$inprog_disabled ) {
    echo "<div id='attachment-instructions' class='alert alert-warning' style='display: none'><h4>Instructions for adding an attachment</h4>\nClick the button above to select a file to attach.  To attach an additional file, click Add Attachment again.  ",WORKORDER_ATTACHMENT_INSTRUCTIONS,"</div>\n";
    echo "<button class='btn btn-secondary btn-sm noprint' onclick='addAttachment(); return false;' style='margin-bottom: 0.5em'>Add Attachment</button>\n";
  }
  echo "</div></div>\n";

  ?><script>
  function addAttachment() {
    workOrderChanged();
    $('#attachment-instructions').show();
    var attachments = document.getElementById('attachments');
    var a = document.createElement('div');
    a.className = 'new_attachment';
    var a_id = $('#attachments > div').length + 1;
    var perms = "";
    <?php if( isAdmin() || isShopWorker() ) { ?>
    perms = "customer can: <label><input type='radio' name='attachment_perm_" + a_id + "' value=''/> not view</label> &nbsp;<label><input type='radio' name='attachment_perm_" + a_id + "' value='r'/> view</label> &nbsp;<label><input type='radio' name='attachment_perm_" + a_id + "' value='rw'/> view and update</label><br>";
    <?php } ?>
    a.innerHTML = perms + "<input type='file' name='attachment_" + a_id + "'/>";
    attachments.appendChild(a);
  }
  </script><?php

  echo "<div id='stock_card' style='padding-top: 0.25em;'>\n";
  $stock_order_count = 0;
  if( ENABLE_STOCK ) {
    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='quote'>Stock</label></div><div class='col'>";
    if( !isAdmin() && !isShopWorker() ) {
      echo "<div class='requires_admin_input_notice alert alert-info' style='display: none'>Stock costs that are not automatically filled in will be entered later by the shop manager.</div>\n";
    }
    echo "<table class='records' id='stock_orders' style='display: none;'>";
    echo "<thead><tr>";
    if( $is_quote ) {
      echo "<th>Part</th>";
    }
    echo "<th>Material</th><th>Shape</th><th>T<small>hickness</small><br><small>(inches)</small></th><th>H<small>eight</small><br><small>(inches)</small></th><th>W<small>idth/</small>D<small>ia</small><br><small>(inches)</small></th><th>L<small>ength</small><br><small>(inches)</small></th><th>Qty</th>";
    echo "<th>Material<br>Cost/<br>Foot</th>";
    $materials = $is_quote ? "<br><small>(materials)</small>" : "";
    echo "<th>Material<br>Cost/<br>Piece</th><th>Total{$materials}</th>";
    if( $is_quote ) {
      if( isAdmin() || isShopWorker() ) {
        echo "<th>Hours</th>";
      }
    }
    if( !$inprog_disabled ) {
      # delete checkbox column
      echo "<th><i class='fa fa-trash' aria-hidden='true'></i></th>";
    }
    echo "</tr></thead><tbody class='check-nonempty'>";

    $stock_materials = getStockMaterials();
    $stock_shapes = getStockShapes();
    $canceled_stock_count = 0;
    $stock_total = 0;
    $stock_total_hours = 0;
    if( $editing ) {
      $stock_sql = "SELECT * FROM stock_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID ORDER BY PART_NAME,STOCK_ORDER_ID";
      $stock_stmt = $dbh->prepare($stock_sql);
      $stock_stmt->bindValue(":WORK_ORDER_ID",$editing["WORK_ORDER_ID"]);
      $stock_stmt->execute();

      while( ($stock=$stock_stmt->fetch()) ) {
        $soid = htmlescape($stock["STOCK_ORDER_ID"]);
        $stock_order_count += 1;
        if( $stock["CANCELED"] ) {
          $canceled_stock_count += 1;
        }
        $style = $stock["CANCELED"] ? "style='background-color: #f0f0f0'" : "";
        echo "<tr $style onfocusin='showShapeInfo()'>";
	if( $is_quote ) {
	  if( isAdmin() || isShopWorker() ) {
            echo "<td><input name='edit_stock_part_{$soid}' value='",htmlescape($stock["PART_NAME"]),"' size='6' class='noprint' id='edit_stock_part_{$soid}' oninput='updatePrintTextarea(this)'/><span class='printonly' id='print-edit_stock_part_{$soid}'>",htmlescape($stock["PART_NAME"]),"</span></td>";
	  } else {
	    echo "<td>",htmlescape($stock["PART_NAME"]),"</td>";
	  }
	}
        $style = $stock["CANCELED"] ? "style='text-decoration: line-through'" : "";
        echo "<td $style>";
        if( isset($stock_materials[$stock["MATERIAL_ID"]]) ) {
           $material = $stock_materials[$stock["MATERIAL_ID"]];
           echo htmlescape($material["NAME"]);
        } else if( $stock["MATERIAL_ID"] == OTHER_MATERIAL_ID || $stock["MATERIAL_ID"] == OTHER_ITEM_ID ) {
          echo htmlescape($stock["OTHER_MATERIAL"]);
        }
        echo "</td>";
        echo "<td $style>";
        if( isset($stock_shapes[$stock["SHAPE_ID"]]) ) {
          $shape = $stock_shapes[$stock["SHAPE_ID"]];
          echo "<a href='#' onclick='showShapeInfo(); return false;'><img src='",htmlescape($shape["IMAGE"]),"' style='max-width: 20px; max-height: 20px;'/>";
	  echo htmlescape($shape["NAME"]);
          echo "<input type='hidden' class='stock_shape' value='",htmlescape($stock["SHAPE_ID"]),"'/></a>";
        }
        echo "</td>";
        echo "<td $style>",htmlescape($stock["THICKNESS"]),"</td>";
        echo "<td $style>",htmlescape($stock["HEIGHT"]),"</td>";
        echo "<td $style>",htmlescape($stock["WIDTH"]),"</td>";
        echo "<td $style>",htmlescape($stock["LENGTH"]),"</td>";
        echo "<td $style>",htmlescape($stock["QUANTITY"]),"</td>";

        if( (isAdmin() || isShopWorker()) && !$stock["CANCELED"] && (!isset($stock_shapes[$stock["SHAPE_ID"]]) || !$stock_shapes[$stock["SHAPE_ID"]]["VOLUME_FORMULA"]) ) {
          echo "<td class='currency'><input class='stock_cost_per_foot currency' size='5' name='edit_stock_cost_per_foot_{$soid}' value='",htmlescape($stock["COST_PER_FOOT"]),"' onchange='invalidateStockTotal(this); invalidateStockUnitCost(this);'/><input type='hidden' name='edit_stock_orig_cost_per_foot_{$soid}' value='",htmlescape($stock["COST_PER_FOOT"]),"'/></td>";
        } else {
          echo "<td class='currency' $style>",htmlescape($stock["COST_PER_FOOT"]),"</td>";
        }

        if( (isAdmin() || isShopWorker()) && !$stock["CANCELED"] ) {
          echo "<td class='currency'><input class='stock_unit_cost currency' size='5' name='edit_stock_unit_cost_{$soid}' value='",htmlescape($stock["UNIT_COST"]),"' onchange='invalidateStockTotal(this); invalidateStockCostPerFoot(this);'/><input type='hidden' name='edit_stock_orig_unit_cost_{$soid}' value='",htmlescape($stock["UNIT_COST"]),"'/></td>";
        } else {
          echo "<td class='currency' $style>",htmlescape($stock["UNIT_COST"]),"</td>";
        }

        if( $stock["TOTAL_COST"] === null ) {
          if( !$stock["CANCELED"] ) {
            $stock_total = null;
          }
        } else if( $stock_total !== null ) {
          $stock_total += $stock["TOTAL_COST"];
        }
        echo "<td class='stock_total_cost currency'>",htmlescape($stock["TOTAL_COST"]),"</td>";

        if( $is_quote ) {
          if( isAdmin() || isShopWorker() ) {
	    echo "<td><input class='fixednum' name='edit_stock_hours_{$soid}' value='",htmlescape($stock["HOURS"]),"' size='5'/></td>";
	    if( $stock["HOURS"] === null ) {
	      if( !$stock["CANCELED"] ) {
                $stock_total_hours = null;
	      }
	    } else if( $stock_total_hours !== null ) {
	      $stock_total_hours += $stock["HOURS"];
	    }
	  }
        }
        if( !$inprog_disabled ) {
          $checked = $stock["CANCELED"] ? "checked" : "";
          $orig_delete = $stock["CANCELED"] ? "1" : "0";
          echo "<td><input type='hidden' name='orig_delete_stock_{$soid}' value='",htmlescape($orig_delete),"'/><input type='checkbox' name='delete_stock_{$soid}' value='1' $checked/></td>";
        }
        echo "</tr>\n";
      }

      if( $stock_order_count > 1 && ($stock_total !== null || $is_quote && $stock_total_hours !== null) ) {
        $value = $stock_total !== null ? sprintf('$%.2f',$stock_total) : "";
	$part_col = $is_quote ? "<td></td>" : "";
        echo "<tr><td><b>Total</b></td>{$part_col}<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td class='stock_grand_total currency'><b>",htmlescape($value),"</b></td>";
	if( isAdmin() || isShopWorker() ) {
	  if( $is_quote ) {
	    $value = $stock_total_hours !== null ? sprintf("%.2f",$stock_total_hours) : "";
	    echo "<td class='fixednum'><b>",htmlescape($value),"</b></td>";
	  }
	  echo "<td></td>";
	}
	echo "</tr>";
      }
    }

    echo "</tbody></table>\n";

    if( $editing && $stock_order_count ) {
      echo '<script>$("#stock_orders").show();</script>';
    }
    if( !$inprog_disabled ) {
      echo "<button class='btn btn-secondary btn-sm noprint' onclick='addStock(); return false;' style='margin-top: 0.1em; margin-bottom: 0.5em'>Add Stock</button>\n";
    }

    if( $editing && $canceled_stock_count && (isAdmin() || isShopWorker()) ) {
      echo "<input type='hidden' name='clear_deleted_stock' id='clear_deleted_stock' value='0'/>";
      echo "<button class='btn btn-secondary btn-sm noprint' id='clear_deleted_stock_button' onclick='clearDeletedStock(); return false;' data-bs-toggle='button' style='margin-top: 0.1em; margin-bottom: 0.5em'>Clear Deleted Stock Entries</button>";
      echo "<span id='clear_on_submit_msg' style='display: none; color: red'> (Deleted items will be cleared when you submit this form.)</span>\n";
    }
    ?><style>
    #selected_shape_info img {
      vertical-align: top;
      max-width: 200px;
      margin-left: 2em;
    }
    </style><?php
    echo "<span id='selected_shape_info'></span>";
    echo "</div></div>\n"; # end Stock
  }

  $parts_header = false;
  $parts_count = 0;
  $parts_total = 0;
  foreach( getOtherShopsInWorkOrders() as $shop_name => $shop_info ) {
    # NOTE: intentionally ignoring $shop_info['SHOW_IN_WORK_ORDER_FORM'] here.  Always show if there are any items.
    if( isset($shop_info['SHOP_NAME']) ) {
      $shop_name = $shop_info['SHOP_NAME'];
    }
    $items = $editing ? getShopItemsInWorkOrder($editing["WORK_ORDER_ID"],$shop_info) : array();
    foreach( $items as $item ) {
      if( !$parts_header ) {
        $parts_header = true;
        echo "<div class='$rowclass'><div class='$col1'><label>Parts</label></div><div class='col'>";
        echo "<table class='records' id='part_orders' style='display: none;'>";
        echo "<thead><tr><th>Part</th><th>Qty</th><th>Cost/<br>Piece</th><th>Total</th></tr></thead><tbody>\n";
      }
      $parts_count += 1;
      echo "<tr>";
      $part_desc = $item['STOCK_NUM'] . " - " . $item['DESCRIPTION'];
      echo "<td>",htmlescape($shop_name)," part ",htmlescape($part_desc),"</td>";
      $qty = $item['QTY'] . " " . $item['UNITS'];
      echo "<td>",htmlescape($qty),"</td>";
      echo "<td class='currency'>",$item['PRICE'],"</td>";
      $parts_total += $item['TOTAL'];
      echo "<td class='currency'>",$item['TOTAL'],"</td>";
      echo "</tr>\n";
    }
  }
  if( $parts_count > 1 && $parts_total !== null ) {
    echo "<tr><td><b>Total</b></td><td></td><td></td><td class='currency'><b>\$",htmlescape(sprintf("%.2f",$parts_total)),"</b></td></tr>\n";
  }
  if( $parts_count > 0 ) {
    echo "</tbody></table>\n";
    if( $editing ) {
      echo '<script>$("#part_orders").show();</script>';
    }
  }

  if( isAdmin() || isShopWorker() ) {
    foreach( getOtherShopsInWorkOrders() as $shop_name => $shop_info ) {
      if( isset($shop_info["SHOW_IN_WORK_ORDER_FORM"]) && !$shop_info["SHOW_IN_WORK_ORDER_FORM"] ) {
        continue;
      }
      if( $editing ) {
        $url = getWorkOrderShopCheckoutUrl($editing['WORK_ORDER_ID'],$shop_info);
        $disabled = "";
        $title = "";
      } else {
        $url = '#';
        $disabled = "disabled";
        $title = "save the order first before adding items";
      }
      if( !$parts_header ) {
        $parts_header = true;
        echo "<div class='$rowclass noprint'><div class='$col1'><label for='quote'>Parts</label></div><div class='col'>";
      }
      echo "<span title='",htmlescape($title),"'><a class='btn btn-secondary btn-sm noprint $disabled' href='",htmlescape($url),"' style='margin-top: 0.1em; margin-bottom: 0.5em'>Add ",htmlescape($shop_name)," Item</a></a>\n";
    }
  }
  if( $parts_header ) {
    echo "</div></div>\n"; # end Parts
  }

  echo "</div>"; # end id=stock_card

  $hide_stock_card = false;
  if( $is_timecode ) {
    $hide_stock_card = true;
  }
  # The instrument shop does not want customers to be able to add stock to a non-stock order, but
  # admins and shop workers should be able to.
  if( !$is_stock_order && !$is_quote && $stock_order_count == 0 && !isAdmin() && !isShopWorker() ) {
    $hide_stock_card = true;
  }
  if( $hide_stock_card ) {
    echo '<script>$("#stock_card").hide();</script>';
  }

  ?><script>
  var stock_row_num = 0;
  function addStock() {
    workOrderChanged();
    var row = document.createElement('tr');
    row.className = "new_stock_row";
    var html = "";
    <?php if( $is_quote ) { ?>
      html += "<td><input size='6' name='stock_part_" + stock_row_num + "'/></td>";
    <?php } ?>
    html += "<td><select class='stock_material' name='stock_material_" + stock_row_num + "' onchange='stockMaterialSelected(" + stock_row_num + ")'/><option value=''>select material</option>";
    <?php
    foreach( $stock_materials as $material ) {
      if( $material["HIDE"] ) continue;
      echo "html += \"<option value='",htmlescape($material["MATERIAL_ID"]),"'>",htmlescape($material["NAME"]),"</option>\";";
    }
    echo "html += \"<option value='",htmlescape(OTHER_MATERIAL_ID),"'>other material</option>\";";
    echo "html += \"<option value='",htmlescape(OTHER_ITEM_ID),"'>other item</option>\";";
    ?>
    html += "</select>";
    html += "<div style='white-space: nowrap'><input class='stock_other_material' name='stock_other_material_" + stock_row_num + "' placeholder='describe' style='display: none'/>";
    html += "<button id='stock_other_material_x_" + stock_row_num + "' class='btn btn-sm btn-light' onclick='otherMaterialX(" + stock_row_num + "); return false;' style='display: none'>X</button></div>";
    html += "</td>";
    html += "<td><select class='stock_shape' name='stock_shape_" + stock_row_num + "' onchange='showShapeInfo()'><option value=''>select shape</option>";
    <?php
    foreach( $stock_shapes as $shape ) {
      echo "html += \"<option value='",htmlescape($shape["SHAPE_ID"]),"'>",htmlescape($shape["NAME"]),"</option>\";";
    }
    ?>
    html += "</select></td>";
    html += "<td><input size='6' class='stock_thickness' name='stock_thickness_" + stock_row_num + "' onchange='fixUnits(this)'/></td>";
    html += "<td><input size='5' class='stock_height' name='stock_height_" + stock_row_num + "' onchange='fixUnits(this)'/></td>";
    html += "<td><input size='5' class='stock_width' name='stock_width_" + stock_row_num + "' onchange='fixUnits(this)'/></td>";
    html += "<td><input size='5' class='stock_length' name='stock_length_" + stock_row_num + "' onchange='fixUnits(this)'/></td>";
    html += "<td><input size='5' class='stock_quantity' name='stock_quantity_" + stock_row_num + "' value='1' type='number' min='1'/></td>";
    <?php if( isAdmin() || isShopWorker() ) { ?>
      html += "<td><input size='5' class='stock_cost_per_foot currency' name='stock_cost_per_foot_" + stock_row_num + "'/></td>";
      html += "<td><input size='5' class='stock_unit_cost currency' name='stock_unit_cost_" + stock_row_num + "'/></td>";
    <?php } else { ?>
      html += "<td class='stock_cost_per_foot currency'></td>";
      html += "<td class='stock_unit_cost currency'></td>";
    <?php } ?>
    html += "<td class='stock_total_cost currency'></td>";
    <?php if( (isAdmin() || isShopWorker()) && $is_quote ) { ?>
      html += "<td><input size='5' class='stock_hours fixednum' name='stock_hours_" + stock_row_num + "'/></td>";
    <?php } ?>
    row.innerHTML = html;
    row.onfocusin = function(){showShapeInfo();};
    $(row).find('input,select,textarea').change(function() {workOrderChanged();});
    $(row).find('input,select,textarea').on("input",function() {updateStockCosts();});
    $('#stock_orders tbody').append(row);
    $('#stock_orders').show();
    stock_row_num += 1;
  }
  function stockMaterialSelected(stock_row_num) {
    var material = $('[name="stock_material_' + stock_row_num + '"]');
    if( material.val() === "<?php echo OTHER_ITEM_ID ?>" ) {
      var shape = $('[name="stock_shape_' + stock_row_num + '"]');
      shape.val('');
      shape.prop("disabled",true);
    }
    if( material.val() === "<?php echo OTHER_ITEM_ID ?>" || material.val() === "<?php echo OTHER_MATERIAL_ID ?>" ) {
      material.hide();
      var other_material = $('[name="stock_other_material_' + stock_row_num + '"]');
      other_material.show();
      other_material.focus();
      var other_material_x = $('#stock_other_material_x_' + stock_row_num);
      other_material_x.show();
      showShapeInfo();
    }
  }
  function otherMaterialX(stock_row_num) {
    var other_material = $('[name="stock_other_material_' + stock_row_num + '"]');
    other_material.val("");
    other_material.hide();
    var other_material_x = $('#stock_other_material_x_' + stock_row_num);
    other_material_x.hide();
    var material = $('[name="stock_material_' + stock_row_num + '"]');
    material.val("");
    material.show();
    var shape = $('[name="stock_shape_' + stock_row_num + '"]');
    shape.prop("disabled",false);
    showShapeInfo();
  }
  function getStockRow(e) {
    for( ; e; e = e.parentElement ) {
      if( e.tagName == "TR" && e.parentElement.tagName == "TBODY" && e.parentElement.parentElement.tagName == "TABLE" && e.parentElement.parentElement.id == "stock_orders" ) {
        return e;
      }
    }
  }
  function getActiveStockRow() {
    return getStockRow(document.activeElement);
  }
  var stock_shapes = <?php echo json_encode($stock_shapes); ?>;
  function showShapeInfo() {
    var row = getActiveStockRow();
    var shape_id = null;
    var material_id = null;
    var shape_info = document.getElementById('selected_shape_info');
    if( row ) {
      shape_id = $(row).find('.stock_shape').val();
      material_id = $(row).find('.stock_material').val();
    }

    if( shape_id ) {
      var shape_img = stock_shapes[shape_id]['IMAGE'];
      shape_info.innerHTML = "<img src='" + shape_img + "'/>";
    } else if( material_id === "<?php echo OTHER_ITEM_ID ?>" ) {
      shape_info.innerHTML = "If more details are needed to fully describe the item, please add them in the notes box.";
    } else if( material_id === "<?php echo OTHER_MATERIAL_ID ?>" ) {
      shape_info.innerHTML = "If more details are needed to fully describe the material, please add them in the notes box.";
    } else {
      shape_info.innerHTML = "";
    }

    var shape_fields = shape_id ? stock_shapes[shape_id]['DIMENSIONS'] : '';
    if( shape_fields.indexOf('T') >= 0 ) {
      $(row).find('.stock_thickness').prop("disabled",false);
    } else {
      $(row).find('.stock_thickness').prop("disabled",true);
      $(row).find('.stock_thickness').val("");
    }
    if( shape_fields.indexOf('H') >= 0 ) {
      $(row).find('.stock_height').prop("disabled",false);
    } else {
      $(row).find('.stock_height').prop("disabled",true);
      $(row).find('.stock_height').val("");
    }
    if( shape_fields.indexOf('W') >= 0 ) {
      $(row).find('.stock_width').prop("disabled",false);
    } else {
      $(row).find('.stock_width').prop("disabled",true);
      $(row).find('.stock_width').val("");
    }
    if( shape_fields.indexOf('L') >= 0 ) {
      $(row).find('.stock_length').prop("disabled",false);
    } else {
      $(row).find('.stock_length').prop("disabled",true);
      $(row).find('.stock_length').val("");
    }
    if( shape_id && stock_shapes[shape_id]["VOLUME_FORMULA"] ) {
      $(row).find('.stock_cost_per_foot').prop("disabled",true);
      $(row).find('.stock_cost_per_foot').val("");
    } else {
      $(row).find('.stock_cost_per_foot').prop("disabled",false);
    }
  }
  function clearDeletedStock() {
    setTimeout(function() {
      if( $('#clear_deleted_stock_button').hasClass('active') ) {
        document.getElementById('clear_deleted_stock').value = '1';
        $('#clear_on_submit_msg').show();
      } else {
        document.getElementById('clear_deleted_stock').value = '0';
        $('#clear_on_submit_msg').hide();
      }
    },1);
  }
  function toInches(value) {
    var m = value.match(/^ *((?:[0-9]+(?:\.[0-9]*){0,1})|(?:\.[0-9]+)) *('|"|inches|in|feet|ft|mm|m){0,1} *$/);
    if( m ) {
      var num = m[1];
      var m2 = num.match(/^([0-9+])\.$/);
      if( m2 ) num = m2[1];

      var unit = m[2];
      if( unit == null || unit == '"' || unit == 'inches' || unit == 'in' ) {
        return num;
      }
      else if( unit == "'" || unit == 'feet' || unit == 'ft' ) {
        return Math.round(parseFloat(num)*12*1000)/1000.0;
      }
      else if( unit == "mm" ) {
        return Math.round(parseFloat(num)/25.4*1000)/1000.0;
      }
      else if( unit == "m" ) {
        return Math.round(parseFloat(num)*39.3701*1000)/1000.0;
      }
    }
    return value;
  }
  function fixUnits(e) {
    var new_value = toInches(e.value);
    if( new_value != e.value ) {
      e.value = new_value;
    }
  }
  function invalidateStockTotal(e) {
    var row = getStockRow(e);
    $(row).find('.stock_total_cost').addClass('stale');
    $('.stock_grand_total').addClass('stale');
  }
  function invalidateStockUnitCost(e) {
    var row = getStockRow(e);
    $(row).find('.stock_unit_cost').val('');
  }
  function invalidateStockCostPerFoot(e) {
    var row = getStockRow(e);
    $(row).find('.stock_cost_per_foot').val('');
  }
  var stock_cost_queries = {};
  function updateStockCosts() {
    $('#stock_orders .new_stock_row').each(function (index) {
      var row = this;

      var material_id = $(row).find('.stock_material').val();
      var shape_id = $(row).find('.stock_shape').val();
      var thickness = toInches($(row).find('.stock_thickness').val());
      var height = toInches($(row).find('.stock_height').val());
      var width = toInches($(row).find('.stock_width').val());
      var length = toInches($(row).find('.stock_length').val());
      var quantity = $(row).find('.stock_quantity').val();
      var unit_cost = $(row).find('.stock_unit_cost').val();
      if( unit_cost == undefined ) unit_cost = "";
      var cost_per_foot = $(row).find('.stock_cost_per_foot').val();
      if( cost_per_foot == undefined ) cost_per_foot = "";

      var url = "?s=stock_cost_query";
      url += "&material_id=" + encodeURIComponent(material_id);
      url += "&shape_id=" + encodeURIComponent(shape_id);
      url += "&thickness=" + encodeURIComponent(thickness);
      url += "&height=" + encodeURIComponent(height);
      url += "&width=" + encodeURIComponent(width);
      url += "&length=" + encodeURIComponent(length);
      url += "&quantity=" + encodeURIComponent(quantity);
      url += "&unit_cost=" + encodeURIComponent(unit_cost);
      url += "&cost_per_foot=" + encodeURIComponent(cost_per_foot);

      if( stock_cost_queries.hasOwnProperty(url) ) {
	var data = stock_cost_queries[url];
	if( data != 0 ) {
          handleStockCostQueryResult(row,data);
	}
	return;
      }
      stock_cost_queries[url] = 0;

      $.ajax({ url: url, success: function(data) {
	stock_cost_queries[url] = data;
	handleStockCostQueryResult(row,data);
      }});
    });
  }
  function handleStockCostQueryResult(row,data) {
    var reply = JSON.parse(data);
    if( reply ) {
      var default_unit_cost = reply['default_unit_cost'];
      var unit_cost = reply['unit_cost'];
      var default_cost_per_foot = reply['default_cost_per_foot'];
      var cost_per_foot = reply['cost_per_foot'];
      var total_cost = reply['total_cost'];
      if( default_unit_cost == null ) default_unit_cost = "";
      if( unit_cost == null ) unit_cost = "";
      if( default_cost_per_foot == null ) default_cost_per_foot = "";
      if( cost_per_foot == null ) cost_per_foot = "";
      if( total_cost == null ) total_cost = "";
      $(row).find('input.stock_unit_cost').prop("placeholder",default_unit_cost);
      $(row).find('td.stock_unit_cost').text(unit_cost);
      $(row).find('input.stock_cost_per_foot').prop("placeholder",default_cost_per_foot);
      $(row).find('td.stock_cost_per_foot').text(cost_per_foot);
      $(row).find('.stock_total_cost').text(total_cost);
      if( reply['requires_admin_input'] ) {
        $('.requires_admin_input_notice').show();
      }
    }
  }
  </script><?php

  if( $is_stock_order ) {
    # move the stock card above less important fields when showing a stock order
    ?><script>
      document.addEventListener('DOMContentLoaded', function(event) {
        var new_position = document.getElementById('stock_placeholder');
	var stock_card = document.getElementById('stock_card');
	new_position.appendChild(stock_card);
	<?php if( !$editing ) echo "addStock(); addStock(); addStock(); work_order_changed = false;"; ?>
      });
    </script><?php
  }

  if( $editing && !$is_timecode ) {
    getWorkOrderLaborCharge($editing["WORK_ORDER_ID"],null,$labor_charge,$total_hours,$hourly_rate);

    $Total = $is_quote ? "Estimated Bill" : "Total Charged";
    echo "<div class='$rowclass'><div class='$col1'>$Total</div><div class='col'>";
    echo "<table style='border: 1px solid black'>";
    echo "<tr><th>Labor</th>";

    if( $hourly_rate ) {
      echo "<td class='currency'>\$",sprintf("%0.2f",$labor_charge),"</td><td> = \$",sprintf("%0.2f",$hourly_rate)," * ",sprintf("%0.2f",$total_hours)," hours</td>";
    } else {
      echo "<td class='currency'></td><td>",sprintf("%0.2f",$total_hours)," hours</td>";
    }
    echo "</tr>\n";

    getWorkOrderMaterialsCharge($editing["WORK_ORDER_ID"],null,$materials_charge);
    echo "<tr><th>Materials</th><td class='currency'>\$",sprintf("%0.2f",$materials_charge),"</td><td></td></tr>\n";

    echo "<tr><th>Total</th><td class='currency'>\$",sprintf("%0.2f",$labor_charge + $materials_charge),"</td><td></td></tr>\n";
    echo "</table></div></div>\n";
  }

  if( isAdmin() ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='assigned_to'>Assigned To</label></div><div class='col'>";
    echo "<select name='assigned_to'>";
    echo "<option value=''>Choose</option>";
    $users = getUsers();
    foreach( $users as $u ) {
      if( !$u->is_shop_worker ) continue;
      $selected = $editing && $editing["ASSIGNED_TO"] == $u->user_id ? "selected" : "";
      echo "<option value='",htmlescape($u->user_id),"' $selected>",htmlescape($u->lastFirst()),"</option>\n";
    }
    echo "</select>";
    echo "</div></div>\n";
  }
  else if( isShopWorker() && $editing && $editing["ASSIGNED_TO"] ) {
    echo "<div class='$rowclass'><div class='$col1'><label>Assigned To</label></div><div class='col'>";
    $worker = new User;
    $worker->loadFromUserID($editing["ASSIGNED_TO"]);
    echo htmlescape($worker->lastFirst());
    echo "</div></div>\n";
  }

  if( isAdmin() || isShopWorker() ) {

    if( !$is_timecode ) {
      if( !$is_quote ) {
        echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='quote'>Quote</label></div><div class='col'><input type='text' name='quote' id='quote' size='10' maxlength='60' aria-label='quote' value='",htmlescape($editing && $editing["QUOTE"] !== null ? $editing["QUOTE"] : ""),"'/></div></div>\n";
      }

      echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='date_queued'>$Work Queued</label></div><div class='col'><input type='date' name='date_queued' id='date_queued' aria-label='date queued' value='",htmlescape($editing && $editing["QUEUED"] !== null ? $editing["QUEUED"] : ""),"'/> <button class='btn btn-secondary btn-sm clear-date-btn noprint' type='button' data-date-field='date_queued'>Clear</button></div></div>\n";

      echo "<div class='$rowclass'><div class='$col1'><label for='work_completed'>$Work Completed</label></div><div class='col'>";
      $checked = $editing && $editing["COMPLETED"] ? "checked" : "";
      echo "<input name='work_completed' id='work_completed' type='radio' value='1' $checked onchange='workCompletedChanged()'/> Yes ";
      $checked = $editing && $editing["COMPLETED"] ? "" : "checked";
      echo "<input name='work_completed' id='work_not_completed' type='radio' value='0' $checked onchange='workCompletedChanged()'/> No ";
      echo "</div></div>\n";

      echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='date_completed'>Date Completed</label></div><div class='col'><input type='date' name='date_completed' id='date_completed' aria-label='date completed' value='",htmlescape($editing && $editing["COMPLETED"] !== null ? $editing["COMPLETED"] : ""),"' onchange='dateCompletedChanged()'/> <button class='btn btn-secondary btn-sm clear-date-btn noprint' type='button' data-date-field='date_completed'>Clear</button></div></div>\n";
      ?><script>
        function workCompletedChanged() {
          var date_completed = document.getElementById('date_completed');
          var completed = document.getElementById('work_completed');
	  if( !completed ) return;
          if( completed.checked ) {
            if( !date_completed.value ) {
              date_completed.value = "<?php echo date("Y-m-d") ?>";
            }
          } else {
            date_completed.value = "";
          }
        }
        function dateCompletedChanged() {
          var date_completed = document.getElementById('date_completed');
          var completed = document.getElementById('work_completed');
          var not_completed = document.getElementById('work_not_completed');
	  if( !completed ) return;
          if( date_completed.value ) {
            completed.checked = true;
          } else {
            not_completed.checked = true;
	  }
        }
      </script><?php
    }

    $again = "";
    if( $editing && $editing["COMPLETION_EMAIL_SENT"] ) $again = "Again";
    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'>Completion Email</div><div class='col'><button type='button' class='btn btn-secondary btn-sm noprint' onclick='sendCompletionEmail()'>Send $again</button> <span id='email_sent_status'>";
    if( $editing && $editing["COMPLETION_EMAIL_SENT"] ) {
      echo "<span class='check-nonempty'>(sent on ",date("Y-m-d",strtotime($editing["COMPLETION_EMAIL_SENT"])),")</span>";
    } else {
      echo "<button type='button' class='btn btn-secondary btn-sm noprint' onclick='sendCompletionEmail(true)'>Sent Externally</button>";
    }
    echo "</span></div></div>\n";

    ?><script>
    function sendCompletionEmail(already_sent=false) {
      var url = "?s=send_completion_email&id=" + encodeURIComponent("<?php echo $editing ? $editing["WORK_ORDER_ID"] : "" ?>");
      if( already_sent ) url += "&already_sent=1";
      $.ajax({ url: url, success: function(data) {
        $('#email_sent_status').text(data).addClass('highlighted');
	setTimeout(function() {
	  $('#email_sent_status').removeClass('highlighted');
	},5000);
      }});
    }
    </script><?php

    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='date_closed'>$Order Closed</label></div><div class='col'><input type='date' name='date_closed' id='date_closed' aria-label='date closed' value='",htmlescape($editing && $editing["CLOSED"] !== null ? $editing["CLOSED"] : ""),"'/> <button class='btn btn-secondary btn-sm clear-date-btn noprint' type='button' data-date-field='date_closed'>Clear</button></div></div>\n";

    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='date_canceled'>$Order Canceled</label></div><div class='col'><input type='date' name='date_canceled' id='date_canceled' aria-label='date canceled' value='",htmlescape($editing && $editing["CANCELED"] !== null ? $editing["CANCELED"] : ""),"'/> <button class='btn btn-secondary btn-sm clear-date-btn noprint' type='button' data-date-field='date_canceled'>Clear</button></div></div>\n";

    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'><label for='date_picked_up'>Picked Up</label></div><div class='col'><input type='date' name='date_picked_up' id='date_picked_up' aria-label='date picked up' value='",htmlescape($editing && $editing["PICKED_UP_DATE"] !== null ? date("Y-m-d",strtotime($editing["PICKED_UP_DATE"])) : ""),"'/> <button class='btn btn-secondary btn-sm clear-date-btn noprint' type='button' data-date-field='date_picked_up'>Clear</button></div></div>\n";

    echo "<div class='$rowclass print-if-nonempty'><div class='$col1'>Picked Up By</div><div class='col'>";
    echo "<select name='picked_up_by'><option value=''></option>\n";
    $users = getUsers();
    foreach( $users as $u ) {
      $selected = $editing && $editing["PICKED_UP_BY"] == $u->user_id ? "selected" : "";
      echo "<option value='",htmlescape($u->user_id),"' $selected>",htmlescape($u->lastFirst()),"</option>\n";
    }
    echo "</select>\n";
    echo "</div></div>\n";

    if( $is_timecode ) {
      echo "<input type='hidden' name='is_timecode' value='1'/>\n";
    }

  } else if( $editing ) {
    if( $editing["QUOTE"] !== null ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='quote'>Quote</label></div><div class='col'>",htmlescape($editing["QUOTE"]),"</div></div>\n";
    }
    if( $editing["QUEUED"] ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='date_queued'>$Work Queued</label></div><div class='col'>",htmlescape(displayDate($editing["QUEUED"])),"</div></div>\n";
    }
    if( isShopWorker() ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='work_completed'>$Work Completed</label></div><div class='col'>";
      $checked = $editing["COMPLETED"] ? "checked" : "";
      echo "<input name='work_completed' id='work_completed' type='radio' value='1' $checked/> Yes ";
      $checked = $editing["COMPLETED"] ? "" : "checked";
      echo "<input name='work_completed' type='radio' value='0' $checked/> No ";
      echo "</div></div>\n";
    }
    if( $editing["COMPLETED"] ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='date_completed'>Date Completed</label></div><div class='col'>",htmlescape(displayDate($editing["COMPLETED"])),"</div></div>\n";
    }
    if( $editing["CLOSED"] ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='date_closed'>$Order Closed</label></div><div class='col'>",htmlescape(displayDate($editing["CLOSED"])),"</div></div>\n";
    }
    if( $editing["CANCELED"] ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='date_canceled'>$Order Canceled</label></div><div class='col'>",htmlescape(displayDate($editing["CANCELED"])),"</div></div>\n";
    }
    if( $editing["PICKED_UP_DATE"] ) {
      $picked_up_by = new User;
      $picked_up_by->loadFromUserID($editing["PICKED_UP_BY"]);

      echo "<div class='$rowclass'><div class='$col1'><label for='date_picked_up'>Picked Up</label></div><div class='col'>by ",htmlescape($picked_up_by->displayName())," on ",htmlescape(displayDateTime($editing["PICKED_UP_DATE"])),"</div></div>\n";
    }
  }

  if( HEALTH_HAZARD_QUESTION && !$is_stock_order && !$is_timecode && !$is_quote ) {
    echo "<p>I certify that none of the equipment to be serviced under this work order constitutes a health hazard due to chemical, biological, radiological or other contamination.<br>\n";
    $checked = $editing && $editing["HEALTH_HAZARD"] == "Y" ? "checked" : "";
    echo "<label><input type='radio' name='health_hazard' value='Y' $checked {$shopworker_editing} {$inprog_disabled}/> Yes</label>\n";
    $checked = $editing && $editing["HEALTH_HAZARD"] == "N" ? "checked" : "";
    echo "<label><input type='radio' name='health_hazard' value='N' $checked {$shopworker_editing} {$inprog_disabled}/> No</label>\n";
    echo "</p>\n";
  }

  if( isset($_REQUEST["self_service"]) && $_REQUEST["self_service"] ) {
    echo "<input type='hidden' value='1' name='self_service'/>\n";
    echo "<p class='alert alert-info'>This is a self-service order. If you are unsure what to do, please talk to the shop supervisor.</p>\n";
  }
  else if( $is_stock_order ) {
    echo "<p><label><input type='checkbox' name='convert_to_workorder' value='1'/> convert this stock order to a work order</label></p>\n";
  }

  echo "</div>\n"; # end container

  echo "<input type='submit' value='Submit' class='noprint' {$closed_disabled}/>\n";
  echo "</form>\n";

  if( $editing && isAdmin() ) {
    $sql = "SELECT *,concat(user.FIRST_NAME,' ',user.LAST_NAME) as NAME FROM timesheet JOIN user ON user.USER_ID = timesheet.USER_ID WHERE WORK_ORDER_ID = :WORK_ORDER_ID ORDER BY DATE DESC";
    $timesheet_stmt = $dbh->prepare($sql);
    $timesheet_stmt->bindValue(":WORK_ORDER_ID",$editing["WORK_ORDER_ID"]);
    $timesheet_stmt->execute();

    $header_done = false;
    $total_hours = 0;
    while( ($row=$timesheet_stmt->fetch()) ) {
      if( !$header_done ) {
        $header_done = true;
        echo "<hr>";
        echo "<table class='records clicksort'>\n";
        echo "<caption>Timesheet for ",htmlescape($editing["WORK_ORDER_NUM"]),"</caption>\n";
        echo "<thead><tr><th>Date</th><th>Who</th><th>Hours</th><th>Notes</th></tr></thead><tbody>\n";
      }
      echo "<tr class='record'>";
      echo "<td>",htmlescape($row["DATE"]),"</td>";
      echo "<td>",htmlescape($row["NAME"]),"</td>";
      echo "<td class='align-right'>",htmlescape($row["HOURS"]),"</td>";
      $total_hours += $row["HOURS"];
      echo "<td>",htmlescape($row["NOTES"]),"</td>";
      echo "</tr>\n";
    }
    if( $header_done ) {
      echo "</tbody><tfoot><tr><th>Total</th><th></th><th class='align-right'>",sprintf("%.2f",$total_hours),"</th><th></th></tr></tfoot>\n";
      echo "</table>\n";
    }
  }
  else if( $editing && isShopWorker() ) {
    $sql = "SELECT * FROM timesheet WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND USER_ID = :USER_ID ORDER BY DATE DESC";
    $timesheet_stmt = $dbh->prepare($sql);
    $timesheet_stmt->bindValue(":WORK_ORDER_ID",$editing["WORK_ORDER_ID"]);
    $timesheet_stmt->bindValue(":USER_ID",$web_user->user_id);
    $timesheet_stmt->execute();

    $header_done = false;
    $total_hours = 0;
    while( ($row=$timesheet_stmt->fetch()) ) {
      if( !$header_done ) {
        $header_done = true;
        echo "<hr>";
        echo "<table class='records clicksort'>\n";
        echo "<caption>Your timesheet for ",htmlescape($editing["WORK_ORDER_NUM"]),"</caption>\n";
        echo "<thead><tr><th>Date</th><th>Hours</th><th>Notes</th></tr></thead><tbody>\n";
      }
      echo "<tr class='record'>";
      echo "<td>",htmlescape($row["DATE"]),"</td>";
      echo "<td class='align-right'>",htmlescape($row["HOURS"]),"</td>";
      $total_hours += $row["HOURS"];
      echo "<td>",htmlescape($row["NOTES"]),"</td>";
      echo "</tr>\n";
    }
    if( $header_done ) {
      echo "</tbody><tfoot><tr><th>Total</th><th class='align-right'>",sprintf("%.2f",$total_hours),"</th><th></th></tr></tfoot>\n";
      echo "</table>\n";
    }
  }

  if( $editing ) {
    showWorkOrderBills($editing["WORK_ORDER_ID"],"<hr>");
  }

  echo "<p class='noprint'>&nbsp;</p>\n";

  ?><script>
  function validateWorkOrderForm() {
    var missing = [];

    <?php if( !isAdmin() && !isShopWorker() ) { ?>
      var fund = $('#funding_source');
      if( fund.length && !fund.val() ) {
        missing.push("Fund");
      }
    <?php } ?>

    <?php if( (isAdmin() || isShopWorker()) && !$is_timecode ) { ?>
      if( $("input[name='contract']").length && !$("input[name='contract']:checked").length &&
          ($("input[name='reviewed']:checked").val() == "Y" || $("#date_closed").val()) ) {
        missing.push("shop contract");
      }
    <?php } ?>

    if( $('[name="health_hazard"]').length ) {
      var health_hazard = $('[name="health_hazard"]:checked').val();
      if( !health_hazard ) {
        missing.push("health hazard statement");
      }
    }

    $('.new_stock_row').each(function(index) {
      var shape_id = $(this).find('.stock_shape').val();
      if( !shape_id ) {
        var e;
	e = $(this).find('.stock_material');
	if( e.val() && e.val() != <?php echo OTHER_ITEM_ID ?> ) missing.push("stock shape");
      }
      if( shape_id ) {
        var e;
	e = $(this).find('.stock_thickness');
	if( !e.val() && !e.attr("disabled") ) missing.push("stock thickness");
	e = $(this).find('.stock_width');
	if( !e.val() && !e.attr("disabled") ) missing.push("stock width/diameter");
	e = $(this).find('.stock_height');
	if( !e.val() && !e.attr("disabled") ) missing.push("stock height");
	e = $(this).find('.stock_length');
	if( !e.val() && !e.attr("disabled") ) missing.push("stock length");
      }
    });
    if( missing.length ) {
      alert("The following information is missing: " + arrayToEnglishList(missing) + ".");
      return false;
    }
    return true;
  }

  var work_order_submitting = false;
  function workOrderSubmitting() {
    work_order_submitting = true;
  }

  var work_order_changed = false;
  function workOrderChanged() {
    work_order_changed = true;
    <?php if( ENABLE_STOCK ) echo "updateStockCosts();"; ?>
    updatePrintIfNonEmpty();
  }

  window.onload = function() {
    $('#work_order_form').submit(function() {workOrderSubmitting();});
    $('#work_order_form').find('input,select,textarea').change(function() {workOrderChanged();});

    window.addEventListener("beforeunload", function (e) {
      if (work_order_submitting || !work_order_changed) {
        return undefined;
      }

      var confirmationMessage = 'If you leave before submitting the form, your changes will be lost.';

      (e || window.event).returnValue = confirmationMessage; //Gecko + IE
      return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
    });
  };

  updatePrintIfNonEmpty();
  </script><?php
}

function getWorkOrderUploadDir($work_order_id) {
  $ch = sprintf("%02d",(int)$work_order_id % 100);
  return "uploads/$ch/{$work_order_id}";
}

function getWorkOrderFilePath($work_order_id,$fname) {
  return getWorkOrderUploadDir($work_order_id) . "/" . $fname;
}

function getWorkOrderFileUrl($work_order_id,$fname) {
  return getWorkOrderFilePath($work_order_id,$fname);
}

function deleteWorkOrderFile($web_user,$work_order_id,$file_id,&$error_msg) {
  $dbh = connectDB();
  $sql = "SELECT FILENAME FROM work_order_file WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND WORK_ORDER_FILE_ID = :WORK_ORDER_FILE_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$work_order_id);
  $stmt->bindValue(":WORK_ORDER_FILE_ID",$file_id);
  $stmt->execute();
  $row = $stmt->fetch();
  if( !$row ) {
    $error_msg = "Failed to delete attachment from worker order id {$work_order_id}, because no file with id {$file_id} was found.";
    return false;
  }
  $fname = getWorkOrderFilePath($work_order_id,$row["FILENAME"]);
  if( unlink($fname) !== true ) {
    $error_msg = "Failed to delete file {$fname}.";
    # continue anyway
  }

  $delete_sql = "DELETE FROM work_order_file WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND WORK_ORDER_FILE_ID = :WORK_ORDER_FILE_ID";
  $delete_stmt = $dbh->prepare($delete_sql);
  $delete_stmt->bindValue(":WORK_ORDER_ID",$work_order_id);
  $delete_stmt->bindValue(":WORK_ORDER_FILE_ID",$file_id);
  $before_edit = getWorkOrderFile($file_id);
  $delete_stmt->execute();

  auditlogModifyWorkOrderFile($web_user,$before_edit,null);

  return true;
}

function getWorkOrder($work_order_id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM work_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$work_order_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getStockOrder($stock_order_id) {
  $dbh = connectDB();
  # audit log requires WORK_ORDER_NUM, so include that as well
  $sql = "SELECT stock_order.*,work_order.WORK_ORDER_NUM FROM stock_order LEFT JOIN work_order ON work_order.WORK_ORDER_ID = stock_order.WORK_ORDER_ID WHERE STOCK_ORDER_ID = :STOCK_ORDER_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":STOCK_ORDER_ID",$stock_order_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getWorkOrderFile($id) {
  $dbh = connectDB();
  # audit log requires WORK_ORDER_NUM, so include that as well
  $sql = "SELECT work_order_file.*,work_order.WORK_ORDER_NUM FROM work_order_file LEFT JOIN work_order ON work_order.WORK_ORDER_ID = work_order_file.WORK_ORDER_ID WHERE WORK_ORDER_FILE_ID = :WORK_ORDER_FILE_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_FILE_ID",$id);
  $stmt->execute();
  return $stmt->fetch();
}

function saveWorkOrder($web_user,&$show) {
  $dbh = connectDB();
  $id = isset($_REQUEST["id"]) ? $_REQUEST["id"] : "";
  $is_stock_order = $id == "new_stock" ? ENABLE_STOCK : false;
  $is_quote = $id == "new_quote";
  if( $id == "new" || $id == "new_stock" || $id == "new_quote" ) $id = "";

  $contracts = getContracts();
  $funding_source_id = isset($_REQUEST["funding_source"]) ? $_REQUEST["funding_source"] : 0;
  # load this here in case it is needed, so it doesn't have to happen inside the transaction
  $funding_source = getFundingSourceAndAuthRecord($funding_source_id);

  $admin_sql = "";
  if( isAdmin() || isShopWorker() ) {
    $admin_sql  = "QUOTE = :QUOTE,";
    if( isset($_REQUEST["reviewed"]) && $_REQUEST["reviewed"] == "Y" ) {
      $admin_sql .= "REVIEWED = IF(REVIEWED IS NULL,now(),REVIEWED),";
    } else {
      $admin_sql .= "REVIEWED = NULL,";
    }
    $admin_sql .= "QUEUED = :QUEUED,";
    $admin_sql .= "COMPLETED = :COMPLETED,";
    $admin_sql .= "CLOSED = :CLOSED,";
    $admin_sql .= "CANCELED = :CANCELED,";
    $admin_sql .= "PICKED_UP_DATE = :PICKED_UP_DATE,";
    $admin_sql .= "PICKED_UP_BY = :PICKED_UP_BY,";
    $admin_sql .= "STATUS = :STATUS,";
    $admin_sql .= "ADMIN_NOTES = :ADMIN_NOTES,";
    if( isset($_REQUEST["new_owner"]) && $_REQUEST["new_owner"] ) {
      $admin_sql .= "ORDERED_BY = :ORDERED_BY,";
    }
  }
  if( isAdmin() ) {
    $admin_sql .= "ASSIGNED_TO = :ASSIGNED_TO,";
  }

  $shopworker_editing = false;
  $inprog_disabled = false;

  if( $id ) {
    $editing = getWorkOrder($_REQUEST["id"]);
    if( $editing ) {
      $is_stock_order = $editing["STOCK_ORDER"];
      $is_quote = $editing["IS_QUOTE"];
    }

    $inprog_disabled = false;

    if( $editing && !isAdmin() && !isShopWorker() ) {
      if( !isWorkOrderEditor($web_user,$editing) ) {

        # As of 2021-12-23, by request of Doug Dummer, shop workers have full
	# admin access to work orders, so we no longer get here for shop workers.

        if( isShopWorker() ) {
	  shopWorkerSaveWorkOrder($web_user,$editing["WORK_ORDER_ID"]);
	  return;
	} else {
          echo "<div class='alert alert-danger'>Work order ",htmlescape($wo_desc)," does not belong to you.</div>\n";
          return;
	}
      }
      else if( $editing["CLOSED"] || $editing["CANCELED"] ) {
        echo "<div class='alert alert-danger'>This work order is closed for edits.  If changes need to be made, please contact <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      } else if( $editing["QUEUED"] ) {
        $inprog_disabled = true;
      }
    }

    if( $inprog_disabled ) {
      $sql = "
      UPDATE work_order SET
      WORK_ORDER_NUM = :WORK_ORDER_NUM,
      STOCK_ORDER = :STOCK_ORDER,
      IS_QUOTE = :IS_QUOTE,
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID
      WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
    } else {
      $sql = "
      UPDATE work_order SET
      WORK_ORDER_NUM = :WORK_ORDER_NUM,
      STOCK_ORDER = :STOCK_ORDER,
      IS_QUOTE = :IS_QUOTE,
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
      CONTRACT_ID = :CONTRACT_ID,
      CONTRACT_HOURLY_RATE = :CONTRACT_HOURLY_RATE,
      {$admin_sql}
      INVENTORY_NUM = :INVENTORY_NUM,
      HEALTH_HAZARD = :HEALTH_HAZARD,
      DESCRIPTION = :DESCRIPTION
      WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
    }
  }
  else {
    $editing = null;
    $sql = "
      INSERT INTO work_order SET
      WORK_ORDER_NUM = :WORK_ORDER_NUM,
      STOCK_ORDER = :STOCK_ORDER,
      IS_QUOTE = :IS_QUOTE,
      ORDERED_BY = :ORDERED_BY,
      CREATED = now(),
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
      CONTRACT_ID = :CONTRACT_ID,
      CONTRACT_HOURLY_RATE = :CONTRACT_HOURLY_RATE,
      {$admin_sql}
      INVENTORY_NUM = :INVENTORY_NUM,
      HEALTH_HAZARD = :HEALTH_HAZARD,
      DESCRIPTION = :DESCRIPTION";
  }
  $stmt = $dbh->prepare($sql);

  # Get a write-lock on the work_order table, so work order number uniqueness checks are not
  # defeated by race conditions.
  $dbh->beginTransaction();
  $dbh->exec("LOCK TABLES work_order WRITE");

  if( $id ) {
    $stmt->bindValue(":WORK_ORDER_ID",$id);
  } else {
    $stmt->bindValue(":ORDERED_BY",$web_user->user_id);
  }

  if( isset($_REQUEST["convert_to_workorder"]) && $_REQUEST["convert_to_workorder"] ) {
    $is_stock_order = false;
    $is_quote = false;
    $wo_type_char = "";
    unset($_REQUEST["work_order_num"]);
    $wo_num = getUniqueWorkOrderNum(null,$wo_type_char);
  }
  else {
    $wo_type_char = $is_stock_order ? "S" : "";
    if( $is_quote ) $wo_type_char = "Q" . $wo_type_char;
    $wo_num = getUniqueWorkOrderNum($id,$wo_type_char);
  }
  $stmt->bindValue(":WORK_ORDER_NUM",$wo_num);

  if( $wo_num && ENABLE_STOCK ) {
    # allow admin to change whether it is a stock order by changing the work order num
    if( preg_match('/^[0-9]{2}-' . SHOP_WORK_ORDER_CHAR . 'S[0-9]{3}$/',$wo_num) ) {
      $is_stock_order = true;
    }
    if( preg_match('/^[0-9]{2}-' . SHOP_WORK_ORDER_CHAR . '[Q]{0,1}[0-9]{3}$/',$wo_num) ) {
      $is_stock_order = false;
    }
  }
  if( $wo_num ) {
    # allow admin to change whether it is a quote by changing the work order num
    if( preg_match('/^[0-9]{2}-' . SHOP_WORK_ORDER_CHAR . 'Q[0-9]{3}$/',$wo_num) ) {
      $is_quote = true;
    }
    if( preg_match('/^[0-9]{2}-' . SHOP_WORK_ORDER_CHAR . '[S]{0,1}[0-9]{3}$/',$wo_num) ) {
      $is_quote = false;
    }
  }
  $stmt->bindValue(":STOCK_ORDER",$is_stock_order ? 1 : 0);
  $stmt->bindValue(":IS_QUOTE",$is_quote ? 1 : 0);
  $stmt->bindValue(":GROUP_ID",isset($_REQUEST["group_id"]) ? $_REQUEST["group_id"] : 0);
  if( !canSeeFundGroup($funding_source) && (!$editing || $editing["FUNDING_SOURCE_ID"] != $funding_source_id) ) {
    echo "<div class='alert alert-danger'>You are not configured to have access to the selected funding source.  Please contact the shop administrator for help.</div>\n";
    $dbh->rollBack();
    $dbh->exec("UNLOCK TABLES");
    return;
  }
  $stmt->bindValue(":FUNDING_SOURCE_ID",$funding_source_id);
  if( !$inprog_disabled ) {
    $contract_id = isset($_REQUEST["contract"]) ? $_REQUEST["contract"] : (isset($editing["CONTRACT_ID"]) ? $editing["CONTRACT_ID"] : null);
    if( !$contract_id ) {
      $contract_id = DEFAULT_SHOP_CONTRACT($funding_source);
    }
    $stmt->bindValue(":CONTRACT_ID",$contract_id);
    $contract_rate = null;
    if( $contract_id && isset($contracts[$contract_id]) ) {
      $contract = $contracts[$contract_id];
      if( !$editing || !$editing["CONTRACT_HOURLY_RATE"] || $contract["CONTRACT_ID"] !== $editing["CONTRACT_ID"] ) {
        $contract_rate = $contract["CONTRACT_HOURLY_RATE"];
      } else if( $editing ) {
        $contract_rate = $editing["CONTRACT_HOURLY_RATE"];
      }
    }
    $stmt->bindValue(":CONTRACT_HOURLY_RATE",$contract_rate);
    if( isAdmin() || isShopWorker() ) {
      $stmt->bindValue(":QUOTE", (!isset($_REQUEST["quote"]) || $_REQUEST["quote"] == "") ? null : $_REQUEST["quote"]);
      $stmt->bindValue(":QUEUED", (!isset($_REQUEST["date_queued"]) || $_REQUEST["date_queued"] == "") ? null : $_REQUEST["date_queued"]);
      $stmt->bindValue(":COMPLETED",(!isset($_REQUEST["date_completed"]) || $_REQUEST["date_completed"] == "") ? null : $_REQUEST["date_completed"]);
      $stmt->bindValue(":CLOSED",$_REQUEST["date_closed"] == "" ? null : $_REQUEST["date_closed"]);
      $stmt->bindValue(":CANCELED",$_REQUEST["date_canceled"] == "" ? null : $_REQUEST["date_canceled"]);
      $date_picked_up = $_REQUEST["date_picked_up"] == "" ? null : $_REQUEST["date_picked_up"];
      if( $editing && $editing["PICKED_UP_DATE"] && $date_picked_up && date("Y-m-d",strtotime($date_picked_up)) == date("Y-m-d",strtotime($editing["PICKED_UP_DATE"])) ) {
        # preserve the time
        $date_picked_up = $editing["PICKED_UP_DATE"];
      } else if( $date_picked_up && date("Y-m-d",strtotime($date_picked_up)) == date("Y-m-d") ) {
        # set the time to now
	$date_picked_up = date("Y-m-d H:i");
      }
      $stmt->bindValue(":PICKED_UP_DATE",$date_picked_up);
      $stmt->bindValue(":PICKED_UP_BY",$_REQUEST["picked_up_by"] ? $_REQUEST["picked_up_by"] : null);
      $status = '';
      if( isset($_REQUEST["is_timecode"]) ) {
        $status = WORK_ORDER_STATUS_TIMECODE;
      }
      if( $_REQUEST["date_closed"] ) {
        $status = WORK_ORDER_STATUS_CLOSED;
      }
      if( $_REQUEST["date_canceled"] ) {
        $status = WORK_ORDER_STATUS_CANCELED;
      }
      $stmt->bindValue(":STATUS",$status);
      $stmt->bindValue(":ADMIN_NOTES",$_REQUEST["admin_notes"] == "" ? null : $_REQUEST["admin_notes"]);
      if( isset($_REQUEST["new_owner"]) && $_REQUEST["new_owner"] ) {
        $stmt->bindValue(":ORDERED_BY",$_REQUEST["new_owner"]);
      }
    }
    if( isAdmin() ) {
      $stmt->bindValue(":ASSIGNED_TO",($_REQUEST["assigned_to"] ?? null) ? $_REQUEST["assigned_to"] : null);
    }
    $stmt->bindValue(":INVENTORY_NUM",isset($_REQUEST["inventory_num"]) ? $_REQUEST["inventory_num"] : "");
    $stmt->bindValue(":HEALTH_HAZARD",isset($_REQUEST["health_hazard"]) ? $_REQUEST["health_hazard"] : ($editing ? $editing["HEALTH_HAZARD"] : ""));
    $stmt->bindValue(":DESCRIPTION",$_REQUEST["description"]);
  }

  $stmt->execute();
  $wo_created = false;
  if( !$id ) {
    $wo_created = true;
    $id = $dbh->lastInsertId();
    $_REQUEST["id"] = $id;
  }

  if( isset($_REQUEST["self_service"]) && $_REQUEST["self_service"] ) {
    if( !$editing || !$editing["COMPLETED"] ) {
      $sql = "UPDATE work_order SET COMPLETED = now() WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
      $stmt = $dbh->prepare($sql);
      $stmt->bindValue(":WORK_ORDER_ID",$id);
      $stmt->execute();
    }
  }

  $after_edit = getWorkOrder($id);

  $dbh->commit();
  $dbh->exec("UNLOCK TABLES");

  auditlogModifyWorkOrder($web_user,$editing,$after_edit);

  if( !$inprog_disabled ) {
    if( ENABLE_STOCK ) {
      saveStockOrder($web_user,$id);
    }
    saveWorkOrderAttachments($web_user,$id);
  }

  echo "<div class='alert alert-success noprint'>Saved</div>\n";

  $order_user = loadUserFromUserID($after_edit['ORDERED_BY']);
  handleDefaultFundingSourceUpdateForm($order_user);

  if( $wo_created && NOTIFY_SHOP_ADMIN_OF_NEW_WORK_ORDERS ) {
    emailWorkOrder($id,SHOP_ADMIN_EMAIL,true);
  }
  if( $wo_created ) {
    emailWorkOrder($id);
  }
}

function emailWorkOrder($wo_id,$email=null,$to_admin=false) {
  global $self_full_url;

  $dbh = connectDB();
  $sql = "
    SELECT
      work_order.*,
      CONCAT(user.FIRST_NAME,' ',user.LAST_NAME) as ORDERED_BY_NAME,
      user.EMAIL,
      funding_source.FUNDING_DESCRIPTION,
      funding_source.PI_NAME,
      funding_source.PI_EMAIL,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_FUND
    FROM
      work_order
    JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order.FUNDING_SOURCE_ID
    WHERE
      WORK_ORDER_ID = :WORK_ORDER_ID
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $stmt->execute();
  $row = $stmt->fetch();
  if( !$row ) return;

  if( $email === null ) {
    $recipients = array();
    addNonEmptyUniqueItemToList($row["EMAIL"],$recipients);
    addNonEmptyUniqueItemToList($row["PI_EMAIL"],$recipients);
    $email = implode(",",$recipients);
  }
  if( !$email ) return;

  $ordered_by_email = $row["EMAIL"];

  $work = $row["STOCK_ORDER"] ? "stock" : "work";
  $Work = $row["STOCK_ORDER"] ? "Stock" : "Work";
  $subject = SHOP_NAME . " $work order " . $row["WORK_ORDER_NUM"] . " submitted by " . $row["ORDERED_BY_NAME"];

  $msg = getStandardHTMLEmailHead();
  $msg[] = "<body>";

  $url = $self_full_url . "?s=work_order&id=" . $wo_id;
  $msg[] = "<p>" . htmlescape(SHOP_NAME) . " $work order <a href='" . htmlescape($url) . "'>" . $row["WORK_ORDER_NUM"] . "</a>:</p>";

  $msg[] = "<h2>Description</h2>";
  $msg[] = "<p>" . htmlescape($row["DESCRIPTION"]) . "</p>";

  $funding_string = getFundingSourceLongStringHTML($row);
  $msg[] = "<h2>Funding Source</h2>";
  $msg[] = "<p>" . $funding_string . "</p>";

  $perm_sql = "";
  if( !$to_admin ) {
    $perm_sql .= "AND USER_READ_PERM = 'Y'";
  }
  $sql = "
    SELECT
      *
    FROM
      work_order_file
    WHERE
      WORK_ORDER_ID = :WORK_ORDER_ID
      {$perm_sql}
    ORDER BY
      WORK_ORDER_FILE_ID
  ";
  $attachment_stmt = $dbh->prepare($sql);
  $attachment_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $attachment_stmt->execute();

  $printed_header = false;
  while( ($attachment=$attachment_stmt->fetch()) ) {
    if( !$printed_header ) {
      $printed_header = true;
      $msg[] = "<h2>Attachments</h2>";
    }
    $url = $self_full_url . getWorkOrderFileUrl($attachment['WORK_ORDER_ID'],$attachment['FILENAME']);
    $msg[] = "<a href='" . htmlescape($url) . "'>" . htmlescape($attachment["FILENAME"]) . "</a><br>";
  }

  if( ENABLE_STOCK ) {
    $sql = "
      SELECT
        stock_order.*,
        stock_material.NAME as MATERIAL_NAME,
        stock_shape.NAME as SHAPE_NAME
      FROM
        stock_order
      JOIN
        stock_material
      ON
        stock_material.MATERIAL_ID = stock_order.MATERIAL_ID
      LEFT JOIN
        stock_shape
      ON
        stock_shape.SHAPE_ID = stock_order.SHAPE_ID
      WHERE
        stock_order.WORK_ORDER_ID = :WORK_ORDER_ID
      ORDER BY
        STOCK_ORDER_ID
    ";
    $stock_stmt = $dbh->prepare($sql);
    $stock_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
    $stock_stmt->execute();

    $printed_header = false;
    while( ($stock=$stock_stmt->fetch()) ) {
      if( !$printed_header ) {
        $printed_header = true;
        $msg[] = "<h2>Stock</h2>";
        $msg[] = "<table class='records'><thead><tr><th>Material</th><th>Shape</th><th>Thickness</th><th>Height</th><th>Width/Dia</th><th>Length</th><th>Units</th></tr></thead><tbody>";
      }
      $msg[] = "<tr class='record'>";
      if( $stock["MATERIAL_ID"] == OTHER_MATERIAL_ID || $stock["MATERIAL_ID"] == OTHER_ITEM_ID ) {
        $msg[] = "<td>" . htmlescape($stock["OTHER_MATERIAL"]) . "</td>";
      } else {
        $msg[] = "<td>" . htmlescape($stock["MATERIAL_NAME"]) . "</td>";
      }
      $msg[] = "<td>" . htmlescape($stock["SHAPE_NAME"]) . "</td>";
      $msg[] = "<td>" . htmlescape($stock["THICKNESS"]) . "</td>";
      $msg[] = "<td>" . htmlescape($stock["HEIGHT"]) . "</td>";
      $msg[] = "<td>" . htmlescape($stock["WIDTH"]) . "</td>";
      $msg[] = "<td>" . htmlescape($stock["LENGTH"]) . "</td>";
      $msg[] = "<td>" . htmlescape($stock["QUANTITY"]) . "</td>";
      $msg[] = "</tr>";
    }
    if( $printed_header ) {
      $msg[] = "</tbody></table>";
    }
  }

  $msg[] = "</body></html>";

  $msg = implode("\r\n",$msg);

  $headers = array();
  $headers[] = "From: " . SHOP_NAME . " <" . SHOP_FROM_EMAIL . ">";
  $headers[] = "Reply-To: " . $ordered_by_email;
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-type: text/html; charset=iso-8859-1';
  $headers = implode("\r\n",$headers);

  mail($email,$subject,$msg,$headers,"-f " . SHOP_FROM_EMAIL);
}

function saveStockOrder($web_user,$wo_id) {
  $dbh = connectDB();
  $stock_sql = "INSERT INTO stock_order SET WORK_ORDER_ID = :WORK_ORDER_ID, CREATED = NOW(), CREATED_BY = :CREATED_BY, MATERIAL_ID = :MATERIAL_ID, OTHER_MATERIAL = :OTHER_MATERIAL, SHAPE_ID = :SHAPE_ID, QUANTITY = :QUANTITY, THICKNESS = :THICKNESS, HEIGHT = :HEIGHT, WIDTH = :WIDTH, LENGTH = :LENGTH, COST_PER_FOOT = :COST_PER_FOOT, UNIT_COST = :UNIT_COST, TOTAL_COST = :UNIT_COST * :QUANTITY, COST_METHOD = :COST_METHOD, HOURS = :HOURS, PART_NAME = :PART_NAME";
  $stock_stmt = $dbh->prepare($stock_sql);
  $stock_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $stock_stmt->bindValue(":CREATED_BY",$web_user->user_id);

  foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^stock_material_([0-9]+)$/',$key,$match) ) continue;
    $sid = $match[1];

    if( $_REQUEST["stock_material_$sid"] == '' ) continue;
    if( $_REQUEST["stock_material_$sid"] == OTHER_MATERIAL_ID && $_REQUEST["stock_other_material_$sid"] == '' ) continue;
    if( $_REQUEST["stock_material_$sid"] == OTHER_ITEM_ID && $_REQUEST["stock_other_material_$sid"] == '' ) continue;

    $stock_stmt->bindValue(":MATERIAL_ID",$_REQUEST["stock_material_$sid"]);
    $stock_stmt->bindValue(":OTHER_MATERIAL",$_REQUEST["stock_other_material_$sid"]);
    $stock_stmt->bindValue(":SHAPE_ID",isset($_REQUEST["stock_shape_$sid"]) && $_REQUEST["stock_shape_$sid"] ? $_REQUEST["stock_shape_$sid"] : 0);
    $quantity = $_REQUEST["stock_quantity_$sid"];
    $stock_stmt->bindValue(":QUANTITY",$quantity);
    $stock_stmt->bindValue(":THICKNESS",isset($_REQUEST["stock_thickness_$sid"]) ? $_REQUEST["stock_thickness_$sid"] : "");
    $stock_stmt->bindValue(":HEIGHT",isset($_REQUEST["stock_height_$sid"]) ? $_REQUEST["stock_height_$sid"] : "");
    $stock_stmt->bindValue(":WIDTH",isset($_REQUEST["stock_width_$sid"]) ? $_REQUEST["stock_width_$sid"] : "");
    $stock_stmt->bindValue(":LENGTH",isset($_REQUEST["stock_length_$sid"]) ? $_REQUEST["stock_length_$sid"] : "");
    $stock_stmt->bindValue(":PART_NAME",isset($_REQUEST["stock_part_$sid"]) ? $_REQUEST["stock_part_$sid"] : "");
    if( isAdmin() || isShopWorker() ) {
      $cost_per_foot = isset($_REQUEST["stock_cost_per_foot_$sid"]) ? $_REQUEST["stock_cost_per_foot_$sid"] : "";
      if( $cost_per_foot == "" ) $cost_per_foot = null;
      else $cost_per_foot = (float)$cost_per_foot;
      $stock_stmt->bindValue(":COST_PER_FOOT",$cost_per_foot);

      $unit_cost = $_REQUEST["stock_unit_cost_$sid"];
      if( $unit_cost == "" ) $unit_cost = null;
      else $unit_cost = (float)$unit_cost;
      $stock_stmt->bindValue(":UNIT_COST",$unit_cost);
      $stock_stmt->bindValue(":COST_METHOD",$unit_cost === null ? COST_METHOD_NONE : COST_METHOD_MANUAL);
      $stock_stmt->bindValue(":HOURS",isset($_REQUEST["stock_hours_$sid"]) && $_REQUEST["stock_hours_$sid"] != "" ? (float)$_REQUEST["stock_hours_$sid"] : null);
    } else {
      $stock_stmt->bindValue(":COST_PER_FOOT",null);
      $stock_stmt->bindValue(":UNIT_COST",null);
      $stock_stmt->bindValue(":COST_METHOD",COST_METHOD_NONE);
      $stock_stmt->bindValue(":HOURS",null);
    }
    $stock_stmt->execute();
    $stock_order_id = $dbh->lastInsertId();
    $after_edit = getStockOrder($stock_order_id);

    auditlogModifyStockOrder($web_user,null,$after_edit);
  }

  $stock_sql = "UPDATE stock_order SET COST_PER_FOOT = :COST_PER_FOOT, UNIT_COST = :UNIT_COST, TOTAL_COST = IF(CANCELED IS NULL AND :UNIT_COST IS NOT NULL,:UNIT_COST*QUANTITY,NULL), COST_METHOD = IF(:COST_CHANGED,:COST_METHOD,COST_METHOD), HOURS = :HOURS, PART_NAME = :PART_NAME WHERE STOCK_ORDER_ID = :STOCK_ORDER_ID AND WORK_ORDER_ID = :WORK_ORDER_ID";
  $stock_stmt = $dbh->prepare($stock_sql);
  $stock_stmt->bindValue(":WORK_ORDER_ID",$wo_id);

  if( isAdmin() || isShopWorker() ) foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^edit_stock_unit_cost_([0-9]+)$/',$key,$match) ) continue;
    $sid = $match[1];

    $stock_stmt->bindValue(":STOCK_ORDER_ID",$sid);

    $cost_per_foot = isset($_REQUEST["edit_stock_cost_per_foot_$sid"]) ? $_REQUEST["edit_stock_cost_per_foot_$sid"] : "";
    if( $cost_per_foot == "" ) $cost_per_foot = null;
    else $cost_per_foot = (float)$cost_per_foot;
    $stock_stmt->bindValue(":COST_PER_FOOT",$cost_per_foot);

    $unit_cost = $_REQUEST["edit_stock_unit_cost_$sid"];
    $orig_unit_cost = $_REQUEST["edit_stock_orig_unit_cost_$sid"];
    $cost_changed = $unit_cost != $orig_unit_cost ? 1 : 0;
    if( $unit_cost == "" ) $unit_cost = null;
    else $unit_cost = (float)$unit_cost;
    $stock_stmt->bindValue(":UNIT_COST",$unit_cost);
    $stock_stmt->bindValue(":COST_CHANGED",$cost_changed);
    $stock_stmt->bindValue(":COST_METHOD",$unit_cost === null ? COST_METHOD_NONE : COST_METHOD_MANUAL);
    $stock_stmt->bindValue(":HOURS",isset($_REQUEST["edit_stock_hours_$sid"]) && $_REQUEST["edit_stock_hours_$sid"] != "" ? (float)$_REQUEST["edit_stock_hours_$sid"] : null);
    $stock_stmt->bindValue(":PART_NAME",isset($_REQUEST["edit_stock_part_$sid"]) ? $_REQUEST["edit_stock_part_$sid"] : "");

    $before_edit = getStockOrder($sid);
    $stock_stmt->execute();
    $after_edit = getStockOrder($sid);

    auditlogModifyStockOrder($web_user,$before_edit,$after_edit);
  }

  $delete_stock_sql = "UPDATE stock_order SET CANCELED = now(), TOTAL_COST=NULL WHERE STOCK_ORDER_ID = :STOCK_ORDER_ID AND WORK_ORDER_ID = :WORK_ORDER_ID AND CANCELED IS NULL";
  $delete_stock_stmt = $dbh->prepare($delete_stock_sql);
  $delete_stock_stmt->bindValue(":WORK_ORDER_ID",$wo_id);

  $undelete_stock_sql = "UPDATE stock_order SET CANCELED = NULL, TOTAL_COST=UNIT_COST*QUANTITY WHERE STOCK_ORDER_ID = :STOCK_ORDER_ID AND WORK_ORDER_ID = :WORK_ORDER_ID AND CANCELED IS NOT NULL";
  $undelete_stock_stmt = $dbh->prepare($undelete_stock_sql);
  $undelete_stock_stmt->bindValue(":WORK_ORDER_ID",$wo_id);

  foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^orig_delete_stock_([0-9]+)$/',$key,$match) ) continue;
    $sid = $match[1];

    $value = (int)$value;
    $cb_val = isset($_REQUEST["delete_stock_$sid"]) ? 1 : 0;
    if( $value == $cb_val ) continue;

    $before_edit = getStockOrder($sid);
    if( $cb_val ) {
      $delete_stock_stmt->bindValue(":STOCK_ORDER_ID",$sid);
      $delete_stock_stmt->execute();
    } else {
      $undelete_stock_stmt->bindValue(":STOCK_ORDER_ID",$sid);
      $undelete_stock_stmt->execute();
    }
    $after_edit = getStockOrder($sid);
    auditlogModifyStockOrder($web_user,$before_edit,$after_edit);
  }

  if( (isAdmin() || isShopWorker()) && isset($_REQUEST["clear_deleted_stock"]) && (int)$_REQUEST["clear_deleted_stock"] ) {
    $clear_deleted_stock_sql = "DELETE FROM stock_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND CANCELED IS NOT NULL";
    $clear_stock_stmt = $dbh->prepare($clear_deleted_stock_sql);
    $clear_stock_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
    $clear_stock_stmt->execute();
  }

  calculateStockCosts($web_user,$wo_id);
}

function calculateStockCosts($web_user,$wo_id) {
  $dbh = connectDB();
  $sql = "
    SELECT
      stock_order.MATERIAL_ID,
      stock_order.SHAPE_ID,
      STOCK_ORDER_ID,
      THICKNESS,
      HEIGHT,
      WIDTH,
      LENGTH,
      VOLUME_FORMULA,
      DENSITY,
      COST_PER_POUND,
      stock_order.COST_PER_FOOT,
      mlc.COST_PER_FOOT as DEFAULT_COST_PER_FOOT
    FROM
      stock_order
    JOIN
      stock_material
    ON
      stock_material.MATERIAL_ID = stock_order.MATERIAL_ID
    JOIN
      stock_shape
    ON
      stock_shape.SHAPE_ID = stock_order.SHAPE_ID
    LEFT JOIN
      material_linear_cost mlc
    ON
      mlc.MATERIAL_ID = stock_order.MATERIAL_ID
      AND mlc.SHAPE_ID = stock_order.SHAPE_ID
    WHERE
      stock_order.WORK_ORDER_ID = :WORK_ORDER_ID
      AND stock_order.UNIT_COST IS NULL
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $stmt->execute();

  $sql = "UPDATE stock_order SET COST_PER_FOOT = :COST_PER_FOOT, UNIT_COST = :UNIT_COST, TOTAL_COST = UNIT_COST*QUANTITY, COST_METHOD = :COST_METHOD, WEIGHT = :WEIGHT WHERE STOCK_ORDER_ID = :STOCK_ORDER_ID AND WORK_ORDER_ID = :WORK_ORDER_ID";
  $update_stmt = $dbh->prepare($sql);
  $update_stmt->bindValue(":WORK_ORDER_ID",$wo_id);

  while( ($row=$stmt->fetch()) ) {
    $weight = null;
    $cost_per_foot = null;
    $cost_method = COST_METHOD_NONE;
    $has_missing_dimensions = null;
    $unit_cost = calcStockUnitCost($row,$weight,$cost_per_foot,$cost_method,$has_missing_dimensions);
    if( $unit_cost !== null ) {
      $update_stmt->bindValue(":STOCK_ORDER_ID",$row["STOCK_ORDER_ID"]);
      $update_stmt->bindValue(":COST_PER_FOOT",$cost_per_foot);
      $update_stmt->bindValue(":UNIT_COST",$unit_cost);
      $update_stmt->bindValue(":COST_METHOD",$cost_method);
      $update_stmt->bindValue(":WEIGHT",$weight);
      $before_edit = getStockOrder($row["STOCK_ORDER_ID"]);
      $update_stmt->execute();
      $after_edit = getStockOrder($row["STOCK_ORDER_ID"]);

      auditlogModifyStockOrder($web_user,$before_edit,$after_edit);
    }
  }
}

function calculateStockCostsAjax() {
  $dbh = connectDB();
  $sql = "
    SELECT
      VOLUME_FORMULA,
      DENSITY,
      COST_PER_POUND,
      mlc.COST_PER_FOOT as DEFAULT_COST_PER_FOOT
    FROM
      stock_material
    LEFT JOIN
      stock_shape
    ON
      stock_shape.SHAPE_ID = :SHAPE_ID
    LEFT JOIN
      material_linear_cost mlc
    ON
      mlc.MATERIAL_ID = :MATERIAL_ID
      AND mlc.SHAPE_ID = :SHAPE_ID
    WHERE
      stock_material.MATERIAL_ID = :MATERIAL_ID
  ";
  $stmt = $dbh->prepare($sql);

  $stock = array();
  $stock["THICKNESS"] = $_REQUEST["thickness"];
  $stock["HEIGHT"] = $_REQUEST["height"];
  $stock["WIDTH"] = $_REQUEST["width"];
  $stock["LENGTH"] = $_REQUEST["length"];
  $stock["COST_PER_FOOT"] = $_REQUEST["cost_per_foot"];
  $stock["MATERIAL_ID"] = $_REQUEST["material_id"] != "" ? $_REQUEST["material_id"] : null;
  $stock["SHAPE_ID"] = $_REQUEST["shape_id"] != "" ? $_REQUEST["shape_id"] : null;

  $stmt->bindValue(":MATERIAL_ID",$stock["MATERIAL_ID"]);
  $stmt->bindValue(":SHAPE_ID",$stock["SHAPE_ID"]);
  $stmt->execute();
  $row = $stmt->fetch();
  $stock["VOLUME_FORMULA"] = $row ? $row["VOLUME_FORMULA"] : null;
  $stock["DENSITY"] = $row ? $row["DENSITY"] : null;
  $stock["COST_PER_POUND"] = $row ? $row["COST_PER_POUND"] : null;
  $stock["DEFAULT_COST_PER_FOOT"] = $row ? $row["DEFAULT_COST_PER_FOOT"] : null;

  $default_unit_cost = null;
  $unit_cost = null;
  $default_cost_per_foot = null;
  $cost_per_foot = null;
  $total_cost = null;

  $weight = null;
  $cost_per_foot = null;
  $default_cost_per_foot = $stock["DEFAULT_COST_PER_FOOT"];
  $cost_method = COST_METHOD_NONE;
  $has_missing_dimensions = null;
  $default_unit_cost = calcStockUnitCost($stock,$weight,$cost_per_foot,$cost_method,$has_missing_dimensions);

  if( $_REQUEST["unit_cost"] != "" ) {
    $unit_cost = floatval($_REQUEST["unit_cost"]);
  } else {
    $unit_cost = $default_unit_cost;
  }

  if( $_REQUEST["quantity"] != "" && $unit_cost !== null ) {
    $quantity = intval($_REQUEST["quantity"]);
    if( $quantity > 0 ) {
      $total_cost = $unit_cost * $quantity;
    }
  }
  # format for display here, after using unit_cost to calculate total_cost
  if( $unit_cost !== null ) {
    $unit_cost = sprintf("%.2f",$unit_cost);
  }
  if( $default_unit_cost !== null ) {
    $default_unit_cost = sprintf("%.2f",$default_unit_cost);
  }
  if( $default_cost_per_foot !== null ) {
    $default_cost_per_foot = sprintf("%.2f",$default_cost_per_foot);
  }
  if( $cost_per_foot !== null ) {
    $cost_per_foot = sprintf("%.2f",$cost_per_foot);
  }
  if( $total_cost !== null ) {
    $total_cost = sprintf("%.2f",$total_cost);
  }

  $result = array();
  $result["default_unit_cost"] = $default_unit_cost;
  $result["unit_cost"] = $unit_cost;
  $result["default_cost_per_foot"] = $default_cost_per_foot;
  $result["cost_per_foot"] = $cost_per_foot;
  $result["total_cost"] = $total_cost;
  $result["requires_admin_input"] = $unit_cost === null && !$has_missing_dimensions;
  if( isset($_REQUEST["request_id"]) ) $result["request_id"] = $_REQUEST["request_id"];

  echo json_encode($result);
}

function evalFormula($formula,$vars) {
  $core_re = ' *(?:(?:[\*\/])|(?:[0-9]+\.[0-9]+)|(?:\.[0-9]+)|(?:[0-9]+)|(?:[a-zA-Z]+)) *';
  if( !preg_match('/^(' . $core_re . ')+$/',$formula) ) {
    return null;
  }
  if( !preg_match_all('/' . $core_re . '/',$formula,$tokens,PREG_PATTERN_ORDER) ) {
    return null;
  }
  $tokens = $tokens[0];

  $result = null;
  $op = null;
  foreach($tokens as $token) {
    $token = trim($token);
    if( $vars && array_key_exists($token,$vars) ) {
      $token = $vars[$token];
    }
    if( $token == "*" || $token == "/" ) {
      if( $op ) {
        return null;
      }
      $op = $token;
      continue;
    }
    if( preg_match('/^[0-9.]+$/',$token) ) {
      $token = floatval($token);
      if( !$op ) {
        if( $result !== null ) {
	  return null;
	}
	$result = $token;
      } else if( $op == "*" ) {
        $result *= $token;
	$op = null;
      } else if( $op == "/" ) {
        $result /= $token;
	$op = null;
      } else {
        return null;
      }
    } else {
      return null;
    }
  }
  return $result;
}

function calcStockUnitCost($stock,&$weight,&$cost_per_foot,&$cost_method,&$has_missing_dimensions) {
  $vars = array();
  # use evalFormula to allow dimensions to contain fractions
  $vars["T"] = evalFormula($stock["THICKNESS"],null);
  $vars["H"] = evalFormula($stock["HEIGHT"],null);
  $vars["W"] = evalFormula($stock["WIDTH"],null);
  $vars["L"] = evalFormula($stock["LENGTH"],null);
  $vars["PI"] = M_PI;

  $unit_cost = null;
  $has_missing_dimensions = false;
  if( $stock["VOLUME_FORMULA"] && $stock["COST_PER_POUND"] && $stock["DENSITY"] ) {
    $volume = evalFormula($stock["VOLUME_FORMULA"],$vars);
    if( $volume === null ) {
      $has_missing_dimensions = true;
      return null;
    }
    $weight = $volume * $stock["DENSITY"];
    $unit_cost = $weight * $stock["COST_PER_POUND"];
    $cost_method = COST_METHOD_VOLUME;
  } else if( $stock["COST_PER_FOOT"] ) {
    $cost_per_foot = $stock["COST_PER_FOOT"];
    if( $vars["L"] === null ) {
      $has_missing_dimensions = true;
      return null;
    }
    $unit_cost = $cost_per_foot * $vars["L"]/12.0;
    $cost_method = COST_METHOD_CUSTOM_COST_PER_FOOT;
  } else if( $stock["DEFAULT_COST_PER_FOOT"] ) {
    $cost_per_foot = $stock["DEFAULT_COST_PER_FOOT"];
    if( $vars["L"] === null ) {
      $has_missing_dimensions = true;
      return null;
    }
    $unit_cost = $cost_per_foot * $vars["L"]/12.0;
    $cost_method = COST_METHOD_DEFAULT_COST_PER_FOOT;
  } else {
    if( $stock["MATERIAL_ID"] === null ) {
      $has_missing_dimensions = true;
    }
    else if( $stock["COST_PER_POUND"] && $stock["DENSITY"] && $stock["SHAPE_ID"] === null ) {
      $has_missing_dimensions = true;
    }
  }
  return $unit_cost;
}

function saveWorkOrderAttachments($web_user,$wo_id) {
  $dbh = connectDB();
  $delete_perm_sql = "SELECT USER_WRITE_PERM,FILENAME FROM work_order_file WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND WORK_ORDER_FILE_ID = :WORK_ORDER_FILE_ID";
  $delete_perm_stmt = $dbh->prepare($delete_perm_sql);
  $delete_perm_stmt->bindValue(":WORK_ORDER_ID",$wo_id);

  if( isset($_REQUEST["delete_attachment"]) ) {
    foreach( $_REQUEST["delete_attachment"] as $fid ) {
      if( !isAdmin() && !isShopWorker() ) {
        $delete_perm_stmt->bindValue(":WORK_ORDER_FILE_ID",$fid);
        $delete_perm_stmt->execute();
        $row = $delete_perm_stmt->fetch();
        if( $row["USER_WRITE_PERM"] != 'Y' ) {
          echo "<div class='alert alert-danger'>You do not have permission to delete attachment ",htmlescape($row['FILENAME']),".</div>\n";
          continue;
        }
      }
      $error_msg = "";
      deleteWorkOrderFile($web_user,$wo_id,$fid,$error_msg);
      if( $error_msg ) {
        echo "<div class='alert alert-warning'>",htmlescape($error_msg),"</div>\n";
      }
    }
  }

  $file_sql = "INSERT INTO work_order_file SET WORK_ORDER_ID = :WORK_ORDER_ID, USER_READ_PERM = :USER_READ_PERM, USER_WRITE_PERM = :USER_WRITE_PERM, CREATED = now(), CREATED_BY = :CREATED_BY, FILENAME = :FILENAME";
  $file_stmt = $dbh->prepare($file_sql);
  $file_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $file_stmt->bindValue(":CREATED_BY",$web_user->user_id);

  $fname_sql = "SELECT FILENAME FROM work_order_file WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND FILENAME = :FILENAME";
  $fname_stmt = $dbh->prepare($fname_sql);
  $fname_stmt->bindValue(":WORK_ORDER_ID",$wo_id);

  foreach( $_FILES as $key => $value ) {
    if( !preg_match('/^attachment_([0-9]+)$/',$key,$match) ) continue;
    if( !isset($_FILES[$key]["name"]) || !$_FILES[$key]["name"] ) continue;

    $a_id = $match[1];
    $perm = 'rw';
    if( isAdmin() || isShopWorker() ) {
      $perm_key = 'attachment_perm_' . $a_id;
      $perm = isset($_REQUEST[$perm_key]) ? $_REQUEST[$perm_key] : '';
    } else if( isShopWorker() ) {
      # shop worker attachments should be private
      $perm = '';
    }
    $user_read_perm = ($perm == 'rw' || $perm == 'r') ? 'Y' : 'N';
    $user_write_perm = ($perm == 'rw') ? 'Y' : 'N';

    $uploaded_file = basename($_FILES[$key]["name"]);
    $safe_name = makeSafeFileName($uploaded_file);
    if( !$safe_name ) {
      echo "<div class='alert alert-danger'>",htmlescape($uploaded_file)," has an invalid name.</div>\n";
      continue;
    }
    $uploaded_file = $safe_name;

    $file_info = pathinfo($uploaded_file);
    $file_ext = strtolower($file_info['extension']);

    $unique_fname = null;
    for($i=0; $i<100; $i++) {
      if( $i ) {
        $fname = $file_info['filename'] . '_' . $i . '.' . $file_ext;
      } else {
        $fname = $uploaded_file;
      }
      $fname_stmt->bindValue(":FILENAME",$fname);
      $fname_stmt->execute();
      if( !$fname_stmt->fetch() ) {
        $unique_fname = $fname;
        break;
      }
    }
    if( !$unique_fname ) {
      echo "<div class='alert alert-danger'>Failed to create unique upload name for ",htmlescape($uploaded_file),"</div>\n";
      continue;
    }
    $uploaded_file = $unique_fname;

    if( in_array($file_ext,DISALLOWED_UPLOAD_FILE_TYPES) ) {
      echo "<div class='alert alert-danger'>",htmlescape($uploaded_file)," is not of a supported file type.</div>";
      continue;
    }

    if( filesize($_FILES[$key]["tmp_name"]) > MAX_UPLOAD_FILE_SIZE ) {
      echo "<div class='alert alert-danger'>",htmlescape($uploaded_file)," is larger than the maximum allowed size of ",MAX_UPLOAD_FILE_SIZE," bytes.</div>";
      continue;
    }

    $upload_dir = getWorkOrderUploadDir($wo_id);
    if( !file_exists($upload_dir) ) {
      mkdir($upload_dir,0755,true);
    }

    $full_fname = "$upload_dir/$uploaded_file";
    if( !move_uploaded_file($_FILES[$key]["tmp_name"],$full_fname) ) {
      echo "<div class='alert alert-danger'>Unable to save uploaded file ",htmlescape($uploaded_file),"!  Please contact ",htmlescape(SHOP_ADMIN_EMAIL),".</div>\n";
      continue;
    }

    $file_stmt->bindValue(":USER_READ_PERM",$user_read_perm);
    $file_stmt->bindValue(":USER_WRITE_PERM",$user_write_perm);
    $file_stmt->bindValue(":FILENAME",$uploaded_file);
    $file_stmt->execute();
    $fid = $dbh->lastInsertId();
    $after_edit = getWorkOrderFile($fid);
    auditlogModifyWorkOrderFile($web_user,null,$after_edit);
  }
}

function shopWorkerSaveWorkOrder($web_user,$wo_id) {

  if( isset($_REQUEST["work_completed"]) && $_REQUEST["work_completed"] == "1" ) {
    $editing = getWorkOrder($wo_id);
    if( !$editing["COMPLETED"] ) {
      $dbh = connectDB();
      $sql = "UPDATE work_order SET COMPLETED = now() WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
      $stmt = $dbh->prepare($sql);
      $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
      $stmt->execute();

      $after_edit = getWorkOrder($wo_id);
      auditlogModifyWorkOrder($web_user,$editing,$after_edit);
    }
  }

  saveStockOrder($web_user,$wo_id);
  saveWorkOrderAttachments($web_user,$wo_id);
  echo "<div class='alert alert-success noprint'>Saved</div>\n";
}

function getStudentShopPartCost($part_id,$work_order_id=null) {
  $dbh = connectDB();

  if( $work_order_id ) {
    $sql = "SELECT PRICE FROM checkout WHERE ISHOP_WORK_ORDER_ID = :WORK_ORDER_ID AND PART_ID = :PART_ID";
    $wo_price_stmt = $dbh->prepare($sql);
    $wo_price_stmt->bindValue(":WORK_ORDER_ID",$work_order_id);
    $wo_price_stmt->bindValue(":PART_ID",$part_id);
    $wo_price_stmt->execute();
    $row = $wo_price_stmt->fetch();
    if( $row ) {
      return $row["PRICE"];
    }
  }

  $sql = "SELECT COST FROM part WHERE PART_ID = :PART_ID";
  $cost_stmt = $dbh->prepare($sql);

  $cost_stmt->bindValue(":PART_ID",$part_id);
  $cost_stmt->execute();
  $row = $cost_stmt->fetch();
  if( $row ) {
    return $row["COST"];
  }
}

function showStudentShopAccessOrder($web_user) {
  $dbh = connectDB();
  $stmt = null;
  if( isset($_REQUEST["id"]) && $_REQUEST["id"] != "new_student_shop" ) {
    $sql = "SELECT * FROM work_order WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":WORK_ORDER_ID",$_REQUEST["id"]);
  }

  $editing = null;
  if( $stmt ) {
    $stmt->execute();
    $editing = $stmt->fetch();
  }

  if( $editing ) {
    $wo_desc = $editing["WORK_ORDER_NUM"];
    if( !$wo_desc ) $wo_desc = 'ID ' . $editing["WORK_ORDER_ID"];
  }

  $Work_Order = "Student Shop Access Order";

  $shopworker_editing = "";
  $inprog_disabled = "";
  $closed_disabled = "";
  $is_owner = false;

  if( $editing && !isAdmin() ) {
    if( !isWorkOrderEditor($web_user,$editing) ) {
      if( isShopWorker() ) {
        $shopworker_editing = "disabled";
      } else {
        echo "<div class='alert alert-warning'>$Work_Order ",htmlescape($wo_desc)," does not belong to you.</div>\n";
        return;
      }
    } else if( $editing["CLOSED"] ) {
      echo "<div class='alert alert-success'>To change this order, contact <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      $closed_disabled = "disabled";
      $inprog_disabled = "disabled";
      $is_owner = true;
    } else if( $editing["CANCELED"] ) {
      echo "<div class='alert alert-success'>This order has been canceled.  Any questions?  Contact <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      $closed_disabled = "disabled";
      $inprog_disabled = "disabled";
      $is_owner = true;
    } else if( $editing["QUEUED"] ) {
      $inprog_disabled = "disabled";
      $is_owner = true;
    }
  }

  if( $editing ) {
    echo "<h2>$Work_Order ",htmlescape($wo_desc),"</h2>\n";
  } else {
    echo "<h2>New $Work_Order</h2>\n";
  }

  echo "<form id='work_order_form' enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateOrderForm();'>\n";
  echo "<input type='hidden' name='form' value='student_shop_access_order'/>\n";
  if( $editing ) {
    echo "<input type='hidden' name='id' value='",htmlescape($editing["WORK_ORDER_ID"]),"'/>\n";
  } else {
    echo "<input type='hidden' name='id' value='new_student_shop'/>\n";
  }

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  echo "<p>This form is for use by student employees who are not employed by the ",htmlescape(tolower(STUDENT_SHOP_DEPARTMENT))," department to request access to the <a href='",htmlescape(STUDENT_SHOP_URL),"'>",htmlescape(tolower(STUDENT_SHOP_DEPARTMENT))," department student shop</a>.  Please consult your supervisor about the appropriate fund to use to cover the costs.</p>\n";

  $url = '?s=work_order';
  echo "<p>To view past orders, go to <a href='",htmlescape($url),"'>My Orders</a>.</p>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='group_id'>Fund Group</label></div><div class='col'>";
  echoSelectUserGroup($web_user,$editing ? $editing["GROUP_ID"] : null,$closed_disabled);
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='funding_source'>Fund</label></div><div class='col'>";
  $order_user = $editing ? loadUserFromUserID($editing["ORDERED_BY"]) : $web_user;
  $show_details = true;
  $offer_to_set_default = true;
  echoSelectFundingSource($order_user,$editing ? $editing["FUNDING_SOURCE_ID"] : null,$closed_disabled,$show_details,$offer_to_set_default);
  echo "</div></div>\n";

  if( $editing ) {
    $creator = new User;
    $creator->loadFromUserID($editing["ORDERED_BY"]);
    echo "<div class='$rowclass'><div class='$col1'>Ordered By</div><div class='col'><b>",htmlescape($creator->displayName()),"</b> on ",htmlescape(displayDateTime($editing["CREATED"]));
    if( isAdmin() ) {
      echo "<br><select name='new_owner' class='noprint'><option value=''>change owner</option>\n";
      $users = getUsers();
      foreach( $users as $u ) {
        echo "<option value='",htmlescape($u->user_id),"'>",htmlescape($u->lastFirst()),"</option>\n";
      }
      echo "</select>\n";
    }
    echo "</div></div>\n";
    echo "<div class='$rowclass'><div class='$col1'>Email</div><div class='col'><a href='mailto:",htmlescape($creator->email),"'>",htmlescape($creator->email),"</a></div></div>\n";
    echo "<div class='$rowclass'><div class='$col1'>Phone</div><div class='col'>",htmlescape($creator->phone),"</div></div>\n";
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='period'>Period of Access</label></div><div class='col'>";
  $access_date = date("Y-m-d");
  if( $editing ) {
    $access_date = date("Y-m-d",strtotime($editing["CREATED"]));
  }

  $price = getStudentShopPartCost(STUDENT_SHOP_SEMESTER_PART_ID,$editing ? $editing['WORK_ORDER_ID'] : null);
  $price = truncZeroCents($price);
  $yearprice = getStudentShopPartCost(STUDENT_SHOP_YEAR_PART_ID,$editing ? $editing['WORK_ORDER_ID'] : null);
  $yearprice = truncZeroCents($yearprice);

  $year = date("Y",strtotime($access_date));
  $next_year = intval($year)+1;
  $month = intval(date("n",strtotime($access_date)));
  echo "<input type='hidden' name='year' value='",htmlescape($year),"'/>\n";
  $selected_period = $editing ? $editing["STUDENT_SHOP_PERIOD"] : "";
  if( $month < 6 ) {
    $period = "$year-spring";
    $checked = $period == $selected_period ? "checked" : "";
    echo "<input type='radio' name='period' value='$period' $checked/> \${$price} - Spring $year<br>";
  }
  if( $month < 9 ) {
    $period = "$year-summer";
    $checked = $period == $selected_period ? "checked" : "";
    echo "<input type='radio' name='period' value='$period' $checked/> \${$price} - Summer $year<br>";
  }
  $period = "$year-fall";
  $checked = $period == $selected_period ? "checked" : "";
  echo "<input type='radio' name='period' value='$period' $checked/> \${$price} - Fall $year<br>";
  $period = "{$next_year}-spring";
  $checked = $period == $selected_period ? "checked" : "";
  echo "<input type='radio' name='period' value='$period' $checked/> \${$price} - Spring $next_year<br>";
  $period = "{$next_year}-summer";
  $checked = $period == $selected_period ? "checked" : "";
  echo "<input type='radio' name='period' value='$period' $checked/> \${$price} - Summer $next_year<br>";
  $period = "{$year}-academic-year";
  $checked = $period == $selected_period ? "checked" : "";
  echo "<input type='radio' name='period' value='$period' $checked/> \${$yearprice} - Fall $year, Spring $next_year, and Summer $next_year<br>";
  echo "</div></div>\n";

  if( isAdmin() ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='date_closed'>Order Accepted</label></div><div class='col'><input type='date' name='date_closed' id='date_closed' aria-label='date closed' value='",htmlescape($editing && $editing["CLOSED"] !== null ? $editing["CLOSED"] : ""),"'/></div></div>\n";

    echo "<div class='$rowclass'><div class='$col1'><label for='date_canceled'>Order Canceled</label></div><div class='col'><input type='date' name='date_canceled' id='date_canceled' aria-label='date canceled' value='",htmlescape($editing && $editing["CANCELED"] !== null ? $editing["CANCELED"] : ""),"'/></div></div>\n";
  }
  else if( $editing ) {
    if( $editing["CLOSED"] ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='date_closed'>$Work_Order Accepted</label></div><div class='col'>",htmlescape(displayDate($editing["CLOSED"])),"</div></div>\n";
    }
    if( $editing["CANCELED"] ) {
      echo "<div class='$rowclass'><div class='$col1'><label for='date_canceled'>$Work_Order Canceled</label></div><div class='col'>",htmlescape(displayDate($editing["CANCELED"])),"</div></div>\n";
    }
  }

  if( isAdmin() ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='admin_notes'>Shop Notes<br>(not visible to customer)</label></div><div class='col'>";
    echo "<textarea name='admin_notes' id='admin_notes' rows='3' cols='40' class='noprint' oninput='updatePrintTextarea(this)'>",htmlescape($editing ? $editing["ADMIN_NOTES"] : ""),"</textarea>";
    echo "<div id='print-admin_notes' class='printonly print-textarea'>",htmlescape($editing ? $editing["ADMIN_NOTES"] : ""),"</div>";
    echo "</div></div>\n";
  }

  echo "</div>\n"; # end container

  echo "<p>I certify that my supervisor has approved this request.<br>\n";
  $checked = $editing ? "checked" : "";
  echo "<label><input type='radio' name='approved' value='Y' $checked {$shopworker_editing} {$inprog_disabled}/> Yes</label>\n";
  $checked = "";
  echo "<label><input type='radio' name='approved' value='N' $checked {$shopworker_editing} {$inprog_disabled}/> No</label>\n";
  echo "</p>\n";

  echo "<input type='submit' value='Submit'/>\n";
  echo "</form>\n";

  if( $editing ) {
    echo "<hr>";
    showWorkOrderBills($editing["WORK_ORDER_ID"]);
  }

  echo "<p>&nbsp;</p>\n";
  ?><script>
  function validateOrderForm() {
    var missing = [];

    <?php if( !isAdmin() ) { ?>
      var fund = $('#funding_source');
      if( fund.length && !fund.val() ) {
        missing.push("Fund");
      }
    <?php } ?>

    var period = $('[name="period"]:checked').val();
    if( !period ) {
      missing.push("Period");
    }

    if( missing.length ) {
      alert("The following information is missing: " + arrayToEnglishList(missing) + ".");
      return false;
    }

    if( $('[name="approved"]').length ) {
      var approved = $('[name="approved"]:checked').val();
      if( approved != 'Y' ) {
        alert("You must certify approval by your supervisor.");
        return false;
      }
    }

    return true;
  }
  </script><?php
}

function saveStudentShopAccessOrder($web_user) {

  $dbh = connectDB();
  $id = $_REQUEST['id'];
  if( $id == "new_student_shop" ) $id = "";

  $funding_source_id = isset($_REQUEST["funding_source"]) ? $_REQUEST["funding_source"] : 0;
  $funding_source = getFundingSourceRecord($funding_source_id);

  $admin_sql = "";
  if( isAdmin() ) {
    $admin_sql .= "CLOSED = :CLOSED,";
    $admin_sql .= "COMPLETED = :CLOSED,";
    $admin_sql .= "REVIEWED = :CLOSED,";
    $admin_sql .= "CANCELED = :CANCELED,";
    $admin_sql .= "ADMIN_NOTES = :ADMIN_NOTES,";

    if( isset($_REQUEST["new_owner"]) && $_REQUEST["new_owner"] ) {
      $admin_sql .= "ORDERED_BY = :ORDERED_BY,";
    }
  }

  if( $id ) {
    $editing = getWorkOrder($_REQUEST["id"]);
    if( $editing ) {
      if( !$editing["STUDENT_SHOP_ACCESS"] ) {
        echo "<div class='alert alert-danger'>Order ",htmlescape($editing["WORK_ORDER_NUM"])," is not a student shop access order.</div>\n";
	return;
      }
    }

    if( !isAdmin() && $editing ) {
      if( !isWorkOrderEditor($web_user,$editing) ) {
        echo "<div class='alert alert-danger'>Work order ",htmlescape($wo_desc)," does not belong to you.</div>\n";
        return;
      }
      else if( $editing["CLOSED"] || $editing["CANCELED"] ) {
        echo "<div class='alert alert-danger'>This order is closed for edits.  If changes need to be made, please contact <a href='mailto:'",htmlescape(SHOP_ADMIN_EMAIL),"'>",htmlescape(SHOP_ADMIN_NAME),"</a>.</div>\n";
      }
    }

    $sql = "
      UPDATE work_order SET
      {$admin_sql}
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
      STUDENT_SHOP_PERIOD = :STUDENT_SHOP_PERIOD,
      STUDENT_SHOP_BEGIN = :BEGIN_DATE,
      STUDENT_SHOP_END = :END_DATE,
      DESCRIPTION = :DESCRIPTION
      WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  }
  else {
    $editing = null;
    $sql = "
      INSERT INTO work_order SET
      {$admin_sql}
      WORK_ORDER_NUM = :WORK_ORDER_NUM,
      STUDENT_SHOP_ACCESS = 1,
      ORDERED_BY = :ORDERED_BY,
      CREATED = now(),
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
      STUDENT_SHOP_PERIOD = :STUDENT_SHOP_PERIOD,
      STUDENT_SHOP_BEGIN = :BEGIN_DATE,
      STUDENT_SHOP_END = :END_DATE,
      DESCRIPTION = :DESCRIPTION";
  }
  $stmt = $dbh->prepare($sql);

  # Get a write-lock on the work_order table, so work order number uniqueness checks are not
  # defeated by race conditions.
  $dbh->beginTransaction();
  $dbh->exec("LOCK TABLES work_order WRITE");

  if( $id ) {
    $stmt->bindValue(":WORK_ORDER_ID",$id);
  } else {
    $stmt->bindValue(":ORDERED_BY",$web_user->user_id);
  }

  if( !$id ) {
    $wo_num = getUniqueWorkOrderNum($id,"Std");
    $stmt->bindValue(":WORK_ORDER_NUM",$wo_num);
  }

  $stmt->bindValue(":GROUP_ID",isset($_REQUEST["group_id"]) ? $_REQUEST["group_id"] : 0);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$funding_source_id);

  $year = $_REQUEST['year'];
  $next_year = intval($year)+1;
  $period = $_REQUEST['period'];
  $stmt->bindValue(":STUDENT_SHOP_PERIOD",$period);
  $description = $period;
  $begin_date = null;
  $end_date = null;
  $part_id = STUDENT_SHOP_SEMESTER_PART_ID;
  if( preg_match('|^([0-9][0-9][0-9][0-9])-(.*)$|',$period,$match) ) {
    $period_year = $match[1];
    $period_next_year = intval($match[1])+1;
    $period_season = $match[2];
    if( $period_season == 'academic-year' ) {
      $part_id = STUDENT_SHOP_YEAR_PART_ID;
      $description = "Fall {$period_year}, Spring {$period_next_year}, and Summer {$period_next_year}";
      $begin_date = "{$period_year}-08-01";
      $end_date = "{$period_next_year}-08-01";
    } else {
      $description = ucfirst($period_season) . " {$period_year}";
      switch( $period_season ) {
      case "spring":
        $begin_date = "{$period_year}-01-01";
        $end_date = "{$period_year}-06-01";
	break;
      case "summer":
        $begin_date = "{$period_year}-05-01";
        $end_date = "{$period_year}-09-01";
	break;
      case "fall":
        $begin_date = "{$period_year}-08-01";
        $end_date = "{$period_next_year}-01-01";
	break;
      }
    }
  }
  $description = STUDENT_SHOP_DEPARTMENT . " student shop access for $description";
  $stmt->bindValue(":DESCRIPTION",$description);

  if( empty($begin_date) || empty($end_date) ) {
    echo "<div class='alert alert-danger'>Error setting begin and end date.</div>\n";
    return;
  }
  $stmt->bindValue(":BEGIN_DATE",$begin_date);
  $stmt->bindValue(":END_DATE",$end_date);

  if( isAdmin() ) {
    $stmt->bindValue(":CLOSED",$_REQUEST["date_closed"] == "" ? null : $_REQUEST["date_closed"]);
    $stmt->bindValue(":CANCELED",$_REQUEST["date_canceled"] == "" ? null : $_REQUEST["date_canceled"]);
    $stmt->bindValue(":ADMIN_NOTES",$_REQUEST["admin_notes"] == "" ? null : $_REQUEST["admin_notes"]);
    if( isset($_REQUEST["new_owner"]) && $_REQUEST["new_owner"] ) {
      $stmt->bindValue(":ORDERED_BY",$_REQUEST["new_owner"]);
    }
  }

  $stmt->execute();
  $wo_created = false;
  if( !$id ) {
    $wo_created = true;
    $id = $dbh->lastInsertId();
    $_REQUEST["id"] = $id;
  }

  $after_edit = getWorkOrder($id);

  $dbh->commit();
  $dbh->exec("UNLOCK TABLES");

  auditlogModifyWorkOrder($web_user,$editing,$after_edit);

  if( !$editing ) {
    $sql = "
      INSERT INTO checkout SET
      USER_ID = :USER_ID,
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
      PRICE = :PRICE,
      TOTAL = :PRICE,
      PART_ID = :PART_ID,
      QTY = 1,
      DELETED = :DELETED,
      DATE = now(),
      LOGIN_METHOD = :LOGIN_METHOD,
      IPADDR = :IPADDR,
      ISHOP_WORK_ORDER_ID = :WORK_ORDER_ID
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":LOGIN_METHOD",getLoginMethod());
    $stmt->bindValue(":IPADDR",$_SERVER['REMOTE_ADDR']);
  }
  else {
    $sql = "
      UPDATE checkout SET
      USER_ID = :USER_ID,
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
      PRICE = :PRICE,
      TOTAL = :PRICE,
      PART_ID = :PART_ID,
      QTY = 1,
      DELETED = :DELETED
    WHERE
      ISHOP_WORK_ORDER_ID = :WORK_ORDER_ID
    ";
    $stmt = $dbh->prepare($sql);
  }
  $stmt->bindValue(":USER_ID",$after_edit['ORDERED_BY']);
  $stmt->bindValue(":GROUP_ID",$after_edit['GROUP_ID']);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$after_edit['FUNDING_SOURCE_ID']);
  $stmt->bindValue(":PART_ID",$part_id);
  $price = getStudentShopPartCost($part_id,$id);
  $stmt->bindValue(":PRICE",$price);
  $stmt->bindValue(":WORK_ORDER_ID",$id);
  $stmt->bindValue(":DELETED",$after_edit['CANCELED']);
  $stmt->execute();

  echo "<div class='alert alert-success noprint'>Saved</div>\n";

  $order_user = loadUserFromUserID($after_edit['ORDERED_BY']);
  handleDefaultFundingSourceUpdateForm($order_user);

  if( $wo_created && NOTIFY_SHOP_ADMIN_OF_NEW_WORK_ORDERS ) {
    emailWorkOrder($id,SHOP_ADMIN_EMAIL,true);
  }
}

function getWorkOrderYearStart($now=null) {
  if( empty($now) ) $now = time();
  $year = date("Y",$now);
  $month = intval(date("n",$now));
  if( $month < 6 ) {
    $year = intval($year)-1;
  }
  return "$year-06-01";
}

function displayWorkOrderYear($dt) {
  $year = date("Y",strtotime($dt));
  return "" . (intval($year)+1);
}

class WorkOrderPager {
  public $start;
  public $end;
  public $next;
  public $prev;

  function __construct() {
    if( isset($_REQUEST["start"]) ) {
      $this->start = $_REQUEST["start"];
    } else {
      $this->start = getWorkOrderYearStart();
    }

    if( isset($_REQUEST["end"]) ) {
      $this->end = $_REQUEST["end"];
    } else {
      $this->end = getWorkOrderYearStart(strtotime($this->start . " +370 day"));
    }

    $this->prev = getWorkOrderYearStart(strtotime($this->start . " -360 day"));
    $this->next = $this->end;
  }

  function pageButtons($base_url) {
    $url = "{$base_url}&start={$this->prev}";
    echo "<a href='",htmlescape($url),"' class='btn btn-primary noprint'><i class='fas fa-arrow-left'></i></a>\n";

    $url = $base_url;
    $year = getWorkOrderYearStart(strtotime($this->start));
    $url .= "&start=$year";
    $disabled = $this->start == $year ? "disabled" : "";
    echo "<a href='" . htmlescape($url) . "' class='btn btn-primary noprint $disabled'>",displayWorkOrderYear($year),"</a>\n";

    $url = "{$base_url}&start={$this->next}";
    $disabled = $this->next ? "" : "disabled";
    echo "<a href='",htmlescape($url),"' class='btn btn-primary noprint $disabled'><i class='fas fa-arrow-right'></i></a>\n";
  }
};

function showStudentShopAccessOrders() {

  $page = new WorkOrderPager();

  $dbh = connectDB();
  $sql = "
    SELECT
      work_order.*,
      user.FIRST_NAME,
      user.LAST_NAME,
      user.EMAIL,
      user.PHONE
    FROM
      work_order
    JOIN user
    ON user.USER_ID = work_order.ORDERED_BY
    WHERE
      STUDENT_SHOP_ACCESS
      AND work_order.CREATED >= :START_DATE
      AND work_order.CREATED < :END_DATE
    ORDER BY
      WORK_ORDER_ID DESC
  ";

  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START_DATE",$page->start);
  $stmt->bindValue(":END_DATE",$page->end);
  $stmt->execute();

  $page->pageButtons("?s=student_shop_access_orders");

  echo "<a class='btn btn-primary' href='?id=new_student_shop'>New Student Shop Access Order</a>\n";

  echo "<table class='records clicksort'><thead>";
  echo "<tr><th>WO #</th>";
  echo "<th><small>Status</small></th>";
  echo "<th>Customer</th>";
  echo "<th>Email</th>";
  echo "<th>Phone</th>";
  echo "<th>Created</th>";
  echo "<th>Description</th></tr></thead><tbody>\n";

  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = '?s=work_order&id=' . $row['WORK_ORDER_ID'];
    $wo_name = $row['WORK_ORDER_NUM'] <> "" ? $row['WORK_ORDER_NUM'] : ('#' . $row['WORK_ORDER_ID']);
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($wo_name),"</a></td>";

    $status = "";
    if( $row["COMPLETED"] ) $status = "<i class='fas fa-check text-success'></i><span class='clicksort_data'>Y</span>";
    if( $row["CANCELED"] ) $status = "X";
    echo "<td>",$status,"</td>";

    $url = '?s=edit_user&user_id=' . $row["ORDERED_BY"];
    if( !isAdmin() ) { # shop worker does not have access to user profile
      $url = 'mailto:' . $row["EMAIL"];
    }
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["LAST_NAME"] . ", " . $row["FIRST_NAME"]),"</a></td>";
    echo "<td>";
    if( $row["EMAIL"] ) {
      echo "<a href='mailto:",htmlescape($row["EMAIL"]),"'>";
    }
    echo htmlescape($row["EMAIL"]);
    if( $row["EMAIL"] ) {
      echo "</a>";
    }
    echo "</td>";
    echo "<td>",htmlescape($row["PHONE"]),"</td>";

    echo "<td>",htmlescape(displayDate($row["CREATED"])),"</td>";
    echo "<td>",htmlescape($row["DESCRIPTION"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";
}

function showStudentShopAccessFeeConfig() {
  echo "<div class='card'>\n";
  echo "<h2>Student Shop Fees</h2>\n";
  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='student_access_fee'/>\n";
  echo "<table class='records'>\n";
  echo "<thead><tr><th>Term</th><th>Fee</th></tr></thead><tbody>\n";
  $fee = getStudentShopPartCost(STUDENT_SHOP_SEMESTER_PART_ID);
  echo "<tr><th>1 Semester</th><td><input name='semester' value='",htmlescape($fee),"' size='7'/></td></tr>\n";
  $fee = getStudentShopPartCost(STUDENT_SHOP_YEAR_PART_ID);
  echo "<tr><th>Fall, Spring, Summer</th><td><input name='academic-year' value='",htmlescape($fee),"' size='7'/></td></tr>\n";
  echo "</tbody></table>\n";
  echo "<p><input type='submit' value='Submit'/></p>\n";
  echo "</form>\n";
  echo "</div>\n";
}

function saveStudentShopAccessFeeConfig($user) {
  if( !isAdmin() ) {
    echo "<div class='alert alert-danger'>Only admins can update the student shop access fee.</div>\n";
    return;
  }
  $dbh = connectDB();
  $sql = "UPDATE part SET COST = :FEE, PRICE = :FEE WHERE PART_ID = :PART_ID";
  $update_stmt = $dbh->prepare($sql);

  foreach( array(STUDENT_SHOP_SEMESTER_PART_ID,STUDENT_SHOP_YEAR_PART_ID) as $part_id ) {
    if( $part_id == STUDENT_SHOP_SEMESTER_PART_ID ) {
      $fee = $_REQUEST['semester'];
    }
    else if( $part_id == STUDENT_SHOP_YEAR_PART_ID ) {
      $fee = $_REQUEST['academic-year'];
    }
    else {
      throw new Exception("Unexpected part_id $part_id");
    }
    $before_change = getPartRecord($part_id);
    if( $before_change['COST'] == $fee ) {
      continue;
    }

    $update_stmt->bindValue(":FEE",$fee);
    $update_stmt->bindValue(":PART_ID",$part_id);
    $update_stmt->execute();

    $after_change = getPartRecord($part_id);
    auditlogModifyPart($user,$before_change,$after_change);
  }
  echo "<div class='alert alert-success'>Saved.</div>\n";
}

function downloadAttachment($web_user,$work_order_id,$fname) {
  $sql = "SELECT * FROM work_order_file WHERE WORK_ORDER_ID = :WORK_ORDER_ID AND FILENAME = :FILENAME";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$work_order_id);
  $stmt->bindValue(":FILENAME",$fname);
  $stmt->execute();
  $row = $stmt->fetch();

  if( !$row ) {
    header('HTTP/1.0 404 File not found');
    echo "File not found.\n";
    return;
  }

  if( !isAdmin() && !isShopWorker() ) {
    if( $row["USER_READ_PERM"] != 'Y' ) {
      header('HTTP/1.0 403 Forbidden');
      echo "Access denied.\n";
      return;
    }
  }

  $full_fname = getWorkOrderUploadDir($work_order_id) . "/" . $fname;
  $content_type = mime_content_type($full_fname);

  $file_info = pathinfo($full_fname);
  $file_ext = strtolower($file_info['extension']);

  $inline_file_types = array("pdf","png","bmp","gif","jpg","jpeg");
  $content_disposition = "inline";
  if( !in_array($file_ext,$inline_file_types) ) {
    $content_disposition = "attachment";
  }

  header("Content-type: {$content_type}");
  header("Content-Disposition: {$content_disposition}; filename=\"$fname\"");
  readfile($full_fname);
}

function showTimesheet($web_user) {
  $day = isset($_REQUEST['day']) ? date('Y-m-d',strtotime($_REQUEST['day'])) : date('Y-m-d');

  echo "<h2>",htmlescape(date('l, M j, Y',strtotime($day))),"</h2>\n";

  $today = date('Y-m-d');
  $next_day = getNextTimesheetDay($day);
  $prev_day = getPrevTimesheetDay($day);

  echo "<p>";
  $base_url = "?s=timesheet";
  $url = $base_url . "&day=" . $prev_day;
  if( $prev_day == $today ) $url = $base_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  $url = $base_url;
  if( $day == $today ) {
    $disabled_class = "disabled";
    $url = "#";
  } else {
    $disabled_class = "";
  }
  echo "<a href='$url' class='btn btn-primary $disabled_class'>Today</a>\n";

  $url = $base_url . "&day=" . $next_day;
  if( $next_day == $today ) $url = $base_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-right'></i></a>\n";

  $url = "?s=work_history&who=" . $web_user->user_id;
  echo "<a href='",htmlescape($url),"' class='btn btn-primary'>Summary</a>\n";

  echo "</p>\n";


  $dbh = connectDB();
  $sql = "SELECT timesheet.*,work_order.WORK_ORDER_NUM FROM timesheet JOIN work_order ON work_order.WORK_ORDER_ID = timesheet.WORK_ORDER_ID WHERE USER_ID = :USER_ID AND DATE = :DATE";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":USER_ID",$web_user->user_id);
  $stmt->bindValue(":DATE",$day);
  $stmt->execute();

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='timesheetSubmitting()'>\n";
  echo "<input type='hidden' name='form' value='timesheet'/>\n";
  echo "<input type='hidden' name='day' value='",htmlescape($day),"'/>\n";
  echo "<table class='records' id='timesheet'><thead><tr><th>Job</th><th>Hours</th><th>Notes</th></tr></thead><tbody>\n";

  $row_num = 0;
  while( ($row=$stmt->fetch()) ) {
    echo "<tr>";
    # NOTE: keep the html here in sync with the html in addTimesheetRow()
    echo "<td><input type='hidden' name='row_{$row_num}_id' value='",htmlescape($row["TIMESHEET_ID"]),"'/>";
    echo "<div class='autocomplete'><input name='row_{$row_num}_job' id='row_{$row_num}_job' size='7' value='",htmlescape($row['WORK_ORDER_NUM']),"' class='job_num' onchange='timesheetChanged($row_num)'/></div></td>";
    echo "<td><input name='row_{$row_num}_hours' size='5' value='",htmlescape($row['HOURS']),"' class='timesheet_hours' onchange='timesheetChanged($row_num); updateTimesheetTotal();'/></td>";
    echo "<td><input name='row_{$row_num}_notes' size='15' maxlength='100' value='",htmlescape($row['NOTES']),"' onchange='timesheetChanged($row_num)'/></td>";
    echo "</tr>";
    echo "<script>document.addEventListener('DOMContentLoaded', function(event) {setupJobAutocomplete(\$('input[name=\"row_{$row_num}_job\"]')[0]);});</script>\n";
    $row_num += 1;
  }
  echo "</tbody>";
  echo "<tfoot><tr><th>Total</th><th id='total_hours'></th><th></th></tr></tfoot>\n";
  echo "</table>\n";

  echo "<p><button class='btn btn-secondary noprint' onclick='addTimesheetRow(); return false;'>Add Row</button></p>\n";

  echo "<p><input type='submit' value='Submit' class='noprint'/></p>\n";
  echo "</form>\n";

  $autocomplete_job_nums = getAutocompleteWorkOrderNumsJSON();

  ?><script>
  function addTimesheetRow() {
    var row = document.createElement('tr');
    var row_num = $('#timesheet tbody > tr').length;
    var html = "<td><div class='autocomplete'><input name='row_" + row_num + "_job' id='row_" + row_num + "_job' size='7' class='job_num' onchange='timesheetChanged(" + row_num + ")'/></div></td>";
    html += "<td><input name='row_" + row_num + "_hours' size='5' class='timesheet_hours' onchange='timesheetChanged(" + row_num + "); updateTimesheetTotal();'/></td>";
    html += "<td><input name='row_" + row_num + "_notes' size='15' maxlength='100' onchange='timesheetChanged(" + row_num + ")'/></td>";
    row.innerHTML = html;
    $('#timesheet tbody').append(row);
    setupJobAutocomplete($(row).find("input[name='row_" + row_num + "_job']")[0]);
  }

  function updateTimesheetTotal() {
    var total = 0;
    $('input.timesheet_hours').each(function( index ) {
      if( this.value ) total += parseFloat(this.value);
    });
    if( !total ) total = "";
    document.getElementById('total_hours').innerHTML = total;
  }
  updateTimesheetTotal();

  var timesheet_submitting = false;
  function timesheetSubmitting() {
    timesheet_submitting = true;
  }

  var timesheet_changed = false;
  function timesheetChanged(row_num) {
    timesheet_changed = true;
  }

  window.onload = function() {
    window.addEventListener("beforeunload", function (e) {
      if (timesheet_submitting || !timesheet_changed) {
        return undefined;
      }

      var confirmationMessage = 'If you leave before submitting the form, your changes will be lost.';

      (e || window.event).returnValue = confirmationMessage; //Gecko + IE
      return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
    });
  };

  var autocomplete_job_nums = <?php echo $autocomplete_job_nums; ?>;
  function setupJobAutocomplete(e) {
    autocomplete_unordered_words(e, autocomplete_job_nums, 2);
  }

  </script><?php

  for(; $row_num < 5; $row_num++) {
    echo "<script>addTimesheetRow();</script>\n";
  }

  $sql = "SELECT WORK_ORDER_NUM, DESCRIPTION FROM work_order WHERE STATUS = :STATUS ORDER BY WORK_ORDER_NUM";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":STATUS",WORK_ORDER_STATUS_TIMECODE);
  $stmt->execute();

  echo "<p>&nbsp;</p><table class='records'><caption>Timesheet Codes</caption>\n";
  echo "<thead><tr><th>Code</th><th>Description</th></tr></thead><tbody>\n";
  while( ($row=$stmt->fetch()) ) {
    echo "<tr><td>",htmlescape($row["WORK_ORDER_NUM"]),"</td><td>",htmlescape($row["DESCRIPTION"]),"</td></tr>\n";
  }
  echo "</tbody></table><p>&nbsp;</p>\n";
}

function getAutocompleteWorkOrderNumsJSON() {
  $dbh = connectDB();
  $sql = "SELECT WORK_ORDER_NUM FROM work_order WHERE STATUS NOT IN ('" . WORK_ORDER_STATUS_CLOSED . "','" . WORK_ORDER_STATUS_CANCELED . "') AND NOT STOCK_ORDER";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();
  $results = array();
  while( ($row=$stmt->fetch()) ) {
    $results[] = $row['WORK_ORDER_NUM'];
  }
  return json_encode($results);
}

function workOrderNumToId($wo_num) {
  $dbh = connectDB();
  $sql = "SELECT WORK_ORDER_ID FROM work_order WHERE WORK_ORDER_NUM = :WORK_ORDER_NUM";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_NUM",$wo_num);
  $stmt->execute();
  $row = $stmt->fetch();
  if( $row ) return $row["WORK_ORDER_ID"];
}

function getTimesheet($timesheet_id) {
  $dbh = connectDB();
  $sql = "SELECT * FROM timesheet WHERE TIMESHEET_ID = :TIMESHEET_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":TIMESHEET_ID",$timesheet_id);
  $stmt->execute();
  return $stmt->fetch();
}

function saveTimesheet($web_user) {

  $day = $_REQUEST["day"];

  $dbh = connectDB();
  $sql = "INSERT INTO timesheet SET WORK_ORDER_ID = :WORK_ORDER_ID, USER_ID = :USER_ID, DATE = :DATE, HOURS = :HOURS, NOTES = :NOTES";
  $insert_stmt = $dbh->prepare($sql);
  $insert_stmt->bindValue(":USER_ID",$web_user->user_id);
  $insert_stmt->bindValue(":DATE",$day);

  $sql = "UPDATE timesheet SET WORK_ORDER_ID = :WORK_ORDER_ID, HOURS = :HOURS, NOTES = :NOTES WHERE TIMESHEET_ID = :TIMESHEET_ID AND USER_ID = :USER_ID AND DATE = :DATE";
  $update_stmt = $dbh->prepare($sql);
  $update_stmt->bindValue(":USER_ID",$web_user->user_id);
  $update_stmt->bindValue(":DATE",$day);

  $sql = "DELETE FROM timesheet WHERE TIMESHEET_ID = :TIMESHEET_ID AND USER_ID = :USER_ID AND DATE = :DATE";
  $delete_stmt = $dbh->prepare($sql);
  $delete_stmt->bindValue(":USER_ID",$web_user->user_id);
  $delete_stmt->bindValue(":DATE",$day);

  $wo_ids = array();
  foreach( $_REQUEST as $key => $wo_num ) {
    if( !preg_match('/^row_([0-9]+)_job$/',$key,$match) ) continue;
    if( !$wo_num ) continue;
    if( array_key_exists($wo_num,$wo_ids) ) continue;
    $wo_id = workOrderNumToId($wo_num);
    if( !$wo_id ) {
      echo "<div class='alert alert-danger'>WARNING: Ignoring timesheet entry with invalid job/code '",htmlescape($wo_num),"'.</div>\n";
      continue;
    }
    $wo_ids[$wo_num] = $wo_id;
  }

  foreach( $_REQUEST as $key => $wo_num ) {
    if( !preg_match('/^row_([0-9]+)_job$/',$key,$match) ) continue;
    $row_num = $match[1];

    $timesheet_id = isset($_REQUEST["row_{$row_num}_id"]) ? $_REQUEST["row_{$row_num}_id"] : null;
    $hours = $_REQUEST["row_{$row_num}_hours"];
    $notes = $_REQUEST["row_{$row_num}_notes"];

    if( !$wo_num ) {
      if( $timesheet_id ) {
        if( $hours != "" || $notes != "" ) {
          echo "<div class='alert alert-danger'>WARNING: The job field was cleared, but other fields are not empty, so not deleting row.</div>\n";
        } else {
          $delete_stmt->bindValue(":TIMESHEET_ID",$timesheet_id);
	  $before_edit = getTimesheet($timesheet_id);
          $delete_stmt->execute();

          auditlogModifyTimesheet($web_user,$before_edit,null);
        }
      } else if( $hours != "" || $notes != "" ) {
        echo "<div class='alert alert-danger'>WARNING: ignoring row with empty job field.</div>\n";
      }
      continue;
    }
    $wo_id = isset($wo_ids[$wo_num]) ? $wo_ids[$wo_num] : null;
    if( !$wo_id ) continue;

    if( $timesheet_id ) {
      $update_stmt->bindValue(":TIMESHEET_ID",$timesheet_id);
      $update_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
      $update_stmt->bindValue(":HOURS",$hours);
      $update_stmt->bindValue(":NOTES",$notes);
      $before_edit = getTimesheet($timesheet_id);
      $update_stmt->execute();

      $after_edit = getTimesheet($timesheet_id);
      auditlogModifyTimesheet($web_user,$before_edit,$after_edit);
    } else {
      $insert_stmt->bindValue(":WORK_ORDER_ID",$wo_id);
      $insert_stmt->bindValue(":HOURS",$hours);
      $insert_stmt->bindValue(":NOTES",$notes);
      $insert_stmt->execute();
      $timesheet_id = $dbh->lastInsertId();

      $after_edit = getTimesheet($timesheet_id);
      auditlogModifyTimesheet($web_user,null,$after_edit);
    }
  }
  echo "<div class='alert alert-success noprint'>Saved</div>\n";
}

function showTimesheets() {
  if( !isAdmin() ) return;

  $day = isset($_REQUEST["day"]) ? $_REQUEST["day"] : date("Y-m-d");
  $week = getWeekStart($day);
  $next_week = getNextWeek($week);
  $prev_week = getPrevWeek($week);
  $this_week = getWeekStart(date("Y-m-d"));

  echo "<h2>Timesheets for the Week of ",htmlescape(date("M j",strtotime(getNextDay($week)))),"</h2>\n";

  echo "<p class='noprint'>";
  $base_url = "?s=timesheets";
  $url = $base_url . "&day=" . $prev_week;
  if( $prev_week == $this_week ) $url = $base_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  $url = $base_url;
  if( $week == $this_week ) {
    $disabled_class = "disabled";
    $url = "#";
  } else {
    $disabled_class = "";
  }
  echo "<a href='$url' class='btn btn-primary $disabled_class'>This Week</a>\n";

  $url = $base_url . "&day=" . $next_week;
  if( $next_week == $this_week ) $url = $base_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-right'></i></a>\n";

  echo "<button class='btn btn-primary' onclick='showExportOptions()'>Export ...</button>\n";

  echo "<a class='btn btn-primary' href='?s=all_work_history'>Summary</a>\n";

  echo "</p>\n";

  $dbh = connectDB();
  $workers_sql = "SELECT user.USER_ID,CONCAT(user.FIRST_NAME,' ',user.LAST_NAME) as NAME FROM timesheet JOIN user on user.USER_ID = timesheet.USER_ID WHERE DATE >= :START_DATE AND DATE < :END_DATE GROUP BY user.USER_ID,user.FIRST_NAME,user.LAST_NAME ORDER BY user.LAST_NAME,user.FIRST_NAME";
  $workers_stmt = $dbh->prepare($workers_sql);
  $workers_stmt->bindValue(":START_DATE",$week);
  $workers_stmt->bindValue(":END_DATE",$next_week);
  $workers_stmt->execute();

  $workers = array();
  while( ($row=$workers_stmt->fetch()) ) {
    $workers[$row["USER_ID"]] = $row["NAME"];
  }

  echo "<div class='card' id='export_form' style='display: none'><div class='card-body'>\n";
  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='s' value='export_timesheets'/>\n";
  echo "<label for='start_date'>Start Date</label>: <input type='date' name='start_date' id='start_date' value='",htmlescape($this_week),"'/>\n";
  echo "<label for='start_date'>End Date</label>: <input type='date' name='end_date' id='end_date' value='",htmlescape($next_week),"'/><br>\n";
  echo "<select name='who' id='export_who'>\n";
  echo "<option value='all'>All People</option>\n";
  foreach( $workers as $worker_id => $worker ) {
    echo "<option value='",htmlescape($worker_id),"'>",htmlescape($worker),"</option>\n";
  }
  echo "</select><br>\n";
  echo "<input type='submit' value='Export'/>\n";
  echo "</form></div></div>\n";
  ?><script>
  function showExportOptions() {
    if( $('#export_form').is(":visible") ) {
      $('#export_form').hide();
    } else {
      $('#export_form').show();
    }
  }
  </script><?php

  showWeekTimesheet($week,$next_week,null,$workers);
}

function exportWorkOrders() {
  $filename = "WorkOrders.csv";

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  $csv = array("Work Order Num","Ordered By","Email","Phone","Group","Funding Source","Fund","Fund Program","Fund Dept","Fund Project","Status","Created (date)","Reviewed (date)","Queued (date)","Completed (date)","Canceled (date)","Closed (date)","Contract","Quote","Inventory Num","Health Hazard","Description","Admin Notes");
  fputcsv_excel($F,$csv);

  $sql = "
  SELECT
    work_order.*,
    CONCAT(user.FIRST_NAME,' ',user.LAST_NAME) as 'USER_NAME',
    user.EMAIL,
    user.PHONE,
    user_group.GROUP_NAME,
    shop_contract.CONTRACT_NAME,
    funding_source.FUNDING_DESCRIPTION,
    funding_source.FUNDING_FUND,
    funding_source.FUNDING_PROGRAM,
    funding_source.FUNDING_DEPT,
    funding_source.FUNDING_PROJECT
  FROM
    work_order
  JOIN user
  ON user.USER_ID = work_order.ORDERED_BY
  LEFT JOIN user_group
  ON user_group.GROUP_ID = work_order.GROUP_ID
  LEFT JOIN funding_source
  ON funding_source.FUNDING_SOURCE_ID = work_order.FUNDING_SOURCE_ID
  LEFT JOIN shop_contract
  ON shop_contract.CONTRACT_ID = work_order.CONTRACT_ID
  WHERE
    True
  ";

  $start_date = isset($_REQUEST["start_date"]) ? $_REQUEST["start_date"] : "";
  if( $start_date ) {
    $sql .= "\nAND DATE(work_order.CREATED) >= :START_DATE";
  }

  $end_date = isset($_REQUEST["end_date"]) ? $_REQUEST["end_date"] : "";
  if( $end_date ) {
    $sql .= "\nAND DATE(work_order.CREATED) < :END_DATE";
  }

  $status = isset($_REQUEST["order_status"]) ? $_REQUEST["order_status"] : "";
  if( $status == "open" ) {
    $sql .= "\nAND work_order.status <> '" . WORK_ORDER_STATUS_CLOSED . "'";
  }
  if( $status == "closed" ) {
    $sql .= "\nAND work_order.status = '" . WORK_ORDER_STATUS_CLOSED . "'";
  }

  $sql .= "\nORDER BY WORK_ORDER_ID";

  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  if( $start_date ) {
    $stmt->bindValue(":START_DATE",$start_date);
  }
  if( $end_date ) {
    $stmt->bindValue(":END_DATE",$end_date);
  }
  $stmt->execute();

  while( ($row=$stmt->fetch()) ) {
    $csv = array();
    $csv[] = $row["WORK_ORDER_NUM"];
    $csv[] = $row["USER_NAME"];
    $csv[] = $row["EMAIL"];
    $csv[] = $row["PHONE"];
    $csv[] = $row["GROUP_NAME"];
    $csv[] = $row["FUNDING_DESCRIPTION"];
    $csv[] = $row["FUNDING_FUND"];
    $csv[] = $row["FUNDING_PROGRAM"];
    $csv[] = $row["FUNDING_DEPT"];
    $csv[] = $row["FUNDING_PROJECT"];
    $csv[] = $row["STATUS"];
    $csv[] = $row["CREATED"];
    $csv[] = $row["REVIEWED"];
    $csv[] = $row["QUEUED"];
    $csv[] = $row["COMPLETED"];
    $csv[] = $row["CANCELED"];
    $csv[] = $row["CLOSED"];
    $csv[] = $row["CONTRACT_NAME"];
    $csv[] = $row["QUOTE"];
    $csv[] = $row["INVENTORY_NUM"];
    $csv[] = $row["HEALTH_HAZARD"];
    $csv[] = $row["DESCRIPTION"];
    $csv[] = $row["ADMIN_NOTES"];

    fputcsv_excel($F,$csv);
  }

  fclose($F);
}

function exportTimesheets() {
  $start_date = isset($_REQUEST["start_date"]) ? $_REQUEST["start_date"] : "";
  $end_date = isset($_REQUEST["end_date"]) ? $_REQUEST["end_date"] : "";
  $who = isset($_REQUEST["who"]) ? $_REQUEST["who"] : "";
  if( $who == "all" ) $who = "";

  $filename = "Timesheet";
  if( $who ) {
    $who_user = new User;
    if( $who_user->loadFromUserID($who) ) {
      $filename .= "_" . makeSafeFileName($who_user->displayName());
    } else {
      $who = "";
      $who_user = null;
    }
  }
  if( $start_date ) {
    $filename .= "_" . makeSafeFileName($start_date);
  }
  $filename .= ".csv";

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  $csv = array("Worker","Work Order Num","Date","Hours","Notes");
  fputcsv_excel($F,$csv);

  $sql = "
  SELECT
    timesheet.*,
    work_order.WORK_ORDER_NUM,
    CONCAT(user.FIRST_NAME,' ',user.LAST_NAME) as 'USER_NAME'
  FROM
    timesheet
  JOIN user
  ON user.USER_ID = timesheet.USER_ID
  JOIN work_order
  ON work_order.WORK_ORDER_ID = timesheet.WORK_ORDER_ID
  WHERE
    True
  ";

  if( $start_date ) {
    $sql .= "\nAND DATE(timesheet.DATE) >= :START_DATE";
  }

  if( $end_date ) {
    $sql .= "\nAND DATE(timesheet.DATE) < :END_DATE";
  }

  if( $who ) {
    $sql .= "\nAND timesheet.USER_ID = :USER_ID";
  }

  $sql .= "\nORDER BY user.LAST_NAME, user.FIRST_NAME, timesheet.DATE, timesheet.TIMESHEET_ID";

  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  if( $start_date ) {
    $stmt->bindValue(":START_DATE",$start_date);
  }
  if( $end_date ) {
    $stmt->bindValue(":END_DATE",$end_date);
  }
  if( $who ) {
    $stmt->bindValue(":USER_ID",$who);
  }
  $stmt->execute();

  while( ($row=$stmt->fetch()) ) {
    $csv = array();
    $csv[] = $row["USER_NAME"];
    $csv[] = $row["WORK_ORDER_NUM"];
    $csv[] = $row["DATE"];
    $csv[] = $row["HOURS"];
    $csv[] = $row["NOTES"];

    fputcsv_excel($F,$csv);
  }

  fclose($F);
}

function showEditUser() {
  global $self_path;

  $user = new User;
  if( isset($_REQUEST["user_id"]) ) {
    $user->loadFromUserID($_REQUEST["user_id"]);
  }
  if( $user->user_id && isAdmin() ) {
    echo "<p><form enctype='multipart/form-data' method='POST' autocomplete='off' action='$self_path'>\n";
    echo "<input type='hidden' name='euid' value='",htmlescape($user->user_id),"'/>\n";
    echo "<input type='submit' value='Log In As ",htmlescape($user->displayName()),"'/>\n";
    if( $user->is_admin ) {
      echo " (non-admin mode)";
    }
    echo "</form></p>\n";
  }
  showUserProfile($user);

  if( $user->user_id ) {
    if( SHOP_SUPPORTS_LOANS ) {
      echo "<hr/>\n";
      $show_loan_history = true;
      showLoans($user,$show_loan_history);
    }
  }
}

function showMaterials() {

  $dbh = connectDB();
  $sql = "SELECT * FROM stock_material ORDER BY NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  echo "<p>Changes to prices apply to any newly added stock on stock cards in existing orders as well as newly created ones.</p>\n";

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='materials'/>\n";

  echo "<table class='records clicksort'>";
  echo "<thead><tr><th>Material</th><th>Density (lb/in<sup>3</sup>)</th><th>Price/Pound</th><th>Price/Pound<br>Last Changed</th><th>Hide</th></tr></thead><tbody id='materials'>\n";
  $mid = 0;
  while( ($row=$stmt->fetch()) ) {
    $mid += 1;
    echo "<tr>";
    echo "<input type='hidden' name='material_id_{$mid}' value='",htmlescape($row["MATERIAL_ID"]),"'/>";
    echo "<td><input name='material_name_{$mid}' value='",htmlescape($row["NAME"]),"'/></td>";
    echo "<td><input name='material_density_{$mid}' value='",htmlescape($row["DENSITY"]),"' size='10'/></td>";
    echo "<td><input name='material_cost_per_pound_{$mid}' class='currency' value='",htmlescape($row["COST_PER_POUND"]),"' size='10'/></td>";
    echo "<td>",htmlescape($row["COST_PER_POUND_UPDATED"]),"</td>";
    $checked = $row["HIDE"] ? "checked" : "";
    echo "<td><input name='material_hide_{$mid}' type='checkbox' value='1' $checked/></td>";
    echo "</tr>";
  }
  echo "</tbody></table>\n";
  echo "<p><button class='btn btn-secondary noprint' onclick='addMaterial(); return false;'>Add Material</button></p>\n";
  echo "<p><input type='submit' value='Submit' class='noprint'/></p>\n";
  echo "</form>\n";
  ?><script>
  var mid = 0;
  function addMaterial() {
    mid += 1;
    var tr = document.createElement('tr');
    tr.innerHTML = "<?php
      echo "<input type='hidden' name='new_material_id_\" + mid + \"' value='1'/>";
      echo "<td><input name='new_material_name_\" + mid + \"'/></td>";
      echo "<td><input name='new_material_density_\" + mid + \"' size='10'/></td>";
      echo "<td><input name='new_material_cost_per_pound_\" + mid + \"' class='currency' size='10'/></td>";
      echo "<td></td>";
      echo "<td><input name='new_material_hide_\" + mid + \"' type='checkbox' value='1'/></td>";
    ?>";
    var container = document.getElementById('materials');
    container.appendChild(tr);
  }
  </script><?php
}

function saveMaterials($web_user) {
  if( !isAdmin() ) {
    echo "<p class='alert alert-danger'>Only administrators can modify materials.</p>\n";
    return;
  }

  $dbh = connectDB();
  $sql = "
    UPDATE
      stock_material
    SET
      NAME = :NAME,
      DENSITY = :DENSITY,
      COST_PER_POUND_UPDATED = IF(COST_PER_POUND IS NULL AND :COST_PER_POUND IS NULL OR COST_PER_POUND IS NOT NULL AND :COST_PER_POUND IS NOT NULL AND COST_PER_POUND = :COST_PER_POUND,COST_PER_POUND_UPDATED,now()),
      COST_PER_POUND = :COST_PER_POUND,
      HIDE = :HIDE
    WHERE
      MATERIAL_ID = :MATERIAL_ID";
  $update_stmt = $dbh->prepare($sql);

  $sql = "DELETE FROM stock_material WHERE MATERIAL_ID = :MATERIAL_ID";
  $delete_stmt = $dbh->prepare($sql);

  $sql = "SELECT COUNT(*) as COUNT FROM stock_order WHERE MATERIAL_ID = :MATERIAL_ID";
  $in_use_stmt = $dbh->prepare($sql);

  $stock_materials = getStockMaterials();
  foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^material_id_([0-9]+)$/',$key,$match) ) continue;
    $mid = $match[1];

    $material_id = $_REQUEST["material_id_{$mid}"];
    $before_edit = getStockMaterial($material_id);
    $name = $_REQUEST["material_name_{$mid}"];
    $density = $_REQUEST["material_density_{$mid}"];
    $cost_per_pound = $_REQUEST["material_cost_per_pound_{$mid}"];
    $hide = (isset($_REQUEST["material_hide_{$mid}"]) && $_REQUEST["material_hide_{$mid}"] == '1') ? 1 : 0;

    if( $name == "" ) {
      $in_use_stmt->bindValue(":MATERIAL_ID",$material_id);
      $in_use_stmt->execute();
      $in_use = $in_use_stmt->fetch()["COUNT"];
      if( !$in_use ) {
        $delete_stmt->bindValue(":MATERIAL_ID",$material_id);
	$delete_stmt->execute();
	continue;
      }
    }

    $update_stmt->bindValue(":MATERIAL_ID",$material_id);
    $update_stmt->bindValue(":NAME",$name);
    $update_stmt->bindValue(":DENSITY",$density);
    $update_stmt->bindValue(":COST_PER_POUND",$cost_per_pound <> "" ? $cost_per_pound : null);
    $update_stmt->bindValue(":HIDE",$hide);
    $update_stmt->execute();

    $after_edit = getStockMaterial($material_id);
    auditlogModifyMaterial($web_user,$before_edit,$after_edit);
  }

  $sql = "
    INSERT INTO
      stock_material
    SET
      NAME = :NAME,
      DENSITY = :DENSITY,
      COST_PER_POUND_UPDATED = IF(:COST_PER_POUND IS NULL,NULL,now()),
      COST_PER_POUND = :COST_PER_POUND,
      HIDE = :HIDE
  ";
  $insert_stmt = $dbh->prepare($sql);

  foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^new_material_id_([0-9]+)$/',$key,$match) ) continue;
    $mid = $match[1];

    $name = $_REQUEST["new_material_name_{$mid}"];
    $density = $_REQUEST["new_material_density_{$mid}"];
    $cost_per_pound = $_REQUEST["new_material_cost_per_pound_{$mid}"];
    $hide = (isset($_REQUEST["new_material_hide_{$mid}"]) && $_REQUEST["new_material_hide_{$mid}"] == '1') ? 1 : 0;

    if( !$name ) continue;

    $insert_stmt->bindValue(":NAME",$name);
    $insert_stmt->bindValue(":DENSITY",$density);
    $insert_stmt->bindValue(":COST_PER_POUND",$cost_per_pound <> "" ? $cost_per_pound : null);
    $insert_stmt->bindValue(":HIDE",$hide);
    $insert_stmt->execute();
    $material_id = $dbh->lastInsertId();

    $after_edit = getStockMaterial($material_id);
    auditlogModifyMaterial($web_user,null,$after_edit);
  }
}

function showShopRates() {

  $dbh = connectDB();
  $sql = "SELECT * FROM shop_contract";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  echo "<p>Changes to shop contract rates will apply to newly created orders and orders in which a change is made to the selected shop contract.</p>\n";

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='shop_rates'/>\n";

  echo "<table class='records clicksort'>";
  echo "<thead><tr><th>Contract</th><th>Hourly Rate</th><th>Last Change</th><th>Hide</th></tr></thead><tbody id='contracts'>\n";
  $mid = 0;
  while( ($row=$stmt->fetch()) ) {
    $mid += 1;
    echo "<tr>";
    echo "<input type='hidden' name='contract_id_{$mid}' value='",htmlescape($row["CONTRACT_ID"]),"'/>";
    echo "<td><input name='contract_name_{$mid}' value='",htmlescape($row["CONTRACT_NAME"]),"'/></td>";
    echo "<td><input name='contract_contract_hourly_rate_{$mid}' class='currency' value='",htmlescape($row["CONTRACT_HOURLY_RATE"]),"' size='10'/></td>";
    echo "<td>",htmlescape($row["CONTRACT_HOURLY_RATE_UPDATED"]),"</td>";
    $checked = $row["HIDE"] ? "checked" : "";
    echo "<td><input name='contract_hide_{$mid}' type='checkbox' value='1' $checked/></td>";
    echo "</tr>";
  }
  echo "</tbody></table>\n";
  echo "<p><button class='btn btn-secondary noprint' onclick='addContract(); return false;'>Add Contract</button></p>\n";
  echo "<p><input type='submit' value='Submit' class='noprint'/></p>\n";
  echo "</form>\n";
  ?><script>
  var mid = 0;
  function addContract() {
    mid += 1;
    var tr = document.createElement('tr');
    tr.innerHTML = "<?php
      echo "<input type='hidden' name='new_contract_id_\" + mid + \"' value='1'/>";
      echo "<td><input name='new_contract_name_\" + mid + \"'/></td>";
      echo "<td><input name='new_contract_contract_hourly_rate_\" + mid + \"' class='currency' size='10'/></td>";
      echo "<td></td>";
      echo "<td><input name='new_contract_hide_\" + mid + \"' type='checkbox' value='1'/></td>";
    ?>";
    var container = document.getElementById('contracts');
    container.appendChild(tr);
    $(tr).find('input[type!=hidden]')[0].focus();
  }
  </script><?php
}

function saveContracts($web_user) {
  if( !isAdmin() ) {
    echo "<p class='alert alert-danger'>Only administrators can modify contracts.</p>\n";
    return;
  }

  $dbh = connectDB();
  $sql = "
    UPDATE
      shop_contract
    SET
      CONTRACT_NAME = :CONTRACT_NAME,
      CONTRACT_HOURLY_RATE_UPDATED = IF(CONTRACT_HOURLY_RATE IS NULL AND :CONTRACT_HOURLY_RATE IS NULL OR CONTRACT_HOURLY_RATE IS NOT NULL AND :CONTRACT_HOURLY_RATE IS NOT NULL AND CONTRACT_HOURLY_RATE = :CONTRACT_HOURLY_RATE,CONTRACT_HOURLY_RATE_UPDATED,now()),
      CONTRACT_HOURLY_RATE = :CONTRACT_HOURLY_RATE,
      HIDE = :HIDE
    WHERE
      CONTRACT_ID = :CONTRACT_ID";
  $update_stmt = $dbh->prepare($sql);

  $sql = "DELETE FROM shop_contract WHERE CONTRACT_ID = :CONTRACT_ID";
  $delete_stmt = $dbh->prepare($sql);

  $sql = "SELECT COUNT(*) as COUNT FROM stock_order WHERE CONTRACT_ID = :CONTRACT_ID";
  $in_use_stmt = $dbh->prepare($sql);

  foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^contract_id_([0-9]+)$/',$key,$match) ) continue;
    $mid = $match[1];

    $contract_id = $_REQUEST["contract_id_{$mid}"];
    $before_edit = getContract($contract_id);
    $name = $_REQUEST["contract_name_{$mid}"];
    $contract_hourly_rate = $_REQUEST["contract_contract_hourly_rate_{$mid}"];
    $hide = (isset($_REQUEST["contract_hide_{$mid}"]) && $_REQUEST["contract_hide_{$mid}"] == '1') ? 1 : 0;

    if( $name == "" ) {
      $in_use_stmt->bindValue(":CONTRACT_ID",$contract_id);
      $in_use_stmt->execute();
      $in_use = $in_use_stmt->fetch()["COUNT"];
      if( !$in_use ) {
        $delete_stmt->bindValue(":CONTRACT_ID",$contract_id);
	$delete_stmt->execute();
	continue;
      }
    }

    $update_stmt->bindValue(":CONTRACT_ID",$contract_id);
    $update_stmt->bindValue(":CONTRACT_NAME",$name);
    $update_stmt->bindValue(":CONTRACT_HOURLY_RATE",$contract_hourly_rate <> "" ? $contract_hourly_rate : null);
    $update_stmt->bindValue(":HIDE",$hide);
    $update_stmt->execute();

    $after_edit = getContract($contract_id);
    auditlogModifyContract($web_user,$before_edit,$after_edit);
  }

  $sql = "
    INSERT INTO
      shop_contract
    SET
      CONTRACT_NAME = :CONTRACT_NAME,
      CONTRACT_HOURLY_RATE_UPDATED = IF(:CONTRACT_HOURLY_RATE IS NULL,NULL,now()),
      CONTRACT_HOURLY_RATE = :CONTRACT_HOURLY_RATE,
      HIDE = :HIDE
  ";
  $insert_stmt = $dbh->prepare($sql);

  foreach( $_REQUEST as $key => $value ) {
    if( !preg_match('/^new_contract_id_([0-9]+)$/',$key,$match) ) continue;
    $mid = $match[1];

    $name = $_REQUEST["new_contract_name_{$mid}"];
    $contract_hourly_rate = $_REQUEST["new_contract_contract_hourly_rate_{$mid}"];
    $hide = (isset($_REQUEST["new_contract_hide_{$mid}"]) && $_REQUEST["new_contract_hide_{$mid}"] == '1') ? 1 : 0;

    if( !$name ) continue;

    $insert_stmt->bindValue(":CONTRACT_NAME",$name);
    $insert_stmt->bindValue(":CONTRACT_HOURLY_RATE",$contract_hourly_rate <> "" ? $contract_hourly_rate : null);
    $insert_stmt->bindValue(":HIDE",$hide);
    $insert_stmt->execute();
    $contract_id = $dbh->lastInsertId();

    $after_edit = getContract($contract_id);
    auditlogModifyContract($web_user,null,$after_edit);
  }
}

function showWorkHistory($web_user) {

  if( isAdmin() && isset($_REQUEST["who"]) ) {
    $worker_id = $_REQUEST["who"];
  } else {
    $worker_id = $web_user->user_id;
  }

  $worker = new User();
  if( !$worker->loadFromUserID($worker_id) ) {
    echo "<div class='alert alert-danger'>No user found with ID ",htmlescape($worker_id),"</div>\n";
    return;
  }

  $dbh = connectDB();
  $sql = "SELECT MAX(DATE) as MAX_DATE FROM timesheet where USER_ID = :USER_ID";
  $max_stmt = $dbh->prepare($sql);
  $max_stmt->bindValue(":USER_ID",$worker_id);
  $max_stmt->execute();
  $max_date = date("Y-m-d");
  $row = $max_stmt->fetch();
  if( $row ) {
    if( $row['MAX_DATE'] > $max_date ) {
      $max_date = $row['MAX_DATE'];
    }
  }

  $page = new TimePager('work_history',"weekly",null,null,$max_date);
  $page->offerWeeklyPaging(true);

  $title = $worker->displayName() . "'s";
  $title .= " Work History ";
  $title .= $page->time_title;

  echo "<h2>",htmlescape($title),"</h2>\n";


  echo "<p>";
  $base_url = "?s=work_history&who=" . $worker_id;

  $page->pageButtons($base_url);
  $page->moreOptionsButton();
  $page->moreOptions(array("who" => $worker_id));

  if( $page->by_week ) {
    showWeekTimesheet($page->start,$page->end,$worker_id);
    echo "<p>&nbsp;</p>\n";
  }

  $sql = "
    SELECT
      work_order.WORK_ORDER_ID,
      work_order.WORK_ORDER_NUM,
      SUM(timesheet.HOURS) as THIS_PERIOD_HOURS
    FROM
      timesheet
    JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = timesheet.WORK_ORDER_ID
    WHERE
      timesheet.USER_ID = :WORKER_ID
      AND timesheet.DATE >= :START_DATE
      AND timesheet.DATE < :END_DATE
    GROUP BY
      work_order.WORK_ORDER_ID,work_order.WORK_ORDER_NUM
    ORDER BY
      work_order.WORK_ORDER_NUM
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORKER_ID",$worker_id);
  $stmt->bindValue(":START_DATE",$page->start);
  $stmt->bindValue(":END_DATE",$page->end);
  $stmt->execute();

  echo "<table class='records clicksort'><thead><tr><th>Job</th><th>Hours</th></tr></thead><tbody>\n";

  $total_hours = 0;
  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=work_order&id=" . $row["WORK_ORDER_ID"];
    echo "<td><a href='",htmlescape($url),"'>",$row["WORK_ORDER_NUM"],"</a></td>";
    echo "<td class='align-right'>",htmlescape($row["THIS_PERIOD_HOURS"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody>";
  echo "<tfoot><tr><th>Total</th><td class='align-right'>",sprintf("%.2f",$total_hours),"</td></tr></tfoot>\n";
  echo "</table>\n";
}

function showWeekTimesheet($this_week,$next_week,$worker_id,$workers=null) {
  $dbh = connectDB();
  $sql = "SELECT timesheet.*,work_order.WORK_ORDER_NUM FROM timesheet JOIN work_order ON work_order.WORK_ORDER_ID = timesheet.WORK_ORDER_ID WHERE DATE >= :START_DATE AND DATE < :END_DATE";
  if( $worker_id ) {
    $sql .= " AND timesheet.USER_ID = :USER_ID";
  }
  $timesheet_stmt = $dbh->prepare($sql);
  $timesheet_stmt->bindValue(":START_DATE",$this_week);
  $timesheet_stmt->bindValue(":END_DATE",$next_week);
  if( $worker_id ) {
    $timesheet_stmt->bindValue(":USER_ID",$worker_id);
  }
  $timesheet_stmt->execute();

  $timesheet = array();
  $show_weekend = false;
  while( ($row=$timesheet_stmt->fetch()) ) {
    if( !isset($timesheet[$row["USER_ID"]]) ) {
      $timesheet[$row["USER_ID"]] = array(array(),array(),array(),array(),array(),array(),array());
    }
    $week_day = (int)date("w",strtotime($row["DATE"]));
    if( $week_day == 0 || $week_day == 6 ) {
      $show_weekend = true;
    }
    $timesheet[$row["USER_ID"]][$week_day][] = $row;
  }

  if( !$show_weekend ) {
    echo "<style>.weekend {display: none;}</style>\n";
  }

  if( !$workers && $worker_id ) {
    $workers = array($worker_id => "");
  }

  echo "<table class='records' style='border: none'><tbody>\n";
  foreach( $workers as $user_id => $name ) {

    echo "<tr><td style='border: none; padding-top: 1em' colspan='14'></td></tr>\n";
    if( !$worker_id ) {
      $url = "?s=work_history&who=" . $user_id;
      echo "<tr class='centered dark'><th colspan='14' style='padding-top: 0.75em; padding-bottom: 0.25em;'><a href='",htmlescape($url),"'>",htmlescape($name),"</a></th></tr>\n";
    }
    echo "<tr class='centered dark'><th colspan='2' class='weekend'>Sunday</th><th colspan='2'>Monday</th><th colspan='2'>Tuesday</th><th colspan='2'>Wednesday</th><th colspan='2'>Thursday</th><th colspan='2'>Friday</th><th colspan='2' class='weekend'>Saturday</th><th>Total</th></tr>\n";

    $rows=0;
    for( $week_day=0; $week_day<7; $week_day++ ) {
      $l = isset($timesheet[$user_id]) ? count($timesheet[$user_id][$week_day]) : 0;
      if( $l > $rows ) $rows = $l;
    }

    for( $r=0; $r<$rows; $r++ ) {
      echo "<tr>";
      for( $week_day=0; $week_day<7; $week_day++ ) {
        $weekend = ($week_day==0 || $week_day==6) ? "weekend" : "";
        $row = isset($timesheet[$user_id][$week_day][$r]) ? $timesheet[$user_id][$week_day][$r] : null;
        if( $row ) {
          $url = "?s=work_order&id=" . $row["WORK_ORDER_ID"];
          echo "<td class='align-right $weekend' style='padding-left: 1em'><a href='$url'>",htmlescape($row["WORK_ORDER_NUM"]),"</a>";
          if( $row["NOTES"] ) {
            echo " <span title='",htmlescape($row["NOTES"]),"'>*</span>";
          }
          echo "</td>";
          echo "<td class='align-right $weekend' style='padding-right: 1em'>",htmlescape($row["HOURS"]),"</td>";
        } else {
          echo "<td class='$weekend'></td><td class='$weekend'></td>";
        }
      }
      echo "<td></td></tr>";
    }

    echo "<tr>";
    $total_printed = false;
    $week_total = 0;
    for( $week_day=0; $week_day<7; $week_day++ ) {
      $weekend = ($week_day==0 || $week_day==6) ? "weekend" : "";
      $total = 0;
      if( isset($timesheet[$user_id]) ) {
        foreach( $timesheet[$user_id][$week_day] as $row ) {
          $total += $row["HOURS"];
        }
      }
      $week_total += $total;
      if( $total && !$total_printed ) {
        $total_printed = true;
        echo "<th class='$weekend'>Total</th>";
      } else {
        echo "<td class='$weekend'></td>";
      }
      if( $total ) {
        echo "<td class='align-right $weekend' style='padding-right: 1em'>",htmlescape(sprintf("%.2f",$total)),"</td>";
      } else {
        echo "<td class='align-right $weekend'></td>";
      }
    }
    echo "<td class='align-right' style='padding-right: 1em'>",sprintf("%.2f",$week_total),"</td>";
    echo "</tr>";
  }
  echo "</tbody></table>\n";
}

function showWorkHistoryOfAll($web_user) {

  $page = new TimePager('all_work_history',"weekly");
  $page->offerWeeklyPaging(true);

  $title = "Work History ";
  $title .= $page->time_title;

  echo "<h2>",htmlescape($title),"</h2>\n";


  echo "<p>";
  $base_url = "?s=all_work_history";

  $page->pageButtons($base_url);
  $page->moreOptionsButton();
  $page->moreOptions();

  $dbh = connectDB();
  $sql = "
    SELECT
      work_order.WORK_ORDER_ID,
      work_order.WORK_ORDER_NUM,
      user.user_id,
      user.FIRST_NAME,
      user.LAST_NAME,
      SUM(timesheet.HOURS) as THIS_PERIOD_HOURS
    FROM
      timesheet
    JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = timesheet.WORK_ORDER_ID
    JOIN
      user
    ON
      user.USER_ID =  timesheet.USER_ID
    WHERE
      timesheet.DATE >= :START_DATE
      AND timesheet.DATE < :END_DATE
    GROUP BY
      work_order.WORK_ORDER_ID,work_order.WORK_ORDER_NUM,user.user_id,user.FIRST_NAME,user.LAST_NAME
    ORDER BY
      work_order.WORK_ORDER_NUM
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START_DATE",$page->start);
  $stmt->bindValue(":END_DATE",$page->end);
  $stmt->execute();

  $workers = array();
  $worker_total = array();
  $jobs = array();
  $job_hours = array();
  $job_total = array();
  while( ($row=$stmt->fetch()) ) {
    $worker_id = $row["USER_ID"];
    $workers[$worker_id] = $row["LAST_NAME"];
    if( !isset($worker_total[$worker_id]) ) $worker_total[$worker_id] = 0;
    $worker_total[$worker_id] += $row["THIS_PERIOD_HOURS"];

    $job_id = $row["WORK_ORDER_ID"];
    $jobs[$job_id] = $row["WORK_ORDER_NUM"];
    if( !isset($job_hours[$job_id]) ) $job_hours[$job_id] = array();
    if( !isset($job_hours[$job_id][$worker_id]) ) $job_hours[$job_id][$worker_id] = 0;
    $job_hours[$job_id][$worker_id] += $row["THIS_PERIOD_HOURS"];

    if( !isset($job_total[$job_id]) ) $job_total[$job_id] = 0;
    $job_total[$job_id] += $row["THIS_PERIOD_HOURS"];
  }

  asort($workers);

  echo "<table class='records clicksort'><thead><tr><th>Job</th><th>Total</th>";
  foreach( $workers as $worker_id => $worker ) {
    echo "<th>",htmlescape($worker),"</th>";
  }
  echo "</tr></thead><tbody>\n";

  $total_hours = 0;
  foreach( $jobs as $job_id => $job_name ) {
    echo "<tr class='record'>";
    $url = "?s=work_order&id=" . $job_id;
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($job_name),"</a></td>";
    echo "<td class='align-right'>",sprintf("%.2f",$job_total[$job_id]),"</td>";

    foreach( $workers as $worker_id => $worker ) {
      $hours = isset($job_hours[$job_id][$worker_id]) ? $job_hours[$job_id][$worker_id] : 0;
      $total_hours += $hours;
      if( $hours > 0 ) {
        echo "<td class='align-right'>",sprintf("%.2f",$hours),"</td>";
      } else {
        echo "<td></td>";
      }
    }
    echo "</tr>\n";
  }
  echo "</tbody>";
  echo "<tfoot><tr><th>Total</th><th class='align-right'>",sprintf("%.2f",$total_hours),"</th>";
  foreach( $workers as $worker_id => $worker ) {
    $hours = isset($worker_total[$worker_id]) ? $worker_total[$worker_id] : 0;
    echo "<th class='align-right'>",sprintf("%.2f",$hours),"</th>";
  }
  echo "</tr></tfoot>\n";
  echo "</table>\n";
}

function sendCompletionEmail() {
  global $self_full_url;

  $woid = $_REQUEST["id"];
  $dbh = connectDB();

  if( !isset($_REQUEST['already_sent']) ) {
    $sql = "SELECT FIRST_NAME,EMAIL,WORK_ORDER_NUM FROM user JOIN work_order ON work_order.ORDERED_BY = user.USER_ID WHERE work_order.WORK_ORDER_ID = :WORK_ORDER_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":WORK_ORDER_ID",$woid);
    $stmt->execute();
    $row = $stmt->fetch();
    if( !$row ) {
      echo "(failed to find order)";
      return;
    }
    if( !$row["EMAIL"] ) {
      echo "(failed to find email)";
      return;
    }

    $subject = SHOP_NAME . " order " . $row["WORK_ORDER_NUM"] . " is ready";
    $body = array();
    $body[] = "<html><head><meta charset='UTF-8'></head>";
    $body[] = "<body>";
    $body[] = "<p>Hello " . $row["FIRST_NAME"] . ",</p>";
    $url = $self_full_url . "?s=work_order&id=" . $woid;
    $body[] = "<p>Your order <a href='" . htmlescape($url) . "'>" . $row["WORK_ORDER_NUM"] . "</a> is ready.  Please drop by to pick it up.</p>";
    $body[] = "<p>Sincerely,<br>";
    $body[] = SHOP_NAME . " notification robot";
    $body[] = "</body></html>";

    $body = implode("\r\n",$body);

    $headers = array();
    $headers[] = "From: " . SHOP_NAME . " <" . SHOP_FROM_EMAIL . ">";
    $headers[] = "Reply-To: " . SHOP_ADMIN_NAME . " <" . SHOP_ADMIN_EMAIL . ">";
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';

    $headers = implode("\r\n",$headers);

    $to = $row["EMAIL"];
    if( !mail($to,$subject,$body,$headers,"-f " . SHOP_FROM_EMAIL) ) {
      echo "Failed to send email to ",htmlescape($to),".";
      return;
    }
  }

  $sql = "UPDATE work_order SET COMPLETION_EMAIL_SENT=now() WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$woid);
  $stmt->execute();
  echo "(sent on " . date("Y-m-d") . " at " . date("g:ia") . ")";
}

function getMyWorkOrderSQL() {
  $sql = "AND (ORDERED_BY = :ORDERED_BY";
  $sql .= " OR EXISTS (
    SELECT funding_source.FUNDING_SOURCE_ID
    FROM funding_source
    WHERE
      funding_source.FUNDING_SOURCE_ID = work_order.FUNDING_SOURCE_ID
      AND (funding_source.PI_EMAIL = :WEB_USER_EMAIL
      OR   funding_source.BILLING_CONTACT_EMAIL = :WEB_USER_EMAIL)))";
  return $sql;
}

function showPickUp($web_user) {
  echo "<p>Use this form to record that you are picking up a work order";
  if( ENABLE_STOCK ) {
    echo " or stock order.  Note that stock order numbers begin with an 'S'";
  }
  echo ".</p>\n";
  $slash_Stock = ENABLE_STOCK ? "/Stock" : "";
  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='pickup'/>\n";
  echo "<input type='hidden' name='s' value='work_order'/>\n";
  $YY = date("y");
  $wo = isset($_REQUEST["wo"]) ? $_REQUEST["wo"] : "";
  echo "Work{$slash_Stock} Order Number: <input type='text' name='wo' value='",htmlescape($wo),"' placeholder='e.g. $YY-",SHOP_WORK_ORDER_CHAR,"123";
  if( ENABLE_STOCK ) {
    echo " or ",SHOP_WORK_ORDER_CHAR,"S$YY-",SHOP_WORK_ORDER_CHAR,"123";
  }
  echo "'/><br>";
  echo "<input type='submit' value='Submit'/>\n";
  echo "</form>\n";

  $my_work_order_sql = getMyWorkOrderSQL();
  $sql = "
    SELECT
      WORK_ORDER_NUM,
      WORK_ORDER_ID
    FROM
      work_order
    WHERE
     PICKED_UP_DATE IS NULL
     $my_work_order_sql
     AND CANCELED IS NULL
     AND (COMPLETED IS NULL OR DATEDIFF(NOW(),COMPLETED) > 30)
     AND (CLOSED IS NULL OR DATEDIFF(NOW(),CLOSED) > 30)
  ";

  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ORDERED_BY",$web_user->user_id);
  $stmt->bindValue(":WEB_USER_EMAIL",$web_user->email);
  $stmt->execute();

  $header_done = false;
  while( ($row=$stmt->fetch()) ) {
    if( !$header_done ) {
      $header_done = true;
      echo "<p>&nbsp;</p><p>Or choose from the following list of your recent orders:</p>\n";
    }
    echo "<form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
    echo "<input type='hidden' name='form' value='pickup'/>\n";
    echo "<input type='hidden' name='s' value='work_order'/>\n";
    echo "<input type='hidden' name='wo' value='",htmlescape($row["WORK_ORDER_NUM"]),"'/>\n";
    echo "<a href='?wo=",htmlescape($row["WORK_ORDER_NUM"]),"' onclick='this.closest(\"form\").submit(); return false;'>",htmlescape($row["WORK_ORDER_NUM"]),"</a>";
    $url = "?s=work_order&id=" . $row["WORK_ORDER_ID"];
    echo " &nbsp; (<a href='",htmlescape($url),"'>see ",htmlescape($row["WORK_ORDER_NUM"])," details</a>)\n";
    echo "</form>\n";
  }
}

function pickUpWorkOrder($web_user,&$show) {
  $wo = isset($_REQUEST["wo"]) ? $_REQUEST["wo"] : "";

  $dbh = connectDB();
  $sql = "
    SELECT
      WORK_ORDER_ID,
      PICKED_UP_DATE,
      FIRST_NAME,
      LAST_NAME
    FROM
      work_order
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.PICKED_UP_BY
    WHERE
      work_order.WORK_ORDER_NUM = :WORK_ORDER_NUM
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_NUM",$wo);
  $stmt->execute();
  $row = $stmt->fetch();

  $work = "work";
  if( preg_match("{^S}",$wo) ) {
    $work = "stock";
  }

  if( !$row ) {
    echo "<div class='alert alert-danger'>No $work order found with order number '",htmlescape($wo),"'.</div>\n";
    $show = "pickup";
    return;
  }
  if( $row["PICKED_UP_DATE"] ) {
    echo "<div class='alert alert-danger'>Order ",htmlescape($wo)," was already picked up";
    if( $row["FIRST_NAME"] || $row["LAST_NAME"] ) {
      echo " by ",htmlescape($row["FIRST_NAME"])," ",htmlescape($row["LAST_NAME"]);
    }
    echo " on ",date("M j, Y",strtotime($row["PICKED_UP_DATE"]))," at ",date("g:ia",strtotime($row["PICKED_UP_DATE"])),".</div>\n";
    $show = "pickup";
    return;
  }

  $sql = "
    UPDATE
      work_order
    SET
      PICKED_UP_BY = :USER_ID,
      PICKED_UP_DATE = now()
    WHERE
      WORK_ORDER_ID = :WORK_ORDER_ID
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":USER_ID",$web_user->user_id);
  $id = $row["WORK_ORDER_ID"];
  $stmt->bindValue(":WORK_ORDER_ID",$id);
  $stmt->execute();

  echo "<div class='alert alert-danger'>Recorded pickup of $work order '",htmlescape($wo),"'.</div>\n";
}
