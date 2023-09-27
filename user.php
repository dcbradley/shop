<?php

require_once "people_ldap.php";

class User {
  public $user_id;
  public $local_login;
  public $netid;
  public $block_login = 0;
  public $first_name;
  public $last_name;
  public $email;
  public $phone;
  public $room;
  public $department;
  public $auto_set_departments = null;
  public $admin_set_departments = null;
  public $employee_type;
  public $leader_id;
  public $leader = null;
  public $default_group_id;
  public $default_funding_source_id;
  public $created;
  public $last_login;
  public $shop_last_login;
  public $is_admin = 0;
  public $is_shop_worker = 0;
  public $is_internal_login = 0;
  public $shop_tech;
  public $shop_orientatated;
  private $shop_access_info;

  function loadFromNetID($netid) {
    return $this->loadFromKey($netid,"NETID");
  }
  function loadFromLocalLogin($local_login) {
    return $this->loadFromKey($local_login,"LOCAL_LOGIN");
  }
  function loadFromUserID($user_id) {
    return $this->loadFromKey($user_id,"USER_ID");
  }
  function loadFromKey($id,$idname) {
    $db = connectDB();
    $sql = "SELECT * FROM user WHERE $idname = :IDNAME";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":IDNAME",$id);
    $stmt->execute();

