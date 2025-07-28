jQuery(document).ready(function($) {
    $('#payPingDonate_UseCustomStyle').change(function() {
        if ($(this).is(':checked')) {
            $('#payPingDonate_CustomStyleBox').show(500);
        } else {
            $('#payPingDonate_CustomStyleBox').hide(500);
        }
    });
});