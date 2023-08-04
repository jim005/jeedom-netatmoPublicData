<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}

$npd_jeedom_id = crypt(jeedom::getApiKey('netatmoPublicData'), "OnExposePasCetteInfoInterne");
$npd_connection_method = config::byKey('npd_connection_method', 'netatmoPublicData', 'ownApp');

?>
<form class="form-horizontal">
    <fieldset>

        <div class="row">

            <?php

            $npd_access_token = config::byKey('npd_access_token', 'netatmoPublicData');
            $npdStatus = !empty($npd_access_token) ? true : false;


            ?>

            <form class="form-horizontal">
                <fieldset>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="npd_client_id">{{Statut}}</label>
                        <div class="col-sm-2">
                            <?php
                            if (!$npdStatus) {
                                echo ' <span class="label label-danger">NOK</span>';
                            } else {
                                echo '<span class="label label-success">OK</span>';
                            }
                            ?></div>

                        <?php if ($npdStatus) { ?>
                            <label class="col-sm-2 control-label">{{Méthode}}</label>
                            <div class="col-sm-2"> <?= $npd_connection_method ?></div>

                            <label class="col-sm-2 control-label">{{Action}}</label>
                            <div class="col-sm-2"><a class="btn btn-danger btn-xs" id="npd_connection_reset"> <i
                                            class="fas fa-times"></i> Débrancher</a></div>
                        <?php } ?>
                    </div>

                    <br/>

                    <?php if (!$npdStatus) { ?>
                        <div>

                            <ul class="nav nav-tabs" role="tablist">
                                <li role="presentation"
                                    class="<?= ($npd_connection_method === "hostedApp") ? "active" : "" ?>"><a
                                            href="#npd_hosted_app" role="tab" data-toggle="tab"
                                            style="border-bottom-width: 4px !important;">{{Utilise l'application
                                        hébergée}}
                                        (simple)
                                        (BETA)</a></li>
                                <li role="presentation"
                                    class="<?= ($npd_connection_method === "ownApp") ? "active" : "" ?>"><a
                                            href="#npd_own_app" role="tab" data-toggle="tab"
                                            style="border-bottom-width: 4px !important;">{{Utilise ton application}}
                                        (expert) </a></li>

                            </ul>

                            <div class="tab-content" style="height: unset !important; padding-top: 2em;">

                                <div role="tabpanel"
                                     class="tab-pane <?= ($npd_connection_method === "hostedApp") ? "active" : "" ?>"
                                     id="npd_hosted_app">

                                    <div class="form-group">
                                        <label class="col-md-3 control-label"></label>
                                        <div class="col-md-6">
                                            <a class="btn btn-success form-control npd_btn_association"
                                               data-npd-connection-method="hostedApp"><i
                                                        class="fas fa-link"></i>
                                                {{J'autorise l'application à l'accès mes stations favorites Netatmo}}
                                                <img
                                                        src="/plugins/netatmoPublicData/plugin_info/netatmoPublicData_icon.svg"
                                                        alt="Logo du plugin netatmoOpenData" style="width: 20px;">
                                            </a>
                                        </div>
                                    </div>

                                </div>

                                <div role="tabpanel"
                                     class="tab-pane <?= ($npd_connection_method === "ownApp") ? "active" : "" ?>"
                                     id="npd_own_app">

                                    <?php
                                    //@@todo : move to the top
                                    if (!filter_var(network::getNetworkAccess('external'), FILTER_VALIDATE_URL)) {
                                        echo 'L\'accès externe Jeedom est requis. Il est non défini ou invalide';
                                    } else {
                                        $netatmoAuthorizationUrl = network::getNetworkAccess('external') . '/plugins/netatmoPublicData/core/php/AuthorizationCodeGrant.php';
                                        ?>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label" for="npd_client_id"> {{Client
                                                ID}}</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="configKey form-control"
                                                       data-l1key="npd_client_id"
                                                       id="npd_client_id"
                                                       placeholder="" autocomplete="off">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-sm-3  control-label" for="npd_client_secret">{{Client
                                                secret}}</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="configKey form-control"
                                                       data-l1key="npd_client_secret"
                                                       id="npd_client_secret" placeholder="" autocomplete="off">
                                            </div>
                                        </div>


                                        <div class="form-group">
                                            <label class="col-md-3 control-label"></label>
                                            <div class="col-md-6">
                                                <a class="btn btn-success form-control npd_btn_association"
                                                   data-npd-connection-method="ownApp"><i
                                                            class="fas fa-link"></i>{{Association Netatmo}}</a>
                                            </div>
                                        </div>
                                    <?php } ?>


                                </div>
                            </div>

                        </div>

                    <?php } ?>


                    <?php if ($npdStatus) { ?>
                        <fieldset>
                            <legend>
                                <i class="fas fa-stream"></i> Options
                            </legend>

                            <div class=" form-group">
                                <div class="col-sm-1">
                                    <input type="checkbox" class="configKey form-control"
                                           id="npd_log_error_weather_station"
                                           data-l1key="npd_log_error_weather_station">
                                </div>
                                <label class="col-sm-11 control-label" for="npd_log_error_weather_station"
                                       style="text-align: left;">{{Désactiver les messages d'alertes lorsqu'une station
                                    est indisponible}}</label>
                            </div>


                        </fieldset>
                    <?php } ?>

                    <br/>
                    <br/>
                    <br/>
                    <br/>
                    <br/>

                    <fieldset>
                        <legend>
                            <i class="fas fa-bug"></i> Informations de connexion (debug)
                        </legend>

                        <div class="form-group">
                            <label class="col-sm-3 control-label"> {{npd_access_token}}</label>
                            <div class="col-sm-6">
                                <?= config::byKey('npd_access_token', 'netatmoPublicData'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label"> {{npd_refresh_token}}</label>
                            <div class="col-sm-6">
                                <?= config::byKey('npd_refresh_token', 'netatmoPublicData'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{npd_connection_method}}</label>
                            <div class="col-sm-6">
                                <?= config::byKey('npd_connection_method', 'netatmoPublicData'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{npd_expires_at}}</label>
                            <div class="col-sm-6">
                                <?= config::byKey('npd_expires_at', 'netatmoPublicData'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{An Jeedom ID Crypted (for hosted app)}}</label>
                            <div class="col-sm-6">
                                <?php echo $npd_jeedom_id; ?>
                            </div>
                        </div>


                    </fieldset>

        </div>

    </fieldset>
</form>

<script>

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
            let netatmoAuthorizationUrl = '<?= $netatmoAuthorizationUrl ?>';
            window.open(netatmoAuthorizationUrl, "_blank");


        } else {
            // hostedApp

            // Redirect to Netatmo Authorization URL
            window.open("https://gateway.websenso.net/flux/netatmo/AuthorizationCodeGrant.php?jeedom_id=<?php echo $npd_jeedom_id; ?>", "_blank");
            setTimeout(npdGetTokensAppHosted, 5000);

        }

    });


    getTokenTry = 1;
    getTokenTryTotal = 5;

    function npdGetTokensAppHosted() {

        $.ajax({
            url: "https://gateway.websenso.net/flux/netatmo/getTokens.php?jeedom_id=<?php echo $npd_jeedom_id; ?>",
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
                    message: '{{Tokens recupérés}}',
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


    function npdRemoveTokens() {

        // Remove current tokens
        //@@todo : bug impossible to pass an array.
        // AJAX :           core/js/config.class.js:198
        // PHP Database :   core/class/config.class.php:117
        // configuration: ['npd_access_token', 'npd_refresh_token', 'npd_expires_at', 'npd_connection_method', 'npd_oauth2state'],
        // Topic :  https://community.jeedom.com/t/jeedom-config-remove-avec-un-array-bug/110334

        jeedom.config.remove({
            configuration: 'npd_access_token',
            plugin: "netatmoPublicData",
        });
        jeedom.config.remove({
            configuration: 'npd_refresh_token',
            plugin: "netatmoPublicData",
        });
        jeedom.config.remove({
            configuration: 'npd_expires_at',
            plugin: "netatmoPublicData",
        });
        jeedom.config.remove({
            configuration: 'npd_connection_method',
            plugin: "netatmoPublicData",
        });
        jeedom.config.remove({
            configuration: 'npd_oauth2state',
            plugin: "netatmoPublicData",
        });

        $.fn.showAlert({message: '{{Suppression des tokens existants}}', level: 'success'});

        $('.bt_refreshPluginInfo').trigger('click');

    }

</script>