    $row = $stmt->fetch();
    return $this->loadFromRow($row);
  }
  function loadFromRow($row) {
    if( !$row ) {
      $this->user_id = null;
      return False;
    }

    $this->user_id = $row["USER_ID"];
    $this->netid = $row["NETID"];
    $this->block_login = $row["BLOCK_LOGIN"];
    $this->local_login = $row["LOCAL_LOGIN"];
    $this->first_name = $row["FIRST_NAME"];
    $this->last_name = $row["LAST_NAME"];
    $this->email = $row["EMAIL"];
    $this->phone = $row["PHONE"];
    $this->room = $row["ROOM"];
    $this->department = $row["DEPARTMENT"];
    $this->auto_set_departments = $row["AUTO_SET_DEPARTMENTS"];
    $this->admin_set_departments = $row["ADMIN_SET_DEPARTMENTS"];
    $this->employee_type = $row["EMPLOYEE_TYPE"];
    $this->leader_id = $row["LEADER_ID"];
    $this->default_group_id = $row["DEFAULT_GROUP_ID"];
    $this->default_funding_source_id = $row["DEFAULT_FUNDING_SOURCE_ID"];
    $this->created = $row["CREATED"];
    $this->last_login = $row["LAST_LOGIN"];
    $this->shop_last_login = $row[SHOP_LAST_LOGIN_COL];
    $this->is_admin = (int)$row[SHOP_ADMIN_COL];
    if( !$this->is_admin && defined('ALT_SHOP_ADMIN_COLS') ) {
      foreach( ALT_SHOP_ADMIN_COLS as $admin_col ) {
        if( (int)$row[$admin_col] ) {
	  $this->is_admin = 1;
	}
      }
    }
    if( SHOP_ORIENTATED_COL ) {
      $this->shop_orientated = (int)$row[SHOP_ORIENTATED_COL];
    }
    if( SHOP_TECH_COL ) {
      $this->shop_tech = (int)$row[SHOP_TECH_COL];
    }
    if( SHOP_WORKER_COL ) {
      $this->is_shop_worker = (int)$row[SHOP_WORKER_COL];
    }

    return True;
  }

  function loadUserLackingNetID($first,$last,$email) {
    $db = connectDB();

    if( $email && $first && $last ) {
      $sql = "SELECT userid FROM user WHERE email = :EMAIL AND first_name = :FIRST_NAME AND last_name = :LAST_NAME AND netid IS NULL";
      $stmt = $db->prepare($sql);
      $stmt->bindParam(":EMAIL",$email);
      $stmt->bindParam(":FIRST_NAME",$first);
      $stmt->bindParam(":LAST_NAME",$last);
      $stmt->execute();
      $row = $stmt->fetch();
    }

    if( !$row && $email ) {
      $sql = "SELECT userid FROM user WHERE email = :EMAIL AND netid IS NULL";
      $stmt = $db->prepare($sql);
      $stmt->bindParam(":EMAIL",$email);
      $stmt->execute();
      $row = $stmt->fetch();
    }

    if( !$row && $first && $last ) {
      $sql = "SELECT userid FROM user WHERE first_name = :FIRST_NAME AND last_name = :LAST_NAME AND netid IS NULL";
      $stmt = $db->prepare($sql);
      $stmt->bindParam(":FIRST_NAME",$first);
      $stmt->bindParam(":LAST_NAME",$last);
      $stmt->execute();
      $row = $stmt->fetch();
    } else if( !$row && $last ) {
      $sql = "SELECT userid FROM user WHERE last_name = :LAST_NAME AND netid IS NULL";
      $stmt = $db->prepare($sql);
      $stmt->bindParam(":LAST_NAME",$last);
      $stmt->execute();
      $row = $stmt->fetch();
    } else if( !$row ) {
      return null;
    }

    if( !$row && $first ) {
      $sql = "SELECT userid FROM user WHERE last_name = :LAST_NAME AND first_name LIKE :FIRST_NAME AND netid IS NULL";
      $stmt = $db->prepare($sql);
      $like_first = $first . "%";
      $stmt->bindParam(":FIRST_NAME",$like_first);
      $stmt->bindParam(":LAST_NAME",$last);
      $stmt->execute();
      $row = $stmt->fetch();
    }

    if( $row ) {
      return $this->loadFromUserID($row["userid"]);
    }
    return False;
  }

  function loadGroupLeader() {
    if( $this->leader_id === null ) return null;
    $group_leader = new User;
    if( $group_leader->loadFromUserID($this->leader_id) ) {
      return $group_leader;
    }
    return null;
  }

  function lastFirst() {
    $result = $this->last_name;
    if( $this->first_name ) {
      $result .= ", " . $this->first_name;
    }
    if( !$result ) $result = $this->netid;
    return $result;
  }

  function displayName() {
    if( !$this->first_name && !$this->last_name ) {
      return $this->netid;
    }
    $result = trim($this->first_name . " " . $this->last_name);
    return $result;
  }

  function isGroupAccount() {
    return preg_match("/_group\$/",$this->netid);
  }

  function registerLogin() {
    if( $this->is_internal_login ) return;

    $existing_user = !empty($this->created) ? True : False;

    $set_netid = "";
    if( !$this->netid ) {
      $this->netid = $_SERVER["REMOTE_USER"];
      if( $existing_user ) {
        $set_netid = ", netid = :NETID";
      }
    }

    $this->first_name = isset($_SERVER["givenName"]) ? $_SERVER["givenName"] : $this->first_name;
    $this->last_name = isset($_SERVER["sn"]) ? $_SERVER["sn"] : $this->last_name;
    $this->email = isset($_SERVER["wiscEduMSOLPrimaryAddress"]) ? strtolower($_SERVER["wiscEduMSOLPrimaryAddress"]) : (isset($_SERVER["mail"]) ? strtolower($_SERVER["mail"]) : $this->email);
    $this->autoSetDepartments();
    if( !$this->department && $this->auto_set_departments ) $this->department = $this->auto_set_departments;
    if( !$this->department && $this->admin_set_departments ) $this->department = $this->admin_set_departments;

    if( $this->first_name === null ) {
      $this->first_name = "";
    }
    if( $this->last_name === null ) {
      $this->last_name = "";
    }
    if( $this->email === null ) {
      $this->email = "";
    }
    if( $this->department === null ) {
      $this->department = "";
    }

    $db = connectDB();
    if( !$existing_user ) {
      $sql = "INSERT INTO user SET " . SHOP_USER_CREATED_COL . " = 1, NETID = :NETID, FIRST_NAME = :FIRST_NAME, LAST_NAME = :LAST_NAME, EMAIL = :EMAIL, CREATED = now()";
      $stmt = $db->prepare($sql);
      $stmt->bindParam(":NETID",$this->netid);
      $stmt->bindParam(":FIRST_NAME",$this->first_name);
      $stmt->bindParam(":LAST_NAME",$this->last_name);
      $stmt->bindParam(":EMAIL",$this->email);

      $stmt->execute();
      $this->user_id = $db->lastInsertId();
    }

    $sql = "UPDATE user SET " . SHOP_LAST_LOGIN_COL . " = now(), LAST_LOGIN = now(), FIRST_NAME = :FIRST_NAME, LAST_NAME = :LAST_NAME, EMAIL = :EMAIL, DEPARTMENT = :DEPARTMENT {$set_netid}, IS_MEMBER_OF = :IS_MEMBER_OF, AUTO_SET_DEPARTMENTS = :AUTO_SET_DEPARTMENTS WHERE USER_ID = :USER_ID";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":USER_ID",$this->user_id);
    $stmt->bindParam(":FIRST_NAME",$this->first_name);
    $stmt->bindParam(":LAST_NAME",$this->last_name);
    $stmt->bindParam(":EMAIL",$this->email);
    $stmt->bindParam(":DEPARTMENT",$this->department);
    $stmt->bindValue(":IS_MEMBER_OF",array_key_exists("isMemberOf",$_SERVER) ? $_SERVER["isMemberOf"] : "");
    $stmt->bindValue(":AUTO_SET_DEPARTMENTS",$this->auto_set_departments);
    if( $set_netid ) {
      $stmt->bindParam(":NETID",$this->netid);
    }
    $stmt->execute();
  }

  function saveDefaultFundingSource($new_group,$new_funding_source) {
    $user_before = clone $this;
    $this->default_group_id = $new_group;
    $this->default_funding_source_id = $new_funding_source;

    $dbh = connectDB();
    $sql = "UPDATE user SET DEFAULT_GROUP_ID = :DEFAULT_GROUP_ID, DEFAULT_FUNDING_SOURCE_ID = :DEFAULT_FUNDING_SOURCE_ID WHERE USER_ID = :USER_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":DEFAULT_GROUP_ID",$this->default_group_id);
    $stmt->bindValue(":DEFAULT_FUNDING_SOURCE_ID",$this->default_funding_source_id);
    $stmt->bindValue(":USER_ID",$this->user_id);
    $stmt->execute();

    auditlogModifyUser($user_before,$this);
  }

  function fillMissingDirectoryInfo() {
    if( $this->isGroupAccount() ) return;
    if( $this->first_name && $this->last_name && $this->email && $this->department && $this->phone && $this->room ) return;
    if( !$this->first_name && !$this->last_name && !$this->email && !$this->netid ) return;

    if( $this->user_id !== null ) {
      $dbh = connectDB();
      $sql = "UPDATE user SET LAST_LDAP_LOOKUP=now() WHERE USER_ID = :USER_ID AND (LAST_LDAP_LOOKUP IS NULL OR DATEDIFF(now(),LAST_LDAP_LOOKUP)>0)";
      $stmt = $dbh->prepare($sql);
      $stmt->bindValue(":USER_ID",$this->user_id);
      $stmt->execute();
      if( $stmt->rowCount() == 0 ) {
        # We recently did an ldap lookup for this user, so do not repeat it.
        return;
      }
    }

    $info = getLdapInfo($this->first_name,null,$this->last_name,$this->email,$this->netid);
    if( !$info ) return;

    $changed = false;
    if( !$this->first_name && isset($info["givenname"]) ) {
      $this->first_name = $info["givenname"];
      $changed = true;
    }
    if( !$this->last_name && isset($info["sn"]) ) {
      $this->last_name = $info["sn"];
      $changed = true;
    }
    if( !$this->email && isset($info["mail"]) ) {
      $this->email = $info["mail"];
      $changed = true;
    }
    if( !$this->department && isset($info["department"]) ) {
      $this->department = $info["department"];
      $changed = true;
    }
    if( !$this->phone && isset($info["telephonenumber"]) ) {
      $this->phone = $info["telephonenumber"];
      $changed = true;
    }
    if( !$this->room && isset($info["roomnumber"]) ) {
      $this->room = $info["roomnumber"];
      $changed = true;
    }
    return $changed;
  }

  function getShopAccessOrderInfo() {
    if( $this->shop_access_info ) {
      return $this->shop_access_info;
    }
    $dbh = connectDB();
    $sql = "SELECT * FROM " . SHOP_ACCESS_TABLE . " WHERE NETID = :NETID ORDER BY STUDENT_SHOP_END DESC";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":NETID",$this->netid);
    $stmt->execute();

    $this->shop_access_info = null;
    while( ($row=$stmt->fetch()) ) {
      if( !$this->shop_access_info ) {
        $this->shop_access_info = array();
      }
      $this->shop_access_info[] = $row;
    }
    return $this->shop_access_info;
  }
  function hasActiveShopAccessOrder($today=null) {
    $orders = $this->getShopAccessOrderInfo();
    if( !$orders ) return false;
    if( $today === null ) {
      $today = dbNowDate();
    }
    foreach( $orders as $order ) {
      if( $order['CANCELED'] ) continue;
      if( !$order['COMPLETED'] ) continue;
      if( $today >= $order['STUDENT_SHOP_BEGIN'] && $today < $order['STUDENT_SHOP_END'] ) {
        return true;
      }
    }
    return false;
  }
  function loadLeader() {
    if( !$this->leader_id || $this->leader_id == $this->user_id ) return false;
    if( $this->leader ) return $this->leader;
    $this->leader = new User;
    if( !$this->leader->loadFromUserID($this->leader_id) ) {
      $this->leader = null;
    }
    return $this->leader;
  }
  function requiresShopAccessOrder($check_leader=null) {
    $confirmed = $this->isConfirmedMemberOfShopDepartment();
    if( $confirmed === true ) return false;

    if( $check_leader === null && defined('TREAT_FOLLOWERS_AS_MEMBERS_OF_LEADERS_DEPARTMENT') ) {
      $check_leader = TREAT_FOLLOWERS_AS_MEMBERS_OF_LEADERS_DEPARTMENT;
    }
    if( $check_leader ) {
      $leader = $this->loadLeader();
      if( $leader ) {
        $leader_requires_access_order = $leader->requiresShopAccessOrder(false);
	if( $leader_requires_access_order === false ) return false;
      }
    }

    if( $confirmed === false ) return true;

    # confirmation is unknown, so use self-reported department
    if( in_array($this->department,SHOP_DEPARTMENTS) ) {
      return false;
    }
    return true;
  }
  function getShopAccessOrderStatusHTML($today=null) {
    if( !$this->requiresShopAccessOrder() ) {
      return "";
    }
    if( $this->hasActiveShopAccessOrder($today) ) {
      return "<i class='fas fa-check text-success'></i>\n<span class='clicksort_data'>Y</span>";
    } else {
      return "<b class='text-danger'>X</b>\n<span class='clicksort_data'>N</span>";
    }
  }
  function autoSetDepartments() {
    $result = array();
    foreach( IS_MEMBER_OF_DEPARTMENTS as $department_key => $department_value ) {
      if( isWebUserInManifestGroup($department_key) ) {
        if( !in_array($department_value,$result) ) {
          $result[] = $department_value;
	}
      }
    }
    $this->auto_set_departments = implode(";",$result);
  }
  function isAdminConfirmedMemberOfDepartment($dept) {
    if( $this->admin_set_departments ) {
      $a = explode(";",$this->admin_set_departments);
      if( in_array($dept,$a) ) return true;
    }
    if( $this->admin_set_departments !== null ) {
      return false;
    }
    return null; // confirmation state is unknown
  }
  function isAutoConfirmedMemberOfDepartment($dept) {
    if( $this->auto_set_departments ) {
      $a = explode(";",$this->auto_set_departments);
      if( in_array($dept,$a) ) return true;
    }
    if( $this->auto_set_departments !== null ) {
      return false;
    }
    return null; // confirmation state is unknown
  }
  function isConfirmedMemberOfDepartment($dept) {
    $rc1 = $this->isAutoConfirmedMemberOfDepartment($dept);
    $rc2 = $this->isAdminConfirmedMemberOfDepartment($dept);
    if( $rc1 || $rc2 ) return true;
    if( $rc1 === false || $rc2 === false ) return false;

    return null; // confirmation state is unknown
  }
  function isConfirmedMemberOfShopDepartment() {
    $got_false = false;
    foreach( SHOP_DEPARTMENTS as $dept ) {
      $rc = $this->isConfirmedMemberOfDepartment($dept);
      if( $rc ) return true;
      if( $rc === false ) $got_false = true;
    }
    if( $got_false ) {
      return false;
    }
    return null; // confirmation state is unknown
  }
}

