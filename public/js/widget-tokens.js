/**
 * @file This file contains functions and bindings for the UserFrosting token management pages.
 *
 * @author Johan Cwiklinski
 * @license MIT
 */
 
$(document).ready(function() {
    bindTokenTableButtons($("body"));
});

function bindTokenTableButtons(table) {
    // Link buttons
    $(table).find('.js-token-create').click(function() {
        tokenForm('dialog-token-create');
    });

    $(table).find('.js-token-edit').click(function() {
        var btn = $(this);
        var token_id = btn.data('id');
        tokenForm('dialog-token-edit', token_id);
    });

    $(table).find('.js-token-reset').click(function() {
        var btn = $(this);
        var token_id = btn.data('id');
        tokenResetForm('dialog-token-reset', token_id);
    });

    $(table).find('.js-token-enable').click(function () {
        var btn = $(this);
        var token_id = btn.data('id');
        updateTokenEnabledStatus(token_id, "1")
        .always(function(response) {
            // Reload page after updating token details
            window.location.reload();
        });
    });

    $(table).find('.js-token-disable').click(function () {
        var btn = $(this);
        var token_id = btn.data('id');
        updateTokenEnabledStatus(token_id, "0")
        .always(function(response) {
            // Reload page after updating token details
            window.location.reload();
        });
    });

    $(table).find('.js-token-delete').click(function() {
        var btn = $(this);
        var token_id = btn.data('id');
        var app_name = btn.data('app_name');
        deleteTokenDialog('dialog-token-delete', token_id, app_name);
    });
}

// Enable/disable the specified token
function updateTokenEnabledStatus(token_id, flag_enabled) {
    flag_enabled = typeof flag_enabled !== 'undefined' ? flag_enabled : 1;
    csrf_token = $("meta[name=csrf_token]").attr("content");
    var data = {
        flag_enabled: flag_enabled,
        csrf_token: csrf_token
    };

    var url = site['uri']['public'] + "/tokens/t/" + token_id;

    return $.ajax({
      type: "POST",
      url: url,
      data: data
    });
}

function deleteTokenDialog(box_id, token_id, name){
    // Delete any existing instance of the form with the same name
    if($('#' + box_id).length ) {
        $('#' + box_id).remove();
    }

    var url = site['uri']['public'] + "/forms/confirm";

    var data = {
        box_id: box_id,
        box_title: "Delete token",
        confirm_message: "Are you sure you want to delete the token for " + name + "?",
        confirm_button: "Yes, delete token"
    };

    // Generate the form
    $.ajax({
      type: "GET",
      url: url,
      data: data
    })
    .fail(function(result) {
        // Display errors on failure
        $('#userfrosting-alerts').flashAlerts().done(function() {
        });
    })
    .done(function(result) {
        // Append the form as a modal dialog to the body
        $( "body" ).append(result);
        $('#' + box_id).modal('show');
        $('#' + box_id + ' .js-confirm').click(function(){

            var url = site['uri']['public'] + "/tokens/t/" + token_id + "/delete";

            csrf_token = $("meta[name=csrf_token]").attr("content");
            var data = {
                token_id: token_id,
                csrf_token: csrf_token
            };

            $.ajax({
              type: "POST",
              url: url,
              data: data
            }).done(function(result) {
              // Reload the page
              window.location.reload();
            }).fail(function(jqXHR) {
                if (site['debug'] == true) {
                    document.body.innerHTML = jqXHR.responseText;
                } else {
                    console.log("Error (" + jqXHR.status + "): " + jqXHR.responseText );
                }
                $('#userfrosting-alerts').flashAlerts().done(function() {
                    // Close the dialog
                    $('#' + box_id).modal('hide');
                });
            });
        });
    });
}

/**
 * Display a modal form for updating/creating a token.
 */
