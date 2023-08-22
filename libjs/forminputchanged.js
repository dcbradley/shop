function formInputChanged(element) {
  var name = element.name.replace("[]","") + "_changed";
  if( !document.getElementById(name) ) {
    var input = document.createElement("input");
    input.type = "hidden";
    input.value = "1";
    input.id = name;
    input.name = name;
    input.className = 'form-input-changed ignore-unsaved-changes';
    element.form.appendChild(input);
  }
}
function clearFormInputChanged(form) {
    for(var i=form.childNodes.length; i--;) {
	var e = form.childNodes[i];
	if( e.tagName == "INPUT" && e.type == "hidden" && e.name.endsWith("_changed") ) {
	    form.removeChild(e);
	}
    }
}
function initFormInputChanged(top,selector) {
  var nodes = top.querySelectorAll(selector);
  for( var i=0; i<nodes.length; i++ ) {
    if( nodes[i].tagname == "FORM" ) {
      initFormInputChanged(nodes[i],"input,select,textarea");
    } else {
      nodes[i].addEventListener("change",function(e) {formInputChanged(e.target);});
    }
  }
}
document.addEventListener("DOMContentLoaded", function(){ initFormInputChanged(document,'.track_form_inputs_changed'); });
