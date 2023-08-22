<?php
  ini_set('display_errors', 'On');

  require_once "db.php";
  require_once "common.php";
  require_once "config.php";
  require_once "post_config.php";
  require_once "libphp/excelcsv.php";
  require_once "loans.php";
  require_once "billing.php";

  initLogin();

  if( isset($_REQUEST["f"]) ) {
    switch($_REQUEST["f"]) {
    case "purchases":
      if( isAdmin() ) {
        downloadPurchases();
      }
      break;
    case "parts":
      if( isAdmin() ) {
        downloadParts();
      }
      break;
    case "delete_checkout":
      deleteCheckout($web_user);
      break;
    }
    exit;
  }

  if( isset($_REQUEST["s"]) ) {
    $show = $_REQUEST["s"];
    switch($show) {
      case "work_order":
        header('Location: ' . SHOP_URL . "/workorder/?" . $_SERVER["QUERY_STRING"]);
	exit;
      default:
        doBillingFileExports($show);
	break;
    }
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
  <script src="<?php echo WEBAPP_TOP ?>english.js"></script>

</head>
<body>

<?php

if( !$web_user ) {
  ?>
    <main role="main" class="container">
    <div class="bg-light p-5 rounded text-center">
      <h1>Welcome to the UW Physics <?php echo htmlescape(SHOP_NAME)  ?></h1>
      <p class="lead">Please use this form to record purchases.</p>
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
      <p>(To set up a group account, contact help@physics.wisc.edu or enable group access in <a href='#' onclick='login("profile")'>your profile</a>.)</p>
      <?php } ?>
      <noscript>ERROR: javascript is disabled and is required by this web app.</noscript>
      <?php echo SHOP_LOGIN_NOTICE ?>
    </div>
    </main>
  <?php
} else {

  $show = isset($_REQUEST["s"]) ? $_REQUEST["s"] : "";

  showNavbar($web_user,$show);

  if( isset($_POST["form"]) ) {
    $form = $_POST["form"];
    switch($form) {
      case "user_profile":
        saveUserProfile($web_user,$show);
        break;
      case "loan":
        saveLoan($web_user);
        break;
      case "returned":
        returnLoan();
        break;
      case "edit_group":
        saveGroup($web_user,$show);
        break;
      case "checkout":
        saveCheckout($web_user,$show);
        break;
      case "part":
        if( isAdmin() ) {
          savePart($web_user,$show);
        }
        break;
      case "vendor":
        if( isAdmin() ) {
          saveVendor($web_user,$show);
        }
        break;
      case "order":
        if( isAdmin() ) {
          saveOrder($web_user,$show);
        }
        break;
      case "batch_order":
        if( isAdmin() ) {
          saveBatchOrder($web_user,$show);
        }
        break;
      default:
        doBillingFormPosts($form);
        break;
    }
  }

  $page_class = "container";
  switch($show) {
    case "users":
    case "purchases":
    case "history":
    case "parts":
    case "order":
    case "auditlog":
      # get rid of left margin on these pages with wide tables
      $page_class = "container-fluid";
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
    case "profile":
      showUserProfile($web_user);
      break;
    case "worklog":
      showWorklog($web_user);
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
    case "loans":
      if( isAdmin() ) {
        showLoans();
      }
      break;
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
    case "groups":
      showGroups();
      break;
    case "purchases":
      if( isAdmin() ) {
        showPurchases();
      }
      break;
    case "history":
      showPurchases($web_user);
      break;
    case "parts":
      if( isAdmin() ) {
        showParts();
      }
      break;
    case "browse":
      browseParts();
      break;
    case "part":
      if( isAdmin() ) {
        editPart();
      }
      break;
    case "vendors":
      if( isAdmin() ) {
        showVendors();
      }
      break;
    case "vendor":
      if( isAdmin() ) {
        editVendor();
      }
      break;
    case "orders":
      if( isAdmin() ) {
        showOrders();
      }
      break;
    case "order":
      if( isAdmin() ) {
        editOrder();
      }
      break;
    case "batch_order":
      if( isAdmin() ) {
        showBatchOrder();
      }
      break;
    default:
      if( !showBillingPage($show) ) {
        showDashboard($web_user);
      }
      break;
  }

  echo "</main>\n";
}

echoShopLoginJavascript();

?>

</body>
</html>

<?php

function getCheckoutRecord($checkout_id) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from checkout WHERE CHECKOUT_ID = :CHECKOUT_ID");
  $stmt->bindValue(":CHECKOUT_ID",$checkout_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getPartRecordFromStockNum($stock_num) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from part WHERE STOCK_NUM = :STOCK_NUM");
  $stmt->bindValue(":STOCK_NUM",$stock_num);
  $stmt->execute();
  return $stmt->fetch();
}

function getVendorRecord($vendor_id) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from vendor WHERE VENDOR_ID = :VENDOR_ID");
  $stmt->bindValue(":VENDOR_ID",$vendor_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getOrderRecord($order_id) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from part_order WHERE ORDER_ID = :ORDER_ID");
  $stmt->bindValue(":ORDER_ID",$order_id);
  $stmt->execute();
  return $stmt->fetch();
}

function showNavbar($user,$show) {
?>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
      <span class="navbar-brand" href="#"><img src="<?php echo WEBAPP_TOP ?>uwcrest_web_sm.png" height="30" class="d-inline-block align-top" alt="UW"> Physics <?php echo htmlescape(SHOP_NAME) ?></span>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav me-auto">
          <li class="nav-item <?php echo $show=="" ? "active" : ""  ?>">
            <a class="nav-link" href=".">Checkout</a>
          </li>
          <li class="nav-item <?php echo $show=="browse" ? "active" : "" ?>">
            <a class="nav-link" href="?s=browse">Browse</a>
          </li>
          <li class="nav-item <?php echo $show=="profile" ? "active" : "" ?>">
            <a class="nav-link" href="?s=profile">Profile</a>
          </li>
          <li class="nav-item <?php echo $show=="history" ? "active" : "" ?>">
            <a class="nav-link" href="?s=history">History</a>
          </li>
          <?php if(isAdmin()) { ?>
            <li class="navbar-text admin-only">&nbsp;&nbsp;<small>Admin:</small></li>
            <li class="nav-item admin-only <?php echo $show=="users" ? "active" : "" ?>">
              <a class="nav-link" href="?s=users">Users</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="groups" ? "active" : "" ?>">
              <a class="nav-link" href="?s=groups">Funds</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="purchases" ? "active" : "" ?>">
              <a class="nav-link" href="?s=purchases">Purchases</a>
            </li>
            <?php if(SHOP_SUPPORTS_LOANS) { ?>
            <li class="nav-item admin-only <?php echo $show=="loans" ? "active" : "" ?>">
              <a class="nav-link" href="?s=loans">Loans</a>
            </li>
            <?php } ?>
            <li class="nav-item admin-only <?php echo $show=="parts" ? "active" : "" ?>">
              <a class="nav-link" href="?s=parts">Parts</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="vendors" ? "active" : "" ?>">
              <a class="nav-link" href="?s=vendors">Vendors</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="orders" ? "active" : "" ?>">
              <a class="nav-link" href="?s=orders">Orders</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="billing" ? "active" : "" ?>">
              <a class="nav-link" href="?s=billing">Billing</a>
            </li>
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
        ?>
        <button class="btn btn-secondary my-2 my-sm-0" onclick='logout(); return false;'>Log Out</button>
      </div>
    </nav>
<?php
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
    echo "<hr/>\n";
    $show_loan_history = true;
    showLoans($user,$show_loan_history);

    echo "<hr/>\n";
    showPurchases($user);

    echo "<hr/>\n";
    showAuditLog($user);
  }
}

function showDashboard($user) {
  showCheckout($user);
  if( SHOP_SUPPORTS_LOANS ) {
    echo "<hr class='noprint'/>\n";
    showCurrentLoans($user);
  }
}

function saveCheckout($user,&$show) {

  if( isset($_REQUEST["submit"]) && $_REQUEST["submit"] == "Cancel" ) {
    echo "<div class='alert alert-warning'>Cancelled.  Please remember to <button class='btn btn-secondary' onclick='logout()'>Log Out</button> if you are done.</div>\n";
    unset($_REQUEST["part"]);
    return;
  }

  $user_id = $user->user_id;
  if( isAdmin() ) {
    if( isset($_POST["user_id"]) ) {
      $user_id = $_POST["user_id"];
    }
  }

  $part = $_POST["part"];
  $stock_num = explode(" ",$part)[0];
  $quantity = $_POST["quantity"];

  $sql = "SELECT PART_ID,PRICE,UNITS FROM part WHERE STOCK_NUM = :STOCK_NUM";
  $dbh = connectDB();
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":STOCK_NUM",$stock_num);
  $stmt->execute();
  $row = $stmt->fetch();
  if( $row ) {
    $part_id = $row["PART_ID"];
    $price = $row["PRICE"];
    $units = $row["UNITS"];
    if( $stmt->fetch() ) {
      echo "<div class='alert alert-danger'>Aborted. Stock number '",htmlescape($stock_num),"' is ambiguous.</div>\n";
      return;
    }
  } else {
    echo "<div class='alert alert-danger'>Aborted. Stock number '",htmlescape($stock_num),"' not found.</div>\n";
    return;
  }

  if( $quantity != floor($quantity) || $quantity <= 0 ) {
    echo "<div class='alert alert-danger'>Aborted.  Quantity must be a positive whole number.</div>\n";
    return;
  }
  $total = $price * $quantity;
  $total_str = sprintf("%.2f",$total);
  $published_total = $_POST["total"];
  if( $total_str != $published_total && floatval($total_str) != floatval($published_total) ) {
    echo "<div class='alert alert-danger'>Aborted.  Computed total: {$total_str} does not match published total {$published_total}.</div>\n";
    return;
  }

  $dbh->beginTransaction();

  $sql = "INSERT INTO checkout SET
          USER_ID = :USER_ID,
          GROUP_ID = :GROUP_ID,
          FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID,
          PART_ID = :PART_ID,
          QTY = :QTY,
          PRICE = :PRICE,
          TOTAL = :TOTAL,
          UNITS = :UNITS,
          LOGIN_METHOD = :LOGIN_METHOD,
          IPADDR = :IPADDR,
          DATE = now()";

  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    if( isset($_REQUEST[$shop_info["CHECKOUT_WORK_ORDER_ID_COL"]]) ) {
      $wo_col = $shop_info["CHECKOUT_WORK_ORDER_ID_COL"];
      $sql .= ", {$wo_col} = :{$wo_col}";
    }
  }

  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":USER_ID",$user_id);
  $stmt->bindValue(":GROUP_ID",$_POST["group_id"]);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$_POST["funding_source"]);
  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    if( isset($_REQUEST[$shop_info["CHECKOUT_WORK_ORDER_ID_COL"]]) ) {
      $wo_col = $shop_info["CHECKOUT_WORK_ORDER_ID_COL"];
      $wo_id = $_REQUEST[$wo_col];
      $stmt->bindValue(":{$wo_col}",$wo_id);
    }
  }
  $stmt->bindValue(":PART_ID",$part_id);
  $stmt->bindValue(":PRICE",$price);
  $stmt->bindValue(":QTY",$quantity);
  $stmt->bindValue(":TOTAL",$published_total);
  $stmt->bindValue(":UNITS",$units);
  $login_method = getLoginMethod();
  $stmt->bindValue(":LOGIN_METHOD",$login_method);
  $ipaddr = $_SERVER['REMOTE_ADDR'];
  $stmt->bindValue(":IPADDR",$ipaddr);
  $stmt->execute();
  $checkout_id = $dbh->lastInsertId();

  $adjust_quantity = true;
  if( isQuoteCheckout($checkout_id) ) {
    $adjust_quantity = false;
  }

  if( $adjust_quantity ) {
    $sql = "UPDATE part SET QTY = QTY - :QTY WHERE PART_ID = :PART_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":QTY",$quantity);
    $stmt->bindValue(":PART_ID",$part_id);
    $stmt->execute();
  }

  $dbh->commit();

  handleDefaultFundingSourceUpdateForm($user);

  echo "<div class='alert alert-success'>Recorded purchase.  Make another or <button class='btn btn-secondary' onclick='logout()'>Log Out</button> if you are done.</div>\n";
  unset($_REQUEST["part"]);
}

function getWorkOrderNum($wo_id,$shop_info) {
  $dbh = connectDB();
  $wo_table = $shop_info['WORK_ORDER_TABLE'];
  $sql = "SELECT WORK_ORDER_NUM FROM {$wo_table} WHERE WORK_ORDER_ID = :WORK_ORDER_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":WORK_ORDER_ID",$wo_id);
  $stmt->execute();
  $row = $stmt->fetch();
  if( $row ) return $row["WORK_ORDER_NUM"];
}

function getWorkOrderUrl($wo_id,$shop_info) {
  $url = $shop_info["WORK_ORDER_URL"];
  if( strstr($url,"?") === false ) $url .= "?";
  else $url .= "&";
  $url .= "id=" . urlencode($wo_id);
  return $url;
}

