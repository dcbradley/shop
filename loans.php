<?php

function saveLoan($user) {
  $loan_item = isset($_POST["loan_item"]) ? trim($_POST["loan_item"]) : "";
  if( !$loan_item ) return;

  $user_id = $user->user_id;
  if( isLoanAdmin() ) {
    if( isset($_POST["user_id"]) ) {
      $user_id = $_POST["user_id"];
    }
  }

  $expected_return = isset($_REQUEST["expected_return"]) && trim($_REQUEST["expected_return"]) ? trim($_REQUEST["expected_return"]) : null;

  $dbh = connectDB();
  $sql = "INSERT INTO loan SET USER_ID = :USER_ID, START = now(), ITEM_NAME = :ITEM, EXPECTED_RETURN = :EXPECTED_RETURN";
  $stmt = $dbh->prepare($sql);
  $stmt->bindParam(":USER_ID",$user_id);
  $stmt->bindParam(":ITEM",$loan_item);
  $stmt->bindParam(":EXPECTED_RETURN",$expected_return);
  $stmt->execute();
  $loanid = $dbh->lastInsertId();

  $after = getLoanRecord($loanid);
  auditlogModifyLoan($user,null,$after);

  if( SHOP_LOAN_NOTICE ) {
    echo "<div class='alert alert-success'>",SHOP_LOAN_NOTICE,"</div>\n";
  }
}

function getLoanRecord($loanid) {
  $dbh = connectDB();
  $sql = "SELECT * FROM loan WHERE LOAN_ID = :LOANID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":LOANID",$loanid);
  $stmt->execute();
  return $stmt->fetch();
}

function returnLoan() {
  global $web_user;

  if( !isLoanAdmin() ) {
    echo "<div class='alert alert-danger'>Only the administrator can mark items as returned.</div>\n";
    return;
  }

  $loanid = $_POST["loanid"];
  $item_name = $_POST["item_name"];

  $db = connectDB();
  $before = getLoanRecord($loanid);

  $sql = "UPDATE loan SET RETURNED = now() WHERE LOAN_ID = :LOANID AND RETURNED is NULL";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(":LOANID",$loanid);
  $stmt->execute();

  $after = getLoanRecord($loanid);
  auditlogModifyLoan($web_user,$before,$after);

  if( $stmt->rowCount() == 1 ) {
    echo "<div class='alert alert-success'>Marked '",htmlescape($item_name),"' as returned.</div>\n";
    sendLoanReturnedReceipt($after);
  } else {
    echo "<div class='alert alert-danger'>Failed to mark '",htmlescape($item_name),"' as returned.</div>\n";
  }
}

function sendLoanReturnedReceipt($loan_row) {
  $dbh = connectDB();
  $user = loadUserFromUserID($loan_row["USER_ID"]);
  if( !$user ) return;

  $msg = array();
  $msg[] = "This is an automated receipt acknowledging the return of " . $loan_row["ITEM_NAME"];
  $msg[] = "";
  $msg[] = "Thanks!";
  $msg[] = SHOP_NAME . " Robot";

  $msg = implode("\r\n",$msg);

  $headers = array();
  $headers[] = "From: " . SHOP_NAME . " <help@physics.wisc.edu>";
  $headers = implode("\r\n",$headers);

  $subject = "Thank you for returning " . $loan_row["ITEM_NAME"];
  $to = $user->email;

  if( !mail($to,$subject,$msg,$headers,"-f help@physics.wisc.edu") ) {
    echo "<p class='alert alert-danger'>Failed to send receipt to ",htmlescape($to),".</p>\n";
  }
}