function getUserNamesJSON() {
  return getUserNamesJSONFromSQL("SELECT FIRST_NAME,LAST_NAME FROM user");
}
function getGroupLeaderNamesJSON() {
  return getUserNamesJSONFromSQL("SELECT first_name,last_name FROM user WHERE leader_id = user_id");
}
function getUserNamesJSONFromSQL($sql) {
  $db = connectDB();
  $stmt = $db->prepare( ($sql) );
  $stmt->execute();
  $result = "";
  while( ($row=$stmt->fetch()) ) {
    if( !$result ) $result = "[";
    else $result .= ",";
    $name = $row["LAST_NAME"] . ", " . $row["FIRST_NAME"];
    $name = str_replace("\"","",$name);
    $result .= "\"" . $name . "\"";
  }
  $result .= "]";
  return $result;
}

function getUsers() {
  $db = connectDB();
  $sql = ("SELECT * FROM user ORDER BY LAST_NAME,FIRST_NAME");
  $stmt = $db->prepare($sql);
  $stmt->execute();

  $users = array();
  while( ($row=$stmt->fetch()) ) {
    $user = new User;
    if( $user->loadFromRow($row) ) {
      $users[$user->user_id] = $user;
    }
  }
  return $users;
}

function getShopUsers() {
  $db = connectDB();
  $sql = "SELECT * FROM user WHERE " . SHOP_LAST_LOGIN_COL . " IS NOT NULL OR " . SHOP_USER_CREATED_COL . " OR EXISTS (SELECT USER_ID FROM user follower WHERE follower." . SHOP_LAST_LOGIN_COL . " IS NOT NULL AND follower.LEADER_ID = user.USER_ID) ORDER BY LAST_NAME,FIRST_NAME";
  $stmt = $db->prepare($sql);
  $stmt->execute();

  $users = array();
  while( ($row=$stmt->fetch()) ) {
    $user = new User;
    if( $user->loadFromRow($row) ) {
      $users[$user->user_id] = $user;
    }
  }
  return $users;
}