function showCheckout($user) {
  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    $wo_col = $shop_info["CHECKOUT_WORK_ORDER_ID_COL"];
    if( isset($_REQUEST[$wo_col]) ) {
      $wo_id = $_REQUEST[$wo_col];
      $wo_num = getWorkOrderNum($wo_id,$shop_info);
      $wo_url = getWorkOrderUrl($wo_id,$shop_info);
      echo "<div class='alert alert-success'>To return to the work order, click here: <a class='btn btn-secondary' href='",htmlescape($wo_url),"'>Back to Work Order</a></div>\n";
    }
  }

  echo "<div class='card card-md noprint'><div class='card-body'>\n";
  echo "<h2>Part Purchase Form</h2>\n";
  echo "<form id='checkout_form' enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='checkout'/>\n";
  if( isAdmin() ) {
    echo "<input type='hidden' name='user_id' value='",htmlescape($user->user_id),"'/>\n";
  }

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  $is_for_work_order = false;
  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    $wo_col = $shop_info["CHECKOUT_WORK_ORDER_ID_COL"];
    if( isset($_REQUEST[$wo_col]) ) {
      $is_for_work_order = true;
      $wo_id = $_REQUEST[$wo_col];
      $wo_num = getWorkOrderNum($wo_id,$shop_info);
      echo "<input type='hidden' name='",htmlescape($wo_col),"' value='",htmlescape($wo_id),"'/>\n";
      echo "<input type='hidden' id='funding_source' name='funding_source' value='-1'/>\n";
      echo "<input type='hidden' id='group_id' name='group_id' value='-1'/>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>",htmlescape($shop_name)," Work Order #</label></div><div class='col'>",htmlescape($wo_num),"</div></div>\n";
    }
  }

  if( !$is_for_work_order ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='group_id'>Fund Group</label></div><div class='col'>";
    echoSelectUserGroup($user);
    echo "</div></div>\n";

    echo "<div class='$rowclass'><div class='$col1'><label for='funding_source'>Fund</label></div><div class='col'>";
    echoSelectFundingSource($user,null,"",false,true);
    echo "</div></div>\n";
  }

  $preselected_part = isset($_REQUEST["part"]) ? $_REQUEST["part"] : "";
  echo "<div class='$rowclass'><div class='$col1'><label for='part'>Part</label></div><div class='col'><div class='autocomplete'><input type='text' name='part' id='part' size='30' maxlength='60' placeholder='stock # or search terms' aria-label='part stock number' value='",htmlescape($preselected_part),"'/></div></div></div>\n";

  echo "<div class='$rowclass requires_part_info'><div class='$col1'><label for='quantity'>Quantity</label></div><div class='col'><input type='text' name='quantity' id='quantity' size='4' value='' aria-label='quantity'/> <span id='quantity_unit'></span></div></div>\n";

  echo "<div class='$rowclass requires_part_info'><div class='$col1'>Unit Price</div><div class='col'><span id='unit_price'></span> <span id='unit_price_unit'></span></div></div>\n";

  echo "<div class='$rowclass requires_part_info requires_total'><div class='$col1'>Total</div><div class='col'><span id='total'></span></div></div>\n";

  echo "<div class='$rowclass requires_part_info requires_location'><div class='$col1'>Location</div><div class='col'><span id='location'></span></div></div>\n";

  echo "<img id='part_img' class='part_img requires_part_info'/><br/>\n";

  echo "<div id='submit_msg'></div>\n";
  echo "<div id='non-integer-quantity-msg' class='alert alert-danger' style='display:none'>The quantity must be a whole number.</div>\n";

  echo "<input type='hidden' id='published_total' name='total' value=''/>\n";
  echo "<input type='submit' name='submit' id='submit_checkout' value='Buy' disabled onclick='return checkSubmit();'/>\n";
  echo "<input type='submit' name='submit' value='Cancel'/>\n";

  echo "</div></form>\n";

  echo "</div></div>\n"; # end of card

  showPurchases($user,null,true,true);

  $part_names = getPartNamesJSON();
  ?>
    <script>
      function autocomplete_part_visible(entries,observer) {
        // add images to parts listed in the autocompletion drop-down when they become visible
        for( var i in entries ) {
          var entry = entries[i];
          if( entry.isIntersecting && !entry.target.getElementsByTagName("img").length ) {
            var part_name = entry.target.getElementsByTagName("input")[0].value;
            callWithPartInfo(part_name,function (pi) {
              if( pi["IMAGE"] && !this.getElementsByTagName("img").length ) {
                var img = document.createElement("span");
                img.innerHTML = "<img style='max-height: 30px; max-width: 30px; float: right' src='" + pi["IMAGE"] + "'/>";
                this.appendChild(img);
              }
            }.bind(entry.target)); // set this = entry.target, because 'entry' may change by the time the callback is called
          }
        }
      }

      var part_names = <?php echo $part_names; ?>;
      document.addEventListener("DOMContentLoaded", function(event) { autocomplete_unordered_words(document.getElementById("part"), part_names, 2, autocomplete_part_visible); });
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", function(event) {
        $( "#checkout_form input, #checkout_form select" ).on("change",checkout_form_changed);
        $( "#checkout_form input, #checkout_form select" ).on("input",checkout_form_changed);
        $( "#part" ).on("focusout",partFocusChanged);
        $( "#part" ).on("blur",partFocusChanged);
        $( "#partautocomplete-list" ).on("focusout",partFocusChanged);
	<?php if( $preselected_part != "" ) { ?>
          document.activeElement = null;
          partFocusChanged();
          checkout_form_changed();
        <?php } else { ?>
          $( "#part" ).focus();
	<?php } ?>
      } );
      function formatCurrency(x) {
        x = x.toFixed(2);
        if( x == "NaN" ) return "";
        return '$' + x;
      }
      var part_focus_changed_timer;
      function partFocusChanged() {
        if( part_focus_changed_timer ) window.clearTimeout(part_focus_changed_timer);
        partFocusChangedTimeout();
      }
      function focusOnQuantity() {
        if( !document.activeElement || document.activeElement.tagName == "BODY" ) {
          var part_elem = document.getElementById("part");
          var part = part_elem.value;
          if( !part || $(part_elem).is(":focus") ) return;
          var part_idx = part_names.indexOf(part);
	  if( part_idx !== -1 ) {
            var q = document.getElementById("quantity");
            if( !q.value ) {
              $(q).focus();
            }
	  }
        }
      }
      function partFocusChangedTimeout() {
        part_focus_changed_timer = null;
        var part_elem = document.getElementById("part");
        var part = part_elem.value;
        if( !part || $(part_elem).is(":focus") ) return;
        var part_idx = part_names.indexOf(part);

        if( part_idx !== -1 ) {
          focusOnQuantity();
          return;
        }

        var part_autocomplete = document.getElementById("partautocomplete-list");
        if( part_autocomplete ) {
          // as long as the autocomplete drop-down menu still exists, do not do anything
          part_focus_changed_timer = window.setTimeout(partFocusChangedTimeout,200);
          return;
        }

        // The part input box does not have the focus, the
        // autocomplete drop-down menu is not open, and there is
        // incomplete input in the part input box, so either match
        // the partial input to something, or display an error message.

        for(var i=0; i<part_names.length; i++) {
          if( part_names[i].substr(0,part.length) == part ) {
            var space_pos = part_names[i].indexOf(" ");
            if( (space_pos > 0 && space_pos <= part.length) || part.length == part_names[i].length ) {
              part_elem.value = part_names[i];
              checkout_form_changed();
              return;
            }
          }
        }
        document.getElementById('submit_msg').innerHTML = "<br><div class='alert alert-danger'>Unknown part.</div>";
      }
      var missing_input = false;
      function checkSubmit() {
        if( !validateQuantity() ) return false;
        if( !missing_input ) {
          document.getElementById('submit_msg').innerHTML = "";
          return true;
        }

        document.getElementById('submit_msg').innerHTML = "<br><div class='alert alert-danger'>You must first enter " + missing_input + ".</div>";
        return false;
      }
      function validateQuantity() {
        var quantity = parseFloat($('#quantity').val());
        if( $('#quantity').val() != "" && quantity != Math.floor(quantity) ) {
          $('#non-integer-quantity-msg').show();
          return false;
        }
        $('#non-integer-quantity-msg').hide();
        return true;
      }
      function checkout_form_changed() {
        var submit = document.getElementById("submit_checkout");
        var part_elem = document.getElementById("part");
        var part = part_elem.value;
        var fund = document.getElementById("funding_source").value;

        part_elem.size = part.length < 30 ? 30 : part.length;

        if( !part || part_names.indexOf(part) === -1 ) {
          submit.disabled = true;
          $('.requires_part_info').hide();
        } else if( !(part in part_info) ) {
          submit.disabled = true;
          $('.requires_part_info').hide();
          callWithPartInfo(part,checkout_form_changed);
        } else {
          var pi = part_info[part];
          var units = pi["UNITS"];
          if( units == "ea." || units == "ea" ) units = "each";
          var quantity_unit = units;
          if( quantity_unit == "each" ) quantity_unit = "";
          $('#quantity_unit').html(quantity_unit);

          var per = "per ";
          if( units == "each" ) per = "";
          $('#unit_price_unit').html(per + units);

          var price = parseFloat(pi["PRICE"]);
          $('#unit_price').html(formatCurrency(price));

          var quantity = parseFloat($('#quantity').val());
          validateQuantity();
          var total = formatCurrency(quantity * price);
          $('#total').html(total);
          $('#published_total').val(total.replace('$',''));
          $('.requires_part_info').show();

          $('#part_img').attr("src",pi["IMAGE"]);

          $('#location').text(pi["LOCATION"]);
          if( pi["LOCATION"] ) {
            $('.requires_location').show();
          } else {
            $('.requires_location').hide();
          }

          // People sometimes don't see why they can't click Buy once
          // a part is chosen, so enable the Buy button even if the
          // quantity or funding source is not filled in yet, and
          // display an error message if they try to buy without filling
          // it in.

          submit.disabled = false;
          var new_missing_input = false;
          if( !fund ) {
            new_missing_input = "a funding source";
          }
          if( total != "" && quantity > 0) {
            $('.requires_total').show();
          } else {
            new_missing_input = "the quantity";
            $('.requires_total').hide();
          }
          missing_input = new_missing_input;
          document.getElementById('submit_msg').innerHTML = "";

	  focusOnQuantity();
        }
      }
      $('.requires_part_info').hide();
    </script>
    <script src='partinfo.js'></script>
  <?php

}

function getPartNamesJSON() {
  $db = connectDB();
  $sql = "SELECT STOCK_NUM, DESCRIPTION FROM part WHERE NOT INACTIVE";
  $stmt = $db->prepare( ($sql) );
  $stmt->execute();

  $items = array();
  while( ($row=$stmt->fetch()) ) {
    $name = $row["STOCK_NUM"] . " - " . $row["DESCRIPTION"];
    $items[] = $name;
  }
  return json_encode($items);
}

function showPurchases($user=null,$part_id=null,$recent=null,$compact=false) {

  if( !$user && isset($_REQUEST["user_id"]) ) {
    $user = new User;
    $user->loadFromUserID($_REQUEST["user_id"]);
  }
  if( !$part_id && isset($_REQUEST["part_id"]) ) {
    $part_id = $_REQUEST["part_id"];
  }

  $where_user = $user ? "AND checkout.USER_ID = :USER_ID" : "";
  $where_part = $part_id ? "AND checkout.PART_ID = :PART_ID" : "";
  # don't show deleted items in the view of recent purchases:
  $where_deleted = $recent ? "AND checkout.DELETED IS NULL" : "";

  if( isset($_REQUEST["s"]) && $_REQUEST["s"] == "history" ) {
    $show = 'history';
  } else {
    $show = 'purchases';
  }

  $dbh = connectDB();
  $most_recent_bunch_stmt = null;
  if( $part_id || ($user && !$recent && $show != 'history') ) {
    $sql = "
      SELECT
        DATE(checkout.DATE) as DATE
      FROM
        checkout
      WHERE
        TRUE
        $where_user
        $where_part
        $where_deleted
      ORDER BY
        checkout.DATE DESC
      LIMIT 10";
    $most_recent_bunch_stmt = $dbh->prepare($sql);
    if( $user ) $most_recent_bunch_stmt->bindValue(":USER_ID",$user->user_id);
    if( $part_id ) $most_recent_bunch_stmt->bindValue(":PART_ID",$part_id);
  }

  $page = new TimePager($show,$user || $part_id,$recent,$most_recent_bunch_stmt);

  $sql = "
  SELECT
    checkout.CHECKOUT_ID,
    checkout.DATE,
    checkout.PRICE,
    checkout.UNITS,
    checkout.QTY,
    checkout.TOTAL,
    checkout.DELETED,
    user.USER_ID,
    user.LAST_NAME,
    user.FIRST_NAME,
    user_group.GROUP_ID,
    user_group.GROUP_NAME,
    funding_source.PI_NAME,
    funding_source.FUNDING_DESCRIPTION,
    funding_source.FUNDING_SOURCE_ID,
    funding_source.FUNDING_FUND,
    funding_source.FUNDING_PROGRAM,
    funding_source.FUNDING_DEPT,
    funding_source.FUNDING_PROJECT,";

  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    $sql .= "checkout." . $shop_info["CHECKOUT_WORK_ORDER_ID_COL"] . ", ";
  }

  $sql .= "
    part.PART_ID,
    part.STOCK_NUM,
    part.DESCRIPTION as PART_DESCRIPTION,
    part.LOCATION,
    part.SECTION,
    part.ROW,
    part.SHELF
  FROM
    checkout
    INNER JOIN user ON user.USER_ID = checkout.USER_ID
    LEFT JOIN user_group ON user_group.GROUP_ID = checkout.GROUP_ID
    LEFT JOIN funding_source ON funding_source.FUNDING_SOURCE_ID = checkout.FUNDING_SOURCE_ID
    INNER JOIN part ON part.PART_ID = checkout.PART_ID
  WHERE
    DATE >= :START AND DATE < :END
    $where_user
    $where_part
    $where_deleted
  ORDER BY checkout.DATE DESC";

  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START",$page->start);
  $stmt->bindValue(":END",$page->end);
  if( $user ) $stmt->bindValue(":USER_ID",$user->user_id);
  if( $part_id ) $stmt->bindValue(":PART_ID",$part_id);
  $stmt->execute();
  $row = $stmt->fetch(); # fetch one to see if there are any rows

  if( $recent && !$row ) return;

  $part_title = "";
  if( $part_id ) {
    $part = getPartRecord($part_id);
    $part_title = " for " . $part["DESCRIPTION"];
  }

  $user_title = $user ? "by " . $user->displayName() : "";

  if( $recent ) echo "<p>&nbsp;</p><h3>Today's Purchases</h3>\n";
  else echo "<h1>",htmlescape($page->pre_title)," Purchases {$user_title} ",htmlescape($page->time_title),htmlescape($part_title),"</h1>\n";

  echo "<div class='noprint'>\n";

  $base_url = "?s=" . $show;
  $base_url .= $user ? "&user_id=" . $user->user_id : "";
  $base_url .= $part_id ? "&part_id=" . $part_id : "";

  if( !$user && !$part_id && !$recent ) {
    $url = "{$base_url}&start={$page->start}&f=purchases";
    if( $page->end ) $url .= "&end=" . $page->end;
    echo "<a href='$url' class='btn btn-primary'>Download</a>\n";
  }

  if( $recent ) {
    echo "<button class='btn btn-primary' onclick='window.print()'><i class='fas fa-print'></i></button>\n";
  }

  $page->pageButtons($base_url);
  $page->moreOptionsButton();

  echo "<span id='purchase_updated_msg'></span>\n";

  $hidden_vars = array();
  if( $part_id ) {
    $hidden_vars['part_id'] = $part_id;
  }
  if( $user ) {
    $hidden_vars['user_id'] = $user->user_id;
  }
  $page->moreOptions($hidden_vars);

  echo "</div>\n"; # end of noprint

  echo "<table class='records clicksort'><thead><tr><th><i class='fa fa-trash' aria-hidden='true'></i></th><th>Date</th><th>Customer</th><th>Part</th><th>Price</th><th>Units</th><th>Qty</th><th>Total</th>";
  if( ENABLE_PART_LOCATION ) {
    echo "<th id='location_col'>Location</th>";
    echo "<th id='section_col'><small><b>Sec</b></small></th><th id='row_col'><small><b>Row</b></small></th><th id='shelf_col'><small><b>Shlf</b></small></th>";
  }
  echo "<th>Fund Group</th>";
  if( !$compact ) {
    echo "<th><small><b>Fund</b></small></th><th><small><b>Prog</b></small></th><th>Dept</th><th>Project</th>";
  }
  echo "<th>Fund Desc</th></tr></thead><tbody>\n";

  $total_total = 0;
  $total_quantity = 0;
  $last_part_id = null;
  $row_count = 0;
  $location_empty = true;
  $section_row_shelf_empty = true;
  for( ; $row ; ($row=$stmt->fetch()) ) {
    $row_count += 1;
    $class = $row["DELETED"] ? "class='deleted'" : "";
    echo "<tr $class>";
    $checked = $row["DELETED"] ? "checked" : "";
    echo "<td><input type='checkbox' value='1' $checked onclick='deleteClicked(this,",$row["CHECKOUT_ID"],");'/></td>";

    echo "<td>",htmlescape(displayDateTime($row["DATE"])),"</td>";

    $name = $row["LAST_NAME"] . ", " . $row["FIRST_NAME"];
    if( isAdmin() ) {
      $url = "?s=edit_user&user_id=" . $row["USER_ID"];
      echo "<td><a href='",htmlescape($url),"'>",htmlescape($name),"</a>\n<span class='clicksort_data'>",htmlescape($name),"</span></td>";
    } else {
      echo "<td>",htmlescape($name),"</td>";
    }

    if( isAdmin() ) {
      $url = "?s=part&part_id=" . $row["PART_ID"];
      echo "<td><small><a href='",htmlescape($url),"'><tt>",htmlescape($row["STOCK_NUM"]),"</tt></a> - ",htmlescape($row["PART_DESCRIPTION"]),"</small></td>";
    } else {
      echo "<td><small><tt>",htmlescape($row["STOCK_NUM"]),"</tt> - ",htmlescape($row["PART_DESCRIPTION"]),"</small></td>";
    }

    echo "<td class='align-right'>\$",htmlescape($row["PRICE"]),"</td>";
    echo "<td>",htmlescape($row["UNITS"]),"</td>";
    $id = "qty_" . $row["CHECKOUT_ID"];
    echo "<td id='$id' class='align-right'>",htmlescape(formatQty($row["QTY"])),"</td>";
    $total_class = $row["DELETED"] ? "deleted" : "";
    $id = "total_" . $row["CHECKOUT_ID"];
    echo "<td class='align-right'><span id='$id' class='{$total_class}'>\$",htmlescape($row["TOTAL"]),"</span></td>";

    $total_total += $row["DELETED"] ? 0 : $row["TOTAL"];
    if( $last_part_id !== null && $last_part_id != $row["PART_ID"] ) {
      $total_quantity = NAN; # only show total_quantity if there is a single part type
    }
    $last_part_id = $row["PART_ID"];
    $total_quantity += $row["DELETED"] ? 0 : $row["QTY"];

    if( ENABLE_PART_LOCATION ) {
      echo "<td class='location_col'>",$row["LOCATION"],"</td>";
      echo "<td class='section_col'>",$row["SECTION"],"</td>";
      echo "<td class='row_col'>",$row["ROW"],"</td>";
      echo "<td class='shelf_col'>",$row["SHELF"],"</td>";

      if( $row["LOCATION"] ) $location_empty = false;
      if( $row["SECTION"] || $row["ROW"] || $row["SHELF"] ) $section_row_shelf_empty = false;
    }

    $name = $row["GROUP_NAME"];
    echo "<td>";
    if( isAdmin() ) {
      $url = "?s=edit_group&group_id=" . $row["GROUP_ID"];
      echo "<small><a href='",htmlescape($url),"'>",htmlescape($name),"</a></small>\n<span class='clicksort_data'>",htmlescape($name),"</span>";
    } else {
      echo "<small>",htmlescape($name),"</small>";
    }
    echo "</td>";

    if( !$compact ) {
      echo "<td><small>",htmlescape($row["FUNDING_FUND"]),"</small></td>";
      echo "<td><small>",htmlescape($row["FUNDING_PROGRAM"]),"</small></td>";
      echo "<td><small>",htmlescape($row["FUNDING_DEPT"]),"</small></td>";
      echo "<td><small>",htmlescape($row["FUNDING_PROJECT"]),"</small></td>";
    }

    $fund_desc = htmlescape($row["PI_NAME"] . " - " . $row["FUNDING_DESCRIPTION"]);
    if( trim($fund_desc) == "-" ) $fund_desc = "";
    foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
      $wo_col = $shop_info['CHECKOUT_WORK_ORDER_ID_COL'];
      $wo_id = $row[$wo_col];
      if( $wo_id !== null ) {
        $wo_num = getWorkOrderNum($wo_id,$shop_info);
	$wo_url = getWorkOrderUrl($wo_id,$shop_info);
	$fund_desc .= " " . htmlescape($shop_name) . " work order <a href='" . htmlescape($wo_url) . "'>" . htmlescape($wo_num) . "</a>";
      }
    }
    echo "<td><small>",$fund_desc,"</small></td>";

    echo "</tr>\n";
  }
  echo "</tbody>\n";

  if( $row_count > 0 ) {
    if( is_nan($total_quantity) ) $total_quantity = "";
    echo "<tfoot><tr><th></th><th>TOTAL</th><th></th><th></th><th></th><th></th><th id='total_qty' class='align-right'>",htmlescape(formatQty($total_quantity)),"</th><th>\$<span id='total_total' class='align-right'>",htmlescape(sprintf("%.2f",$total_total)),"</span></th>";
    if( ENABLE_PART_LOCATION ) {
      echo "<th class='location_col'></th><th class='section_col'></th><th class='row_col'></th><th class='shelf_col'></th>";
    }
    echo "<th></th>";
    if( !$compact ) {
      echo "<th></th><th></th><th></th><th></th>";
    }
    echo "<th></th></tr></tfoot>\n";
  }

  echo "</table>\n";

  if( ENABLE_PART_LOCATION ) {
    if( $location_empty ) {
      echo "<style>.location_col, #location_col { display: none; }</style>\n";
    }
    if( $section_row_shelf_empty ) {
      echo "<style>.section_col, #section_col, .row_col, #row_col, .shelf_col, #shelf_col { display: none; }</style>\n";
    }
  }

  echo "<p>&nbsp;</p>\n";
  ?>
  <script>
  function formatQty(qty) {
    var m = qty.match('^(.*)[.]0*$');
    if( m ) {
      return m[1];
    }
    m = qty.match('^((.*)[.]([0-9]*[1-9]))0*$');
    if( m ) {
      return m[1];
    }
    return qty;
  }
  function deleteClicked(cb_object,checkout_id) {
    var deleted = cb_object.checked ? true : false;

    var tr = cb_object.parentElement;
    while( tr && tr.nodeName != "TR" ) tr = tr.parentElement;

    query_data = {f: "delete_checkout", checkout_id: checkout_id, deleted: (deleted ? "1" : "0")};
    $.post("?",query_data,function (data) {
      if( data.indexOf("SUCCESS") === -1 ) {
        alert(data);
        return;
      }

      // indicate that this item's total is deleted (or not)
      // (This is now redundant, because we mark the whole row as deleted, but
      // preserve it in case that changes.)
      var item_total_e = document.getElementById("total_" + checkout_id);
      item_total_e.className = deleted ? "deleted" : "";

      // update the total total
      var item_total = parseFloat(item_total_e.textContent.replace("$",""));
      var tt = document.getElementById("total_total");
      tt.textContent = '' + (parseFloat(tt.textContent) + (deleted ? -item_total : item_total)).toFixed(2);

      // update the total qty
      var item_qty_e = document.getElementById("qty_" + checkout_id);
      var item_qty = parseFloat(item_qty_e.textContent);
      var tq = document.getElementById("total_qty");
      if( tq.textContent ) {
        tq.textContent = formatQty('' + (parseFloat(tq.textContent) + (deleted ? -item_qty : item_qty)).toFixed(2));
      }

      if( deleted ) {
        $(tr).addClass("deleted");
        $("#purchase_updated_msg").html("&nbsp;<tt>Deletion recorded.</tt>");
      } else {
        $(tr).removeClass("deleted");
        $("#purchase_updated_msg").html("&nbsp;<tt>Undeletion recorded.</tt>");
      }

    });
  }
  </script>
  <?php
}

