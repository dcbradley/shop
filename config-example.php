<?php

const SHOP_NAME = "Example Shop";

const SHOP_FULL_NAME = "Department Example Shop";

const SHOP_LOGO = "uwcrest_web_sm.png";

const SHOP_ADMIN_NAME = "Example Admin";

# The shop admin may be sent email regarding work orders and overdue loans.
const SHOP_ADMIN_EMAIL = "example@wisc.edu";

# Email provided for users needing help with accounts.
#const ACCOUNT_HELP_EMAIL = SHOP_ADMIN_EMAIL;

# Email sent from the app, if any, will come from this address.
const SHOP_FROM_EMAIL = "example@department.wisc.edu";

# Full URL of this app.
const SHOP_URL = "https://example.wisc.edu/shop";

# Login URL
const LOGIN_URL = "https://example.wisc.edu/Shibboleth.sso/Login?isPassive=On&target=";

# Logout URL
const LOGOUT_URL = "https://example.wisc.edu/Shibboleth.sso/Logout?return=https://login.wisc.edu/logout";

# IP address blocks in which to allow group account access (i.e. just entering a group account name and not authenticating)
# Format is the left-most part of the IP address to match.
#const IP_RANGE_TO_ALLOW_UNAUTHENTICATED_LOGINS = array();

# SQL to sort the table of parts in the administrative interface.
const SHOP_PART_ORDER = "ORDER BY CAST(STOCK_NUM AS UNSIGNED)";

# SQL to sort the table of parts browsed by users.
const SHOP_BROWSE_PART_ORDER = "ORDER BY AST(STOCK_NUM AS UNSIGNED)";

# Should work orders ask whether the work involves health hazards.
const HEALTH_HAZARD_QUESTION = False;

# This is currently used when setting login state (e.g. group login or impersonation).
# To make that state work seamlessly across all the shops in a domain, use the root path.
const COOKIE_PATH = "/";

# Display the tool borrowing form.
const SHOP_SUPPORTS_LOANS = True;

# Return true if the web user should be allowed to administer loans.  Default is isAdmin().
#function isLoanAdmin() {
#  return isAdmin() || isShopWorker();
#}

# Provide location fields in the admin and user interface.
const ENABLE_PART_LOCATION = False;

# Allow admin to keep track of a quantity of each part that is not on the shelf.
const ENABLE_BACKUP_QTY = False;

# Provide a field for the admin to record the manufacturer of each part.
const ENABLE_MANUFACTURER = False;

const SHOW_MANUFACTURER_IN_PARTS_TABLE = False;

# Provide a field for the admin to record the manufacturer part number.
const SHOW_MAN_NUM_COL = False;

# Show the minimum quantity in the parts table.
const SHOW_MIN_QTY_COL = False;

# A short uppercase string identifying this shop and suitable for use in database column names.
# If multiple shops are sharing the same user table, this may be useful for
# differentiating some of the columns in the user record.
const SHOP_IDENTIFIER = "SHOP";

# Column in the user table that is used to record whether the user is an admin or not.
# This is configurable in case you wish multiple shops to share the same user table.
# Using the same column name across multiple shops will result in the admins being
# the same across the shops.  Using a different column name will allow the admins to
# be different.
# After changing this, running update_database_schema.php will add a new column by
# the specified name.  If you instead wish to rename an existing column, you will need
# to do that yourself.
const SHOP_ADMIN_COL = SHOP_IDENTIFIER . "_ADMIN";

# Column in the user table that is used to record whether the user is a shop worker or not.
# Shop workers have most of the same powers as the shop admin when viewing work orders.
# If you do not wish to have shop workers, this can be set to null.
# This is configurable in case you wish multiple shops to share the same user table.
# Using the same column name across multiple shops will result in the admins being
# the same across the shops.  Using a different column name will allow the admins to
# be different.
# After changing this, running update_database_schema.php will add a new column by
# the specified name.  If you instead wish to rename an existing column, you will need
# to do that yourself.
const SHOP_WORKER_COL = SHOP_IDENTIFIER . "_WORKER";

# Column in the user table that is used to record the time when the user last logged in.
# After changing this, running update_database_schema.php will add a new column by
# the specified name.  If you instead wish to rename an existing column, you will need
# to do that yourself.
const SHOP_LAST_LOGIN_COL = SHOP_IDENTIFIER . '_LAST_LOGIN';

# Column in the user table that is used to record that the user was added via this shop.
# After changing this, running update_database_schema.php will add a new column by
# the specified name.  If you instead wish to rename an existing column, you will need
# to do that yourself.
const SHOP_USER_CREATED_COL = 'ADDED_VIA_' . SHOP_IDENTIFIER;

# If true, the status column in the parts table will indicate whether a photo has been provided for the part.
const NO_PHOTO_STATUS = False;

# If true, the parts table will contain a column to display the image for each part, if any.
const SHOW_IMAGE_COL = True;

