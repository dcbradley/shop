<?php

require_once "user.php";
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

function showUserProfile($user) {
  global $web_user;

  $user->fillMissingDirectoryInfo();

  if( $user->user_id ) {
    echo "<h2>Information about ",htmlescape($user->displayName()),"</h2>\n";
  } else {
    echo "<h2>New Account</h2>\n";
  }
  echo "<div class='card card-md'><div class='card-body'>\n";

  $disabled = "";
  if( isNonNetIDLogin() && !$user->isGroupAccount() ) {
    $disabled = "disabled";
    echo "<p>You must <button class='btn btn-secondary' onclick='login(\"profile\")'>log in</button> to make changes to your profile.</p>\n";
  }

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateForm(this)'>\n";
  echo "<input type='hidden' name='form' value='user_profile'/>\n";
  if( isAdmin() ) {
    if( $user->user_id ) {
      echo "<input type='hidden' name='user_id' value='",htmlescape($user->user_id),"'/>\n";
    } else {
      echo "<input type='hidden' name='user_id' value='new'/>\n";
    }
  }

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  if( isAdmin() || !isAdmin() && !isset($_SERVER["givenName"]) ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='first_name'>First Name</label></div><div class='col'><input name='first_name' id='first_name' type='text' maxlength='30' value='",htmlescape($user->first_name),"' /></div></div>\n";
  }
  if( isAdmin() || !isAdmin() && !isset($_SERVER["sn"]) ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='last_name'>Last Name</label></div><div class='col'><input name='last_name' id='last_name' type='text' maxlength='30' value='",htmlescape($user->last_name),"' /></div></div>\n";
  }
  if( isAdmin() ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='netid'>Netid</label></div><div class='col'><input name='netid' id='netid' type='text' maxlength='60' value='",htmlescape($user->netid),"' placeholder='NetID or &lt;groupname&gt;_group' /></div></div>\n";
    $checked = $user->block_login ? " checked " : "";
    echo "<div class='$rowclass'><label><input type='checkbox' name='block_login' id='block_login' value='1' $checked /> block logins to this account</label></div>\n";
  }

  if( !$user->isGroupAccount() ) {
    $checked = $user->local_login ? " checked " : "";
    echo "<div class='$rowclass'><label><input type='checkbox' name='allow_local_login' id='allow_local_login' value='1' $checked onchange='allowLocalLoginChanged()'/> enable group logins to ",($user->user_id == $web_user->user_id ? "my" : "")," account</label></div>\n";
    echo "<div class='$rowclass' id='local_login_row'><div class='$col1'><label for='local_login'>Group Login</label></div><div class='col'><input name='local_login' id='local_login' type='text' maxlength='30' value='",htmlescape($user->local_login ? $user->local_login : $user->netid),"' /></div></div>\n";
    ?>
    <script>
      function allowLocalLoginChanged() {
        if( document.getElementById('allow_local_login').checked ) {
          $('#local_login_row').show();
        } else {
          $('#local_login_row').hide();
        }
      }
      allowLocalLoginChanged();
    </script>
    <?php
  }

  if( isAdmin() || !isAdmin() && !isset($_SERVER["mail"]) ) {
    echo "<div class='$rowclass'><div class='$col1'><label for='email'>Email</label></div><div class='col'><input name='email' id='email' type='text' maxlength='60' value='",htmlescape($user->email),"' /></div></div>\n";
  }
  if( isAdmin() ) {
    $yes_checked = $user->is_admin ? "checked" : "";
    $no_checked = $yes_checked ? "" : "checked";
    echo "<div class='$rowclass'><div class='$col1'><label for='is_admin'>Is Admin</label></div><div class='col'><input type='radio' name='is_admin' id='is_admin' value='Y' $yes_checked/> yes <input type='radio' name='is_admin' value='N' $no_checked/> no</div></div>\n";

    if( SHOP_WORKER_COL ) {
      $yes_checked = $user->is_shop_worker ? "checked" : "";
      $no_checked = $yes_checked ? "" : "checked";
      echo "<div class='$rowclass'><div class='$col1'><label for='is_shop_worker'>Is Shop Worker</label></div><div class='col'><input type='radio' name='is_shop_worker' id='is_shop_worker' value='Y' $yes_checked/> yes <input type='radio' name='is_shop_worker' value='N' $no_checked/> no</div></div>\n";
    }
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='phone'>Phone Number</label></div><div class='col'><input name='phone' id='phone' type='text' maxlength='20' placeholder='202-555-0124' value='",htmlescape($user->phone),"' /></div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'><label for='room'>Office Room Number</label></div><div class='col'><input name='room' id='room' type='text' maxlength='40' value='",htmlescape($user->room),"' /></div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'><label for='department'>Department</label></div><div class='col'><input name='department' id='department' maxlength='30' type='text' value='",htmlescape($user->department),"' /></div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'><label for='employee_type'>Employee Type</label></div><div class='col'><select name='employee_type' id='employee_type'>\n";
  echo "  <option value=''></option>\n";
  # to reduce confusion, only service accounts and group accounts with fake NetIDs of the form 'groupname_group' offer the choice of Group as the account type
  if( preg_match("/_/",$user->netid) || $user->employee_type == "Group" ) {
    $selected = ($user->employee_type == "Group" || !$user->employee_type) ? "selected" : "";
    echo "  <option value='Group' $selected>Group</option>\n";
  }
  $selected = $user->employee_type == "Student" ? "selected" : "";
  echo "  <option value='Student' $selected>Student</option>\n";
  $selected = $user->employee_type == "Staff" ? "selected" : "";
  echo "  <option value='Staff' $selected>Staff</option>\n";
  $selected = $user->employee_type == "Professor" ? "selected" : "";
  echo "  <option value='Professor' $selected>Professor</option>\n";
  $selected = $user->employee_type == "Visitor" ? "selected" : "";
  echo "  <option value='Visitor' $selected>Visitor</option>\n";
  echo "</select></div></div>\n";

  echo "<div class='$rowclass'><div class='$col1'><label for='phone'>Default Fund Group</label></div><div class='col'>";
  echoSelectUserGroup($user);
  echo " <input type='submit' name='submit' value='New Fund Group'>";
  echo "</div></div>\n";
  echo "<div class='$rowclass'><div class='$col1'><label for='phone'>Default Fund</label></div><div class='col'>";
  echoSelectFundingSource($user);
  echo " <input type='submit' name='submit' value='Edit/Add Fund'>";
  echo "<br><small>Need to look up information or ask others for help?  You can <a href='#' onclick='emailLink(); return false;'>email</a> yourself a link to this form and go fill it out from your own device.</small><span id='email-result'></span>\n"; 
  echo "</div></div>\n";

  echo "</div>\n"; # end of form columns container

  echo "<input type='submit' name='submit' value='Save' $disabled/>\n";
  echo "</form>\n";
  echo "</div></div>\n"; # end of card

  ?>
  <script>
    function emailLink() {
      $.ajax({ url: "<?php echo WEBAPP_TOP ?>emaillink.php", success: function(data) {
        if( data.indexOf("SUCCESS") === 0 ) {
          document.getElementById('email-result').innerHTML = "<br><div class='alert alert-success'>Email sent.</div>";
        } else {
          document.getElementById('email-result').innerHTML = "<br><div class='alert alert-danger' id='failure-msg'></div>";
          $('#failure-msg').text(data);
        }
      }});
    }
    function validateForm(form) {
      var first_name = document.getElementById('first_name');
      var last_name = document.getElementById('last_name');
      if( first_name && last_name && !first_name.value && !last_name.value) {
        alert("You must enter a name.");
        return false;
      }
      return true;
    }
  </script>
  <?php
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

function canonicalDepartment($department) {
  $l = strtolower($department);
  if( $l == "physics" ) {
    return "Physics";
  }
  return $department;
}

function saveUserProfile($user,&$show) {
  if( isNonNetIDLogin() && !$user->isGroupAccount() ) {
    echo "<div class='alert alert-danger'>You must <button class='btn btn-secondary' onclick='login(\"profile\")'>log in</button> to make changes to your profile.</div>\n";
    return false;
  }

  if( isset($_POST["user_id"]) ) {
    $other_user_id = (int)$_POST["user_id"];
    if( $_POST["user_id"] == "new" ) {
      if( !isAdmin() ) {
        echo "<div class='alert alert-danger'>You cannot create a new user.</div>\n";
        return false;
      }
      $user = new User;
      $user->user_id = "new";
    }
    else if( $other_user_id != $user->user_id ) {
      if( isAdmin() ) {
        $user = new User;
        if( !$user->loadFromUserID($other_user_id) ) {
          echo "<div class='alert alert-danger'>Failed to update user with id ",htmlescape($other_user_id),".</div>\n";
          return false;
        }
      } else {
        echo "<div class='alert alert-danger'>You cannot edit somebody else's profile.</div>\n";
        return false;
      }
    }
  }

  $orig_user = clone $user;

  if( isset($_POST["allow_local_login"]) && $_POST["allow_local_login"] ) {
    $new_local_login = trim($_POST["local_login"]);
    if( $new_local_login == "" ) $new_local_login = null;
    if( $user->local_login !== $new_local_login && $new_local_login ) {
      $other_user = new User;
      if( $other_user->loadFromLocalLogin($new_local_login) || $other_user->loadFromNetID($new_local_login) ) {
        if( $other_user->user_id != $user->user_id ) {
          echo "<div class='alert alert-danger'>Error: the login name '",htmlescape($new_local_login),"' is already in use.</div>\n";
          return false;
        }
      }
    }
    $user->local_login = $new_local_login;
  } else {
    $user->local_login = null;
  }

  $user->phone = trim($_POST["phone"]);
  $user->room = trim($_POST["room"]);
  $user->department = canonicalDepartment($_POST["department"]);
  $user->employee_type = trim($_POST["employee_type"]);
  $group_id = $_POST["group_id"];
  if( $group_id == "" ) $group_id = null;
  else if( $group_id == "add" ) {
    $group_id = $user->default_group_id;
    $show = "edit_group";
  }
  else {
    $group_id = (int)$group_id;
  }
  $user->default_group_id = $group_id;

  $funding_source_id = isset($_POST["funding_source"]) ? $_POST["funding_source"] : null;
  if( $funding_source_id == "" ) $funding_source_id = null;
  $user->default_funding_source_id = $funding_source_id;

  $db = connectDB();

  $admin_updated = false;
  if( isAdmin() || !isAdmin() && !isset($_SERVER["givenName"]) ) {
    if( isset( $_POST["first_name"] ) ) {
      $user->first_name = trim($_POST["first_name"]);
      $admin_updated = true;
    }
  }
  if( isAdmin() || !isAdmin() && !isset($_SERVER["sn"]) ) {
    if( isset( $_POST["last_name"] ) ) {
      $user->last_name = trim($_POST["last_name"]);
      $admin_updated = true;
    }
  }
  if( isAdmin() || !isAdmin() && !isset($_SERVER["mail"]) ) {
    if( isset( $_POST["email"] ) ) {
      $user->email = trim($_POST["email"]);
      $admin_updated = true;
    }
  }
  if( isAdmin() ) {
    if( isset( $_POST["netid"] ) ) {
      $user->netid = trim($_POST["netid"]);
      if( $user->netid == "" ) $user->netid = null;
      $admin_updated = true;
    }
    $orig_block_login = $user->block_login;
    $user->block_login = isset($_POST["block_login"]) && $_POST["block_login"] == "1" ? 1 : 0;
    if( $orig_block_login != $user->block_login ) {
      $admin_updated = true;
    }
    if( isset( $_POST["is_admin"] ) ) {
      $user->is_admin = $_POST["is_admin"] == "Y" ? 1 : 0;
      $admin_updated = true;
    }
    if( isset( $_POST["is_shop_worker"] ) ) {
      $user->is_shop_worker = $_POST["is_shop_worker"] == "Y" ? 1 : 0;
      $admin_updated = true;
    }
  }
  if( $admin_updated ) {
    if( !$user->first_name && !$user->last_name ) {
      echo "<div class='alert alert-danger'>User not created, because no name was specified.</div>\n";
      return false;
    }
    if( $user->user_id == "new" ) {
      $sql = "INSERT INTO";
    } else {
      $sql = "UPDATE";
    }
    $sql .= " user SET FIRST_NAME = :FIRST_NAME, LAST_NAME = :LAST_NAME, EMAIL = :EMAIL, NETID = :NETID, BLOCK_LOGIN = :BLOCK_LOGIN, " . SHOP_ADMIN_COL . " = :IS_ADMIN";
    if( SHOP_WORKER_COL ) {
      $sql .= ", " . SHOP_WORKER_COL . " = :IS_SHOP_WORKER";
    }
    if( $user->user_id == "new" ) {
      $sql .= ", " . SHOP_USER_CREATED_COL . " = 1";
    }
    if( $user->user_id == "new" ) {
      $sql .= ", CREATED = now()";
    }
    if( $user->user_id != "new" ) {
      $sql .= " WHERE user_id = :USER_ID";
    }
    $stmt = $db->prepare($sql);
    if( $user->user_id != "new" ) {
      $stmt->bindParam(":USER_ID",$user->user_id);
    }
    $is_admin = $user->is_admin ? 1 : 0;
    $stmt->bindParam(":IS_ADMIN",$is_admin);
    if( SHOP_WORKER_COL ) {
      $is_shop_worker = $user->is_shop_worker ? 1 : 0;
      $stmt->bindParam(":IS_SHOP_WORKER",$is_shop_worker);
    }
    $stmt->bindParam(":FIRST_NAME",$user->first_name);
    $stmt->bindParam(":LAST_NAME",$user->last_name);
    $stmt->bindParam(":EMAIL",$user->email);
    $stmt->bindParam(":NETID",$user->netid);
    $stmt->bindParam(":BLOCK_LOGIN",$user->block_login);
    $stmt->execute();

    if( $user->user_id == "new" ) {
      $user->user_id = $db->lastInsertId();
      $_REQUEST["user_id"] = $user->user_id;
    }
  }

  $sql = "UPDATE user SET PHONE = :PHONE, ROOM = :ROOM, DEPARTMENT = :DEPARTMENT, EMPLOYEE_TYPE = :EMPLOYEE_TYPE, DEFAULT_GROUP_ID = :DEFAULT_GROUP_ID, DEFAULT_FUNDING_SOURCE_ID = :DEFAULT_FUNDING_SOURCE_ID, LOCAL_LOGIN = :LOCAL_LOGIN WHERE USER_ID = :USER_ID";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(":USER_ID",$user->user_id);
  $stmt->bindParam(":PHONE",$user->phone);
  $stmt->bindParam(":ROOM",$user->room);
  $stmt->bindParam(":DEPARTMENT",$user->department);
  $stmt->bindParam(":EMPLOYEE_TYPE",$user->employee_type);
  $stmt->bindParam(":DEFAULT_GROUP_ID",$user->default_group_id);
  $stmt->bindParam(":DEFAULT_FUNDING_SOURCE_ID",$user->default_funding_source_id);
  $stmt->bindParam(":LOCAL_LOGIN",$user->local_login);
  $stmt->execute();

  auditlogModifyUser($orig_user,$user);

  if( $_POST["submit"] == "New Fund Group" ) {
    $show = "edit_group";
    $_REQUEST["set_user_group"] = 1;
  }
  else if( $_POST["submit"] == "Edit/Add Fund" ) {
    $show = "edit_group";
    $_REQUEST["set_user_funding_source"] = 1;
  }
  else {
    echo "<div class='alert alert-success'>Saved updated profile.</div>\n";
  }
}

function showUsers() {

  $show_group_accounts = isset($_REQUEST["group_accounts"]) ? true : false;

  $dbh = connectDB();
  $sql = "SELECT GROUP_NAME from user_group WHERE GROUP_ID = :GROUP_ID";
  $group_stmt = $dbh->prepare($sql);
  $group_stmt->bindParam(":GROUP_ID",$group_id);

  $users = getUsers();

  echo "<a href='?s=edit_user' class='btn btn-primary'>New Account</a>\n";

  $base_url = "?s=users";
  if( $show_group_accounts ) {
    $url = $base_url;
    echo "<a href='",htmlescape($url),"' class='btn btn-primary'>All Accounts</a>\n";
  } else {
    $url = $base_url . "&group_accounts";
    echo "<a href='",htmlescape($url),"' class='btn btn-primary'>Group Accounts</a>\n";
  }

  echo "<table class='records clicksort'>\n";
  echo "<thead><tr><th>Name</th><th>Last Login</th><th>Email</th><th>Department</th><th>Fund Group</th><th>Admin</th></tr></thead>\n";
  echo "<tbody>\n";
  foreach( $users as $user ) {
    if( $show_group_accounts && !$user->isGroupAccount() ) continue;
    echo "<tr class='record'>";
    echo "<td><a href='?s=edit_user&amp;user_id=",$user->user_id,"'>",htmlescape($user->lastFirst()),"</a></td>";
    echo "<td>",htmlescape(displayDateTime($user->last_login)),"</td>";
    echo "<td><a href='mailto:",htmlescape($user->email),"'>",htmlescape($user->email),"</a></td>";
    echo "<td>",htmlescape($user->department),"</td>";
    $group_id = $user->default_group_id;
    if( $group_id === null ) {
      echo "<td></td>";
    } else {
      $group_stmt->execute();
      $group_row = $group_stmt->fetch();
      if( !$group_row ) {
        echo "<td></td>";
      } else {
        $url = "?s=edit_group&group_id={$group_id}";
        echo "<td><a href='",htmlescape($url),"'>",htmlescape($group_row["GROUP_NAME"]),"</a>";
        echo "\n<span class='clicksort_data'>",htmlescape($group_row["GROUP_NAME"]),"</span></td>";
      }
    }
    echo "<td class='centered'>",($user->is_admin ? "Y" : ""),"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table>\n";
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
