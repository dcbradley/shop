function initClearDateButtons() {
    $('.clear-date-btn').each(function() {
	$(this).click(function () {
	    var field_id = $(this).attr('data-date-field');
	    $('#' + field_id).val('').change();
	    return false;
	});
    });
}
$(document).ready(function() {initClearDateButtons();});