function downloadPurchases() {
  if( isset($_REQUEST["start"]) ) {
    $start = $_REQUEST["start"];
  } else {
    $start = date("Y-m-01");
  }

  if( isset($_REQUEST["end"]) ) {
    $end = $_REQUEST["end"];
  } else {
    $end = date("Y-m-d",strtotime($start . " +1 month"));
  }

  $filename = str_replace(" ","",SHOP_NAME . "Purchases_{$start}.csv");

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  $dbh = connectDB();
  $sql = "
  SELECT
    checkout.DATE,
    checkout.PRICE,
    checkout.UNITS,
    checkout.QTY,
    checkout.TOTAL,
    checkout.DELETED,
    checkout.LOGIN_METHOD,
    user.USER_ID,
    user.LAST_NAME,
    user.FIRST_NAME,
    user.EMAIL,
    user_group.GROUP_ID,
    user_group.GROUP_NAME,
    funding_source.PI_NAME,
    funding_source.FUNDING_DESCRIPTION,
    funding_source.FUNDING_SOURCE_ID,
    funding_source.FUNDING_FUND,
    funding_source.FUNDING_PROGRAM,
    funding_source.FUNDING_DEPT,
    funding_source.FUNDING_PROJECT,";

  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    $sql .= "checkout." . $shop_info["CHECKOUT_WORK_ORDER_ID_COL"] . ", ";
  }

  $sql .= "
    part.PART_ID,
    part.STOCK_NUM,
    part.DESCRIPTION as PART_DESCRIPTION
  FROM
    checkout
    INNER JOIN user ON user.USER_ID = checkout.USER_ID
    LEFT JOIN user_group ON user_group.GROUP_ID = checkout.GROUP_ID
    LEFT JOIN funding_source ON funding_source.FUNDING_SOURCE_ID = checkout.FUNDING_SOURCE_ID
    INNER JOIN part ON part.PART_ID = checkout.PART_ID
  WHERE
    DATE >= :START AND DATE < :END
  ORDER BY checkout.DATE DESC";

  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START",$start);
  $stmt->bindValue(":END",$end);
  $stmt->execute();

  $csv = array("Date","Customer","Email","Login","Stock Num","Part Desc","Price","Units","Qty","Total","Deleted","Fund Group","Fund","Prog","Dept","Project","Fund Desc");
  fputcsv_excel($F,$csv);

  while( ($row=$stmt->fetch()) ) {
    $csv = array();

    $csv[] = displayDateTime($row["DATE"]);
    $csv[] = $row["LAST_NAME"] . ", " . $row["FIRST_NAME"];
    $csv[] = $row["EMAIL"];
    $csv[] = $row["LOGIN_METHOD"];
    $csv[] = $row["STOCK_NUM"];
    $csv[] = $row["PART_DESCRIPTION"];
    $csv[] = $row["PRICE"];
    $csv[] = $row["UNITS"];
    $csv[] = formatQty($row["QTY"]);
    $csv[] = $row["DELETED"] ? "" : $row["TOTAL"];
    $csv[] = $row["DELETED"];
    $csv[] = $row["GROUP_NAME"];
    $csv[] = $row["FUNDING_FUND"];
    $csv[] = $row["FUNDING_PROGRAM"];
    $csv[] = $row["FUNDING_DEPT"];
    $csv[] = $row["FUNDING_PROJECT"];
    $fund_desc = $row["PI_NAME"] . " - " . trim($row["FUNDING_DESCRIPTION"]);
    if( trim($fund_desc) == "-" ) $fund_desc = "";
    foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
      $wo_col = $shop_info['CHECKOUT_WORK_ORDER_ID_COL'];
      $wo_id = $row[$wo_col];
      if( $wo_id !== null ) {
        $wo_num = getWorkOrderNum($wo_id,$shop_info);
	$fund_desc .= " " . htmlescape($shop_name) . " work order " . htmlescape($wo_num);
      }
    }
    $csv[] = $fund_desc;

    fputcsv_excel($F,$csv);
  }
  fclose($F);
}

function isQuoteCheckout($checkout_id) {
  $dbh = connectDB();

  foreach( OTHER_SHOPS_IN_CHECKOUT as $shop_name => $shop_info ) {
    $wo_col = $shop_info['CHECKOUT_WORK_ORDER_ID_COL'];
    $wo_table = $shop_info['WORK_ORDER_TABLE'];

    $quote_sql = "SELECT IS_QUOTE FROM {$wo_table} JOIN checkout ON checkout.{$wo_col} = {$wo_table}.WORK_ORDER_ID AND checkout.CHECKOUT_ID = :CHECKOUT_ID";
    $quote_stmt = $dbh->prepare($quote_sql);
    $quote_stmt->bindValue(":CHECKOUT_ID",$checkout_id);
    $quote_stmt->execute();
    $wo = $quote_stmt->fetch();
    if( $wo && $wo["IS_QUOTE"] ) {
      return true;
    }
  }
  return false;
}

function deleteCheckout($user) {
  # this function is called ajax-style
  $checkout_id = $_POST["checkout_id"];
  $deleted = (int)$_POST["deleted"];

  $dbh = connectDB();
  $dbh->beginTransaction();

  $before_change = getCheckoutRecord($checkout_id);

  $deleted_sql = $deleted ? "now()" : "NULL";
  $sql = "UPDATE checkout SET DELETED = {$deleted_sql} WHERE CHECKOUT_ID = :CHECKOUT_ID";
  if( !isAdmin() ) {
    $sql .= " AND USER_ID = :USER_ID";
  }
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":CHECKOUT_ID",$checkout_id);
  if( !isAdmin() ) {
    $stmt->bindValue(":USER_ID",$user->user_id);
  }
  $stmt->execute();

  $after_change = getCheckoutRecord($checkout_id);
  auditlogModifyCheckout($user,$before_change,$after_change);

  $adjust_quantity = true;
  if( isQuoteCheckout($checkout_id) ) {
    $adjust_quantity = false;
  }

  if( $adjust_quantity ) if( ($deleted && ($before_change["DELETED"] === null)) || (!$deleted && ($before_change["DELETED"] !== null)) ) {
    $sql = "UPDATE part SET QTY = QTY + :QTY WHERE PART_ID = :PART_ID";
    $stmt = $dbh->prepare($sql);
    $direction = $deleted ? 1 : -1;
    $stmt->bindValue(":QTY",$direction * $before_change["QTY"]);
    $stmt->bindValue(":PART_ID",$before_change["PART_ID"]);
    $stmt->execute();
  }

  $dbh->commit();

  echo "SUCCESS\n";
}

function showWishList() {
  showParts(null,true);
}

