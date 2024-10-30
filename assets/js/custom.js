jQuery(document).ready(function($) {
    $('#crezco-connect').click(function(){
        var data = {action: 'crezco_connect'}
        jQuery('select[name],input[name]').toArray().forEach(element => {
            data[element.name] = element.value
        });
        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        $.post(ajaxurl, data, function(json) {
            // showAlert(json);
			var response = JSON.parse(json)
			if (response['redirect']) {
				location = response['redirect'];
			}
        });
    });

    $('#crezco-disconnect').click(function() {
        var data = {action: 'crezco_disconnect'}
        jQuery('select[name],input[name]').toArray().forEach(element => {
            data[element.name] = element.value
        });
        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        $.post(ajaxurl, data, function(json) {
            // showAlert(json);
			location.reload();
        });
    });


});