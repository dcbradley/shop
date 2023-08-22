/* Usage: add the class warn_about_unsaved_changes to forms.

   To prevent an input from being tracked, add the class ignore-unsaved-changes to that input.
*/

function getFormSnapshot(form_selector) {
  var form_data = {};
  var form = document.querySelectorAll(form_selector);
  for( var i=0; i<form.length; i++ ) {
    var inputs = form[i].querySelectorAll('input,select,textarea');
    for( var j=0; j<inputs.length; j++ ) {
      var key = inputs[j].name;
      var value = inputs[j].value;
      if( inputs[j].classList.contains('ignore-unsaved-changes') ) continue;
      if( inputs[j].type == 'checkbox' || inputs[j].type == 'radio' ) {
        if( !inputs[j].checked ) continue;
      }
      if( inputs[j].type == 'submit' ) continue;
      if( key.endsWith("[]") ) {
        if( !(key in form_data) ) {
	  form_data[key] = [];
	}
	form_data[key].push(value);
      }
      else {
        form_data[key] = value;
      }
    }
  }
  return form_data;
}
function trackChangesToForm(form_selector) {
  if ( typeof trackChangesToForm.snapshot == 'undefined' ) {
    trackChangesToForm.snapshot = {};
    trackChangesToForm.submitting_form = {};
  }

  trackChangesToForm.submitting_form[form_selector] = 0;
  trackChangesToForm.snapshot[form_selector] = getFormSnapshot(form_selector);

  var form = document.querySelectorAll(form_selector);
  for( var i=0; i<form.length; i++ ) {
    form[i].addEventListener("submit",function(event) {
      if( event.returnValue ) {
        trackChangesToForm.submitting_form[form_selector] = Date.now();
      }
    });
  }
}
function formHasChangedCompare(v1,v2) {
  return JSON.stringify(v1) != JSON.stringify(v2);
}
function formHasChanged(form_selector) {
  var orig_form = trackChangesToForm.snapshot[form_selector];
  var cur_form = getFormSnapshot(form_selector);

  for( const [key,value] of Object.entries(cur_form) ) {
    if( ! (key in orig_form) ) {
      return true;
    }
    if( formHasChangedCompare(value,orig_form[key]) ) {
      return true;
    }
  }
  for( const [key,value] of Object.entries(orig_form) ) {
    if( ! (key in cur_form) ) {
      return true;
    }
    if( formHasChangedCompare(value,cur_form[key]) ) {
      return true;
    }
  }
  return false;
}
function anyTrackedFormHasChanged() {
  for( var form_selector in trackChangesToForm.snapshot ) {
    if( formHasChanged(form_selector) ) {
      return true;
    }
  }
  return false;
}
function warnAboutUnsavedChangesToTrackedForms(e) {
  for( var form_selector in trackChangesToForm.snapshot ) {
    if( Date.now() - trackChangesToForm.submitting_form[form_selector] < 2000 ) {
      // Since submission might have been canceled by a submit handler,
      // ignore submitting_form if it is too old.
      continue;
    }

    if( formHasChanged(form_selector) ) {
      e.preventDefault();
      e.returnValue = 'There are unsaved changes.';
      return;
    }
  }
}

window.addEventListener('beforeunload', warnAboutUnsavedChangesToTrackedForms);

// Use window rather than document DOMContentLoaded, because window
// happens after document.  Therefore, if the form code uses document
// DOMContentLoaded to initialize some form values, we will track
// changes after that.  If the form setup code uses window
// DOMContentLoaded, it must register the event handlers before
// including this file.

window.addEventListener("DOMContentLoaded", function(){ trackChangesToForm('.warn_about_unsaved_changes'); });
