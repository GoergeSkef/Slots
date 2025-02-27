jQuery(document).ready(function ($) {

    // Simple AJAX form for pushing updates to client
    $("#hub-push-form").on("submit", function (e) {
        e.preventDefault();

        var client_url = $(this).find('input[name="client_url"]').val();

        // Call the hub's own endpoint to demonstrate pushing updates to a client
        $.ajax({
            url: window.wpApiSettings.root + 'hub-plugin/v1/slots/push-to-client',
            method: 'POST',
            beforeSend: function (xhr) {
                // If you have a nonce or token, set it here
                xhr.setRequestHeader('X-WP-Nonce', window.wpApiSettings.nonce);
            },
            data: {
                client_url: client_url
            }
        }).done(function (response) {
            $("#hub-push-result").text("Response: " + response.message);
        }).fail(function (err) {
            $("#hub-push-result").text("Error: " + err.statusText);
        });
    });

});
