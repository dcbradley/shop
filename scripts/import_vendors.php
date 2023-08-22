<?php

require "db.php";
require "table.php";

$dbh = connectDB();

$table = new Table($dbh,"vendor",array("VENDOR_ID"));
$table->setCSVColsToIgnore(array("CATALOG","CDROM","TAGGED"));

$fname = $argv[1];
#$no_insert = true;
$no_insert = false;
$table->updateFromCSV($fname,$no_insert);

echo "{$table->name}: {$table->records_added} added; {$table->records_updated} updated\n";
