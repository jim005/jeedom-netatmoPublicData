
$("#npd_connection_reset").on('click', function (e) {
    e.preventDefault();

    // Remove current tokens
    npdRemoveTokens();
});



/**
 * Redirect to Netatmo Authorization URL, for callback
 */
$(".npd_btn_association").on('click', function (e) {
    e.preventDefault();
    $.showLoading();

    // Info
    $.fn.showAlert({
        message: '{{Association en cours}}',
        level: 'warning'
    });

    // Remove current tokens
    npdRemoveTokens();

    //ownApp OR hostedApp
    if ($(this).data('npd-connection-method') === "ownApp") {

        // ownApp

        // // Save Client ID and Client ID
        jeedom.config.save({
            configuration: $('#npd_own_app').getValues('.configKey')[0],
            plugin: "netatmoPublicData",
        });


        // Redirect to Netatmo Authorization URL
        window.open(netatmoAuthorizationUrl, "_blank");


    } else {
        // hostedApp

        // Redirect to Netatmo Authorization URL
        window.open("https://gateway.websenso.net/flux/netatmo/AuthorizationCodeGrant.php?jeedom_id=" + jeedom_id, "_blank");
        setTimeout(npdGetTokensAppHosted, 5000);

    }

});


getTokenTry = 1;
getTokenTryTotal = 5;

function npdGetTokensAppHosted() {

    $.ajax({
        url: "https://gateway.websenso.net/flux/netatmo/getTokens.php?jeedom_id=" + jeedom_id,
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            console.log(data);
            if (data.state != 'ok') {
                $.fn.showAlert({
                    message: 'Nouvelle vérification dans 5 secondes (' + getTokenTry + '/' + getTokenTryTotal + ')',
                    level: 'warning'
                });
                if (getTokenTry < getTokenTryTotal) {
                    setTimeout(npdGetTokensAppHosted, 5000);
                    getTokenTry++;
                } else {
                    $.fn.showAlert({
                        message: 'Impossible de récupérer les tokens',
                        level: 'danger'
                    });
                }
                return;
            }

            $.fn.showAlert({
                message: 'Tokens recupérés',
                level: 'success'
            });


            jeedom.config.save({
                configuration: data,
                plugin: "netatmoPublicData",
            });

            jeedom.config.save({
                configuration: {'npd_connection_method': 'hostedApp'},
                plugin: "netatmoPublicData",
            });

            $('.bt_refreshPluginInfo').trigger('click');

            $.hideLoading();

        }
    });
}

/**
 *  Remove Tokens from Jeedom, so 'Associations' action with be available.
 */
function npdRemoveTokens() {

    // Remove current tokens
    jeedom.config.remove({
            configuration: {
                'npd_access_token': null,
                'npd_refresh_token': null,
                'npd_expires_at': null,
                'npd_connection_method': null,
                'npd_oauth2state': null
            },
            plugin: "netatmoPublicData",
        }
    );

    $.fn.showAlert({message: '{{Suppression des tokens existants}}', level: 'success'});

    $('.bt_refreshPluginInfo').trigger('click');

}