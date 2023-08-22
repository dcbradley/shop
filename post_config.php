<?php

if( !defined('WEBAPP_TOP') ) {
  define('WEBAPP_TOP','');
}

if( !defined('COOKIE_PATH') ) {
  define('COOKIE_PATH',str_replace("/index.php","/",$_SERVER["PHP_SELF"]));
}

if( !defined('TIMESHEET_SCHEDULE') ) {
  define('TIMESHEET_SCHEDULE',array(array('days' => 'MTWRF')));
}

if( !defined('SHOP_WORKER_COL') ) {
  define('SHOP_WORKER_COL',null);
}

if( !defined('SHOP_TECH_COL') ) {
  define('SHOP_TECH_COL',null);
}

if( !defined('SHOP_ORIENTATED_COL') ) {
  define('SHOP_ORIENTATED_COL');
}

if( !defined('OTHER_SHOPS_IN_WORK_ORDERS') ) {
  define('OTHER_SHOPS_IN_WORK_ORDERS',array());
}

if( !defined('OTHER_SHOPS_IN_CHECKOUT') ) {
  define('OTHER_SHOPS_IN_CHECKOUT',array());
}

if( !defined('NOTIFY_SHOP_ADMIN_OF_NEW_WORK_ORDERS') ) {
  define('NOTIFY_SHOP_ADMIN_OF_NEW_WORK_ORDERS',true);
}

if( !defined('ENABLE_STOCK') ) {
  define('ENABLE_STOCK',false);
}

if( !defined('ENABLE_STUDENT_SHOP_ACCESS_ORDERS') ) {
  define('ENABLE_STUDENT_SHOP_ACCESS_ORDERS',false);
}

if( !defined('SHOP_WORK_ORDER_CHAR') ) {
  define('SHOP_WORK_ORDER_CHAR','');
}

if( !defined('ENABLE_CHECKOUT_BATCH_BILLING') ) {
  define('ENABLE_CHECKOUT_BATCH_BILLING',false);
}

if( !defined('BILLING_JNL_LN_REF') ) {
  define('BILLING_JNL_LN_REF',"NAME");
}

if( !function_exists('isLoanAdmin') ) {
  function isLoanAdmin() {
    return isAdmin();
  }
}

if( !defined('WORKORDER_ATTACHMENT_INSTRUCTIONS') ) {
  define('WORKORDER_ATTACHMENT_INSTRUCTIONS','');
}

if( !defined('DEFAULT_BILLING_STATEMENT_HEADER') ) {
  define('DEFAULT_BILLING_STATEMENT_HEADER',SHOP_NAME . " billing statement for the month indicated.  Please respond within 3 working days if you want to change the funding string.");
}

if( !defined('STORE_BILLING_FILES_SUPPORTED') ) {
  define('STORE_BILLING_FILES_SUPPORTED',True);
}

if( !defined('CREDIT_CARD_URL') ) {
  define('CREDIT_CARD_URL','');
}

if( !defined('LIBJS') ) {
  define('LIBJS',WEBAPP_TOP . "libjs/");
}