function showCurrentLoans($user,$show_admin_buttons=false) {
  echo "<div class='card card-md noprint'><div class='card-body'>\n";
  echo "<h2>Tool Borrowing Form</h2>\n";
  echo "<form id='loan_form' enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateLoanForm()'>\n";
  echo "<input type='hidden' name='form' value='loan'/>\n";
  if( isLoanAdmin() ) {
    echo "<input type='hidden' name='user_id' value='",htmlescape($user->user_id),"'/>\n";
  }

  echo "<div class='autocomplete'><input type='text' name='loan_item' id='loan_item' maxlength='60' placeholder='name of borrowed tool' aria-label='tool name'/></div><br>\n";
  # date placeholder is for browsers that do not provide their own date input widget
  echo "<span id='loan_fields' style='display: none'><label for='expected_return'>Expected Return Date</label>: <input type='date' name='expected_return' id='expected_return' placeholder='YYYY-MM-DD' min='",date("Y-m-d"),"'/><br></span>\n";
  echo "<input type='submit' id='submit_loan' value='Borrow Tool' disabled/>\n";

  echo "</form>\n";

  $loan_item_names = getLoanItemNamesJSON();
  ?>
    <script>
      var loan_item_names = <?php echo $loan_item_names; ?>;
      document.addEventListener("DOMContentLoaded", function(event) { autocomplete_unordered_words(document.getElementById("loan_item"), loan_item_names, 2); });
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", function(event) {
        $( "#loan_form input" ).on("change",loan_form_changed);
        $( "#loan_form input" ).on("input",loan_form_changed);
      } );
      function isValidDate(s) {
        if( !s.match(/ *[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] */) ) return false;
        var d = new Date(s);
        return d instanceof Date && !isNaN(d);
      }
      function loan_form_changed() {
        var s = document.getElementById("submit_loan");
        var v = document.getElementById("loan_item");
        var r = document.getElementById("expected_return");
        if( s && v ) {
          if( v.value ) {
            $('#loan_fields').show();
            if( r.value && isValidDate(r.value) ) {
              s.disabled = false;
            } else {
              s.disabled = true;
            }
          } else {
            s.disabled = true;
          }
        }
      }
      function validateLoanForm() {
        var r = document.getElementById("expected_return");
	if( r.value < "<?php echo date("Y-m-d") ?>" ) {
	  alert("The return date cannot be in the past!");
	  return false;
	}
	return true;
      }
    </script>
  <?php

  $db = connectDB();
  $sql = "SELECT ITEM_NAME,START,EXPECTED_RETURN,LOAN_ID,ADMIN_NOTES FROM loan WHERE USER_ID = :USER_ID AND RETURNED IS NULL ORDER BY START";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(":USER_ID",$user->user_id);
  $stmt->execute();

  $table_created = false;
  while( ($row = $stmt->fetch()) ) {
    if( !$table_created ) {
      $table_created = true;
      echo "<p><table class='records clicksort'>\n";
      echo "<caption>Tools Currently On Loan</caption>\n";
      echo "<thead><tr><th>Item</th><th>Checkout Date</th><th>Expected Return</th>";
      if( $show_admin_buttons ) echo "<th></th><th>Admin Notes</th>";
      echo "</tr></thead><tbody>\n";
    }
    echo "<tr class='record'>";
    echo "<td>",htmlescape($row["ITEM_NAME"]),"</td>";
    echo "<td>",htmlescape(displayDateTime($row["START"])),"</td>";
    echo "<td>",htmlescape(displayDate($row["EXPECTED_RETURN"])),"</td>";
    if( $show_admin_buttons ) {
      echo "<td><form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
      echo "<input type='hidden' name='form' value='returned'/>\n";
      echo "<input type='hidden' name='loanid' value='",htmlescape($row["LOAN_ID"]),"'/>\n";
      echo "<input type='hidden' name='item_name' value='",htmlescape($row["ITEM_NAME"]),"'/>\n";
      echo "<input type='submit' value='Return'/>\n";
      echo "</form></td>\n";
      echo "<td>",htmlescape($row["ADMIN_NOTES"]),"</td>";
    }
    echo "</tr>\n";
  }
  if( $table_created ) {
    echo "</tbody></table></p>\n";
    if( SHOP_LOAN_NOTICE ) {
      echo "<div class='alert alert-warning'>",SHOP_LOAN_NOTICE,"</div>\n";
    }
  }
  echo "</div></div>\n"; # end of card

  return $table_created;
}