function isWebUserInManifestGroup($manifest_group) {
  $member_of = array_key_exists("isMemberOf",$_SERVER) ? $_SERVER["isMemberOf"] : "";
  $member_of = explode(";",$member_of);
  if( in_array($manifest_group,$member_of) ) {
    return true;
  }

  return false;
}

$web_user = null;
$web_euid = null;
$web_ruid = null;
$is_non_netid_login = null;
$login_error_msg = null;

function initLogin() {
  global $web_user;
  global $web_euid;
  global $web_ruid;
  global $login_error_msg;
  global $is_non_netid_login;
  global $self_path;

  if( defined('INTERNAL_LOGIN') ) {
    $web_user = new User;
    $web_user->is_internal_login = true;
    return;
  }

  $login_error_msg = null;
  $is_non_netid_login = false;
  if( isset($_SERVER["REMOTE_USER"]) && $_SERVER["REMOTE_USER"] ) {
    $web_user = new User;
    $web_user->loadFromNetID($_SERVER["REMOTE_USER"]);
    $web_ruid = $web_user->user_id;

    if( $web_user->block_login ) {
      $web_user = null;
      $login_error_msg = "Your account is not enabled.";
      return;
    }

    if( isAdmin() ) {
      if( isset($_POST["euid"]) ) {
        $web_euid = $_POST["euid"];
        setcookie("shop_euid",$web_euid,0,COOKIE_PATH,"",true);
      } else if( isset($_COOKIE["shop_euid"]) ) {
        $web_euid = $_COOKIE["shop_euid"];
      }
      if( $web_euid ) {
        $web_user = new User;
        if( !$web_user->loadFromUserID($web_euid) ) {
	  $web_user = null;
	  $login_error_msg = "Unknown user id " . htmlescape($web_euid);
	}
	# no check for block_login here, because admins are allowed to log in to blocked accounts
      }
    }
  }
  else if( allowNonNetIDLogins() && isset($_POST["group_lgn"]) && $_POST["group_lgn"] ) {
    $web_user = new User;
    $group_netid = $_POST["group_lgn"] . "_group";
    $group_netid_already_group = "";
    if( preg_match('|^.*_group$|',$_POST["group_lgn"]) ) {
      $group_netid_already_group = $_POST["group_lgn"];
    }
    if( !$web_user->loadFromNetID($group_netid) &&
        !($group_netid_already_group && $web_user->loadFromNetID($group_netid_already_group)) &&
	!$web_user->loadFromLocalLogin($_POST["group_lgn"]) )
    {
      $web_user = null;
      $login_error_msg = "Unknown group name.";
    } else {
      if( $web_user->block_login ) {
        $web_user = null;
        $login_error_msg = "This account is not enabled.";
        return;
      }
      $is_non_netid_login = true;
      setcookie("shop_group_login",$_POST["group_lgn"],0,COOKIE_PATH,"",true);
    }
  }
  else if( allowNonNetIDLogins() && isset($_COOKIE["shop_group_login"]) && $_COOKIE["shop_group_login"] ) {
    $web_user = new User;
    $group_netid = $_COOKIE["shop_group_login"] . "_group";
    $group_netid_already_group = "";
    if( preg_match('|^.*_group$|',$_COOKIE["shop_group_login"]) ) {
      $group_netid_already_group = $_COOKIE["shop_group_login"];
    }
    if( !$web_user->loadFromNetID($group_netid) &&
        !($group_netid_already_group && $web_user->loadFromNetID($group_netid_already_group)) &&
        !$web_user->loadFromLocalLogin($_COOKIE["shop_group_login"]) )
    {
      $web_user = null;
      $login_error_msg = "Unknown group name.";
    } else {
      if( $web_user->block_login ) {
        $web_user = null;
        $login_error_msg = "This account is not enabled.";
        return;
      }
      $is_non_netid_login = true;
    }
  }

  saveKioskInfo();
  saveBackToAppInfo();
}