function tokenForm(box_id, token_id) {
    token_id = typeof token_id !== 'undefined' ? token_id : "";

    // Delete any existing instance of the form with the same name
    if($('#' + box_id).length ) {
        $('#' + box_id).remove();
    }

    var data = {
        box_id: box_id,
        render: 'modal'
    };

    var url = site['uri']['public'] + "/forms/tokens";

    // If we are updating an existing token
    if (token_id) {
        data = {
            box_id: box_id,
            render: 'modal',
            mode: "update"
        };

        url = site['uri']['public'] + "/forms/tokens/t/" + token_id;
    }

    // Fetch and render the form
    $.ajax({
      type: "GET",
      url: url,
      data: data,
      cache: false
    })
    .fail(function(result) {
        // Display errors on failure
        $('#userfrosting-alerts').flashAlerts().done(function() {
        });
    })
    .done(function(result) {
        // Append the form as a modal dialog to the body
        $( "body" ).append(result);
        $('#' + box_id).modal('show');

        // Initialize select2's
        $('#' + box_id + ' .select2').select2();

        // Initialize bootstrap switches
        var switches = $('#' + box_id + ' .bootstrapswitch');
        switches.data('on-label', '<i class="fa fa-check"></i>');
        switches.data('off-label', '<i class="fa fa-times"></i>');
        switches.bootstrapSwitch();
        switches.bootstrapSwitch('setSizeClass', 'switch-mini' );

        // Initialize primary group buttons
        $(".bootstrapradio").bootstrapradio();

        // Enable/disable primary group buttons when switch is toggled
        switches.on('switch-change', function(event, data){
            var el = data.el;
            var id = el.data('id');
            // Get corresponding primary button
            var primary_button = $('#' + box_id + ' button.bootstrapradio[name="primary_group_id"][value="' + id + '"]');
            // If switch is turned on, enable the corresponding button, otherwise turn off and disable it
            if (data.value) {
                primary_button.bootstrapradio('disabled', false);
            } else {
                primary_button.bootstrapradio('disabled', true);
            }
        });

        // Link submission buttons
        ufFormSubmit(
            $('#' + box_id).find("form"),
            validators,
            $("#form-alerts"),
            function(data, statusText, jqXHR) {
                // Reload the page on success
                window.location.reload(true);
            }
        );
    });
}

/**
 * Display a modal form to confirm token reset
 */
function tokenResetForm(box_id, token_id) {
    // Delete any existing instance of the form with the same name
    if($('#' + box_id).length ) {
        $('#' + box_id).remove();
    }

    var url = site['uri']['public'] + "/forms/confirm";

    var data = {
        box_id: box_id,
        box_title: "Reset token",
        confirm_message: "Are you sure you want to reset the token for " + name + "?",
        confirm_button: "Yes, reset token"
    };

    // Generate the form
    $.ajax({
      type: "GET",
      url: url,
      data: data
    })
    .fail(function(result) {
        // Display errors on failure
        $('#userfrosting-alerts').flashAlerts().done(function() {
        });
    })
    .done(function(result) {
        // Append the form as a modal dialog to the body
        $( "body" ).append(result);
        $('#' + box_id).modal('show');
        $('#' + box_id + ' .js-confirm').click(function(){

            var url = site['uri']['public'] + "/tokens/t/" + token_id + "/reset";

            csrf_token = $("meta[name=csrf_token]").attr("content");
            var data = {
                token_id: token_id,
                csrf_token: csrf_token
            };

            $.ajax({
              type: "POST",
              url: url,
              data: data
            }).done(function(result) {
              // Reload the page
              window.location.reload();
            }).fail(function(jqXHR) {
                if (site['debug'] == true) {
                    document.body.innerHTML = jqXHR.responseText;
                } else {
                    console.log("Error (" + jqXHR.status + "): " + jqXHR.responseText );
                }
                $('#userfrosting-alerts').flashAlerts().done(function() {
                    // Close the dialog
                    $('#' + box_id).modal('hide');
                });
            });
        });
    });
}
