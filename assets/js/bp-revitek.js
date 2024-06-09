jQuery(document).ready(function($) {
    $(document).on('click', '.revitek-button', function(e) {
        e.preventDefault();

        var button = $(this);
        var activityId = button.data('activity-id');
        var nonce = button.data('nonce');

        $.ajax({
            url: bpRevitek.ajax_url,
            type: 'POST',
            data: {
                action: 'bp_revitek',
                activity_id: activityId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    button.text('Revitek (' + response.data.count + ')');
                } else {
                    alert('!' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
            }
        });
    });
});