function showLoans($user=null,$show_loan_history=false) {
  global $web_user;

  if( !$user && isset($_REQUEST["user_id"]) ) {
    $user = new User;
    $user->loadFromUserID($_REQUEST["user_id"]);
  }
  if( $user ) {
    echo "<h1>" . $user->displayName() . "'s Loans</h1>\n";
  }

  if( isset($_REQUEST["show_loan_history"]) ) {
    $show_loan_history = $_REQUEST["show_loan_history"];
  }

  $db = connectDB();
  $history_sql = $show_loan_history ? "" : "AND RETURNED IS NULL";
  $user_sql = $user ? "AND loan.USER_ID = :USER_ID" : "";
  $sql = "SELECT ITEM_NAME,START,RETURNED,EXPECTED_RETURN,LOAN_ID,ADMIN_NOTES,loan.USER_ID as USER_ID,FIRST_NAME,LAST_NAME,RETURNED IS NULL as ACTIVE FROM loan,user WHERE user.USER_ID = loan.USER_ID $history_sql $user_sql ORDER BY ACTIVE DESC,START DESC";
  $stmt = $db->prepare($sql);
  if( $user ) $stmt->bindValue(":USER_ID",$user->user_id);
  $stmt->execute();

  echo "<table class='records'><thead>\n";
  echo "<tr><th>User</th><th>Item</th><th>Checkout Date</th><th>Expected Return</th><th>Returned</th><th>Admin Notes</th></tr></thead><tbody>\n";

  while( ($row = $stmt->fetch()) ) {
    echo "<tr class='record'>";
    $username = $row["LAST_NAME"] . ", " . $row["FIRST_NAME"];
    echo "<td><a href='?s=edit_user&amp;user_id=",htmlescape($row["USER_ID"]),"'>",htmlescape($username),"</a></td>";
    echo "<td>",htmlescape($row["ITEM_NAME"]),"</td>";
    echo "<td>",htmlescape(displayDateTime($row["START"])),"</td>";
    echo "<td>",htmlescape(displayDate($row["EXPECTED_RETURN"])),"</td>";

    if( $row["ACTIVE"] ) {
      echo "<td><form enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
      echo "<input type='hidden' name='form' value='returned'/>\n";
      echo "<input type='hidden' name='loanid' value='",htmlescape($row["LOAN_ID"]),"'/>\n";
      echo "<input type='hidden' name='item_name' value='",htmlescape($row["ITEM_NAME"]),"'/>\n";
      echo "<input type='submit' value='Return'/>\n";
      echo "</form></td>\n";
    } else {
      echo "<td>",htmlescape(displayDateTime($row["RETURNED"])),"</td>";
    }
    echo "<td><input type='text' maxlength='1024' style='width:100%; min-width:15em' name='admin_notes_",htmlescape($row["LOAN_ID"]),"' value='",htmlescape($row["ADMIN_NOTES"]),"' onchange='updateLoanAdminNotes(this); return false;'/></td>";
    echo "</tr>\n";
  }

  echo "</tbody></table>\n";

  ?><script>
  function updateLoanAdminNotes(e) {
    var loan_id = e.name.replace("admin_notes_","");
    var url = "<?php echo WEBAPP_TOP ?>update_loan_admin_notes.php?loan_id=" + encodeURIComponent(loan_id);
    url += "&admin_notes=" + encodeURIComponent(e.value);
    $.ajax({ url: url, success: function(data) {
      if( data.indexOf("SUCCESS") === 0 ) {
        //console.log("successfully updated admin notes");
      } else {
        console.log("failed to update admin notes: " + data);
	alert("failed to update admin notes");
      }
    }});
  }
  </script><?php

  $user_url_arg = $user ? "&user_id=" . $user->user_id : "";

  if( !$show_loan_history ) {
    $url = "?s=loans&show_loan_history=1{$user_url_arg}";
    echo "<p>[<a href='",htmlescape($url),"'>show history</a>]</p>\n";
  }

  if( isShopWorker() ) {
    echo "<hr/>\n";
    showCurrentLoans($web_user,isLoanAdmin());
  }
}

function getLoanItemNamesJSON() {
  $db = connectDB();
  $sql = "SELECT DISTINCT ITEM_NAME FROM loan";
  $stmt = $db->prepare( ($sql) );
  $stmt->execute();

  $items = array();
  while( ($row=$stmt->fetch()) ) {
    $name = $row["ITEM_NAME"];
    $name = "\"" . str_replace("\"","",$name) . "\"";
    $items[] = $name;
  }
  return "[" . implode($items,",") . "]";
}
