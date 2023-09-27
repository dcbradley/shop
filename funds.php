<?php

function sortFundingSourceOptions($a,$b) {
  if( $a[1] < $b[1] ) return -1;
  if( $a[1] > $b[1] ) return 1;
  return 0;
}

function echoSelectFundingSource($user,$selected_funding_source=null,$disabled="",$show_details=false,$offer_to_set_default=false,$suffix="",$include_deleted=false,$show_search_box=true) {
  global $web_user;

  $dbh = connectDB();
  $sql = "SELECT *,user_group.USER_NETIDS FROM funding_source JOIN user_group ON user_group.GROUP_ID = funding_source.GROUP_ID";
  if( !$include_deleted ) {
    $sql .= " WHERE NOT funding_source.DELETED AND NOT user_group.DELETED";
  }
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  echo "<select name='funding_source$suffix' id='funding_source$suffix' class='compact-select' $disabled>";
  echo "</select>\n";

  if( $show_search_box ) {
    ?><style>
      .searchbox-container {
        max-width: 100%;
      }
      .searchbox {
        border: 1px solid grey;
	flex-wrap: none;
	display: flex;
	max-width: 100%;
      }
      .searchbox input {
        border: none;
	max-width: 100%;
	width: 30em;
      }
      .searchbox input:focus {
        outline: none;
      }
      .searchbox .search-icon svg {
        width: 1.25em;
	height: 1.25em;
      }
    </style><?php
    echo "<div class='autocomplete noprint searchbox-container'><div class='searchbox'><div class='search-icon' onclick='\$(this).parent().find(\"input\").focus()'><svg enable-background='new 0 0 512 512' id='Layer_1' version='1.1' xml:space='preserve' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' viewBox='148.92 148.95 212.08 212.05'><g><ellipse cx='223.2' cy='223.2' fill='none' rx='62.2' ry='62.2' stroke='#000000' stroke-linecap='round' stroke-linejoin='round' stroke-miterlimit='10' stroke-width='10' transform='matrix(0.7071 -0.7071 0.7071 0.7071 -92.4615 223.2219)'></ellipse><line fill='none' stroke='#000000' stroke-linecap='round' stroke-linejoin='round' stroke-miterlimit='10' stroke-width='10' x1='267.2' x2='351' y1='267.2' y2='351'></line></g></svg></div><input type='search' id='funding_source_search$suffix' placeholder='search by funding string'/></div></div>\n";
  }

  if( $show_details ) {
    echo "<br><div id='funding_source_details$suffix' style='font-family: monospace'></div>\n";
  }
  if( $offer_to_set_default ) {
    if( $user->netid == $web_user->netid ) {
      $my = "my";
      $by_this_user = "";
      $checked = "checked";
    } else {
      $my = "the";
      $by_this_user = " by this person";
      $checked = "";
    }
    echo "<div id='set_default_funding_source_controls' style='display: none'><label><input type='checkbox' name='set_default_funding_source' id='set_default_funding_source' value='1' $checked/> make this $my default funding source for future transactions$by_this_user</label></div>\n";
    echo "<input type='hidden' name='set_default_funding_source_enabled' id='set_default_funding_source_enabled' value=''/>";
  }

  if( $selected_funding_source === null && $user ) {
    $selected_funding_source = $user->default_funding_source_id;
  }

  $funding_sources = array();
  $fund_search_strings = array();
  while( ($row=$stmt->fetch()) ) {
    if( $row["FUNDING_SOURCE_ID"] !== $selected_funding_source ) {
      if( !canSeeFundGroup($row) || (!$include_deleted && !$row["FUNDING_ACTIVE"]) ) {
        continue;
      }
    }
    $group_id = $row["GROUP_ID"];
    $funding_source_id = $row["FUNDING_SOURCE_ID"];
    if( !array_key_exists($group_id,$funding_sources) ) {
      $funding_sources[$group_id] = array();
    }
    $pi_name_parts = explode(", ",$row["PI_NAME"]);
    $pi = $pi_name_parts[0];
    $fs_display_name = "";
    if( $pi ) {
      $fs_display_name .= "$pi - ";
    }
    $project = $row["FUNDING_PROJECT"];
    if( $project ) {
      $fs_display_name .= "$project - ";
    }
    $fs_display_name .= $row["FUNDING_DESCRIPTION"];

    $selected = $selected_funding_source === $funding_source_id ? 1 : 0;

    if( !$row["PURCHASING_FUNDING_SOURCE_ID"] ) {
      # only htmlescape strings not imported from purchasing system, because the latter contain html entities
      $fs_display_name = htmlescape($fs_display_name);
    }

    $details = "";
    if( $show_details ) {
      $details = "<span class='light-underline'>" . trim($row["FUNDING_FUND"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_PROJECT"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_DEPT"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_PROGRAM"]) . "</span>";
    }

    $funding_sources[$group_id][] = array($funding_source_id,$fs_display_name,$selected,$details);

    $fund_search_strings[] = $fs_display_name;
  }
  foreach( $funding_sources as $key => $value ) {
    usort($funding_sources[$key],"sortFundingSourceOptions");
  }
  ?>
  <script>
    var funding_sources<?php echo $suffix ?> = <?php echo json_encode($funding_sources); ?>;

    $('#funding_source<?php echo $suffix ?>').change(function() {updateFundingSourceDetails<?php echo $suffix ?>();});
    function updateFundingSourceDetails<?php echo $suffix ?>() {
      var group_id = $("#group_id<?php echo $suffix ?>").val();
      var fs_id = $('#funding_source<?php echo $suffix ?>').val();
      var fs = funding_sources<?php echo $suffix ?>[group_id];
      var details = "";
      if( fs ) {
        for( var i=0; i < fs.length; i++ ) {
	  if( fs[i][0] == fs_id ) {
	    details = fs[i][3];
	    break;
	  }
	}
      }
      var e = document.getElementById('funding_source_details<?php echo $suffix ?>');
      if( e ) e.innerHTML = details;
    }

    $('#funding_source<?php echo $suffix ?>').change(function() {fundingSourceChanged<?php echo $suffix ?>();});
    function fundingSourceChanged<?php echo $suffix ?>() {
      var fs_id = $('#funding_source<?php echo $suffix ?>').val();
      if( fs_id ) {
        $('#set_default_funding_source_enabled').val("1");
        $('#set_default_funding_source_controls').show();
      } else {
        $('#set_default_funding_source_enabled').val("0");
        $('#set_default_funding_source_controls').hide();
      }
    }

    $('#group_id<?php echo $suffix ?>').change(function() {groupChanged<?php echo $suffix ?>();});
    function groupChanged<?php echo $suffix ?>() {
      $("#funding_source<?php echo $suffix ?> option").remove();
      var fs_select = $("#funding_source<?php echo $suffix ?>");
      var group_id = $("#group_id<?php echo $suffix ?>").val();
      var fs = funding_sources<?php echo $suffix ?>[group_id];
      var selected = null;
      var o = document.createElement("option");
      o.text = "Select Fund";
      o.value = "";
      $(fs_select).append(o);
      if( fs ) for(var i=0; i<fs.length; i++ ) {
        var o = document.createElement("option");
        o.value = fs[i][0];
        o.innerHTML = fs[i][1];
        if( fs[i][2] ) selected = fs[i][0];
        $(fs_select).append(o);
      }
      if( selected ) {
        fs_select.val(selected);
      }
      updateFundingSourceDetails<?php echo $suffix ?>();
    }
    groupChanged<?php echo $suffix ?>();
  </script>
  <?php

  if( $show_search_box ) {
    ?><script>
      var fund_search_strings = <?php echo json_encode($fund_search_strings); ?>;
      document.addEventListener("DOMContentLoaded", function() {
        var search_box = document.getElementById("funding_source_search<?php echo $suffix ?>");
        autocomplete_unordered_words(search_box, fund_search_strings, 2);

        search_box.addEventListener("change",function() {
          var search_string = this.value;
          var found = false;
          var select_group = document.getElementById('group_id<?php echo $suffix ?>');
          var select_fund = document.getElementById('funding_source<?php echo $suffix ?>');
          for( const [group_id,funds]  of Object.entries(funding_sources<?php echo $suffix ?>) ) {
            for( const fund_info of funds ) {
              if( fund_info[1] == search_string ) {
                select_group.value = group_id;
                groupChanged<?php echo $suffix ?>();
                select_fund.value = fund_info[0];
                fundingSourceChanged<?php echo $suffix ?>();
                updateFundingSourceDetails<?php echo $suffix ?>();
                found = true;
                break;
              }
            }
            if( found ) break;
          }
          if( !found ) {
            select_group.value = '';
            groupChanged<?php echo $suffix ?>();
            fundingSourceChanged<?php echo $suffix ?>();
            select_fund.value = '';
            updateFundingSourceDetails<?php echo $suffix ?>();
          }
        });
      });
    </script><?php
  }
}

function canSeeFundGroup($group_row) {
  global $web_user;
  if( isAdmin() || isShopWorker() ) return true;
  if( $group_row["USER_NETIDS"] == "" ) return true;

  return in_array($web_user->netid,explode(",",$group_row["USER_NETIDS"]));
}

function echoSelectUserGroup($user,$selected_group=null,$disabled="",$suffix="") {
  $dbh = connectDB();
  $sql = "SELECT * from user_group WHERE NOT DELETED ORDER BY PURCHASING_GROUP_ID IS NOT NULL DESC,GROUP_NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  if( $selected_group === null && $user ) {
    $selected_group = $user->default_group_id;
  }

  echo "<select name='group_id$suffix' id='group_id$suffix' class='compact-select' $disabled>\n";
  echo "<option value=''>Select a Group</option>\n";
  $dept_groups = true;
  $dept_groups_header = false;
  while( ($row=$stmt->fetch()) ) {
    if( !canSeeFundGroup($row) ) continue;
    if( $row["PURCHASING_GROUP_ID"] === null && $dept_groups ) {
      $dept_groups = false;
      if( $dept_groups_header ) {
        echo "<option disabled>───Additional Groups───</option>\n";
      }
    }
    if( $dept_groups && !$dept_groups_header && MAIN_FUND_GROUPS_LABEL ) {
      $dept_groups_header = true;
      echo "<option disabled>───",htmlescape(MAIN_FUND_GROUPS_LABEL),"───</option>\n";
    }
    $selected = $selected_group == $row["GROUP_ID"] ? " selected " : "";
    echo "<option value='",htmlescape($row["GROUP_ID"]),"' $selected>",htmlescape($row["GROUP_NAME"]),"</option>\n";
  }
  echo "</select>\n";
}

function fundingStringHTML($row) {
  return "<span class='light-underline'>" . trim($row["FUNDING_FUND"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_PROJECT"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_DEPT"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_PROGRAM"]) . "</span>";
}

function getUserGroupRecord($group_id) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from user_group WHERE GROUP_ID = :GROUP_ID");
  $stmt->bindValue(":GROUP_ID",$group_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getFundingSourceRecord($funding_source_id) {
  $dbh = connectDB();
  $stmt = $dbh->prepare("SELECT * from funding_source WHERE FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID");
  $stmt->bindValue(":FUNDING_SOURCE_ID",$funding_source_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getFundingSourceAndAuthRecord($funding_source_id) {
  $dbh = connectDB();
  $sql = "SELECT funding_source.*,user_group.USER_NETIDS FROM funding_source JOIN user_group ON user_group.GROUP_ID = funding_source.GROUP_ID WHERE FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":FUNDING_SOURCE_ID",$funding_source_id);
  $stmt->execute();
  return $stmt->fetch();
}

function getFundingSourceStringHTML($row) {
  $pi_name_parts = explode(", ",$row["PI_NAME"]);
  $pi = $pi_name_parts[0];
  $fs_display_name = "";
  if( $pi ) {
    $fs_display_name .= "$pi - ";
  }
  $project = $row["FUNDING_PROJECT"];
  if( $project ) {
    $fs_display_name .= "$project - ";
  }
  $fs_display_name .= $row["FUNDING_DESCRIPTION"];

  $fs_numbers = trim(htmlescape($row["FUNDING_FUND"])) . " " . trim(htmlescape($row["FUNDING_PROJECT"])) . " " . trim(htmlescape($row["FUNDING_DEPT"])) . " " . trim(htmlescape($row["FUNDING_PROGRAM"]));
  $fs_numbers = trim($fs_numbers);

  $funding_string = "";
  if( $fs_numbers || $fs_display_name ) {
    $funding_string = "<span title='" . htmlescape($fs_display_name) . "'>" . $fs_numbers . "</span>";
  }

  return $funding_string;
}

function getFundingSourceLongStringHTML($row) {
  $pi_name_parts = explode(", ",$row["PI_NAME"]);
  $pi = $pi_name_parts[0];
  $fs_display_name = "";
  if( $pi ) {
    $fs_display_name .= "$pi - ";
  }
  $fs_display_name .= $row["FUNDING_DESCRIPTION"];

  $fs_numbers = trim(htmlescape($row["FUNDING_FUND"])) . " " . trim(htmlescape($row["FUNDING_PROJECT"])) . " " . trim(htmlescape($row["FUNDING_DEPT"])) . " " . trim(htmlescape($row["FUNDING_PROGRAM"]));
  $fs_numbers = trim($fs_numbers);

  $funding_string = "";
  if( $fs_numbers || $fs_display_name ) {
    $funding_string = $fs_numbers . ": " . $fs_display_name;
  }

  return $funding_string;
}

function showEditGroup($web_user) {
  $group_id = isset($_REQUEST["group_id"]) ? $_REQUEST["group_id"] : "";
  $dbh = connectDB();
  if( $group_id == "add" || $group_id == "" || isset($_REQUEST["set_user_group"])) {
    $user_group = null;
  } else {
    $sql = "SELECT * from user_group WHERE GROUP_ID = :GROUP_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(":GROUP_ID",$group_id);
    $stmt->execute();
    $user_group = $stmt->fetch();
  }

  $verb = $user_group  ? "Edit" : "Add";
  echo "<h2>$verb Fund Group</h2>\n";

  echo "<div class='card card-md'><div class='card-body'>\n";
  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateForm()'>\n";
  echo "<input type='hidden' name='form' value='edit_group'/>\n";
  if( isset($_REQUEST["set_user_group"]) ) {
    echo "<input type='hidden' name='set_user_group' value='1'/>\n";
  }
  if( isset($_REQUEST["set_user_funding_source"]) ) {
    echo "<input type='hidden' name='set_user_funding_source' value='1'/>\n";
  }
  if( isAdmin() && isset($_REQUEST["user_id"]) ) {
    echo "<input type='hidden' name='user_id' value='",htmlescape($_REQUEST["user_id"]),"'/>\n";
  }
  if( $user_group ) {
    echo "<input type='hidden' name='group_id' value='",htmlescape($user_group["GROUP_ID"]),"'/>\n";
  }
  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  $disabled = "";
  if( $user_group && $user_group["PURCHASING_GROUP_ID"] !== null ) {
    echo "<p class='alert alert-warning'>NOTE: this group is imported from the Department's general purchasing database, so it cannot be edited here. Updates to the funds in the purchasing database will be reflected here within a day. If you wish to add a funding source not listed, create a new group and add the funding source to that.</p>\n";
    $disabled = "disabled";
  }
  else if( isNonNetIDLogin() ) {
    echo "<p class='alert alert-danger'>NOTE: you must log in with your NetID in order to make changes to funds.</p>\n";
    $disabled = "disabled";
  }

  echo "<div class='$rowclass'><div class='$col1'><label for='phone'>Group Name</label></div><div class='col'><input name='group_name' id='group_name' type='text' maxlength='20' value='",htmlescape($user_group ? $user_group["GROUP_NAME"] : ""),"'/></div></div>\n";

  echo "</div>\n"; # end of form columns container

  if( $user_group ) {
    $checked = $user_group && $user_group["DELETED"] ? "checked" : "";
    echo "<p><label><input type='checkbox' name='deleted' value='1' $checked/> mark this group as deleted</label></p>\n";
  }

  echo "<p>Specify a comma-separated list of NetIDs of people who can use these funds. If none are specified, the group is visible to everyone. If you do restrict by NetID, please include the NetID of the PI, so they have access too if needed.<br>\n";
  echo "<input name='user_netids' size='30' value='",htmlescape($user_group ? $user_group["USER_NETIDS"] : ""),"' placeholder='netid1,netid2,...'/></p>\n";

  echo "<p>&nbsp;</p>\n";
  echo "<h3>Funds</h3>\n";
  echo "<div id='funding_source_table'>";

  $funding_source_count = 0;
  if( $user_group ) {
    $sql = "SELECT * from funding_source WHERE GROUP_ID = :GROUP_ID ORDER BY FUNDING_DESCRIPTION";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(":GROUP_ID",$group_id);
    $stmt->execute();

    for($funding_source_count=0; ($row=$stmt->fetch()); $funding_source_count++ ) {
      echo "<div class='card card-md'><div class='card-body funding_row'>\n";
      echo "<input type='hidden' name='fs_funding_source_id[]' value='",htmlescape($row["FUNDING_SOURCE_ID"]),"'\>\n";

      echo "<div class='$rowclass'><div class='$col1'><label>Fund Description</label></div><div class='col'><input name='fs_funding_description[]' value='",htmlescape($row["FUNDING_DESCRIPTION"]),"' style='width: 100%'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund</label></div><div class='col'><input name='fs_funding_fund[]' value='",htmlescape($row["FUNDING_FUND"]),"' placeholder='144'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Department UDDS</label></div><div class='col'><input required name='fs_funding_dept[]' value='",htmlescape($row["FUNDING_DEPT"]),"' placeholder='A486700'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Account</label></div><div class='col'><input name='fs_funding_program[]' value='",htmlescape($row["FUNDING_PROGRAM"]),"' placeholder='4'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Project</label></div><div class='col'><input name='fs_funding_project[]' value='",htmlescape($row["FUNDING_PROJECT"]),"' placeholder='XYZ1234'/></div></div>\n";

      echo "<div class='$rowclass'><div class='$col1'><label>PI Name</label></div><div class='col'><input name='fs_pi_name[]' value='",htmlescape($row["PI_NAME"]),"' placeholder='Last, First'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>PI Email</label></div><div class='col'><input name='fs_pi_email[]' value='",htmlescape($row["PI_EMAIL"]),"'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>PI Phone</label></div><div class='col'><input name='fs_pi_phone[]' value='",htmlescape($row["PI_PHONE"]),"'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Billing Contact Name</label></div><div class='col'><input name='fs_billing_contact_name[]' value='",htmlescape($row["BILLING_CONTACT_NAME"]),"' placeholder='Last, First'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Billing Contact Email</label></div><div class='col'><input name='fs_billing_contact_email[]' value='",htmlescape($row["BILLING_CONTACT_EMAIL"]),"'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Billing Contact Phone</label></div><div class='col'><input name='fs_billing_contact_phone[]' value='",htmlescape($row["BILLING_CONTACT_PHONE"]),"'/></div></div>\n";

      echo "<div class='$rowclass'><div class='$col1'><label>Fund Start</label> <i class='fas fa-question-circle' title='Thoptional field is for informational purposes only.'></i></div><div class='col'><input name='fs_start[]' type='date' value='",htmlescape($row["FUNDING_START"]),"'/></div></div>\n";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund End</label> <i class='fas fa-question-circle' title='This optional field is for informational purposes only.'></i></div><div class='col'><input name='fs_end[]' type='date' value='",htmlescape($row["FUNDING_END"]),"'/></div></div>\n";
      $checked = $row["FUNDING_ACTIVE"] ? "checked" : "";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund Active</label></div><div class='col'><label><input name='fs_active[]' type='checkbox' value='",htmlescape($row["FUNDING_SOURCE_ID"]),"' $checked/> active</label></div></div>\n";

      if( $user_group ) {
        $checked = $row["DELETED"] ? "checked" : "";
        echo "<div class='$rowclass'><div class='$col1'><label>Deleted</label></div><div class='col'><label><input name='fs_deleted[]' type='checkbox' value='",htmlescape($row["FUNDING_SOURCE_ID"]),"' $checked/> mark as deleted</label></div></div>\n";
      }

      echo "</div></div>\n";
      echo "<hr>\n";
    }
  }
  echo "</div>\n";

  echo "<button type='button' class='btn btn-secondary' onclick='addFundingSource();'>Add Another Fund</button>\n";

  echo "<p>&nbsp;</p>\n";
  echo "<input type='submit' value='Save' $disabled/>\n";
  echo "</form>\n";
  echo "</div></div>\n"; # end of card

  ?>
  <script>
    function addFundingSource() {
      var funding_source = document.createElement('div');
      funding_source.innerHTML = "\
<?php
      echo "<div class='card card-md'><div class='card-body funding_row'>";

      echo "<input type='hidden' name='fs_funding_source_id[]'/>";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund Description</label></div><div class='col'><input name='fs_funding_description[]' style='width: 100%'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund</label></div><div class='col'><input name='fs_funding_fund[]' placeholder='144'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Department UDDS</label></div><div class='col'><input required name='fs_funding_dept[]' placeholder='A486700'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Account</label></div><div class='col'><input name='fs_funding_program[]' placeholder='4'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Project</label></div><div class='col'><input name='fs_funding_project[]' placeholder='XYZ1234'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>PI Name</label></div><div class='col'><input name='fs_pi_name[]' placeholder='Last, First'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>PI Email</label></div><div class='col'><input name='fs_pi_email[]'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>PI Phone</label></div><div class='col'><input name='fs_pi_phone[]'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Billing Contact Name</label></div><div class='col'><input name='fs_billing_contact_name[]' placeholder='Last, First'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Billing Contact Email</label></div><div class='col'><input name='fs_billing_contact_email[]'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Billing Contact Phone</label></div><div class='col'><input name='fs_billing_contact_phone[]'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund Start</label> <i class='fas fa-question-circle' title='Thoptional field is for informational purposes only.'></i></div><div class='col'><input name='fs_start[]' type='date'/></div></div>";
      echo "<div class='$rowclass'><div class='$col1'><label>Fund End</label> <i class='fas fa-question-circle' title='Thoptional field is for informational purposes only.'></i></div><div class='col'><input name='fs_end[]' type='date'/></div></div>";
      echo "</div></div>";
      echo "<hr>";
      ?>";

      var container = $('#funding_source_table');
      $(container).append(funding_source);
    }
    if( <?php echo $funding_source_count ?> == 0 ) addFundingSource();

    function validateForm() {
      var group_name = document.getElementById("group_name").value;
      if( !group_name ) {
        alert("Please enter a group name.");
        return false;
      }
      var empty = true;
      /* for now, just require that a PI Name and fund description string are entered */
      $(".funding_row").each(function() {
        if( !$(this).find("input[name='fs_pi_name[]']").val() ) return;
        if( !$(this).find("input[name='fs_funding_description[]']").val() ) return;
        empty = false;
      });
      if( empty ) {
        alert("Please enter missing funding source information.");
        return false;
      }
      return true;
    }
  </script>
  <?php
}

function saveGroup($web_user,&$show) {
  if( isNonNetIDLogin() ) {
    echo "<div class='alert alert-danger'>You must log in with your NetID to make changes to funds.</div>\n";
    return;
  }

  $group_id = isset($_POST["group_id"]) ? $_POST["group_id"] : null;
  $group_name = $_POST["group_name"];
  $deleted = isset($_POST["deleted"]) && $_POST["deleted"] ? 1 : 0;
  $set_user_group = isset($_POST["set_user_group"]);
  $set_user_funding_source = $set_user_group || isset($_POST["set_user_funding_source"]);

  $dbh = connectDB();

  if( $group_id === null ) {
    $sql = "INSERT INTO";
    $before_edit = null;
  } else {
    $sql = "UPDATE";
    $before_edit = getUserGroupRecord($group_id);
    if( !canSeeFundGroup($before_edit) ) {
      echo "<div class='alert alert-danger'>You do not have permission to edit this fund group.</div>\n";
      return;
    }
  }
  $sql .= " user_group SET GROUP_NAME = :GROUP_NAME, DELETED = :DELETED, USER_NETIDS = :USER_NETIDS";
  if( $group_id !== null ) {
    $sql .= " WHERE GROUP_ID = :GROUP_ID";
  }
  $stmt = $dbh->prepare($sql);
  $stmt->bindParam(":GROUP_NAME",$group_name);
  $stmt->bindParam(":DELETED",$deleted);
  $user_netids = array();
  foreach( explode(",",$_REQUEST["user_netids"]) as $netid ) {
    $netid = trim(strtolower($netid));
    if( $netid != "" ) {
      $user_netids[] = $netid;
    }
  }
  $user_netids = implode(",",$user_netids);
  $stmt->bindValue(":USER_NETIDS",$user_netids);
  if( $group_id !== null ) {
    $stmt->bindParam(":GROUP_ID",$group_id);
  }
  $stmt->execute();

  $orig_group_id = $group_id;
  if( $group_id === null ) {
    $group_id = $dbh->lastInsertId();
    $_REQUEST["group_id"] = $group_id;
  }

  $after_edit = getUserGroupRecord($group_id);
  auditlogModifyUserGroup($web_user,$before_edit,$after_edit);

  $fs_funding_source_id = $_POST["fs_funding_source_id"];
  $fs_pi_name = $_POST["fs_pi_name"];
  $fs_pi_email = $_POST["fs_pi_email"];
  $fs_pi_phone = $_POST["fs_pi_phone"];
  $fs_billing_contact_name = $_POST["fs_billing_contact_name"];
  $fs_billing_contact_email = $_POST["fs_billing_contact_email"];
  $fs_billing_contact_phone = $_POST["fs_billing_contact_phone"];
  $fs_funding_description = $_POST["fs_funding_description"];
  $fs_funding_fund = $_POST["fs_funding_fund"];
  $fs_funding_program = $_POST["fs_funding_program"];
  $fs_funding_dept = $_POST["fs_funding_dept"];
  $fs_funding_project = $_POST["fs_funding_project"];
  $fs_start = $_POST["fs_start"];
  $fs_end = $_POST["fs_end"];
  $fs_active = isset($_POST["fs_active"]) ? $_POST["fs_active"] : null;
  $fs_deleted = isset($_POST["fs_deleted"]) ? $_POST["fs_deleted"] : null;

  $new_funding_source_count = 0;
  $new_funding_source_id = null;
  for( $i=0; $i<count($fs_funding_source_id); $i++ ) {

    # ignore blank entries
    if( $fs_funding_source_id[$i] == "" && $fs_pi_name[$i] == "" && $fs_pi_email[$i] == "" && $fs_pi_phone[$i] == "" && $fs_billing_contact_name[$i] == "" && $fs_billing_contact_email[$i] == "" && $fs_billing_contact_phone[$i] == "" && $fs_funding_description[$i] == "" && $fs_funding_fund[$i] == "" && $fs_funding_program[$i] == "" && $fs_funding_dept[$i] == "" && $fs_funding_project[$i] == "" ) {
      continue;
    }

    if( $fs_funding_source_id[$i] != "" ) {
      $sql = "UPDATE";
      $sql2 = " WHERE FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID";
      $before_edit = getFundingSourceRecord($fs_funding_source_id[$i]);
    } else {
      $sql = "INSERT INTO";
      $sql2 = ", CREATED = now()";
      $before_edit = null;
    }
    $sql .= " funding_source SET PI_NAME = :PI_NAME, PI_EMAIL = :PI_EMAIL, PI_PHONE = :PI_PHONE, BILLING_CONTACT_NAME = :BILLING_CONTACT_NAME, BILLING_CONTACT_EMAIL = :BILLING_CONTACT_EMAIL, BILLING_CONTACT_PHONE = :BILLING_CONTACT_PHONE, FUNDING_DESCRIPTION = :FUNDING_DESCRIPTION, FUNDING_FUND = :FUNDING_FUND, FUNDING_PROGRAM = :FUNDING_PROGRAM, FUNDING_DEPT = :FUNDING_DEPT, FUNDING_PROJECT = :FUNDING_PROJECT, GROUP_ID = :GROUP_ID, FUNDING_START = :FUNDING_START, FUNDING_END = :FUNDING_END, FUNDING_ACTIVE = :FUNDING_ACTIVE, DELETED = :DELETED" . $sql2;

    $stmt = $dbh->prepare($sql);
    if( $fs_funding_source_id[$i] != "" ) {
      $stmt->bindValue(":FUNDING_SOURCE_ID",$fs_funding_source_id[$i]);
    }
    $stmt->bindValue(":PI_NAME",$fs_pi_name[$i]);
    $stmt->bindValue(":PI_EMAIL",$fs_pi_email[$i]);
    $stmt->bindValue(":PI_PHONE",$fs_pi_phone[$i]);
    $stmt->bindValue(":BILLING_CONTACT_NAME",$fs_billing_contact_name[$i]);
    $stmt->bindValue(":BILLING_CONTACT_EMAIL",$fs_billing_contact_email[$i]);
    $stmt->bindValue(":BILLING_CONTACT_PHONE",$fs_billing_contact_phone[$i]);
    $stmt->bindValue(":FUNDING_DESCRIPTION",$fs_funding_description[$i]);
    $stmt->bindValue(":FUNDING_FUND",$fs_funding_fund[$i]);
    $stmt->bindValue(":FUNDING_PROGRAM",$fs_funding_program[$i]);
    $stmt->bindValue(":FUNDING_DEPT",$fs_funding_dept[$i]);
    $stmt->bindValue(":FUNDING_PROJECT",$fs_funding_project[$i]);
    $stmt->bindValue(":FUNDING_START",$fs_start[$i] == '' ? null : $fs_start[$i]);
    $stmt->bindValue(":FUNDING_END",$fs_end[$i] == '' ? null : $fs_end[$i]);
    $active = !$before_edit ? 1 : ($fs_active && in_array($fs_funding_source_id[$i],$fs_active) ? 1 : 0);
    $stmt->bindValue(":FUNDING_ACTIVE",$active);
    $stmt->bindValue(":GROUP_ID",$group_id);
    $deleted = $fs_deleted && in_array($fs_funding_source_id[$i],$fs_deleted) ? 1 : 0;
    $stmt->bindValue(":DELETED",$deleted);
    $stmt->execute();

    $funding_source_id = $fs_funding_source_id[$i] != "" ? $fs_funding_source_id[$i] : $dbh->lastInsertId();
    if( $fs_funding_source_id[$i] == "" ) {
      $new_funding_source_id = $funding_source_id;
      $new_funding_source_count += 1;
    }
    $after_edit = getFundingSourceRecord($funding_source_id);
    auditlogModifyFundingSource($web_user,$before_edit,$after_edit);
  }

  # set default group id of user to the newly created group
  if( $set_user_group || ($set_user_funding_source && $new_funding_source_count == 1) ) {
    if( isAdmin() && isset($_REQUEST["user_id"]) ) {
      $user_id = $_REQUEST["user_id"];
    } else {
      $user_id = $web_user->user_id;
    }
    if( $user_id != $web_user->user_id ) {
      $cur_user = new User;
      $cur_user->loadFromUserID($user_id);
    } else {
      $cur_user =& $web_user;
    }
    $orig_user = clone $cur_user;

    $cur_user->default_group_id = $group_id;
    $sql = "UPDATE user SET ";
    $comma = "";
    if( $set_user_group ) {
      $sql .= "$comma DEFAULT_GROUP_ID = :DEFAULT_GROUP_ID ";
      $comma = ",";
    }
    if( $set_user_funding_source && $new_funding_source_count == 1 ) {
      $sql .= "$comma DEFAULT_FUNDING_SOURCE_ID = :DEFAULT_FUNDING_SOURCE_ID ";
      $comma = ",";
    }
    $sql .= "WHERE USER_ID = :USER_ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(":USER_ID",$user_id);
    if( $set_user_group ) {
      $stmt->bindParam(":DEFAULT_GROUP_ID",$group_id);
    }
    if( $set_user_funding_source && $new_funding_source_count == 1 ) {
      $stmt->bindParam(":DEFAULT_FUNDING_SOURCE_ID",$new_funding_source_id);
      $cur_user->default_funding_source_id = $new_funding_source_id;
    }
    $stmt->execute();

    auditlogModifyUser($orig_user,$cur_user);
  }

  if( $set_user_group ) {
    $show = "profile";
  } else {
    echo "<div class='alert alert-success'>Saved</div>\n";
  }
}

function handleDefaultFundingSourceUpdateForm($user) {
  if( isset($_POST["set_default_funding_source_enabled"]) && $_POST["set_default_funding_source_enabled"] && isset($_POST["set_default_funding_source"]) && $_POST["set_default_funding_source"] && $_POST["group_id"] && $_POST["funding_source"] ) {
    $user->saveDefaultFundingSource($_POST["group_id"],$_POST["funding_source"]);
  }
}

function showGroups() {

  echo "<h2>Fund Groups</h2>\n";
  if( MAIN_FUND_GROUPS_LABEL ) {
    echo "<p>Groups in bold are imported from elsewhere and are not editable here.  Others have been added by individuals.</p>\n";
  }
  echo "<p>For a list of funds in a particular group, click on the group.  For a list of all funds, see <a href='#all-funds'>All Funds</a>.</p>\n";

  $dbh = connectDB();
  $sql = "SELECT * FROM user_group ORDER BY PURCHASING_GROUP_ID IS NOT NULL DESC,GROUP_NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  $url = "?s=edit_group";
  echo "<a class='btn btn-primary' href='",htmlescape($url),"'>Add Group</a>\n";
  $url = "?s=search_replace_fund";
  echo "<a class='btn btn-primary' href='",htmlescape($url),"'>Search/Replace Fund</a><br>\n";
  echo "<table class='records clicksort'><thead><tr><th>Name</th><th>Deleted</th></tr></thead><tbody>\n";
  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $group_name = $row["GROUP_NAME"];
    if( $group_name == "" ) $group_name = "-";
    $style = $row["PURCHASING_GROUP_ID"] === null ? "" : "style='font-weight: bold'";
    $url = "?s=edit_group&group_id=" . $row["GROUP_ID"];
    echo "<td $style><a href='",htmlescape($url),"'>",htmlescape($group_name),"</a>";
    echo "\n<span class='clicksort_data'>",htmlescape($group_name),"</span></td>";
    echo "<td>", ($row["DELETED"] ? "Y" : "") ,"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table><p>&nbsp;</p>\n";

  echo "<a name='all-funds'></a><h2>All Funds</h2>\n";

  $sql = "SELECT funding_source.*,user_group.GROUP_NAME,user_group.PURCHASING_GROUP_ID FROM funding_source JOIN user_group ON user_group.GROUP_ID = funding_source.GROUP_ID ORDER BY PURCHASING_GROUP_ID IS NOT NULL DESC,GROUP_NAME";
  $stmt = $dbh->prepare($sql);
  $stmt->execute();

  echo "<label><input type='checkbox' name='show_inactive' id='show_inactive' value='1' onchange='showInactiveChanged()'/> show inactive funds</label><br/>\n";

  echo "<table class='records clicksort'><thead><tr><th>Group</th><th>PI</th><th>Fund Description</th><th>Funding String</th></tr></thead><tbody>\n";

  while( ($row=$stmt->fetch()) ) {
    $style = "";
    $row_class = "";
    if( !$row["FUNDING_ACTIVE"] ) {
      $style = "style='text-decoration: line-through'";
      $row_class = 'inactive_fund';
    }
    if( $row["DELETED"] ) {
      $style = "style='text-decoration: line-through'";
      $row_class = 'inactive_fund';
    }
    echo "<tr class='record $row_class' $style>";
    $group_name = $row["GROUP_NAME"];
    if( $group_name == "" ) $group_name = "-";
    $style = $row["PURCHASING_GROUP_ID"] === null ? "" : "style='font-weight: bold'";
    $url = "?s=edit_group&group_id=" . $row["GROUP_ID"];
    echo "<td $style><a href='",htmlescape($url),"'>",htmlescape($group_name),"</a>";
    echo "\n<span class='clicksort_data'>",htmlescape($group_name),"</span></td>";

    echo "<td>",htmlescape($row["PI_NAME"]),"</td>";

    echo "<td>",htmlescape($row["FUNDING_DESCRIPTION"]),"</td>";
    $funding_string = "<span class='light-underline'>" . trim($row["FUNDING_FUND"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_PROJECT"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_DEPT"]) . "</span> <span class='light-underline'>" . trim($row["FUNDING_PROGRAM"]) . "</span>";
    echo "<td>",$funding_string,"</td>";
    echo "</tr>\n";
  }
  echo "</tbody></table><p>&nbsp;</p>\n";

  ?><script>
    function showInactiveChanged() {
      if( document.getElementById('show_inactive').checked ) {
        $('.inactive_fund').show();
      } else {
        $('.inactive_fund').hide();
      }
    }
    showInactiveChanged();
  </script><?php
}

function showSearchReplaceFund() {
  if( !isAdmin() ) return;

  $search_user_group_id = $_REQUEST["group_id"] ?? null;
  $search_funding_source_id = $_REQUEST["funding_source"] ?? null;
  $replace_user_group_id = $_REQUEST["group_id_replace"] ?? null;
  $replace_funding_source_id = $_REQUEST["funding_source_replace"] ?? null;

  echo "<p>Search for users and work orders using a fund.  To search for a fund by a fragment of its funding string or description, use the <a href='?s=groups#all-funds' target='_blank'>All Funds</a> table.</p>\n";

  echo "<form enctype='multipart/form-data' method='POST'>\n";
  echo "<input type='hidden' name='s' value='search_replace_fund'/>\n";

  echo "<div class='container'>\n";
  $rowclass = "row mt-3 mt-sm-0";
  $col1 = "col-sm-4 col-md-3 col-lg-2 label";

  echo "<div class='$rowclass'><div class='$col1'>Fund to search for</div><div class='col'>";
  echoSelectUserGroup(null,$search_user_group_id);
  echoSelectFundingSource(null,$search_funding_source_id,"",true,false,"",true,true);
  echo "</div></div>\n";

  $checked = isset($_REQUEST["replace"]) && $_REQUEST["replace"] ? "checked" : "";
  echo "<div class='$rowclass'><div class='col'><label><input type='checkbox' name='replace' id='replace' value='1' $checked onchange='replaceChanged()'/> replace fund</label></div></div>\n";

  echo "<div class='$rowclass replace_section'><div class='$col1'>Fund to replace with</div><div class='col'>";
  echoSelectUserGroup(null,$replace_user_group_id,"","_replace");
  echoSelectFundingSource(null,$replace_funding_source_id,"",true,false,"_replace",true,true);
  echo "</div></div>\n";

  echo "<input type='submit' value='Submit'/>\n";

  echo "</div>\n"; # end container
  echo "</form>\n";

  ?><script>
  function replaceChanged() {
    if( document.getElementById('replace').checked ) {
      $('.replace_section').show();
    }
    else {
      $('.replace_section').hide();
    }
  }
  document.addEventListener("DOMContentLoaded", function(){
    replaceChanged();
  });
  </script><?php

  doSearchReplaceFund();
}

function doSearchReplaceFund() {
  global $web_user;

  if( !isAdmin() ) return;

  $search_user_group_id = $_REQUEST["group_id"] ?? "";
  $search_funding_source_id = $_REQUEST["funding_source"] ?? "";

  if( $search_user_group_id == "" && $search_funding_source_id == "" ) return;

  $replace = $_REQUEST["replace"] ?? false;
  $replace_user_group_id = $_REQUEST["group_id_replace"] ?? "";
  $replace_funding_source_id = $_REQUEST["funding_source_replace"] ?? "";

  echo "<hr>\n";

  if( $replace && ($replace_user_group_id == "" || $replace_funding_source_id == "") ) {
    $replace = false;
    echo "<p class='alert alert-warning'>Not replacing, because a replacement funding source was not specified.</p>\n";
  }
  if( $replace ) {
    echo "<div id='replace_status'></div>\n";
  }

  $dbh = connectDB();

  if( $replace ) {
    $dbh->beginTransaction();
  }

  $sql = "
    SELECT
      USER_ID,
      FIRST_NAME,
      LAST_NAME,
      EMAIL,
      funding_source.FUNDING_FUND,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_DESCRIPTION
    FROM
      user
    LEFT JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = user.DEFAULT_FUNDING_SOURCE_ID
    WHERE
  ";
  if( $search_funding_source_id != "" ) {
    $sql .= "user.DEFAULT_FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID";
    $funding_col = "";
  } else {
    $sql .= "user.DEFAULT_GROUP_ID = :GROUP_ID";
    $funding_col = "<th>Fund Description</th><th>Funding String</th>";
  }
  if( $replace ) {
    $sql .= " FOR UPDATE";
  }

  $find_user_stmt = $dbh->prepare($sql);
  if( $search_funding_source_id != "" ) {
    $find_user_stmt->bindValue(":FUNDING_SOURCE_ID",$search_funding_source_id);
  } else {
    $find_user_stmt->bindValue(":GROUP_ID",$search_user_group_id);
  }
  $find_user_stmt->execute();

  $sql = "
    UPDATE
      user
    SET
      DEFAULT_GROUP_ID = :GROUP_ID,
      DEFAULT_FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID
    WHERE
      USER_ID = :USER_ID
  ";
  if( $replace ) {
    $update_user_stmt = $dbh->prepare($sql);
    $update_user_stmt->bindValue(":GROUP_ID",$replace_user_group_id);
    $update_user_stmt->bindValue(":FUNDING_SOURCE_ID",$replace_funding_source_id);
  }

  echo "<table class='records'><thead><tr><th></th><th>Name</th><th>Email</th>$funding_col</tr></thead>\n";
  echo "<caption>Users with matching default funding source</caption>\n";
  echo "<tbody>\n";
  $users_updated = 0;
  while( ($row=$find_user_stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=edit_user&user_id=" . $row["USER_ID"];
    echo "<td><a href='",htmlescape($url),"' class='icon'><i class='far fa-edit'></i></a></td>";
    echo "<td>",htmlescape($row["LAST_NAME"]),", ",htmlescape($row["FIRST_NAME"]),"</td>";
    echo "<td><a href='mailto:",htmlescape($row["EMAIL"]),"'>",htmlescape($row["EMAIL"]),"</td>";
    if( $funding_col ) {
      echo "<td>",htmlescape($row["FUNDING_DESCRIPTION"]),"</td>";
      echo "<td>",fundingStringHTML($row),"</td>";
    }
    echo "</tr>\n";

    if( $replace ) {
      $before_edit = loadUserFromUserID($row["USER_ID"]);
      $update_user_stmt->bindValue(":USER_ID",$row["USER_ID"]);
      $update_user_stmt->execute();
      $users_updated += 1;
      $after_edit = loadUserFromUserID($row["USER_ID"]);
      auditLogModifyUser($before_edit,$after_edit);
    }
  }
  echo "</tbody></table>\n";

  if( $search_funding_source_id != "" ) {
    $funding_source_sql = "AND funding_source.FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID";
  } else {
    $funding_source_sql = "AND funding_source.GROUP_ID = :GROUP_ID";
  }

  $sql = "
    SELECT
      BILL_ID,
      BATCH_NAME,
      work_order_bill.BILLING_BATCH_ID,
      work_order_bill.WORK_ORDER_ID,
      work_order_bill.MATERIALS_CHARGE,
      work_order_bill.LABOR_CHARGE,
      work_order.WORK_ORDER_NUM,
      work_order.FUNDING_SOURCE_ID as WORK_ORDER_FUNDING_SOURCE_ID,
      user.FIRST_NAME,
      user.LAST_NAME,
      user.USER_ID,
      funding_source.FUNDING_FUND,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_DESCRIPTION
    FROM
      work_order_bill
    JOIN
      billing_batch
    ON
      billing_batch.BILLING_BATCH_ID = work_order_bill.BILLING_BATCH_ID
    JOIN
      work_order
    ON
      work_order.WORK_ORDER_ID = work_order_bill.WORK_ORDER_ID
    JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order_bill.FUNDING_SOURCE_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    WHERE
      work_order_bill.STATUS = ''
      $funding_source_sql
    ORDER BY
      work_order_bill.END_DATE DESC
  ";
  if( $replace ) {
    $sql .= " FOR UPDATE";
  }
  $bill_stmt = $dbh->prepare($sql);
  if( $search_funding_source_id != "" ) {
    $bill_stmt->bindValue(":FUNDING_SOURCE_ID",$search_funding_source_id);
  } else {
    $bill_stmt->bindValue(":GROUP_ID",$search_user_group_id);
  }
  $bill_stmt->execute();

  $sql = "
    UPDATE
      work_order_bill
    SET
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID
    WHERE
      BILL_ID = :BILL_ID
  ";
  if( $replace ) {
    $update_bill_stmt = $dbh->prepare($sql);
    $update_bill_stmt->bindValue(":GROUP_ID",$replace_user_group_id);
    $update_bill_stmt->bindValue(":FUNDING_SOURCE_ID",$replace_funding_source_id);
  }

  echo "<p>&nbsp;</p>\n";

  echo "<table class='records'><thead><tr><th></th><th>Billing Batch</th><th>Work Order</th><th>User</th><th>Charge</th>$funding_col</tr></thead>\n";
  echo "<caption>Matching unprocessed work order bills</caption>\n";
  echo "<tbody>";
  $bills_updated = 0;
  $total_charge = 0;
  while( ($row=$bill_stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=work_order_bill&id=" . $row["BILL_ID"];
    echo "<td><a href='",htmlescape($url),"' class='icon'><i class='far fa-edit'></i></a></td>";
    $url = "?s=billing&id=" . $row["BILLING_BATCH_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["BATCH_NAME"]),"</a></td>";
    $url = "?s=work_order&id=" . $row["WORK_ORDER_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["WORK_ORDER_NUM"]),"</a></td>";
    $url = "?s=edit_user&user_id=" . $row["USER_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["LAST_NAME"]),", ",htmlescape($row["FIRST_NAME"]),"</a></td>";
    $total_charge += $row["MATERIALS_CHARGE"]+$row["LABOR_CHARGE"];
    echo "<td class='currency'>",htmlescape(sprintf("%.2f",$row["MATERIALS_CHARGE"]+$row["LABOR_CHARGE"])),"</td>";
    if( $funding_col ) {
      echo "<td>",htmlescape($row["FUNDING_DESCRIPTION"]),"</td>";
      echo "<td>",fundingStringHTML($row),"</td>";
    }
    echo "</tr>\n";
    if( $replace ) {
      $before_edit = loadWorkOrderBill($row["BILL_ID"]);
      $update_bill_stmt->bindValue(":BILL_ID",$row["BILL_ID"]);
      $update_bill_stmt->execute();
      $bills_updated += 1;
      $after_edit = loadWorkOrderBill($row["BILL_ID"]);
      auditlogModifyWorkOrderBill($web_user,$before_edit,$after_edit);
    }
  }
  echo "</tbody>";
  echo "<tbody><tr><td></td><td></td><td></td><td><strong>Total</strong></td><td>",sprintf("%.2f",$total_charge),"</td></tr></tbody>\n";
  echo "</table>\n";

  $sql = "
    SELECT
      work_order.WORK_ORDER_ID,
      work_order.WORK_ORDER_NUM,
      work_order.FUNDING_SOURCE_ID as WORK_ORDER_FUNDING_SOURCE_ID,
      user.FIRST_NAME,
      user.LAST_NAME,
      user.USER_ID,
      funding_source.FUNDING_FUND,
      funding_source.FUNDING_PROJECT,
      funding_source.FUNDING_DEPT,
      funding_source.FUNDING_PROGRAM,
      funding_source.FUNDING_DESCRIPTION
    FROM
      work_order
    JOIN
      funding_source
    ON
      funding_source.FUNDING_SOURCE_ID = work_order.FUNDING_SOURCE_ID
    LEFT JOIN
      user
    ON
      user.USER_ID = work_order.ORDERED_BY
    WHERE
      TRUE
      $funding_source_sql
    ORDER BY
      work_order.CREATED DESC
  ";
  if( $replace ) {
    $sql .= " FOR UPDATE";
  }
  $wo_stmt = $dbh->prepare($sql);
  if( $search_funding_source_id != "" ) {
    $wo_stmt->bindValue(":FUNDING_SOURCE_ID",$search_funding_source_id);
  } else {
    $wo_stmt->bindValue(":GROUP_ID",$search_user_group_id);
  }
  $wo_stmt->execute();

  $sql = "
    UPDATE
      work_order
    SET
      GROUP_ID = :GROUP_ID,
      FUNDING_SOURCE_ID = :FUNDING_SOURCE_ID
    WHERE
      WORK_ORDER_ID = :WORK_ORDER_ID
  ";
  if( $replace ) {
    $update_wo_stmt = $dbh->prepare($sql);
    $update_wo_stmt->bindValue(":GROUP_ID",$replace_user_group_id);
    $update_wo_stmt->bindValue(":FUNDING_SOURCE_ID",$replace_funding_source_id);
  }

  echo "<p>&nbsp;</p>\n";

  echo "<table class='records'><thead><tr><th>Work Order</th><th>User</th>$funding_col</tr></thead>\n";
  echo "<caption>Matching work orders</caption>\n";
  echo "<tbody>";
  $work_orders_updated = 0;
  while( ($row=$wo_stmt->fetch()) ) {
    echo "<tr class='record'>";
    $url = "?s=work_order&id=" . $row["WORK_ORDER_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["WORK_ORDER_NUM"]),"</a></td>";
    $url = "?s=edit_user&user_id=" . $row["USER_ID"];
    echo "<td><a href='",htmlescape($url),"'>",htmlescape($row["LAST_NAME"]),", ",htmlescape($row["FIRST_NAME"]),"</a></td>";
    if( $funding_col ) {
      echo "<td>",htmlescape($row["FUNDING_DESCRIPTION"]),"</td>";
      echo "<td>",fundingStringHTML($row),"</td>";
    }
    echo "</tr>\n";

    if( $replace ) {
      $before_edit = loadWorkOrder($row["WORK_ORDER_ID"]);
      $update_wo_stmt->bindValue(":WORK_ORDER_ID",$row["WORK_ORDER_ID"]);
      $update_wo_stmt->execute();
      $work_orders_updated += 1;
      $after_edit = loadWorkOrder($row["WORK_ORDER_ID"]);
      auditlogModifyWorkOrder($web_user,$before_edit,$after_edit);
    }
  }
  echo "</tbody></table>\n";

  echo "<p>&nbsp;</p>\n";

  if( $replace ) {
    $dbh->commit();
    echo "<script>document.getElementById('replace_status').innerHTML = '<p class=\"alert-success\">Updated $users_updated users, $bills_updated bills, and $work_orders_updated work orders.</p>';</script>\n";
  }
}
