<?php

class Translation {
  public $from_colname;
  public $to_colname;
  public $stmt;
}

class Table {
  public $records_added = 0;
  public $records_updated = 0;
  public $records_failed = 0;
  public $records_deleted = 0;
  public $name;

  protected $col_name;
  protected $col_index;
  protected $primary_key;
  protected $primary_key_where_sql;
  protected $primary_key_set_sql;
  protected $non_primary_key_set_sql;
  protected $db_col_info;
  protected $csv_cl_name_from_canon;
  protected $translate_from_col; # Translation indexed by canonColName(from_colname)
  protected $translate_to_col;   # array of Translations indexed by canonColName(to_colname)
  protected $update_record_hook;
  protected $track_touched_records;
  protected $records_touched;
  protected $csv_cols_to_ignore;
  protected $conversion_funcs;
  protected $computed_cols;

  function __construct($dbh,$table_name,$primary_key) {
    $this->dbh = $dbh;
    $this->name = $table_name;
    $this->db_col_info = $this->getDBColInfo();

    $this->primary_key = array();
    if( $primary_key ) foreach( $primary_key as $key ) {
      $db_key = $this->formatColNameForDB($key);
      $this->primary_key[] = $db_key;
    }

    $this->primary_key_where_sql = "";
    $this->primary_key_set_sql = "";
    foreach( $this->primary_key as $key ) {
      if( $this->primary_key_where_sql ) $this->primary_key_where_sql .= " AND ";
      $this->primary_key_where_sql .= "$key = :$key";

      if( $this->primary_key_set_sql ) $this->primary_key_set_sql .= ", ";
      $this->primary_key_set_sql .= "$key = :$key";
    }

    $this->translate_from_col = array();
    $this->translate_to_col = array();

    $this->track_touched_records = false;
    $this->records_touched = array();

    $this->csv_cols_to_ignore = array();
    $this->conversion_funcs = array();
    $this->computed_cols = array();
  }
  function setCSVColsToIgnore($cols) {
    $this->csv_cols_to_ignore = array();
    foreach( $cols as $col ) {
      $this->csv_cols_to_ignore[$this->canonColName($col)] = $col;
    }
  }
  function setConversionFunc($colname,$func) {
    $this->conversion_funcs[$this->canonColName($colname)] = $func;
  }
  function getConvertedValue($canon_colname,$val) {
    if( array_key_exists($canon_colname,$this->conversion_funcs) ) {
      return $this->conversion_funcs[$canon_colname]($val);
    }
    return $val;
  }
  function setComputedCol($colname,$func) {
    $this->computed_cols[$this->canonColName($colname)] = $func;
  }
  function computeCol($canon_colname,$row,&$value) {
    if( array_key_exists($canon_colname,$this->computed_cols) ) {
      $value = $this->computed_cols[$canon_colname]($row,$canon_colname,$this);
      return true;
    }
    return false;
  }
  function setUpdateRecordHook($fn) {
    $this->update_record_hook = $fn;
  }
  function setKeyLookupQuery($from_colname,$to_colname,$sql) {
    $translation = new Translation;
    $translation->from_colname = $from_colname;
    $translation->to_colname = $to_colname;
    $translation->stmt = $this->dbh->prepare($sql);

    $canon_from_colname = $this->canonColName($from_colname);
    $this->translate_from_col[$canon_from_colname] = $translation;

    $canon_to_colname = $this->canonColName($to_colname);
    if( !array_key_exists($canon_to_colname,$this->translate_to_col) ) {
      $this->translate_to_col[$canon_to_colname] = array();
    }
    $this->translate_to_col[$canon_to_colname][] = $translation;
  }
  function getStmtColInfo($stmt) {
    $cols = array();
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
      $col = $stmt->getColumnMeta($i);
      $colname = $col['name'];
      $cols[$this->canonColName($colname)] = $col;
    }
    return $cols;
  }
  function getDBColInfo() {
    $stmt = $this->dbh->query("SELECT * FROM {$this->name} LIMIT 0");
    return $this->getStmtColInfo($stmt);
  }
  function canonColName($colname) {
    $col = preg_replace("|[ _-]|","",strtoupper($colname));
    $col = str_replace("%","PCT",$col);
    $col = str_replace("&","AND",$col);
    return $col;
  }
  function formatColNameForDB($colname) {
    # Match colname to corresponding column in the database.
    # Handle differences such as the folowing:
    #  "dIfferentCASE" -> "DifferentCase"
    #  "two words" -> "TWO_WORDS"
    #  "one word" -> "OneWord"
    #  "one_word" -> "OneWord"

    $colname = preg_replace("| |","_",$colname);

    $canon_colname = $this->canonColName($colname);
    if( array_key_exists($canon_colname,$this->db_col_info) ) {
      $colname = $this->db_col_info[$canon_colname]['name'];
    }
    return $colname;
  }
  function formatColNameForCSV($colname) {
    # Match colname to corresponding column in the CSV.

    $canon_colname = $this->canonColName($colname);
    if( array_key_exists($canon_colname,$this->csv_col_name_from_canon) ) {
      $colname = $this->csv_col_name_from_canon[$canon_colname];
    }
    return $colname;
  }
  function digestHeader($row) {
    $this->col_name = array();
    foreach( $row as $header ) {
      $header = $this->formatColNameForDB($header);
      $this->col_name[] = $header;
      $this->col_index[$header] = count($this->col_name)-1;
      $this->csv_col_name_from_canon[$this->canonColName($header)] = $header;
    }

    foreach( $this->computed_cols as $header => $func ) {
      $header = $this->formatColNameForDB($header);
      if( in_array($header,$this->col_name) ) continue;
      $this->col_name[] = $header;
      $this->csv_col_name_from_canon[$this->canonColName($header)] = $header;
    }

    $this->non_primary_key_set_sql = "";
    foreach( $this->col_name as $col ) {
      $canon_col = $this->canonColName($col);
      if( in_array($col,$this->primary_key) ) continue;
      if( array_key_exists($canon_col,$this->csv_cols_to_ignore) ) continue;
      if( $this->non_primary_key_set_sql ) $this->non_primary_key_set_sql .= ", ";
      $this->non_primary_key_set_sql .= "$col = :$col";
    }
  }
  function getValueFromCSV($colname,$row) {
    $csv_colname = $this->formatColNameForDB($colname);
    return $row[$this->col_index[$csv_colname]];
  }
  function bindPrimaryKey(&$stmt,&$row,&$keys_touched=null) {
    foreach( $this->primary_key as $key ) {
      $canon_key = $this->canonColName($key);
      $value = null;
      $got_value = false;
      if( $this->computeCol($canon_key,$row,$value) ) {
        $got_value = true;
      } else if( array_key_exists($key,$this->col_index) ) {
        $value = $row[$this->col_index[$key]];
	$got_value = true;
      }

      if( $got_value ) {
	$value = $this->getConvertedValue($canon_key,$value);
        $stmt->bindValue(":" . $key,$value);
	if( isset($keys_touched) ) $keys_touched[$key] = $value;
      }
      else if( array_key_exists($canon_key,$this->translate_to_col) ) {
        if( !$this->bindPrimaryKeyFromTranslation($stmt,$row,$key,$canon_key,$keys_touched) ) return false;
      }
      else {
        fprintf(STDERR,"Did not find key column $key in input data.\n");
        return false;
      }
    }
    return true;
  }
  function bindPrimaryKeyFromDBRow(&$stmt,&$row,&$keys_touched=null) {
    foreach( $this->primary_key as $key ) {
      $db_key = $this->formatColNameForDB($key);
      if( array_key_exists($db_key,$row) ) {
        $value = $row[$db_key];
        $stmt->bindValue(":" . $key,$value);
	if( isset($keys_touched) ) $keys_touched[$key] = $value;
      }
      else {
        fprintf(STDERR,"Did not find key column $key in DB row.\n");
        return false;
      }
    }
    return true;
  }
  function bindPrimaryKeyFromTranslation(&$stmt,&$row,$key,$canon_key,&$keys_touched) {
    $found_key = false;
    $errors = array();
    foreach( $this->translate_to_col[$canon_key] as $translation ) {
      $from_colname = $this->formatColNameForCSV($translation->from_colname);
      if( !array_key_exists($from_colname,$this->col_index) ) continue;
      $from_val = $row[$this->col_index[$from_colname]];
      $translation->stmt->bindValue(":" . $translation->from_colname,$from_val);
      $translation->stmt->execute();
      $xlate_row = $translation->stmt->fetch();
      if( $xlate_row ) {
        $value = $xlate_row[0];
        $stmt->bindValue(":" . $translation->to_colname,$value);
	if( isset($keys_touched) ) $keys_touched[$translation->to_colname] = $value;
        $found_key = true;
        break;
      } else {
        $errors[] = "Failed to look up {$translation->to_colname} from {$translation->from_colname} {$from_val}";
      }
    }
    if( !$found_key ) {
      foreach( $errors as $error ) {
        fprintf(STDERR,$error."\n");
      }
      if( !count($errors) ) {
        fprintf(STDERR,"Failed to look up $key\n");
      }
      return false;
    }
    return true;
  }
  function colCanBeNULL($col) {
    $canon_col = $this->canonColName($col);
    if( array_key_exists($canon_col,$this->db_col_info) ) {
      if( !in_array("not_null",$this->db_col_info[$canon_col]['flags']) ) {
        return true;
      }
    }
    return false;
  }
  function bindNonPrimaryKey(&$stmt,&$row) {
    foreach( $this->col_name as $col ) {
      $canon_col = $this->canonColName($col);
      if( in_array($col,$this->primary_key) ) continue;
      if( array_key_exists($canon_col,$this->csv_cols_to_ignore) ) continue;
      $value = null;
      if( !$this->computeCol($canon_col,$row,$value) ) {
        $value = $row[$this->col_index[$col]];
      }
      $value = $this->getConvertedValue($canon_col,$value);
      if( ($value === null || $value === "") && $this->colCanBeNULL($canon_col) ) {
        $stmt->bindValue(":" . $col,null);
      } else {
        $stmt->bindValue(":" . $col,$value);
      }
    }
  }
  function describePrimaryKey(&$row) {
    $desc = "";
    foreach( $this->primary_key as $key ) {
      $canon_key = $this->canonColName($key);
      if( array_key_exists($canon_key,$this->translate_to_col) ) {
        foreach( $this->translate_to_col[$canon_key] as $translation ) {
          $from_colname = $this->formatColNameForCSV($translation->from_colname);
          if( !array_key_exists($from_colname,$this->col_index) ) continue;
          $from_val = $row[$this->col_index[$from_colname]];
          if( $desc ) $desc .= ", ";
	  $desc .= $from_colname . " = " . $from_val;
        }
      } else {
        if( $desc ) $desc .= ", ";
        $desc .= $key . " = " . $row[$this->col_index[$key]];
      }
    }
    return $desc;
  }

  # Call this before updating records.  After all records have been
  # updated, call removeUntouchedRecords() to clean up any records
  # that were not in the update.
  function trackTouchedRecords() {
    $this->track_touched_records = true;
  }

  # Remove all records that did not appear in the update with primary
  # keys matching the subset of the primary key columns in update_batch_keys
  # that matches records that were touched.
  function removeUntouchedRecords($update_batch_keys) {
    $batch_key_sql = "";
    if( !$update_batch_keys || count($update_batch_keys)==0 ) {
      $batch_key_sql = "TRUE";
    }
    else foreach( $update_batch_keys as $key ) {
      $key = $this->formatColNameForDB($key);
      if( $batch_key_sql ) $batch_key_sql .= " AND ";
        $batch_key_sql .= $key . " = :" . $key;
    }
    $record_select_sql = "";
    foreach( $this->primary_key as $key ) {
      if( $record_select_sql ) $record_select_sql .= ", ";
      $record_select_sql .= $key;
    }
    $sql = "SELECT {$record_select_sql} FROM {$this->name} WHERE {$batch_key_sql}";
    $select_stmt = $this->dbh->prepare($sql);

    $sql = "DELETE FROM {$this->name} WHERE {$this->primary_key_where_sql}";
    $delete_stmt = $this->dbh->prepare($sql);

    $this->removeUntouchedRecordsRecurs($update_batch_keys,0,$select_stmt,$delete_stmt,$this->records_touched);
  }

  function removeUntouchedRecordsRecurs($update_batch_keys,$key_idx,&$select_stmt,&$delete_stmt,$records_touched) {
    if( $key_idx < count($update_batch_keys) ) {
      $key = $this->formatColNameForDB($update_batch_keys[$key_idx]);
      if( $key != $this->primary_key[$key_idx] ) {
        fprintf(STDERR,"ERROR: removeUntouchedRecords() is expected to be called with a set of keys in the same order as the primary key list.\n");
	fprintf(STDERR,"Got " . print_r($update_batch_keys,true) . " while primary keys are " . print_r($this->primary_key,true) . "\n");
	exit(1);
      }
      foreach( $records_touched as $key_val => $records ) {
        $select_stmt->bindValue($key,$key_val);
	$this->removeUntouchedRecordsRecurs($update_batch_keys,$key_idx+1,$select_stmt,$delete_stmt,$records);
      }
    } else {
      $select_stmt->execute();
      while( ($row=$select_stmt->fetch()) ) {
        if( !$this->recordWasTouched($row) ) {
	  if( $this->bindPrimaryKeyFromDBRow($delete_stmt,$row) ) {
	    $delete_stmt->execute();
            $this->records_deleted += $delete_stmt->rowCount();
	  }
	}
      }
    }
  }

  function touchRecord($keys_touched) {
    $touched = &$this->records_touched;
    foreach( $this->primary_key as $key ) {
      $value = $keys_touched[$key];
      if( !array_key_exists($value,$touched) ) {
        $touched[$value] = array();
      }
      $touched = &$touched[$value];
    }
  }

  function recordWasTouched($row) {
    $touched = &$this->records_touched;
    foreach( $this->primary_key as $key ) {
      $value = $row[$key];
      if( !array_key_exists($value,$touched) ) {
        return false;
      }
      $touched = &$touched[$value];
    }
    return true;
  }

  function updateRecord($row,$no_insert=false) {
    if( count($row) == 0 || count($row) == 1 && !$row[0] ) return;
    if( count($row) < count($this->col_name) - count($this->computed_cols) ) {
      throw new Exception("WRONG NUMBER OF FIELDS ON ROW: ".print_r($row,true));
    }
    if( count($row) > count($this->col_name) ) {
      for($i=count($this->col_name); $i<count($row); $i++) {
        if( $row[$i] != "" ) {
          throw new Exception("EXTRA FIELDS ON ROW: ".print_r($row,true));
	}
      }
    }
    if( $this->update_record_hook ) {
      $fn = $this->update_record_hook;
      $fn($row);
    }
    if( count($this->primary_key) == 0 ) {
      $is_new = true;
    } else {
      $sql = ("SELECT COUNT(*) FROM {$this->name} WHERE {$this->primary_key_where_sql}");
      $stmt = $this->dbh->prepare($sql);
      if( !$this->bindPrimaryKey($stmt,$row) ) {
        $is_new = true;
      }
      else {
        $stmt->execute();
        $count_row = $stmt->fetch();
        $is_new = $count_row[0] == 0;
      }
    }
    if( $is_new && $no_insert ) {
      fprintf(STDERR,"WARNING: no matching record found for " . $this->describePrimaryKey($row) . "\n");
      $this->records_failed += 1;
      return;
    } else if( $is_new ) {
      $comma = $this->primary_key_set_sql ? "," : "";
      $sql = ("INSERT INTO {$this->name} SET {$this->primary_key_set_sql} $comma {$this->non_primary_key_set_sql}");
    } else {
      $sql = ("UPDATE {$this->name} SET {$this->non_primary_key_set_sql} WHERE {$this->primary_key_where_sql}");
    }
    $stmt = $this->dbh->prepare($sql);
    $keys_touched = array();
    $this->bindPrimaryKey($stmt,$row,$keys_touched);
    $this->bindNonPrimaryKey($stmt,$row);
    try {
      $stmt->execute();
    } catch( Exception $e ) {
      fprintf(STDERR,"FAILED ON ROW: ".print_r($row,true)."\n");
      fprintf(STDERR,"SQL: ".$sql."\n");
      throw($e);
    }

    if( $this->track_touched_records ) {
      $this->touchRecord($keys_touched);
    }

    if( $stmt->rowCount() ) {
      if( $is_new ) $this->records_added += 1;
      else $this->records_updated += 1;
    }
  }
  function updateFromCSV($fname,$no_insert=false) {
    $F = fopen($fname,"r");
    $row = fgetcsv($F);

    $this->digestHeader($row);

    while( ($row = fgetcsv($F)) ) {
      $this->updateRecord($row,$no_insert);
    }
  }
  function updateFromArray($data,$no_insert=false) {
    if( count($data) == 0 ) return;

    $this->digestHeader($data[0]);
    for($i=1; $i<count($data); $i++) {
      $this->updateRecord($data[$i],$no_insert);
    }
  }
  function updateFromStmt($stmt,$no_insert=false) {
    $cols = $this->getStmtColInfo($stmt);
    $colnames = array();
    foreach( $cols as $colname => $col ) {
      $colnames[] = $colname;
    }

    $this->digestHeader($colnames);

    while( ($row = $stmt->fetch(PDO::FETCH_NUM)) ) {
      $this->updateRecord($row,$no_insert);
    }
  }
}
