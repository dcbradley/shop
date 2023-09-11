<?php

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

function canonicalDepartment($department) {
  $l = strtolower($department);
  foreach( SHOP_DEPARTMENTS as $d ) {
    if( strtolower($d) == $l ) {
      return $d;
    }
  }
  return $department;
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
    echo "<td>",htmlescape(displayDateTime($user->shop_last_login)),"</td>";
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
