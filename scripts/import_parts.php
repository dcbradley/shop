<?php

require "db.php";
require "table.php";

function convertDate($val) {
  $parts = explode("/",$val);
  if( count($parts) != 3 ) return $val;
  $month = $parts[0];
  $day = $parts[1];
  $year = (int)$parts[2];
  if( $year < 70 ) {
    $year += 2000;
  } else if( $year < 100 ) {
    $year += 1900;
  }
  return "$year-$month-$day";
}

$dbh = connectDB();

$table = new Table($dbh,"part",array("PART_ID"));
#$table = new Table($dbh,"part",array("STOCK_NUM"));
$table->setCSVColsToIgnore(array("TAG_IT"));
$table->setConversionFunc("CREATED","convertDate");
$table->setConversionFunc("UPDATED","convertDate");

$fname = $argv[1];
#$no_insert = true;
$no_insert = false;
$table->updateFromCSV($fname,$no_insert);

echo "{$table->name}: {$table->records_added} added; {$table->records_updated} updated\n";
