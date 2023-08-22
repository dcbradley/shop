function arrayToEnglishList(a) {
  if( a.length == 0 ) return "";
  if( a.length == 1 ) return a[0];
  var result = a[0];
  for( var i=1; i<a.length-1; i++ ) {
    result += ", " + a[i];
  }
  result += " and " + a[a.length-1];
  return result;
}
