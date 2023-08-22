<?php

function fputcsv_excel($handle,$fields) {
  $next_delim = "";
  $n_written = 0;
  foreach( $fields as $field ) {
    $field = "" . $field;
    if( strcspn($field,"\", \t\n\r") == strlen($field) ) {
      # no quotes or commas in this field
      if( strncmp($field,"0",1)==0 && strncmp($field,"0.",2)!=0 ) {
        # to prevent Excel from dropping leading zeros, write them as: ="0123"
	# but not for floating point numbers such as 0.123
	$rc = fwrite($handle,$next_delim . '="' . $field . '"');
        if( $rc === false ) return false;
        $n_written += $rc;
      } else {
        $rc = fwrite($handle,$next_delim . $field);
        if( $rc === false ) return false;
        $n_written += $rc;
      }
    } else {
      # quote this field
      $rc = fwrite($handle,$next_delim . '"' . str_replace('"','""',$field) . '"');
      if( $rc === false ) return false;
      $n_written += $rc;
    }
    $next_delim = ",";
  }
  $rc = fwrite($handle,"\n");
  if( $rc === false ) return false;
  $n_written += $rc;
  return $n_written;
}

#$F = fopen("php://output","w");
#fputcsv($F,array("abc",123,null,"0123","one,two","the \"quoted\" text","the 'single'","abc def","one\ntwo"));
#fputcsv_excel($F,array("abc",123,null,"0123","one,two","the \"quoted\" text","the 'single'","abc def","one\ntwo"));