function impersonatingUser() {
  global $web_euid;
  return $web_euid ? true : false;
}

function isNonNetIDLogin() {
  global $is_non_netid_login;
  return $is_non_netid_login;
}

function getLoginMethod() {
  if( isNonNetIDLogin() ) return "local";
  if( impersonatingUser() ) return "setuid";
  return "netid";
}

function allowNonNetIDLogins() {
  $ipaddr = $_SERVER['REMOTE_ADDR'];
  foreach( IP_RANGE_TO_ALLOW_UNAUTHENTICATED_LOGINS as $allowed_iprange ) {
    if( strncmp($ipaddr,$allowed_iprange,strlen($allowed_iprange))==0 ) return true;
  }
  return false;
}

function saveBackToAppInfo() {
  if( isset($_REQUEST["back_to_app_url"]) && isset($_REQUEST["back_to_app_name"]) ) {
    setcookie("back_to_app_url",$_REQUEST["back_to_app_url"],0,COOKIE_PATH,"",true);
    setcookie("back_to_app_name",$_REQUEST["back_to_app_name"],0,COOKIE_PATH,"",true);
  }
}

function getBackToAppInfo(&$back_to_app_url,&$back_to_app_name) {
  if( (isset($_REQUEST["back_to_app_url"]) || isset($_COOKIE["back_to_app_url"])) &&
      (isset($_REQUEST["back_to_app_name"]) || isset($_COOKIE["back_to_app_name"])) )
  {
    $back_to_app_url = isset($_REQUEST["back_to_app_url"]) ? $_REQUEST["back_to_app_url"] : $_COOKIE["back_to_app_url"];
    $back_to_app_name = isset($_REQUEST["back_to_app_name"]) ? $_REQUEST["back_to_app_name"] : $_COOKIE["back_to_app_name"];
    return true;
  }
}

