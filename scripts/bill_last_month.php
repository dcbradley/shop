<?php

require_once "db.php";
require_once "common.php";
require_once "config.php";
require_once "post_config.php";
require_once "billing.php";

billLastMonthWorkOrders();
#billLastYearWorkOrders();
