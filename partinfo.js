var part_info = {};

function getPartInfo(part_name,callback) {
  $.ajax({ url: "partinfo.php?part=" + encodeURI(part_name), success: function(data) {
    setPartInfo(part_name,data);
    if( callback ) callback(part_info[part_name]);
  }});
}
function setPartInfo(part_name,data) {
  part_info[part_name] = JSON.parse(data);
}
function callWithPartInfo(part_name,callback) {
  if( part_name in part_info ) {
    callback(part_info[part_name]);
  } else {
    getPartInfo(part_name,callback);
  }
}