function saveKioskInfo() {
  if( isset($_REQUEST["kiosk"]) ) {
    setcookie("shop_kiosk","1",0,COOKIE_PATH,"",true);
  }
}

function isKiosk() {
  return isset($_COOKIE["shop_kiosk"]);
}

function showLogoutButton() {
  return impersonatingUser() || isNonNetIDLogin() || getBackToAppInfo($junk,$junk2) || defined('LOGOUT_URL');
}

function echoShopLoginJavascript() {
  global $self_full_url;
  global $web_euid;

  ?><script>
  function login(show) {
    var url = "<?php echo $self_full_url ?>";
    if( show ) url = url + "?s=" + show;
    window.location.href = '<?php echo LOGIN_URL ?>' + encodeURI(url);
  }
  function logout() {
    <?php
    if( impersonatingUser() ) {
    ?>
      document.cookie = "shop_euid=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=<?php echo COOKIE_PATH ?>; secure";
      window.location.href = "<?php echo $self_full_url ?>?s=edit_user&user_id=<?php echo htmlescape($web_euid)?>";
    <?php
    } else if( isNonNetIDLogin() ) {
      echo "document.cookie = 'shop_group_login=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=",COOKIE_PATH,"; secure';\n";
      echo "window.location.href = '",$self_full_url,"';\n";
    } else if( getBackToAppInfo($back_to_app_url,$back_to_app_name) ) {
      echo "unset_back_to_app_cookies();\n";
      echo "window.location.href = '",$back_to_app_url,"';\n";
    } else {
      # When running in the shop kiosks, connecting to localhost:9999 will restart this browser,
      # which is a faster and less error-prone way of resetting than doing a shibboleth logout.
      # Just in case the script that restarts the browser isn't working, do the shibboleth
      # logout too.
      if( isKiosk() ) {
        #echo "var restart_w = window.open('http://localhost:9999','_blank');\n";
	echo "var restart_w = null;\n";
	echo "var iframe = document.createElement('div');\n";
	echo "var e = document.getElementsByTagName('html')[0].appendChild(iframe);\n";
	echo "iframe.innerHTML = \"<iframe src='http://localhost:9999' width:'1px' height:'1px'></iframe>\";\n";
      } else {
        echo "var restart_w = null;\n";
     }
    ?>
      var w = window.open('<?php echo LOGOUT_URL ?>','_blank');
      if( !w ) {
        /* pop-up windows must be blocked (happens during auto-logout when ending a job; rely on browser to make the problem known to the user */
      }
      else {
        w.focus();
        /* since the window is not same-origin, can't listen for the 'load' event, so continue after the window is closed or we have waited for 10s */
        setTimeout(function() {checkLogoutWindowClosed(w,restart_w);},500);
        setTimeout(function() {w.close();},10000);
      }
    <?php
    }
    ?>
  }
  function checkLogoutWindowClosed(w,restart_w) {
    if( w.closed ) {
      if( restart_w ) restart_w.close();
      window.location.href = "<?php echo $self_full_url?>";
    } else {
      setTimeout(function() {checkLogoutWindowClosed(w,restart_w);},500);
    }
  }
  function unset_back_to_app_cookies() {
    document.cookie = "back_to_app_url=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=<?php echo COOKIE_PATH ?>; secure";
    document.cookie = "back_to_app_name=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=<?php echo COOKIE_PATH ?>; secure";
  }
  </script><?php
}

function loadUserFromUserID($user_id) {
  $user = new User;
  if( !$user->loadFromUserID($user_id) ) {
    return false;
  }
  return $user;
}

function isAdmin() {
  global $web_user;
  if( impersonatingUser() ) return false;
  if( isNonNetIDLogin() ) return false;
  return $web_user && $web_user->is_admin;
}

function isInternalLogin() {
  global $web_user;
  return $web_user && $web_user->is_internal_login;
}

function isShopWorker() {
  global $web_user;
  if( isNonNetIDLogin() ) return false;
  return $web_user && $web_user->is_shop_worker;
}
