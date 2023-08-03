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
                        <div class="col-sm-4">
                            <?php
                            if (!$npdStatus) {
                                echo ' <span class="label label-danger">NOK</span>';
                            } else {
                                echo '<span class="label label-success">OK</span>';
                            }
                            ?></div>
                        <label class="col-sm-2 control-label">{{Méthode}}</label>
                        <div class="col-sm-4"><span class="configKey" data-l1key="npd_connection_method" >-</span></div>
                    </div>


                    <div>

                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation"
                                class="<?= ($npd_connection_method === "hostedApp") ? "active" : "" ?>"><a
                                        href="#npd_hosted_app" role="tab" data-toggle="tab"
                                        style="border-bottom-width: 4px !important;">{{Utilise l'application hébergée}}
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
                                        <a class="btn btn-success form-control npd_btn_association_apps_netatmo_hosted"><i
                                                    class="fas fa-link"></i>
                                            {{J'autorise l'application à l'accès mes stations favorites Netatmo}} <img
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
                                if (!filter_var(network::getNetworkAccess('external'), FILTER_VALIDATE_URL)) {
                                    echo 'L\'accès externe Jeedom est requis. Il est non défini ou invalide';
                                } else { ?>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label" for="npd_client_id"> {{Client ID}}</label>
                                        <div class="col-sm-6">
                                            <input type="text" class="configKey form-control" data-l1key="npd_client_id"
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
                                            <a class="btn btn-success form-control npd_btn_association_apps_netatmo"><i
                                                        class="fas fa-link"></i>{{Association Netatmo}}</a>
                                        </div>
                                    </div>
                                <?php } ?>


                            </div>
                        </div>

                    </div>


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
                            <label class="col-sm-3 control-label" for="npd_client_id"> {{access_token}}</label>
                            <div class="col-sm-6">
                                <span class="configKey" data-l1key="npd_access_token" id="npd_access_token">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="npd_client_id"> {{refresh_token}}</label>
                            <div class="col-sm-6">
                                <span class="configKey" data-l1key="npd_refresh_token" id="npd_refresh_token">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="npd_connection_method">
                                {{npd_connection_method}}</label>
                            <div class="col-sm-6">
                                <span class="configKey" data-l1key="npd_connection_method"
                                      id="npd_connection_method">-</span>
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="npd_client_id"> {{expires_at}}</label>
                            <div class="col-sm-6">
                                <span class="configKey" data-l1key="npd_expires_at" id="npd_expires_at">-</span> (<a
                                        href="https://www.unixtime.fr/" target="_blank">unixtime.fr</a>)
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label"> {{An Jeedom ID Crypted (for hosted app)}}</label>
                            <div class="col-sm-6">
                                <?php echo $npd_jeedom_id; ?>
                            </div>
                        </div>


                    </fieldset>

        </div>

    </fieldset>
</form>

<script>


    $('.npd_btn_association_apps_netatmo').on('click', function (e) {
        e.preventDefault();
        $.fn.showAlert({
            message: '{{Association en cours}}',
            level: 'warning'
        });
        $.ajax({
            type: "POST",
            url: "plugins/netatmoPublicData/core/ajax/netatmoPublicData.ajax.php",
            data: {
                action: "associationAppsNetatmo",
            },
            dataType: 'json',
            global: false,
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $.fn.showAlert({
                        message: data.result,
                        level: 'danger'
                    });
                    return;
                }
                window.open(data.result, "_blank");
            }
        });
    })

    $('.npd_btn_association_apps_netatmo_hosted').on('click', function (e) {
        e.preventDefault();
        $.showLoading();

        $.fn.showAlert({
            message: '{{Association en cours}}',
            level: 'warning'
        });
        npdRemoveTokensAppHosted();

        window.open("https://gateway.websenso.net/flux/netatmo/AuthorizationCodeGrant.php?jeedom_id=<?php echo $npd_jeedom_id; ?>", "_blank");
        setTimeout(npdGetTokensAppHosted, 5000);


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

                console.log(data);
                $.fn.showAlert({
                    message: '{{Tokens recupérés}}',
                    level: 'success'
                });
                npdSaveTokensAppHosted(data);

            }
        });
    }

    function npdSaveTokensAppHosted(content) {

        $.ajax({
            type: "POST",
            url: "plugins/netatmoPublicData/core/ajax/netatmoPublicData.ajax.php",
            data: {
                action: "appHostedSaveTokens",
                tokens: content,
            },
            dataType: 'json',
            global: false,
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $.fn.showAlert({message: data.result, level: 'danger'});
                    return;
                } else {
                    $.fn.showAlert({message: '{{Tokens sauvegardés}}', level: 'success'});
                    $('.bt_refreshPluginInfo').trigger('click');
                    $.hideLoading();
                }
            }
        });
    }

    function npdRemoveTokensAppHosted() {

        $.ajax({
            type: "POST",
            url: "plugins/netatmoPublicData/core/ajax/netatmoPublicData.ajax.php",
            data: {
                action: "appHostedRemoveTokens"
            },
            dataType: 'json',
            global: false,
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function () {
                $.fn.showAlert({message: '{{Suppression des tokens existants}}', level: 'success'});
                $('.bt_refreshPluginInfo').trigger('click');
            }
        });
    }

</script>