function autocomplete(inp,arr,min_chars,listitem_visible_callback) {
    if( min_chars === undefined ) min_chars=1;
    return autocomplete_matchfunc(inp,arr,autocomplete_match_start,min_chars,listitem_visible_callback);
}
function autocomplete_unordered_words(inp,arr,min_chars, listitem_visible_callback ) {
    if( min_chars === undefined ) min_chars=1;
    return autocomplete_matchfunc(inp,arr,autocomplete_match_unordered_words,min_chars,listitem_visible_callback);
}
function autocomplete_match_start(str1,str2) {
    return str1.substr(0, str2.length).toUpperCase() == str2.toUpperCase();
}
function autocomplete_match_unordered_words(str1,str2) {
    var words1 = str1.split(" ");
    var words2 = str2.split(" ");
    var i1,i2;
    var result = false;
    for(i2=0; i2 < words2.length; i2++) {
        var matched = false;
	if( words2[i2].length == 0 || words2[i2] == ",") continue;
        for(i1=0; i1 < words1.length; i1++) {
	    if( words1[i1].length == 0 || words1[i1] == ",") continue;
            if( autocomplete_match_start(words1[i1],words2[i2]) ) {
                matched = true;
		result = true;
                break;
            }
        }
        if( !matched ) return false;
    }
    return result;
}
function autocomplete_matchfunc(inp, arr, match_func, min_chars, listitem_visible_callback ) {
      /*the autocomplete function takes two arguments,
        the text field element and an array of possible autocompleted values:*/
    var currentFocus;
    if( listitem_visible_callback && typeof IntersectionObserver === 'function' ) {
      var observer = new IntersectionObserver(listitem_visible_callback, {});
    } else {
      var observer = null;
    }
    /*execute a function when someone writes in the text field:*/
    inp.addEventListener("input", function(e) {
        var a, b, i, val = this.value;
        /*close any already open lists of autocompleted values*/
        closeAllLists();
        if (!val) { return false;}
        if( val.length < min_chars ) { return false; }
        currentFocus = -1;
        /*create a DIV element that will contain the items (values):*/
        a = document.createElement("DIV");
        a.setAttribute("id", this.id + "autocomplete-list");
        a.setAttribute("class", "autocomplete-items");
        /*append the DIV element as a child of the autocomplete container:*/
        this.parentNode.appendChild(a);
        /*for each item in the array...*/
        for (i = 0; i < arr.length; i++) {
            /*check if the item starts with the same letters as the text field value:*/
            if ( match_func(arr[i],val) ) {
                /*create a DIV element for each matching element:*/
                b = document.createElement("DIV");
                if( autocomplete_match_start(arr[i],val) ) {
                    /*make the matching letters bold:*/
                    b.innerHTML = "<strong>" + arr[i].substr(0, val.length) + "</strong>";
                    b.innerHTML += arr[i].substr(val.length);
                } else {
                    b.innerHTML = arr[i];
                }
                /*insert a input field that will hold the current array item's value:*/
                b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                /*execute a function when someone clicks on the item value (DIV element):*/
		/*Use mousedown rather than click, so this happens before the change event fires
                  on the input due to it losing focus. This avoids some unexpected behaviors if
                  the change event causes other things to happen.*/
                b.addEventListener("mousedown", function(e) {
                    /*insert the value for the autocomplete text field:*/
                    inp.value = this.getElementsByTagName("input")[0].value;
                                  /*close the list of autocompleted values,
                                    (or any other open lists of autocompleted values:*/
		    var change_event;
		    if(typeof(Event) === 'function') {
			change_event = new Event('change');
		    } else { // for Internet Explorer
			change_event = document.createEvent('Event');
			change_event.initEvent('change', true, true);
		    }
		    inp.dispatchEvent(change_event);
                    closeAllLists();
                });
		if( observer ) {
		    observer.observe(b);
		}
                a.appendChild(b);
            }
        }
    });
    inp.addEventListener("focusin", function(e) {
      var input_event;
      if(typeof(Event) === 'function') {
        input_event = new Event('input');
      } else { // for Internet Explorer
        input_event = document.createEvent('input');
        input_event.initEvent('input', false, false);
      }
      inp.dispatchEvent(input_event);
    });
    /*execute a function presses a key on the keyboard:*/
    inp.addEventListener("keydown", function(e) {
        var x = document.getElementById(this.id + "autocomplete-list");
        if (x) x = x.getElementsByTagName("div");
        if (e.keyCode == 40) {
                    /*If the arrow DOWN key is pressed,
                      increase the currentFocus variable:*/
            currentFocus++;
            /*and and make the current item more visible:*/
            addActive(x);
        } else if (e.keyCode == 38) { //up
                    /*If the arrow UP key is pressed,
                      decrease the currentFocus variable:*/
            currentFocus--;
            /*and and make the current item more visible:*/
            addActive(x);
        } else if (e.keyCode == 13) {
            /*If the ENTER key is pressed, prevent the form from being submitted,*/
            e.preventDefault();
            if (currentFocus > -1) {
                /*and simulate a click on the "active" item:*/
                if (x) x[currentFocus].dispatchEvent(new Event("mousedown"));
            } else {
		closeAllLists();
		if( document.activeElement ) document.activeElement.blur();
	    }
        } else if (e.keyCode == 9) {
            if (currentFocus > -1) {
                /*click the selected item*/
                if (x) x[currentFocus].dispatchEvent(new Event("mousedown"));
            } else {
                closeAllLists();
            }
        }
    });
    function addActive(x) {
        /*a function to classify an item as "active":*/
        if (!x) return false;
        /*start by removing the "active" class on all items:*/
        removeActive(x);
        if (currentFocus >= x.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = (x.length - 1);
        /*add class "autocomplete-active":*/
        x[currentFocus].classList.add("autocomplete-active");
    }
    function removeActive(x) {
        /*a function to remove the "active" class from all autocomplete items:*/
        for (var i = 0; i < x.length; i++) {
            x[i].classList.remove("autocomplete-active");
        }
    }
    function closeAllLists(elmnt) {
            /*close all autocomplete lists in the document,
              except the one passed as an argument
              or the one associated with the input passed
              as an argument:*/
        var x = document.getElementsByClassName("autocomplete-items");
	var elmnt_autocomplete_list_id = elmnt ? elmnt.id + "autocomplete-list" : null;
        for (var i = 0; i < x.length; i++) {
            if (elmnt != x[i] && elmnt != inp && x[i].id !== elmnt_autocomplete_list_id ) {
                x[i].parentNode.removeChild(x[i]);
            }
        }
    }
    /*execute a function when someone clicks in the document:*/
    document.addEventListener("click", function (e) {
        closeAllLists(e.target);
    });
}