# If true, inactive parts are shown crossed out in the admin parts table.
# Otherwise, they are only shown if one views the inactive parts table.
const SHOW_INACTIVE_PARTS_CROSSED_OUT = true;

# If true, provide administrative controls for indicating that the quantity in stock has been verified.
const ENABLE_QTY_CORRECT = True;

# Percent of cost added to the price for parts using the 'standard' markup type.
# Changing this will not cause all existing part prices that use the standard markup to be recalculated.
const STANDARD_MARKUP_PCT = 0;

# for new orders, default to same price markup type as last order
const NEW_ORDERS_PRESERVE_MARKUP_TYPE = True;

# when creating a new order for a part, this is the default price markup type to use
const NEW_ORDER_MARKUP_TYPE = NO_MARKUP_CODE;

# HTML to display on shop login screen.
# A QR code could be displayed to make it easy for people to use the app from their own device.
#const SHOP_LOGIN_NOTICE = "<p>You may also use this web app on your own device:<br><img src='shop_qr.png'/></p>";

# HTML to display on the workorder login screen.
# A QR code could be displayed to make it easy for people to use the app from their own device.
const SHOP_WORKORDER_LOGIN_NOTICE = "";

# HTML to display when people borrow a tool, perhaps reminding them of the procedure for returning things.
const SHOP_LOAN_NOTICE = "";

# Heading for funding sources imported from a master list (e.g. scripts/import_from_purchasing_db.php)
#const MAIN_FUND_GROUPS_LABEL = "Main Physics Groups/Areas";

# key = manifest group, value = department name
# The manifest group must have its Entity ID set appropriately to make it accessible to the web server.
const IS_MEMBER_OF_DEPARTMENTS = array(
  #"uw:domain:physics.wisc.edu:physics_employees" => "Physics",
);

const SHOP_DEPARTMENTS = array(
  #"Physics",
);

# Enable stock orders in the work order interface.
const ENABLE_STOCK = false;

# Additional instructions for file attachments in work orders.
const WORKORDER_ATTACHMENT_INSTRUCTIONS = "";

# List of shops that can be used as a source of parts in a work order.
# The shop's checkout table must contain a column to hold this shop's work order id (CHECKOUT_WORK_ORDER_ID_COL).
#const OTHER_SHOPS_IN_WORK_ORDERS = array(
#  'Example Shop' => array(
#    'CHECKOUT_TABLE' => 'example_db.checkout',
#    'PART_TABLE' => 'example_db.part',
#    'CHECKOUT_WORK_ORDER_ID_COL' => 'EXAMPLE_SHOP_WORK_ORDER_ID',
#    'CHECKOUT_URL' => '/shop/',
#  ),
#);

# The mirror of the above.  List of shops that may include parts from this shop in their work orders.
#const OTHER_SHOPS_IN_CHECKOUT = array(
#  'Example Shop' => array(
#    'CHECKOUT_WORK_ORDER_ID_COL' => 'EXAMPLE_SHOP_WORK_ORDER_ID',
#    'WORK_ORDER_TABLE' => 'example_db.work_order',
#    'WORK_ORDER_URL' => '/shop/workorder/?s=work_order',
#  ),
#);

# Billing statements, if any, will be sent from this address.
const BILLING_EMAIL = "billing@example.wisc.edu";

const BILLING_EMAIL_NAME = "Example Department";

# URL where people can go to pay amounts billed to credit card.
#const CREDIT_CARD_URL = "https://charge.wisc.edu/example/invoice.aspx";

# When the billing process is run, create billing records for parts purchased from the shop.
const ENABLE_CHECKOUT_BATCH_BILLING = true;

# Name to use in the billing transfer spreadsheet.
const BILLING_TRANSFER_SHOP_NAME = "TRF EXAMPLE SHOP";

# What to show in the billing transfer file reference column.
# Choices are NAME (i.e. person who made order) or WORK_ORDER_NUM.
const BILLING_JNL_LN_REF = "NAME";

# Return the account number to list in the billing transfer spreadsheet for each billing record.
function BILLING_ACCOUNT($bill) {
  # Example: if there is an inventory num, use one account; if not use another.
  return $bill["INVENTORY_NUM"] ? "example1" : "example2";
}

# In the billing transfer spreadsheet, set the values in various columns of the line specifying the destination account.
# See https://wwwtest.wisdm2.doit.wisc.edu/jet-qa/Help/Help.htm
function UPDATE_BILLING_DEST_ACCOUNT_LINE(&$row) {
  $row[0] = 'UDDS-HERE';
  $row[1] = '136';
  $row[2] = '4';
  $row[5] = 'PROJECT-HERE';
}

const NOTIFY_SHOP_ADMIN_OF_NEW_WORK_ORDERS = false;

# Character to prepend to work order numbers issued from this shop.
const SHOP_WORK_ORDER_CHAR = "";

# Return the shop contract id to use by default in work orders, if any.
function DEFAULT_SHOP_CONTRACT($funding_source) {
}