function showParts($vendor_id=null,$wish_list=false,$grab_focus=true) {
  $dbh = connectDB();

  if( $vendor_id === null && isset($_REQUEST["vendor_id"]) ) {
    $vendor_id = $_REQUEST["vendor_id"];
  }

  if( isset($_REQUEST["wish_list"]) ) {
    $wish_list = true;
  }

  if( isset($_REQUEST["inactive"]) ) {
    echo "<h2>Inactive/Obsolete Parts</h2>\n";
  }

  if( $vendor_id ) {
    $vendor = getVendorRecord($vendor_id);
    if( $wish_list ) echo "<h2>Select a part to order from the wish list for ";
    else echo "<h2>Parts from ";
    echo htmlescape($vendor["NAME"]),"</h2>\n";
  }
  else if( $wish_list ) {
    echo "<h2>Select a part to order from the wish list</h2>";
  }
  if( $wish_list ) {
    echo "<p>(To add to the wish list, <a href='?s=parts'>edit a part</a> and change its status to W or set Minimum Quantity <= Quantity.)</p>\n";
  }

  echo "<p>";

  $url = "?f=parts";
  if( $wish_list ) {
    $url .= "&wish_list";
  }
  if( $vendor_id ) {
    $url .= "&vendor_id=" . $vendor_id;
  }
  if( isset($_REQUEST["inactive"]) ) {
    $url .= "&inactive";
  }
  echo "<a class='btn btn-primary' href='",htmlescape($url),"'>Download</a>\n";

  $showing_inactive_parts_crossed_out = false;
  if( !$wish_list && !isset($_REQUEST["inactive"]) && defined('SHOW_INACTIVE_PARTS_CROSSED_OUT') && SHOW_INACTIVE_PARTS_CROSSED_OUT ) {
    $showing_inactive_parts_crossed_out = true;
  }

  if( !$wish_list ) {
    $url = "?s=part";
    if( $vendor_id ) $url .= "&vendor_id={$vendor_id}";
    echo "<a href='",htmlescape($url),"' class='btn btn-primary'>New Part</a>";

    $url = "?s=parts";
    if( $vendor_id ) $url .= "&vendor_id={$vendor_id}";
    if( isset($_REQUEST["inactive"]) ) {
      echo " <a href='",htmlescape($url),"' class='btn btn-primary'>Show Active Parts</a>";
    } else {
      $hide_inactive_button = false;
      if( $vendor_id ) {
        $sql = "SELECT count(*) FROM part WHERE INACTIVE AND VENDOR_ID = :VENDOR_ID";
        $stmt = $dbh->prepare($sql);
        $stmt->bindValue(":VENDOR_ID",$vendor_id);
        $stmt->execute();
        $row = $stmt->fetch();
        if( $row[0] == 0 ) $hide_inactive_button = true;
      }
      if( !$hide_inactive_button ) {
        $only = $showing_inactive_parts_crossed_out ? " Only" : "";
        echo " <a href='",htmlescape($url . "&inactive"),"' class='btn btn-primary'>Show Inactive/Obsolete Parts$only</a>";
      }
    }
  }

  echo " <input class='ml-5' placeholder='Search' id='part_filter' oninput='callFilterParts()'/>";
  echo "</p>\n";

  $sql = "
    SELECT
      part.PART_ID,
      part.STOCK_NUM,
      part.DESCRIPTION,
      part.PRICE,
      part.COST,
      part.QTY,
      part.QTY_CORRECT,
      part.MIN_QTY,
      part.BACKUP_QTY,
      part.UNITS,
      CASE WHEN part.QTY <= part.MIN_QTY and part.STATUS = '' THEN 'low' ELSE part.STATUS END as PART_STATUS,
      part.LOCATION,
      part.SECTION,
      part.ROW,
      part.SHELF,
      part.MANUFACTURER,
      part.MAN_NUM,
      part.VEND_NUM,
      part.UPDATED,
      part.IMAGE,
      part.INACTIVE,
      vendor.NAME as VENDOR_NAME,
      (SELECT CAST(MAX(DATE) as DATE) FROM checkout WHERE checkout.PART_ID = part.PART_ID AND checkout.DELETED IS NULL) as LAST_PURCHASE_DATE
    FROM
      part
    LEFT JOIN vendor ON vendor.VENDOR_ID = part.VENDOR_ID
    WHERE True";
  if( $vendor_id ) $sql .= " AND part.VENDOR_ID = :VENDOR_ID";
  if( isset($_REQUEST["inactive"]) ) {
    $sql .= " AND part.INACTIVE";
  } else if( !$showing_inactive_parts_crossed_out ) {
    $sql .= " AND NOT part.INACTIVE";
  }
  if( $wish_list ) {
    $sql .= " AND (part.STATUS = 'W' COLLATE 'utf8_bin' OR part.STATUS = 'low' OR part.STATUS = '' AND part.QTY <= part.MIN_QTY)";
  }
  $sql .= " " . SHOP_PART_ORDER;
  $stmt = $dbh->prepare($sql);
  if( $vendor_id ) $stmt->bindValue(":VENDOR_ID",$vendor_id);
  $stmt->execute();

  $col_offset = 0;

  echo "<table id='parts_table' class='records clicksort'><thead><tr><th>Stock #</th>";
  $stock_num_col = 0 + $col_offset;

  if( SHOW_IMAGE_COL ) {
    echo "<th>Img</th>";
  } else {
    $col_offset -= 1;
  }
  echo "<th>Description</th>";
  $description_col = 2 + $col_offset;

  echo "<th>Price</th><th>Cost</th><th>Qty</th>";

  if( ENABLE_QTY_CORRECT ) {
    echo "<th>Qty<br>Crct</th>";
    $col_offset += 1;
  }

  $show_min_qty = SHOW_MIN_QTY_COL || $wish_list;
  if( $show_min_qty ) {
    echo "<th>Min</th>";
  } else {
    $col_offset -= 1;
  }
  if( ENABLE_BACKUP_QTY ) {
    echo "<th>Bck</th>";
  } else {
    $col_offset -= 1;
  }
  echo "<th>Units</th><th>Status</th>";
  if( ENABLE_PART_LOCATION ) {
    echo "<th>Location</th>";
  } else {
    $col_offset -= 1;
  }
  if( ENABLE_MANUFACTURER && SHOW_MANUFACTURER_IN_PARTS_TABLE ) {
    echo "<th>Manufacturer</th>";
    $manufacturer_col = 10 + $col_offset;
  } else {
    $manufacturer_col = -1;
    $col_offset -= 1;
  }
  if( SHOW_MAN_NUM_COL ) {
    echo "<th>Manfr Part#</th>";
    $man_num_col = 11 + $col_offset;
  } else {
    $man_num_col = -1;
    $col_offset -= 1;
  }
  echo "<th>Vend Part#</th>";
  $vend_num_col = 12 + $col_offset;
  echo "<th>Vendor</th>";
  $vendor_col = 13 + $col_offset;

  echo "<th>Last Purchase</th><th>Updated</th></tr></thead><tbody>\n";

  while( ($row=$stmt->fetch()) ) {
    $classes = "";
    if( $showing_inactive_parts_crossed_out && $row["INACTIVE"] ) {
      $classes = "line-through";
    }
    echo "<tr class='record $classes'>";
    if( $wish_list ) {
      $url = "?s=order&part_id=" . $row["PART_ID"];
      if( $vendor_id ) {
        $url .= "&vendor_id=" . $vendor_id;
      }
    } else {
      $url = "?s=part&r=parts&part_id=" . $row["PART_ID"];
    }
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["STOCK_NUM"]),"</a></td>";
    if( SHOW_IMAGE_COL ) {
      $url = getPartImageUrl($row["PART_ID"],$row["IMAGE"],$row["STOCK_NUM"],$row["DESCRIPTION"]);
      echo "<td><img style='max-height: 30px; max-width: 30px;' src='",htmlescape($url),"'/></td>";
    }
    echo "<td>",htmlescape($row["DESCRIPTION"]),"</td>";
    echo "<td>",htmlescape($row["PRICE"]),"</td>";
    echo "<td>",htmlescape($row["COST"]),"</td>";
    $qty_class = $row["PART_STATUS"] == "low" ? " class='qty_low'" : "";
    echo "<td{$qty_class}>",htmlescape($row["QTY"]),"</td>";
    if( ENABLE_QTY_CORRECT ) {
      echo "<td>",htmlescape($row["QTY_CORRECT"] ? "Y" : "N"),"</td>";
    }
    if( $show_min_qty ) {
      echo "<td>",htmlescape($row["MIN_QTY"]),"</td>";
    }
    if( ENABLE_BACKUP_QTY ) {
      echo "<td>",htmlescape($row["BACKUP_QTY"]),"</td>";
    }
    echo "<td>",htmlescape($row["UNITS"]),"</td>";
    if( NO_PHOTO_STATUS ) {
      $status = getPartImageUrl($row["PART_ID"],$row["IMAGE"],$row["STOCK_NUM"],$row["DESCRIPTION"]) ? "" : "NP";
      echo "<td>",$status,"</td>";
    } else {
      echo "<td{$qty_class}>",htmlescape($row["PART_STATUS"]),"</td>";
    }
    if( ENABLE_PART_LOCATION ) {
      echo "<td>",htmlescape(getLocationDesc($row)),"</td>";
    }
    if( ENABLE_MANUFACTURER && SHOW_MANUFACTURER_IN_PARTS_TABLE ) {
      echo "<td>",htmlescape($row["MANUFACTURER"]),"</td>";
    }
    if( SHOW_MAN_NUM_COL ) {
      echo "<td>",htmlescape($row["MAN_NUM"]),"</td>";
    }
    echo "<td>",htmlescape($row["VEND_NUM"]),"</td>";
    echo "<td>",htmlescape($row["VENDOR_NAME"]),"</td>";
    echo "<td>",htmlescape($row["LAST_PURCHASE_DATE"]),"</td>";
    echo "<td>",htmlescape($row["UPDATED"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";

  if( $grab_focus ) echo "<script>\$('#part_filter').focus();</script>\n";

  ?>
  <script>
    var filter_parts_timer;
    function callFilterParts() {
      if( filter_parts_timer ) {
        window.clearTimeout(filter_parts_timer);
      }
      filter_parts_timer = window.setTimeout(filterParts,200);
    }
    function filterParts() {
      filter_parts_timer = null;
      var filter = document.getElementById("part_filter").value;
      var regex = new RegExp(filter,"i");
      var tbody = $('#parts_table > tbody');
      $(tbody).detach(); // temporarily detach from DOM to speed things up
      tbody.children('tr').each(function(index) {
        var matched = (filter == "");
        var cols = [ <?php echo "$stock_num_col,$description_col,$manufacturer_col,$vend_num_col,$vendor_col" ?> ];
        for( var i=0; i<cols.length && !matched; i++) {
          if( cols[i] < 0 ) continue;
          var coltext = this.childNodes[cols[i]].innerText;
          matched = regex.test(coltext);
        }
        if( !matched ) {
          $( this ).hide();
        } else {
          $( this ).show();
        }
      });
      $('#parts_table').append(tbody);
    }

    function partSearchEnterPressed() {
      var filter_text = document.getElementById("part_filter").value.toUpperCase();
      var tbody = $('#parts_table > tbody');
      tbody.children('tr').each(function(index) {
        var stock_num = this.childNodes[0].innerText;
        if( filter_text == stock_num.toUpperCase() ) {
          var url = $(this.childNodes[0]).children("a").attr('href');
          window.location.href = url;
        }
      });

      // having not found an exact match of the stock num, see if there is a single row that matches the filter

      if( filter_parts_timer ) {
        window.clearTimeout(filter_parts_timer);
        filterParts();
      }

      var the_url;
      var num_found = 0;
      tbody.children('tr:visible').each(function(index) {
        the_url = $(this.childNodes[0]).children("a").attr('href');
        num_found += 1;
      });
      if( num_found == 1 ) {
        window.location.href = the_url;
      }
    }

    document.getElementById("part_filter").addEventListener('keyup', function (e) {
      if (e.keyCode === 13) partSearchEnterPressed();
    }, false);

  </script>
  <?php
}

function browseParts($grab_focus=true) {
  $dbh = connectDB();

  echo "<p>\n";
  echo " <input class='ml-5' placeholder='Search' id='part_filter' oninput='callFilterParts()'/>";
  echo "</p>\n";

  $sql = "
    SELECT
      part.PART_ID,
      part.STOCK_NUM,
      part.DESCRIPTION,
      part.PRICE,
      part.QTY,
      part.UNITS,
      part.IMAGE,
      part.LOCATION,
      part.SECTION,
      part.ROW,
      part.SHELF
    FROM
      part
    WHERE
      NOT part.INACTIVE
    " . SHOP_BROWSE_PART_ORDER;

  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  $col_offset = 0;

  echo "<table id='parts_table' class='records clicksort'><thead><tr><th>Stock #</th>";
  $stock_num_col = 0 + $col_offset;

  if( SHOW_IMAGE_COL ) {
    echo "<th>Img</th>";
  } else {
    $col_offset -= 1;
  }
  echo "<th>Description</th>";
  $description_col = 2 + $col_offset;

  echo "<th>Price</th><th>Qty</th>";

  echo "<th>Units</th><th>Location</th>";

  echo "</tr></thead><tbody>\n";

  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=checkout&part=" . urlencode($row["STOCK_NUM"]);
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["STOCK_NUM"]),"</a></td>";
    if( SHOW_IMAGE_COL ) {
      $url = getPartImageUrl($row["PART_ID"],$row["IMAGE"],$row["STOCK_NUM"],$row["DESCRIPTION"]);
      echo "<td><img style='max-height: 30px; max-width: 30px;' src='",htmlescape($url),"'/></td>";
    }
    echo "<td>",htmlescape($row["DESCRIPTION"]),"</td>";
    echo "<td>",htmlescape($row["PRICE"]),"</td>";
    echo "<td>",htmlescape($row["QTY"]),"</td>";
    echo "<td>",htmlescape($row["UNITS"]),"</td>";
    echo "<td>",htmlescape(getLocationDesc($row)),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";

  if( $grab_focus ) echo "<script>\$('#part_filter').focus();</script>\n";

  ?>
  <script>
    var filter_parts_timer;
    function callFilterParts() {
      if( filter_parts_timer ) {
        window.clearTimeout(filter_parts_timer);
      }
      filter_parts_timer = window.setTimeout(filterParts,200);
    }
    function filterParts() {
      filter_parts_timer = null;
      var filter = document.getElementById("part_filter").value;
      var regex = new RegExp(filter,"i");
      var tbody = $('#parts_table > tbody');
      $(tbody).detach(); // temporarily detach from DOM to speed things up
      tbody.children('tr').each(function(index) {
        var matched = (filter == "");
        var cols = [ <?php echo "$stock_num_col,$description_col" ?> ];
        for( var i=0; i<cols.length && !matched; i++) {
          if( cols[i] < 0 ) continue;
          var coltext = this.childNodes[cols[i]].innerText;
          matched = regex.test(coltext);
        }
        if( !matched ) {
          $( this ).hide();
        } else {
          $( this ).show();
        }
      });
      $('#parts_table').append(tbody);
    }

    function partSearchEnterPressed() {
      var filter_text = document.getElementById("part_filter").value.toUpperCase();
      var tbody = $('#parts_table > tbody');
      tbody.children('tr').each(function(index) {
        var stock_num = this.childNodes[0].innerText;
        if( filter_text == stock_num.toUpperCase() ) {
          var url = $(this.childNodes[0]).children("a").attr('href');
          window.location.href = url;
        }
      });

      // having not found an exact match of the stock num, see if there is a single row that matches the filter

      if( filter_parts_timer ) {
        window.clearTimeout(filter_parts_timer);
        filterParts();
      }

      var the_url;
      var num_found = 0;
      tbody.children('tr:visible').each(function(index) {
        the_url = $(this.childNodes[0]).children("a").attr('href');
        num_found += 1;
      });
      if( num_found == 1 ) {
        window.location.href = the_url;
      }
    }

    document.getElementById("part_filter").addEventListener('keyup', function (e) {
      if (e.keyCode === 13) partSearchEnterPressed();
    }, false);

  </script>
  <?php
}

function editPart() {
  $part_id = isset($_REQUEST["part_id"]) ? $_REQUEST["part_id"] : null;

  $dbh = connectDB();
  if( $part_id !== null ) {
    $sql = "SELECT part.*,vendor.NAME as VENDOR_NAME FROM part LEFT JOIN vendor ON vendor.VENDOR_ID = part.VENDOR_ID WHERE PART_ID = :PART_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":PART_ID",$part_id);
  } else {
    $sql = "SELECT part.*,vendor.NAME as VENDOR_NAME FROM (select 1) one LEFT JOIN part ON part.PART_ID IS NULL LEFT JOIN vendor ON vendor.VENDOR_ID IS NULL";
    $stmt = $dbh->prepare($sql);
  }
  $stmt->execute();
  $row = $stmt->fetch();

  echo "<div class='card card-md'><div class='card-body'>\n";
  echo "<h2>Part ",htmlescape($row["STOCK_NUM"]),"</h2>\n";
  echo "<form id='checkout_form' enctype='multipart/form-data' method='POST' onsubmit='return validatePart()' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='part'/>\n";
  echo "<input type='hidden' name='part_id' value='",htmlescape($part_id),"'/>\n";
  if( isset($_REQUEST["r"]) ) {
    # page to return to when done editing
    echo "<input type='hidden' name='r' value='",htmlescape($_REQUEST["r"]),"'/>\n";
  }

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  echo "<div class='$rowclass'><div class='$col1'><label for='stock_num'>Stock Num</label></div><div class='col'>";
  echo "<input id='stock_num' name='stock_num' maxlength='20' value='",htmlescape($row["STOCK_NUM"]),"' onchange='checkStockNum()'/>";
  echo " <spam id='stock_num_msg' class='info'></span>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='description'>Description</label></div><div class='col'>";
  $size = strlen($row["DESCRIPTION"]) < 30 ? 30 : strlen($row["DESCRIPTION"]);
  echo "<input id='description' name='description' maxlength='60' size=",$size," value='",htmlescape($row["DESCRIPTION"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='status'>Status</label></div><div class='col'>";
  $placeholder = $row["QTY"] < $row["MIN_QTY"] ? "placeholder='low'" : "";
  echo "<input id='status' name='status' ",$placeholder," maxlength='20' value='",htmlescape($row["STATUS"]),"'/>";
  echo "</div></div>\n";

  if( ENABLE_PART_LOCATION ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='location'>Location</label></div><div class='col'>";
    echo "<input id='location' name='location' maxlength='30' value='",htmlescape($row["LOCATION"]),"'/>";
    echo "</div></div>\n";

    echo "<div class='$rowclass'><div class='$col1'><label>&nbsp;</label></div><div class='col'>";
    echo "<input name='section' placeholder='sec' size='5' value='",htmlescape($row["SECTION"]),"'/>";
    echo "<input name='row' placeholder='row' size='5' value='",htmlescape($row["ROW"]),"'/>";
    echo "<input name='shelf' placeholder='dwr/shelf' size='5' value='",htmlescape($row["SHELF"]),"'/>";
    echo "</div></div>\n";
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='cost'>Cost</label></div><div class='col'>";
  echo "<input id='cost' name='cost' value='",htmlescape($row["COST"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='markup_type'>Markup</label></div><div class='col'>";
  echoSelectMarkupType($row["MARKUP_TYPE"],"cost","price");
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='price'>Price</label></div><div class='col'>";
  echo "<input id='price' name='price' value='",htmlescape($row["PRICE"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='qty'>Quantity in Stock</label></div><div class='col'>";
  echo "<input id='qty' name='qty' value='",htmlescape($row["QTY"]),"'/>";
  echo "<input type='hidden' name='orig_qty' value='",htmlescape($row["QTY"]),"'/>";
  echo "</div></div>\n";

  if( ENABLE_QTY_CORRECT ) {
    echo "<div class='$rowclass'><div class='$col1'></div><div class='col'>";
    $checked = $row["QTY_CORRECT"] ? "checked" : "";
    $as_of = $row["QTY_CORRECT"] && $row["QTY_CORRECT_DATE"] ? " as of " . $row["QTY_CORRECT_DATE"] : "";
    echo "<label><input type='checkbox' id='qty_correct' name='qty_correct' value='1' $checked onchange='qtyCorrectClicked()'/> quantity verified to be correct<span id='qty_correct_as_of'>{$as_of}</span></label>";
    echo "<input type='hidden' name='qty_correct_date_update' id='qty_correct_date_update' value='0'/>";
    echo "</div></div>\n";

    ?><script>
    function qtyCorrectClicked() {
      if( document.getElementById('qty_correct').checked ) {
        document.getElementById('qty_correct_date_update').value = '1';
        document.getElementById('qty_correct_as_of').innerText = " as of <?php echo date('Y-m-d'); ?>";
      } else {
        document.getElementById('qty_correct_as_of').innerText = "";
      }
    }
    </script><?php
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='qty_last_ordered'>Quantity Last Ordered</label></div><div class='col'>";
  echo "<input id='qty_last_ordered' name='qty_last_ordered' value='",htmlescape($row["QTY_LAST_ORDERED"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='min_qty'>Minimum Quantity</label></div><div class='col'>";
  echo "<input id='min_qty' name='min_qty' value='",htmlescape($row["MIN_QTY"]),"'/>";
  echo "</div></div>\n";

  if( ENABLE_BACKUP_QTY ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='backup_qty'>Backup Quantity</label></div><div class='col'>";
    echo "<input id='backup_qty' name='backup_qty' value='",htmlescape($row["BACKUP_QTY"]),"'/>";
    echo " - <input id='backup_qty_sub' name='backup_qty_sub' placeholder='amt moved to in stock'/>";
    echo "</div></div>\n";
  }

  if( ENABLE_BACKUP_QTY && ENABLE_PART_LOCATION ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='backup_location'>Backup Location</label></div><div class='col'>";
    echo "<input name='backup_location' maxlength='30' value='",htmlescape($row["BACKUP_LOCATION"]),"'/>";
    echo "</div></div>\n";

    echo "<div class='$rowclass'><div class='$col1'><label>&nbsp;</label></div><div class='col'>";
    echo "<input name='backup_section' placeholder='sec' size='5' value='",htmlescape($row["BACKUP_SECTION"]),"'/>";
    echo "<input name='backup_row' placeholder='row' size='5' value='",htmlescape($row["BACKUP_ROW"]),"'/>";
    echo "<input name='backup_shelf' placeholder='dwr/shelf' size='5' value='",htmlescape($row["BACKUP_SHELF"]),"'/>";
    echo "</div></div>\n";
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='units'>Units</label></div><div class='col'>";
  echo "<input id='units' name='units' maxlength='20' value='",htmlescape($row["UNITS"]),"'/>";
  echo "</div></div>\n";

  if( ENABLE_MANUFACTURER ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='manufacturer'>Manufacturer</label></div><div class='col'>";
    echo "<input id='manufacturer' name='manufacturer' maxlength='40' value='",htmlescape($row["MANUFACTURER"]),"'/>";
    echo "</div></div>\n";

    echo "<div class='$rowclass'><div class='$col1'><label for='manufacturer'>Manufacturer Part #</label></div><div class='col'>";
    echo "<input id='man_num' name='man_num' maxlength='20' value='",htmlescape($row["MAN_NUM"]),"'/>";
    echo "</div></div>\n";
  }

  echo "<div class='$rowclass'><div class='$col1'>No Recover (NR)</div><div class='col'>";
  $checked = $row["NO_RECOVER"] == 0 ? "checked" : "";
  echo "<label><input type='radio' id='no_recover_no' name='no_recover' value='0' $checked/> No</label> ";
  $checked = $row["NO_RECOVER"] == 1 ? "checked" : "";
  echo "<label><input type='radio' id='no_recover_yes' name='no_recover' value='1' $checked/> Yes</label> ";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Inactive/Obsolete</div><div class='col'>";
  $checked = !$row["INACTIVE"] ? "checked" : "";
  echo "<label><input type='radio' id='inactive_no' name='inactive' value='0' $checked/> No</label> ";
  $checked = $row["INACTIVE"] ? "checked" : "";
  echo "<label><input type='radio' id='inactive_yes' name='inactive' value='1' $checked/> Yes</label> ";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='vendor_id'>Vendor</label></div><div class='col'>";
  $vendor_id = $row["VENDOR_ID"];
  if( !$part_id && isset($_REQUEST["vendor_id"]) ) $vendor_id = $_REQUEST["vendor_id"];
  echoSelectVendor($vendor_id);
  echo " <a class='btn btn-primary btn-sm' href='?s=vendor'>New Vendor</a>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='vend_num'>Vendor Part #</label></div><div class='col'>";
  echo "<input id='vend_num' name='vend_num' maxlength='20' value='",htmlescape($row["VEND_NUM"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='image'>Image</label></div><div class='col'>";
  $url = getPartImageUrl($part_id,$row["IMAGE"],$row["STOCK_NUM"],$row["DESCRIPTION"]);
  if( $url ) {
    echo "<img class='part_img' src='",htmlescape($url),"'/><br>";
    $remove_image = $row["IMAGE"] ? "remove image" : "do not use default image";
    echo "<label><input type='checkbox' name='remove_image' value='1'/> {$remove_image}</label><br>";
    echo "Replace image: ";
  }
  echo "<input type='file' name='image'/>";
  echo "</div></div>\n";

  echo "<label for='notes' class='label'>Notes</label><br>\n";
  echo "<textarea id='notes' name='notes' rows='4' cols='40'>",htmlescape($row["NOTES"]),"</textarea>\n";

  echo "<div class='$rowclass'><div class='$col1'>Created</div><div class='col'>";
  echo htmlescape($row["CREATED"]);
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Updated</div><div class='col'>";
  echo htmlescape($row["UPDATED"]);
  echo "</div></div>\n";

  $disabled = !$part_id ? "disabled" : "";
  echo "<input id='save_btn' class='btn btn-secondary' type='submit' name='submit' value='Save'/ $disabled>\n";
  if( $part_id ) {
    echo "<input id='order_btn' class='btn btn-secondary' type='submit' name='submit' value='Order'/ $disabled>\n";
  }

  echo "</div></form>\n";

  echo "</div></div>\n"; # end of card

  if( $part_id ) {
    echo "<hr>";
    showPurchases(null,$part_id);
    echo "<hr>";
    showOrders($part_id);
  }
  ?>
  <script>
  function checkStockNum() {
    var stock_num_elem = document.getElementById("stock_num");
    var stock_num = stock_num_elem.value.trim();
    if( stock_num.indexOf(" ") >= 0 ) {
      document.getElementById("save_btn").disabled = true;
      document.getElementById("order_btn").disabled = true;
      $("#stock_num_msg").text("Stock number may not contain a space.");
      return;
    }
    if( stock_num == "" ) {
      document.getElementById("save_btn").disabled = true;
      document.getElementById("order_btn").disabled = true;
      $("#stock_num_msg").text("");
      return;
    }
    $.ajax({ url: "partinfo.php?part=" + encodeURI(stock_num), success: function(data) {
      var part_info = JSON.parse(data);
      if( part_info && !part_info["FOUND"] ) {
        document.getElementById("save_btn").disabled = false;
        document.getElementById("order_btn").disabled = false;
        $("#stock_num_msg").text("");
      }
      else if( part_info && part_info["part_id"] != '<?php echo $part_id ?>' ) {
        document.getElementById("save_btn").disabled = true;
        document.getElementById("order_btn").disabled = true;
        $("#stock_num_msg").text("Stock number " + stock_num + " is already in use.");
      }
    }});
  }
  function validatePart() {
    var missing = [];
    if( $('#cost').val() == "" ) {
      missing.push("cost");
    }

    if( missing.length ) {
      alert("The following information is missing: " + arrayToEnglishList(missing) + ".");
      return false;
    }
    return true;
  }
  </script>
  <?php
}

function echoSelectVendor($selected_vendor_id) {
  $dbh = connectDB();
  $sql = "SELECT VENDOR_ID,NAME FROM vendor WHERE NOT INACTIVE OR VENDOR_ID = :SELECTED_VENDOR_ID ORDER BY NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":SELECTED_VENDOR_ID",$selected_vendor_id);
  $stmt->execute();

  echo "<select name='vendor_id' id='vendor_id'>\n";
  echo "<option value=''>Select a Vendor</option>\n";
  while( ($row=$stmt->fetch()) ) {
    $selected = $row["VENDOR_ID"] == $selected_vendor_id ? "selected" : "";
    echo "<option value='",htmlescape($row["VENDOR_ID"]),"' $selected>",htmlescape($row["NAME"]),"</option>\n";
  }
  echo "</select>\n";
}

function savePart($user,&$show) {
  $show = $_REQUEST["submit"] == "Order" ? "order" : "part";

  # Use the same rule for extracting the stock num here as in partinfo.php to avoid problematic stock numbers getting created.
  # The user interface takes care of avoiding this even happening, so this is just for security.
  $stock_num = explode(" ",trim($_POST["stock_num"]))[0];

  $dbh = connectDB();
  $set_sql = "SET STOCK_NUM = :STOCK_NUM, DESCRIPTION = :DESCRIPTION";
  if( ENABLE_PART_LOCATION ) {
    $set_sql .= ", LOCATION = :LOCATION, SECTION = :SECTION, ROW = :ROW, SHELF = :SHELF";
  }
  $set_sql .= ", PRICE = :PRICE, COST = :COST, MARKUP_TYPE = :MARKUP_TYPE, QTY = IF(:ORIG_QTY <> :QTY,:QTY,QTY), QTY_LAST_ORDERED = :QTY_LAST_ORDERED, MIN_QTY = :MIN_QTY";
  if( ENABLE_QTY_CORRECT ) {
    $set_sql .= ", QTY_CORRECT = :QTY_CORRECT";
    if( (int)$_POST["qty_correct_date_update"] ) {
      $set_sql .= ", QTY_CORRECT_DATE = '" . date("Y-m-d") . "'";
    }
  }
  if( ENABLE_BACKUP_QTY ) {
    $set_sql .= ", BACKUP_QTY = :BACKUP_QTY";
  }
  if( ENABLE_BACKUP_QTY && ENABLE_PART_LOCATION ) {
    $set_sql .= ", BACKUP_LOCATION = :BACKUP_LOCATION, BACKUP_SECTION = :BACKUP_SECTION, BACKUP_ROW = :BACKUP_ROW, BACKUP_SHELF = :BACKUP_SHELF";
  }
  $set_sql .= ", UNITS = :UNITS, STATUS = :STATUS";
  if( ENABLE_MANUFACTURER ) {
    $set_sql .= ", MANUFACTURER = :MANUFACTURER, MAN_NUM = :MAN_NUM";
  }
  $set_sql .= ", NO_RECOVER = :NO_RECOVER, VENDOR_ID = :VENDOR_ID, VEND_NUM = :VEND_NUM, IMAGE = :IMAGE, INACTIVE = :INACTIVE, NOTES = :NOTES, UPDATED = now()";

  if( isset($_POST["part_id"]) && $_POST["part_id"] != "" ) {
    $part_id = $_POST["part_id"];
    $sql = "UPDATE part $set_sql WHERE PART_ID = :PART_ID";
  } else {
    $part_id = null;
    $sql = "INSERT INTO part $set_sql, CREATED = now()";
  }

  $before_change = getPartRecord($part_id);

  $img_fname = $before_change ? $before_change["IMAGE"] : null;
  if( $img_fname === null ) $img_fname = "";
  $orig_img_fname = $img_fname;
  if( $img_fname && ((isset($_POST["remove_image"]) && $_POST["remove_image"]) || (isset($_FILES["image"]) && $_FILES["image"]["name"])) ) {
    $full_fname = getPartImagePath($part_id,$img_fname);
    if( $full_fname ) {
      unlink($full_fname);
    }
    $img_fname = "";
  }
  if( isset($_FILES["image"]) && $_FILES["image"]["name"] ) {
    $uploaded_file = basename($_FILES["image"]["name"]);
    $file_ext = strtolower(pathinfo($uploaded_file,PATHINFO_EXTENSION));
    $img_fname = "." . $file_ext; # do not know part_id yet (for new parts), so can't include that in img_fname

    # if there was already an image, change the name to avoid browser cache issues
    if( preg_match("/_([0-9]+)[.].*/",$orig_img_fname,$match) ) {
      $img_fname = "_" . ($match[1] + 1) . $img_fname;
    } else if( $orig_img_fname ) {
      $img_fname = "_1" . $img_fname;
    }
  } else if( !$img_fname && isset($_POST["remove_image"]) && $_POST["remove_image"] ) {
    $img_fname = "none"; # override stock_img
  }

  $stmt = $dbh->prepare($sql);
  if( $part_id !== null ) $stmt->bindValue(":PART_ID",$part_id);
  $stmt->bindValue(":STOCK_NUM",$stock_num);
  $stmt->bindValue(":DESCRIPTION",$_POST["description"]);
  if( ENABLE_PART_LOCATION ) {
    $stmt->bindValue(":LOCATION",$_POST["location"]);
    $stmt->bindValue(":SECTION",$_POST["section"]);
    $stmt->bindValue(":ROW",$_POST["row"]);
    $stmt->bindValue(":SHELF",$_POST["shelf"]);
  }
  $stmt->bindValue(":COST",$_POST["cost"]);
  $stmt->bindValue(":MARKUP_TYPE",$_POST["markup_type"]);
  $stmt->bindValue(":PRICE",$_POST["price"]);
  $backup_qty_sub = isset($_POST["backup_qty_sub"]) ? intval($_POST["backup_qty_sub"]) : 0;
  $stmt->bindValue(":QTY",intval($_POST["qty"])+$backup_qty_sub);
  $stmt->bindValue(":ORIG_QTY",$_POST["orig_qty"]);
  $stmt->bindValue(":QTY_LAST_ORDERED",intval($_POST["qty_last_ordered"]));
  $stmt->bindValue(":MIN_QTY",intval($_POST["min_qty"]));
  if( ENABLE_QTY_CORRECT ) {
    $stmt->bindValue(":QTY_CORRECT",isset($_POST["qty_correct"]) ? 1 : 0);
  }
  if( ENABLE_BACKUP_QTY ) {
    $stmt->bindValue(":BACKUP_QTY",intval($_POST["backup_qty"])-$backup_qty_sub);
  }
  if( ENABLE_BACKUP_QTY && ENABLE_PART_LOCATION ) {
    $stmt->bindValue(":BACKUP_LOCATION",$_POST["backup_location"]);
    $stmt->bindValue(":BACKUP_SECTION",$_POST["backup_section"]);
    $stmt->bindValue(":BACKUP_ROW",$_POST["backup_row"]);
    $stmt->bindValue(":BACKUP_SHELF",$_POST["backup_shelf"]);
  }
  $stmt->bindValue(":UNITS",$_POST["units"]);
  $stmt->bindValue(":STATUS",$_POST["status"]);
  if( ENABLE_MANUFACTURER ) {
    $stmt->bindValue(":MANUFACTURER",$_POST["manufacturer"]);
    $stmt->bindValue(":MAN_NUM",isset($_POST["man_num"]) ? $_POST["man_num"] : $before_change["MAN_NUM"]);
  }
  $stmt->bindValue(":NO_RECOVER",$_POST["no_recover"]);
  $stmt->bindValue(":VENDOR_ID",$_POST["vendor_id"]!="" ? $_POST["vendor_id"] : 0);
  $stmt->bindValue(":VEND_NUM",$_POST["vend_num"]);
  $stmt->bindValue(":IMAGE",$img_fname);
  $stmt->bindValue(":INACTIVE",$_POST["inactive"]);
  $stmt->bindValue(":NOTES",$_POST["notes"] ? $_POST["notes"] : null);
  $stmt->execute();

  $orig_part_id = $part_id;
  if( $part_id === null ) {
    $part_id = $dbh->lastInsertId();
    $_REQUEST["part_id"] = $part_id;
  }

  # now that part_id is known, save the image file
  if( isset($_FILES["image"]) && $_FILES["image"]["name"] ) {
    $full_fname = getPartImagePath($part_id,$img_fname);
    $d = dirname($full_fname);
    if( !file_exists($d) ) {
      mkdir($d,0755,true);
    }
    if( !move_uploaded_file($_FILES["image"]["tmp_name"],$full_fname) ) {
      echo "<div class='alert alert-danger'>Unable to save uploaded file ",htmlescape($_FILES["image"]["name"]),"!</div>\n";
    }
  }

  $after_change = getPartRecord($part_id);
  auditlogModifyPart($user,$before_change,$after_change);

  if( $_REQUEST["submit"] != "Order" ) {
    $url = "?" . $_SERVER["QUERY_STRING"];
    if( $orig_part_id === null ) $url .= "&part_id=" . $part_id;
    echo "<div class='alert alert-success'>Saved <a href='",htmlescape($url),"'>",htmlescape($stock_num)," - ",htmlescape($_POST["description"]),"</a>.</div>\n";

    if( isset($_REQUEST["r"]) ) {
      $show = $_REQUEST["r"];
      # clear request variables to prevent parts view from seeing them
      unset($_REQUEST["vendor_id"]);
      unset($_REQUEST["inactive"]);
    }
  }
}

function usableUrl($url) {
  if( !$url ) return $url;
  if( preg_match("|^[^:/.]*:|",$url) ) {
    return $url;
  }
  return "http://" . $url;
}

function showVendors() {

  $dbh = connectDB();

  echo "<h2>Vendors</h2>\n";

  echo "<p><a href='?s=vendor' class='btn btn-primary'>New Vendor</a>";
  $url = "?s=vendors";
  if( isset($_REQUEST["inactive"]) ) {
    echo " <a href='",htmlescape($url),"' class='btn btn-primary'>Show Active Vendors</a>";
    $where_inactive = "AND INACTIVE";
  } else {
    echo " <a href='",htmlescape($url . "&inactive"),"' class='btn btn-primary'>Show Inactive/Obsolete Vendors</a>";
    $where_inactive = "AND NOT INACTIVE";
  }
  echo "</p>\n";

  $sql = "SELECT NAME,VENDOR_ID,UW_NUMBER,WWWURL FROM vendor WHERE True {$where_inactive} ORDER BY NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  echo "<table class='records clicksort'><thead><tr><th>Name</th><th>UW Number</th><th>URL</th></tr></thead><tbody>\n";
  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=vendor&vendor_id=" . $row["VENDOR_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["NAME"]),"</a></td>";
    echo "<td>",htmlescape($row["UW_NUMBER"]),"</td>";
    $url = $row["WWWURL"];
    echo "<td><a href='",htmlescape(usableUrl($url)),"'>",htmlescape($url),"</a></td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";
}

function editVendor() {
  $vendor_id = isset($_REQUEST["vendor_id"]) ? $_REQUEST["vendor_id"] : null;

  $dbh = connectDB();
  if( $vendor_id !== null ) {
    $sql = "SELECT * FROM vendor WHERE vendor_id = :VENDOR_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":VENDOR_ID",$vendor_id);
  } else {
    $sql = "SELECT * FROM (select 1) one LEFT JOIN vendor ON vendor.VENDOR_ID IS NULL";
    $stmt = $dbh->prepare($sql);
  }
  $stmt->execute();
  $row = $stmt->fetch();

  echo "<div class='card card-md'><div class='card-body'>\n";

  echo "<form id='checkout_form' enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='hidden' name='form' value='vendor'/>\n";
  echo "<input type='hidden' name='vendor_id' value='",htmlescape($vendor_id),"'/>\n";

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  echo "<div class='$rowclass'><div class='$col1'><label for='name'>Vendor Name</label></div><div class='col'>";
  echo "<input id='name' name='name' maxlength='60' value='",htmlescape($row["NAME"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='uw_number'>UW Number</label></div><div class='col'>";
  echo "<input id='uw_number' name='uw_number' maxlength='20' value='",htmlescape($row["UW_NUMBER"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='address1'>Address Line1</label></div><div class='col'>";
  echo "<input id='address1' name='address1' maxlength='60' value='",htmlescape($row["ADDRESS1"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='address2'>Address Line2</label></div><div class='col'>";
  echo "<input id='address2' name='address2' maxlength='60' value='",htmlescape($row["ADDRESS2"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='city_state_zip'>City, State Zip</label></div><div class='col'>";
  echo "<input id='city_state_zip' name='city_state_zip' maxlength='60' value='",htmlescape($row["CITY_STATE_ZIP"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='phone'>Phone</label></div><div class='col'>";
  echo "<input id='phone' name='phone' maxlength='20' value='",htmlescape($row["PHONE"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='phone_ext'>Phone Ext</label></div><div class='col'>";
  echo "<input id='phone_ext' name='phone_ext' maxlength='20' value='",htmlescape($row["PHONE_EXT"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='fax'>Fax</label></div><div class='col'>";
  echo "<input id='fax' name='fax' maxlength='20' value='",htmlescape($row["FAX"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='rep'>Rep</label></div><div class='col'>";
  echo "<input id='rep' name='rep' maxlength='60' value='",htmlescape($row["REP"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='discount'>Discount</label></div><div class='col'>";
  echo "<input id='discount' name='discount' value='",htmlescape($row["DISCOUNT"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='blanket'>Blanket</label></div><div class='col'>";
  echo "<input id='blanket' name='blanket' maxlength='60' value='",htmlescape($row["BLANKET"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='email'>Email</label></div><div class='col'>";
  echo "<input id='email' name='email' maxlength='60' value='",htmlescape($row["EMAIL"]),"'/>";
  if( $row["EMAIL"] ) echo " <a class='btn btn-secondary btn-sm' href='mailto:",htmlescape($row["EMAIL"]),"'>Compose</a>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='wwwurl'>URL</label></div><div class='col'>";
  echo "<input id='wwwurl' name='wwwurl' maxlength='60' value='",htmlescape($row["WWWURL"]),"'/>";
  if( $row["WWWURL"] ) echo " <a class='btn btn-secondary btn-sm' href='",htmlescape(usableUrl($row["WWWURL"])),"'>Open</a>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Inactive/Obsolete</div><div class='col'>";
  $checked = !$row["INACTIVE"] ? "checked" : "";
  echo "<label><input type='radio' id='inactive_no' name='inactive' value='0' $checked/> No</label> ";
  $checked = $row["INACTIVE"] ? "checked" : "";
  echo "<label><input type='radio' id='inactive_yes' name='inactive' value='1' $checked/> Yes</label> ";
  echo "</div></div>\n";

  echo "<label for='notes' class='label'>Notes</label><br>\n";
  echo "<textarea id='notes' name='notes' rows='4' cols='40'>",htmlescape($row["NOTES"]),"</textarea>\n";

  echo "<div class='$rowclass'><div class='$col1'>Created</div><div class='col'>";
  echo htmlescape($row["CREATED"]);
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'>Updated</div><div class='col'>";
  echo htmlescape($row["UPDATED"]);
  echo "</div></div>\n";

  echo "<input type='submit' value='Save'/>\n";

  echo "</div></form>\n";

  echo "</div></div>\n"; # end of card

  if( $vendor_id ) {
    echo "<hr>\n";
    showParts($vendor_id,false,false);
    echo "<p>&nbsp;</p>\n";
    echo "<hr>\n";
    showOrders(null,$vendor_id);
  }
}

function saveVendor($user,&$show) {
  $show = "vendor";

  $dbh = connectDB();
  $set_sql = "SET NAME = :NAME, UW_NUMBER = :UW_NUMBER, ADDRESS1 = :ADDRESS1, ADDRESS2 = :ADDRESS2, CITY_STATE_ZIP = :CITY_STATE_ZIP, PHONE = :PHONE, PHONE_EXT = :PHONE_EXT, FAX = :FAX, REP = :REP, DISCOUNT = :DISCOUNT, BLANKET = :BLANKET, EMAIL = :EMAIL, WWWURL = :WWWURL, INACTIVE = :INACTIVE, NOTES = :NOTES, UPDATED = now()";

  if( isset($_POST["vendor_id"]) && $_POST["vendor_id"] != "" ) {
    $vendor_id = $_POST["vendor_id"];
    $sql = "UPDATE vendor $set_sql WHERE VENDOR_ID = :VENDOR_ID";
  } else {
    $vendor_id = null;
    $sql = "INSERT INTO vendor $set_sql, CREATED = now()";
  }

  $before_change = getVendorRecord($vendor_id);

  $stmt = $dbh->prepare($sql);
  if( $vendor_id !== null ) $stmt->bindValue(":VENDOR_ID",$vendor_id);
  $stmt->bindValue(":NAME",$_POST["name"]);
  $stmt->bindValue(":UW_NUMBER",$_POST["uw_number"]);
  $stmt->bindValue(":ADDRESS1",$_POST["address1"]);
  $stmt->bindValue(":ADDRESS2",$_POST["address2"]);
  $stmt->bindValue(":CITY_STATE_ZIP",$_POST["city_state_zip"]);
  $stmt->bindValue(":PHONE",$_POST["phone"]);
  $stmt->bindValue(":PHONE_EXT",$_POST["phone_ext"]);
  $stmt->bindValue(":FAX",$_POST["fax"]);
  $stmt->bindValue(":REP",$_POST["rep"]);
  $stmt->bindValue(":DISCOUNT",$_POST["discount"] != "" ? $_POST["discount"] : null);
  $stmt->bindValue(":BLANKET",$_POST["blanket"]);
  $stmt->bindValue(":EMAIL",$_POST["email"]);
  $stmt->bindValue(":WWWURL",$_POST["wwwurl"]);
  $stmt->bindValue(":INACTIVE",$_POST["inactive"]);
  $stmt->bindValue(":NOTES",$_POST["notes"] ? $_POST["notes"] : null);
  $stmt->execute();

  if( $vendor_id === null ) {
    $vendor_id = $dbh->lastInsertId();
    $_REQUEST["vendor_id"] = $vendor_id;
  }

  $after_change = getVendorRecord($vendor_id);
  auditlogModifyVendor($user,$before_change,$after_change);

  echo "<div class='alert alert-success'>Saved.</div>\n";
}

function showOrders($part_id=null,$vendor_id=null) {

  if( !$part_id && isset($_REQUEST["part_id"]) ) {
    $part_id = $_REQUEST["part_id"];
  }

  if( !$vendor_id && isset($_REQUEST["vendor_id"]) ) {
    $vendor_id = $_REQUEST["vendor_id"];
  }

  $part_sql = $part_id ? "AND po.PART_ID = :PART_ID" : "";
  $vendor_sql = $vendor_id ? "AND po.VENDOR_ID = :VENDOR_ID" : "";

  $dbh = connectDB();
  $most_recent_bunch_stmt = null;
  if( $part_id ) {
    $sql = "
      SELECT
        DATE(po.ORDERED) as DATE
      FROM
        part_order po
      WHERE
        TRUE
        $part_sql
        $vendor_sql
      ORDER BY
        po.ORDERED DESC
      LIMIT 10";
    $most_recent_bunch_stmt = $dbh->prepare($sql);
    if( $part_id ) $most_recent_bunch_stmt->bindValue(":PART_ID",$part_id);
    if( $vendor_id ) $most_recent_bunch_stmt->bindValue(":VENDOR_ID",$vendor_id);
  }

  $page = new TimePager('orders',$part_id,false,$most_recent_bunch_stmt);

  $sql = "
    SELECT
      po.ORDER_ID,
      po.ORDERED,
      po.CLOSED,
      po.COST,
      po.QTY,
      po.RECEIVED,
      po.UNITS,
      po.VEND_NUM,
      part.PART_ID,
      part.STOCK_NUM,
      part.DESCRIPTION,
      vendor.VENDOR_ID,
      vendor.NAME as VENDOR_NAME
    FROM
      part_order po
      INNER JOIN part ON part.PART_ID = po.PART_ID
      INNER JOIN vendor ON vendor.VENDOR_ID = po.VENDOR_ID
    WHERE
      ORDERED >= :START AND ORDERED < :END
      $part_sql
      $vendor_sql
    ORDER BY ORDERED DESC, ORDER_ID DESC";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START",$page->start);
  $stmt->bindValue(":END",$page->end);
  if( $part_id ) $stmt->bindValue(":PART_ID",$part_id);
  if( $vendor_id ) $stmt->bindValue(":VENDOR_ID",$vendor_id);
  $stmt->execute();

  $vendor_title = "";
  if( $vendor_id ) {
    $vendor = getVendorRecord($vendor_id);
    $vendor_title = " from " . $vendor["NAME"];
  }
  $part_title = "";
  if( $part_id ) {
    $part = getPartRecord($part_id);
    $part_title = " for " . $part["DESCRIPTION"];
  }

  echo "<h1>",htmlescape($page->pre_title)," Orders ",htmlescape($page->time_title),htmlescape($part_title),htmlescape($vendor_title),"</h1>\n";

  $base_url = "?s=orders";
  if( $part_id ) $base_url .= "&part_id=" . $part_id;
  if( $vendor_id ) $base_url .= "&vendor_id=" . $vendor_id;

  $page->pageButtons($base_url);

  $url = "?s=order";
  if( $part_id ) $url .= "&part_id=" . $part_id;
  if( $vendor_id ) $url .= "&vendor_id=" . $vendor_id;
  echo "<a href='",htmlescape($url),"' class='btn btn-primary'>New Order</a>\n";

  if( !$part_id ) {
    $url = "?s=batch_order";
    if( $vendor_id ) $url .= "&vendor_id=" . $vendor_id;
    echo "<a href='",htmlescape($url),"' class='btn btn-primary'>Batch Order</a>\n";
  }

  $page->moreOptionsButton();
  $hidden_vars = array();
  if( $part_id ) {
    $hidden_vars['part_id'] = $part_id;
  }
  $page->moreOptions($hidden_vars);

  echo "<table class='records clicksort'><thead><tr><td></td><th>Ordered</th><th>Closed</th><th>Part</th><th>Vendor</th><th>Part #</th><th>Cost</th><th>Qty</th><th>Total</th><th>Rcvd</th><th>Units</th></tr></thead><tbody>\n";
  $total = 0;
  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=order&order_id=" . $row["ORDER_ID"];
    echo "<td><a href='",htmlescape($url),"' class='icon'><i class='far fa-edit'></i></a></td>";
    echo "<td>",htmlescape($row["ORDERED"]),"</td>";
    echo "<td>",htmlescape($row["CLOSED"]),"</td>";
    $url = "?s=part&part_id=" . $row["PART_ID"];
    echo "<td><a href='",htmlescape($url),"'><tt>",htmlescape($row["STOCK_NUM"]),"</tt></a> - ",htmlescape($row["DESCRIPTION"]),"</td>";
    $url = "?s=vendor&vendor_id=" . $row["VENDOR_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["VENDOR_NAME"]),"</a></td>";
    echo "<td>",htmlescape($row["VEND_NUM"]),"</td>";
    echo "<td>",htmlescape($row["COST"]),"</td>";
    echo "<td>",htmlescape($row["QTY"]),"</td>";
    $this_total = $row["COST"] * $row["QTY"];
    $total += $this_total;
    echo "<td>",htmlescape(sprintf("%.2f",$this_total)),"</td>";
    echo "<td>",htmlescape($row["RECEIVED"]),"</td>";
    echo "<td>",htmlescape($row["UNITS"]),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody>";
  echo "<tfoot><tr class='record'><td></td><td></td><td></td></td></td><td></td><td></td><td></td><td colspan='2'><b>Total</b></td><td><b>",sprintf("%.2f",$total),"</b></td><td></td><td></td></tr></tfoot>\n";
  echo "</table>\n";
  echo "<p>&nbsp;</p>\n";
}
function echoSelectMarkupTypeCompact($selected_markup_type,$cost_id,$price_id) {
  echoSelectMarkupType($selected_markup_type,$cost_id,$price_id,"compact");
}

function echoSelectMarkupType($selected_markup_type,$cost_id,$price_id,$style="") {
  echo "<select id='markup_type' name='markup_type' data-cost_id='",htmlescape($cost_id),"' data-price_id='",htmlescape($price_id),"' onchange='recalcPrice(this)'>";
  $selected = $selected_markup_type == STANDARD_MARKUP_CODE ? "selected" : "";
  if( $style == "compact" ) {
    echo "<option value='",STANDARD_MARKUP_CODE,"' $selected>",STANDARD_MARKUP_PCT,"%</option>";
  } else {
    echo "<option value='",STANDARD_MARKUP_CODE,"' $selected> Standard (",STANDARD_MARKUP_PCT,"%)</option>";
  }
  $selected = $selected_markup_type == CUSTOM_MARKUP_CODE ? "selected" : "";
  if( $style == "compact" ) {
    echo "<option value='",CUSTOM_MARKUP_CODE,"' $selected>Custom</option>";
  } else {
    echo "<option value='",CUSTOM_MARKUP_CODE,"' $selected> Custom (manually set price)</option>";
  }
  $selected = $selected_markup_type == NO_MARKUP_CODE ? "selected" : "";
  echo "<option value='",NO_MARKUP_CODE,"' $selected>None</option>";
  echo "</select>";
  ?>
  <script>
    function recalcPrice(markup_type_e) {
      if( markup_type_e.dataset.cost_id == "none" ) return;
      var price_e = document.getElementById(markup_type_e.dataset.price_id);
      var markup_type = markup_type_e.options[markup_type_e.selectedIndex].value;
      var cost = document.getElementById(markup_type_e.dataset.cost_id).value;
      if( markup_type == <?php echo STANDARD_MARKUP_CODE ?> && cost != "" ) {
        var price = <?php echo 1.0 + STANDARD_MARKUP_PCT*1.0/100.0 ?>*cost;
        price_e.value = price.toFixed(2);
        price_e.readOnly = true;
      } else if( markup_type == <?php echo CUSTOM_MARKUP_CODE ?> ) {
        price_e.readOnly = false;
      } else if( markup_type == <?php echo NO_MARKUP_CODE ?> ) {
        price_e.value = cost;
        price_e.readOnly = true;
      }
    }
    function initMarkupTypeSelector(markup_type_e) {
      if( markup_type_e.dataset.cost_id == "none" ) return;
      recalcPrice(markup_type_e);
      document.getElementById(markup_type_e.dataset.cost_id).addEventListener("change",function() {
        recalcPrice(markup_type_e);
      });
    }
    document.addEventListener("DOMContentLoaded", function(event) {
      initMarkupTypeSelector(document.getElementById('markup_type'));
    });
  </script>
  <?php
}

function editOrder() {
  $order_id = isset($_REQUEST["order_id"]) ? $_REQUEST["order_id"] : null;

  $dbh = connectDB();
  $select_sql = "
    po.ORDER_ID,
    CASE WHEN po.PART_ID IS NOT NULL THEN po.PART_ID ELSE part.PART_ID END as PART_ID,
    CASE WHEN po.VENDOR_ID IS NOT NULL THEN po.VENDOR_ID ELSE part.VENDOR_ID END as VENDOR_ID,
    CASE WHEN po.COST IS NOT NULL THEN po.COST ELSE part.COST END as COST,
    CASE WHEN po.PRICE IS NOT NULL THEN po.PRICE ELSE part.PRICE END as PRICE,
    CASE WHEN po.MARKUP_TYPE IS NOT NULL THEN po.MARKUP_TYPE ELSE part.MARKUP_TYPE END as MARKUP_TYPE,
    CASE WHEN po.UNITS IS NOT NULL THEN po.UNITS ELSE part.UNITS END as UNITS,
    CASE WHEN po.QTY IS NOT NULL THEN po.QTY ELSE part.QTY_LAST_ORDERED END as QTY,
    CASE WHEN po.MANUFACTURER IS NOT NULL THEN po.MANUFACTURER ELSE part.MANUFACTURER END as MANUFACTURER,
    CASE WHEN po.MAN_NUM IS NOT NULL THEN po.MAN_NUM ELSE part.MAN_NUM END as MAN_NUM,
    CASE WHEN po.VEND_NUM IS NOT NULL THEN po.VEND_NUM ELSE part.VEND_NUM END as VEND_NUM,
    po.PO_ID,
    po.RECEIVED,
    po.SHIP_TO,
    po.ORDERED,
    po.CLOSED,
    part.PART_ID,
    part.STOCK_NUM,
    part.DESCRIPTION,
    part.NOTES";

  if( $order_id !== null ) {
    $sql = "SELECT $select_sql FROM part_order po INNER JOIN part ON part.PART_ID = po.PART_ID WHERE order_id = :ORDER_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":ORDER_ID",$order_id);
  } else {
    $sql = "SELECT $select_sql FROM (select 1) one LEFT JOIN part_order po ON po.ORDER_ID IS NULL LEFT JOIN part ON part.PART_ID ";
    $part_id = isset($_REQUEST["part_id"]) ? $_REQUEST["part_id"] : null;
    if( $part_id ) {
      $sql .= "= :PART_ID";
    } else {
      showWishList();
      return;
      #$sql .= "IS NULL";
    }
    $stmt = $dbh->prepare($sql);
    if( $part_id ) $stmt->bindValue(":PART_ID",$part_id);
  }
  $stmt->execute();
  $row = $stmt->fetch();

  echo "<div class='card card-md'><div class='card-body'>\n";

  $new_order = $order_id !== null ? "" : "New ";
  echo "<h2>{$new_order}Order</h2>\n";

  echo "<form id='checkout_form' enctype='multipart/form-data' method='POST' autocomplete='off' action='.'>\n";
  echo "<input type='hidden' name='form' value='order'/>\n";
  echo "<input type='hidden' name='order_id' value='",htmlescape($order_id),"'/>\n";

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  echo "<div class='$rowclass'><div class='$col1'><label for='name'>Part</label></div><div class='col'>";
  if( $row["STOCK_NUM"] ) {
    $url = "?s=part&part_id=" . $row["PART_ID"];
    echo "<a href='",htmlescape($url),"'><tt>",htmlescape($row["STOCK_NUM"]),"</tt></a> - ",htmlescape($row["DESCRIPTION"]);
  }
  echo "<input type='hidden' name='part_id' value='",htmlescape($row["PART_ID"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='vendor_id'>Vendor</label></div><div class='col'>";
  $vendor_id = $row["VENDOR_ID"];
  if( !$vendor_id && isset($_REQUEST["vendor_id"]) ) $vendor_id = $_REQUEST["vendor_id"];
  echoSelectVendor($vendor_id);
  echo " <a class='btn btn-primary btn-sm' href='?s=vendor'>New Vendor</a>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='cost'>Cost</label></div><div class='col'>";
  echo "<input id='cost' name='cost' value='",htmlescape($row["COST"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='markup_type'>Markup</label></div><div class='col'>";
  echoSelectMarkupType( NEW_ORDERS_PRESERVE_MARKUP_TYPE ? $row["MARKUP_TYPE"] : NEW_ORDER_MARKUP_TYPE,"cost","price");
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='price'>Price</label></div><div class='col'>";
  echo "<input id='price' name='price' value='",htmlescape($row["PRICE"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='units'>Units</label></div><div class='col'>";
  echo "<input id='units' name='units' maxlength='10' value='",htmlescape($row["UNITS"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='qty'>Quantity Ordered</label></div><div class='col'>";
  echo "<input id='qty' name='qty' value='",htmlescape($row["QTY"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='received'>Quantity Received</label></div><div class='col'>";
  echo "<input id='received' name='received' value='",htmlescape($row["RECEIVED"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='ship_to'>Ship To</label></div><div class='col'>";
  echo "<input id='ship_to' name='ship_to' maxlength='60' value='",htmlescape($row["SHIP_TO"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='manufacturer'>Manufacturer</label></div><div class='col'>";
  echo "<input id='manufacturer' name='manufacturer' maxlength='40' value='",htmlescape($row["MANUFACTURER"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='man_num'>Manufacturer Part #</label></div><div class='col'>";
  echo "<input id='man_num' name='man_num' maxlength='20' value='",htmlescape($row["MAN_NUM"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='vend_num'>Vendor Part #</label></div><div class='col'>";
  echo "<input id='vend_num' name='vend_num' maxlength='20' value='",htmlescape($row["VEND_NUM"]),"'/>";
  echo "</div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='po_id'>PO ID</label></div><div class='col'>";
  echo "<input id='po_id' name='po_id' maxlength='15' value='",htmlescape($row["PO_ID"]),"'/>";
  echo "</div></div>\n";

  echo "<label for='notes' class='label'>Notes on this part</label><br>\n";
  echo "<textarea id='notes' name='notes' rows='4' cols='40'>",htmlescape($row["NOTES"]),"</textarea>\n";

  if( $row["ORDERED"] ) {
    echo "<div class='$rowclass'><div class='$col1'>Ordered</div><div class='col'>";
    echo htmlescape($row["ORDERED"]);
    echo "</div></div>\n";
  }

  if( $row["CLOSED"] ) {
    echo "<div class='$rowclass'><div class='$col1'>Closed</div><div class='col'>";
    echo htmlescape($row["CLOSED"]);
    echo "</div></div>\n";
  }
  $checked = $row["CLOSED"] ? "checked" : "";
  echo "<div class='$rowclass'><div class='col'><label><input type='checkbox' id='closed' name='closed' value='1' $checked onchange='orderClosedChanged()'/> order closed</label></div></div>\n";

  echo "<input type='submit' name='submit' value='Save'/>\n";

  if( $order_id !== null ) {
    echo "<input type='submit' name='submit' value='Clone'/>\n";

    echo "<input type='submit' name='submit' value='Delete'/>\n";
  }

  echo "</div></form>\n";

  echo "</div></div>\n"; # end of card

  echo "<hr>";
  showPurchases(null,$row['PART_ID']);
  echo "<hr>";
  showOrders($row['PART_ID']);

  ?>
  <script>
    function orderClosedChanged() {
      var closed = document.getElementById('closed').checked;
      if( closed ) {
        var received = document.getElementById('received');
        if( received.value == "" ) {
          received.value = document.getElementById('qty').value;
        }
      }
    }
  </script>
  <?php
}

function saveOrder($user,&$show) {
  $show = "order";
  $dbh = connectDB();

  if( $_POST["submit"] == "Delete" ) {
    if( !isset($_POST["order_id"]) || $_POST["order_id"] == "" ) {
      echo "<div class='alert alert-success'>Canceled creation of order.</div>\n";
    } else {
      $order_id = $_POST["order_id"];
      $before_change = getOrderRecord($order_id);
      $sql = "DELETE FROM part_order WHERE ORDER_ID = :ORDER_ID";
      $stmt = $dbh->prepare($sql);
      $stmt->bindValue(":ORDER_ID",$_POST["order_id"]);
      $stmt->execute();
      auditlogModifyOrder($user,$before_change,null);

      # update part status to indicate that it is not on order
      $part_id = $_POST["part_id"];
      $part_before_change = getPartRecord($part_id);
      $sql = "UPDATE part SET STATUS='' WHERE PART_ID = :PART_ID AND STATUS in ('PO','BLNK') AND NOT EXISTS(select ORDER_ID FROM part_order WHERE part_order.PART_ID = :PART_ID AND part_order.CLOSED IS NULL)";
      $stmt = $dbh->prepare($sql);
      $stmt->bindValue(":PART_ID",$part_id);
      $stmt->execute();

      $part_after_change = getPartRecord($part_id);
      auditlogModifyPart($user,$part_before_change,$part_after_change);

      echo "<div class='alert alert-success'>Deleted order.</div>\n";
      if( $before_change["CLOSED"] ) {
        echo "<div class='alert alert-warning'>NOTE: The quantity ordered has <em>not</em> been automatically subtracted from the quantity in stock.</div>\n";
      }
    }
    unset($_POST["vendor_id"]);
    unset($_POST["order_id"]);
    $show = "orders";
    return;
  }

  if( $_POST["submit"] == "Clone" ) {
    unset($_POST["order_id"]);
    unset($_REQUEST["order_id"]);
    unset($_POST["closed"]);
    $_POST["received"] = "";
  }

  $set_sql = "SET PART_ID = :PART_ID, VENDOR_ID = :VENDOR_ID, COST = :COST, PRICE = :PRICE, MARKUP_TYPE = :MARKUP_TYPE, UNITS = :UNITS, QTY = :QTY, RECEIVED = :RECEIVED, SHIP_TO = :SHIP_TO, MANUFACTURER =:MANUFACTURER, MAN_NUM = :MAN_NUM, VEND_NUM = :VEND_NUM, PO_ID = :PO_ID";

  if( isset($_POST["closed"]) && $_POST["closed"] ) {
    $set_sql .= ",CLOSED = CASE WHEN CLOSED IS NULL THEN now() ELSE CLOSED END";
  } else {
    $set_sql .= ",CLOSED = NULL";
  }

  if( isset($_POST["order_id"]) && $_POST["order_id"] != "" ) {
    $order_id = $_POST["order_id"];
    $sql = "UPDATE part_order $set_sql WHERE ORDER_ID = :ORDER_ID";
  } else {
    $order_id = null;
    $sql = "INSERT INTO part_order $set_sql, ORDERED = now()";
  }

  $before_change = getOrderRecord($order_id);

  $dbh->beginTransaction();

  $stmt = $dbh->prepare($sql);
  if( $order_id !== null ) $stmt->bindValue(":ORDER_ID",$order_id);
  $stmt->bindValue(":PART_ID",$_POST["part_id"]);
  $stmt->bindValue(":VENDOR_ID",$_POST["vendor_id"]);
  $stmt->bindValue(":COST",$_POST["cost"]);
  $stmt->bindValue(":PRICE",$_POST["price"]);
  $stmt->bindValue(":MARKUP_TYPE",$_POST["markup_type"]);
  $stmt->bindValue(":UNITS",$_POST["units"]);
  $stmt->bindValue(":QTY",$_POST["qty"]);
  $stmt->bindValue(":RECEIVED",$_POST["received"] == "" ? null : $_POST["received"]);
  $stmt->bindValue(":SHIP_TO",$_POST["ship_to"]);
  $stmt->bindValue(":MANUFACTURER",$_POST["manufacturer"]);
  $stmt->bindValue(":MAN_NUM",$_POST["man_num"]);
  $stmt->bindValue(":VEND_NUM",$_POST["vend_num"]);
  $stmt->bindValue(":PO_ID",$_POST["po_id"]);
  $stmt->execute();

  if( $order_id === null ) {
    $order_id = $dbh->lastInsertId();
    $_REQUEST["order_id"] = $order_id;
  }

  $after_change = getOrderRecord($order_id);
  auditlogModifyOrder($user,$before_change,$after_change);

  if( !$before_change ) {
    # update the part status to indicate that it is ordered

    $part_id = $_POST["part_id"];
    $sql = "UPDATE part SET UPDATED = now(), STATUS = :STATUS WHERE PART_ID = :PART_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":PART_ID",$part_id);
    $status = ($_POST["po_id"] && $_POST["po_id"] != "BLNK" && $_POST["po_id"] != "BLANKET") ? "PO" : "BLNK";
    $stmt->bindValue(":STATUS",$status);

    $part_before_change = getPartRecord($part_id);

    $stmt->execute();

    $part_after_change = getPartRecord($part_id);
    auditlogModifyPart($user,$part_before_change,$part_after_change);
  }

  if( (!$before_change || $before_change["CLOSED"] === null) && $after_change["CLOSED"] !== null ) {
    # update part with new information from this order

    $part_id = $_POST["part_id"];
    $part_before_change = getPartRecord($part_id);

    $sql = "UPDATE part SET UPDATED = now(), STATUS = '', QTY = QTY + :RECEIVED, QTY_LAST_ORDERED = :QTY, COST = :COST, PRICE = :PRICE, MARKUP_TYPE = :MARKUP_TYPE, UNITS = :UNITS, MANUFACTURER = :MANUFACTURER, MAN_NUM = :MAN_NUM, VEND_NUM = :VEND_NUM, VENDOR_ID = :VENDOR_ID WHERE PART_ID = :PART_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":PART_ID",$part_id);
    $stmt->bindValue(":QTY",$_POST["qty"]);
    $stmt->bindValue(":RECEIVED",$_POST["received"]);
    $stmt->bindValue(":COST",$_POST["cost"]);
    $stmt->bindValue(":PRICE",$_POST["price"]);
    $stmt->bindValue(":MARKUP_TYPE",$_POST["markup_type"]);
    $stmt->bindValue(":UNITS",$_POST["units"]);
    $stmt->bindValue(":MANUFACTURER",$_POST["manufacturer"] ? $_POST["manufacturer"] : $part_before_change["MANUFACTURER"]);
    $stmt->bindValue(":MAN_NUM",$_POST["man_num"] ? $_POST["man_num"] : $part_before_change["MAN_NUM"]);
    $stmt->bindValue(":VEND_NUM",$_POST["vend_num"] ? $_POST["vend_num"] : $part_before_change["VEND_NUM"]);
    $stmt->bindValue(":VENDOR_ID",$_POST["vendor_id"]);

    $stmt->execute();

    $part_after_change = getPartRecord($part_id);
    auditlogModifyPart($user,$part_before_change,$part_after_change);
  }

  # update part notes if changed
  $part_id = $_POST["part_id"];
  $part_before_change = getPartRecord($part_id);
  if( $part_before_change["NOTES"] !== $_POST["notes"] ) {
    $sql = "UPDATE part SET NOTES = :NOTES WHERE PART_ID = :PART_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":PART_ID",$part_id);
    $stmt->bindValue(":NOTES",$_POST["notes"]);
    $stmt->execute();

    $part_after_change = getPartRecord($part_id);
    auditlogModifyPart($user,$part_before_change,$part_after_change);
  }

  $dbh->commit();

  if( $_POST["submit"] == "Save" ) {
    echo "<div class='alert alert-success'>Saved.</div>\n";
  }
}

function showBatchOrder() {
  echo "<form id='batch_order' enctype='multipart/form-data' method='POST' autocomplete='off' action='.'>\n";
  echo "<input type='hidden' name='form' value='batch_order'/>\n";

  echo "<table id='orders' class='records'><thead><tr><th>Stock #</th><th>Description</th><th>Units</th><th>Qty</th><th>Cost</th><th>Markup</th><th>Price</th><th>Vendor</th><th>Vend #</th></tr></thead><tbody>\n";
  echo "</tbody></table>\n";

  echo "<input type='submit' value='Submit'/>\n";
  echo "</form>\n";

  echo "<div style='display: none'>\n";
  # for cloning into the Vendor cells in the table:
  echoSelectVendor(null);
  # for cloning into the Markup Type cells in the table:
  echoSelectMarkupTypeCompact(0,"none","none");
  echo "</div>\n";

  ?>
  <script>
  $('#batch_order').keypress(function(e) {
    if( e.which === 13 ) {
      if( e.target.name == "stock_num[]" ) {
        stockNumEntered(e.target);
        return false; // prevent the form from being submitted when Enter is pressed
      }
      var inputs = $('#batch_order').find('INPUT:visible,SELECT');
      var i = inputs.index(e.target);
      if( i+1 < inputs.length ) {
        if( inputs[i+1].type == "text" ) inputs[i+1].select();
        inputs[i+1].focus();
      }
      return false; // prevent the form from being submitted when Enter is pressed
    }
  });
  function addOrderRow() {
    var tbody = $('#orders tbody');
    var row_num = tbody.children().length;
    var tr = document.createElement("tr");
    tr.innerHTML = "<td><input type='hidden' name='row_num[]' value='" + row_num + "'/><input name='stock_num[]' size='15'/></td><td class='description'></td><td class='units'></td><td><input name='qty[]' size='7'/></td><td><input id='cost_" + row_num + "' name='cost[]' size='7'/></td><td class='markup_type'></td><td><input id='price_" + row_num + "' name='price[]' size='7'/></td><td class='vendor'></td><td class='vend_num'><input id='vend_num_" + row_num + "' name='vend_num[]' size='15'/></td></tr>";
    tbody.append(tr);
    $(tr).find("[name='stock_num[]']")[0].addEventListener('change', function() {
      stockNumEntered(this);
    }, false);
  }

  addOrderRow();
  $("input[name='stock_num[]']").focus();

  function stockNumEntered(input) {
    var part_name = input.value;
    if( !part_name ) return;

    var row = $(input).parents("tr")[0];

    callWithPartInfo(part_name,function(pi) {
      if( !pi["FOUND"] ) return;

      var siblings = $(row).parent().children();
      var row_num = siblings.index(row);
      if( row_num == siblings.length-1 ) {
        addOrderRow();
      }

      $(row).find(".description").text(pi["DESCRIPTION"]);
      $(row).find(".units").text(pi["UNITS"]);
      $(row).find("input[name='qty[]']").val(pi["QTY_LAST_ORDERED"]);
      $(row).find("input[name='cost[]']").val(pi["COST"]);
      $(row).find("input[name='price[]']").val(pi["PRICE"]);

      var markup = $('#markup_type').clone()[0];
      $(markup).removeAttr("id");
      $(markup).attr("name","markup_type[]");
      $(markup).data("cost_id","cost_" + row_num);
      $(markup).data("price_id","price_" + row_num);
      $(markup).attr("data-cost_id","cost_" + row_num);
      $(markup).attr("data-price_id","price_" + row_num);
      if( <?php echo NEW_ORDERS_PRESERVE_MARKUP_TYPE ? "true" : "false" ?> ) {
        $(markup).val(pi["MARKUP_TYPE"]);
      } else {
        $(markup).val( <?php echo NEW_ORDER_MARKUP_TYPE ?> );
      }
      $(row).find(".markup_type").empty();
      $(row).find(".markup_type").append( markup );
      initMarkupTypeSelector(markup);

      var vendors = $('#vendor_id').clone();
      $(vendors).removeAttr("id");
      $(vendors).attr("name","vendor[]");
      $(vendors).val(pi["VENDOR_ID"]);
      $(row).find(".vendor").empty();
      $(row).find(".vendor").append( vendors );
      $(row).find("input[name='vend_num[]']").val(pi["VEND_NUM"]);

      // do this last, because it may generate a change event
      $(row).find("input[name='qty[]']").select();
      $(row).find("input[name='qty[]']").focus();
    });
  }
  </script>
  <script src='partinfo.js'></script>
  <?php
}

function saveBatchOrder($user,&$show) {
  $show='orders';

  $dbh = connectDB();
  $sql = "INSERT INTO part_order SET PART_ID = :PART_ID, QTY = :QTY, RECEIVED = :QTY, COST = :COST, MARKUP_TYPE = :MARKUP_TYPE, PRICE = :PRICE, VENDOR_ID = :VENDOR_ID, UNITS = :UNITS, VEND_NUM = :VEND_NUM, MAN_NUM = :MAN_NUM, ORDERED = NOW(), CLOSED = NOW()";
  $order_stmt = $dbh->prepare($sql);
  $order_stmt->bindParam(":PART_ID",$part_id);
  $order_stmt->bindParam(":QTY",$qty);
  $order_stmt->bindParam(":COST",$cost);
  $order_stmt->bindParam(":MARKUP_TYPE",$markup_type);
  $order_stmt->bindParam(":PRICE",$price);
  $order_stmt->bindParam(":VENDOR_ID",$vendor_id);
  $order_stmt->bindParam(":UNITS",$units);
  $order_stmt->bindParam(":VEND_NUM",$vend_num);
  $order_stmt->bindParam(":MAN_NUM",$man_num);

  $sql = "UPDATE part SET UPDATED = now(), STATUS = '', QTY = QTY + :RECEIVED, QTY_LAST_ORDERED = :QTY, COST = :COST, PRICE = :PRICE, MARKUP_TYPE = :MARKUP_TYPE, UNITS = :UNITS, VEND_NUM = :VEND_NUM, VENDOR_ID = :VENDOR_ID WHERE PART_ID = :PART_ID";
  $part_stmt = $dbh->prepare($sql);
  $part_stmt->bindParam(":PART_ID",$part_id);
  $part_stmt->bindParam(":QTY",$qty);
  $part_stmt->bindParam(":RECEIVED",$qty);
  $part_stmt->bindParam(":COST",$cost);
  $part_stmt->bindParam(":PRICE",$price);
  $part_stmt->bindParam(":MARKUP_TYPE",$markup_type);
  $part_stmt->bindParam(":UNITS",$units);
  $part_stmt->bindParam(":VEND_NUM",$vend_num);
  $part_stmt->bindParam(":VENDOR_ID",$vendor_id);

  $failed = false;
  $dbh->beginTransaction();
  foreach( $_POST["row_num"] as $row_num ) {
    $stock_num = trim($_POST["stock_num"][$row_num]);
    if( !$stock_num ) continue;

    $part = getPartRecordFromStockNum($stock_num);
    if( !$part ) {
      echo "<div class='alert alert-danger'>Unknown stock number '",htmlescape($stock_num),"'.  Aborting.</div>\n";
      $failed = true;
      break;
    }
    $part_id = $part["PART_ID"];
    $units = $part["UNITS"];
    $man_num = $part["MAN_NUM"];

    $qty = $_POST["qty"][$row_num];
    $cost = $_POST["cost"][$row_num];
    $markup_type = $_POST["markup_type"][$row_num];
    $price = $_POST["price"][$row_num];
    $vendor_id = $_POST["vendor"][$row_num];
    $vend_num = $_POST["vend_num"][$row_num];

    $order_stmt->execute();
    $order_id = $dbh->lastInsertId();
    $order_after_change = getOrderRecord($order_id);
    auditlogModifyOrder($user,null,$order_after_change);

    $part_stmt->execute();
    $part_after_change = getPartRecord($part_id);
    auditlogModifyPart($user,$part,$part_after_change);
  }
  if( $failed ) {
    $dbh->rollback();
  } else {
    $dbh->commit();
    echo "<div class='alert alert-success'>Saved.</div>\n";
  }
}

function downloadParts() {

  $vendor_id = null;
  if( isset($_REQUEST["vendor_id"]) ) {
    $vendor_id = $_REQUEST["vendor_id"];
  }

  $wish_list = null;
  if( isset($_REQUEST["wish_list"]) ) {
    $wish_list = true;
  }

  $filename = str_replace(" ","",SHOP_NAME . "Parts.csv");

  if( $wish_list ) {
    $filename = str_replace(".csv","WishList.csv",$filename);
  }

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $F = fopen('php://output','w');

  $dbh = connectDB();
  $sql = "
    SELECT
      part.*,
      CASE WHEN part.QTY <= part.MIN_QTY and part.STATUS = '' THEN 'low' ELSE part.STATUS END as PART_STATUS,
      vendor.NAME as VENDOR_NAME
    FROM
      part
    LEFT JOIN vendor ON vendor.VENDOR_ID = part.VENDOR_ID
    WHERE True";
  if( $vendor_id ) $sql .= " AND part.VENDOR_ID = :VENDOR_ID";
  if( isset($_REQUEST["inactive"]) ) {
    $sql .= " AND part.INACTIVE";
  } else {
    $sql .= " AND NOT part.INACTIVE";
  }
  if( $wish_list ) {
    $sql .= " AND (part.STATUS = 'W' COLLATE 'utf8_bin' OR part.STATUS = 'low' OR part.STATUS = '' AND part.QTY <= part.MIN_QTY)";
  }
  $sql .= " " . SHOP_PART_ORDER;

  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  $csv = array("Stock Num","Description","Price","Cost","Qty");
  if( ENABLE_QTY_CORRECT ) {
    $csv[] = "Qty Crct";
    $csv[] = "Qty Crct Date";
  }
  $csv[] = "Min Qty";
  if( ENABLE_BACKUP_QTY ) {
    $csv[] = "Bck Qty";
  }
  $csv = array_merge($csv,array("Units","Status","Manufacturer","Vend Part Num","Vendor","Updated"));
  if( ENABLE_PART_LOCATION ) {
    $csv = array_merge($csv,array("Location","Sec","Row","Shelf","Bck Location","Bck Sec","Bck Row","Bck Shelf"));
  }
  if( !$wish_list ) {
    $csv = array_merge($csv,array("Manufacturer Part Num","Markup Type","No Recover","Created","Notes"));
  }

  fputcsv_excel($F,$csv);

  while( ($row=$stmt->fetch()) ) {
    $csv = array();

    $csv[] = $row["STOCK_NUM"];
    $csv[] = $row["DESCRIPTION"];
    $csv[] = $row["PRICE"];
    $csv[] = $row["COST"];
    $csv[] = $row["QTY"];
    if( ENABLE_QTY_CORRECT ) {
      $csv[] = $row["QTY_CORRECT"] ? "Y" : "N";
      $csv[] = $row["QTY_CORRECT"] ? $row["QTY_CORRECT_DATE"] : "";
    }
    $csv[] = $row["MIN_QTY"];
    if( ENABLE_BACKUP_QTY ) {
      $csv[] = $row["BACKUP_QTY"];
    }
    $csv[] = $row["UNITS"];
    $csv[] = $row["PART_STATUS"];
    $csv[] = $row["MANUFACTURER"];
    $csv[] = $row["VEND_NUM"];
    $csv[] = $row["VENDOR_NAME"];
    $csv[] = $row["UPDATED"];
    if( ENABLE_PART_LOCATION ) {
      $csv[] = $row["LOCATION"];
      $csv[] = $row["SECTION"];
      $csv[] = $row["ROW"];
      $csv[] = $row["SHELF"];
      $csv[] = $row["BACKUP_LOCATION"];
      $csv[] = $row["BACKUP_SECTION"];
      $csv[] = $row["BACKUP_ROW"];
      $csv[] = $row["BACKUP_SHELF"];
    }
    if( !$wish_list ) {
      $csv[] = $row["MAN_NUM"];
      $csv[] = $row["MARKUP_TYPE"];
      $csv[] = $row["NO_RECOVER"];
      $csv[] = $row["CREATED"];
      $csv[] = $row["NOTES"];
    }

    fputcsv_excel($F,$csv);
  }
  fclose($F);
}
