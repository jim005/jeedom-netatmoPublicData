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

?>
<form class="form-horizontal">
    <fieldset>

        <div class="row">


            <div class="form-group">
                <label class="col-sm-3 control-label" for="npd_client_id"> {{Client ID}}</label>
                <div class="col-sm-4">
                    <input type="text" class="configKey form-control" data-l1key="npd_client_id" id="npd_client_id"
                           placeholder="" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3  control-label" for="npd_client_secret">{{Client secret}}</label>
                <div class="col-sm-4">
                    <input type="password" class="configKey form-control" data-l1key="npd_client_secret"
                           id="npd_client_secret" placeholder="" autocomplete="off">
                </div>
            </div>


            <div class="form-group">
                <label class="col-md-3 control-label">{{Association de votre compte Netatmo}}
                    <sup><i class="fas fa-question-circle tooltips"
                            title="{{Autoriser la liaison entre Jeedom et votre compte Netatmo}}"></i></sup>
                </label>
                <div class="col-md-2">
                    <a class="btn btn-success form-control npd_btn_association_apps_netatmo"><i class="fas fa-link"></i>
                        {{Association Netatmo}}</a>
                </div>
            </div>


            <fieldset>
                <legend>
                    <i class="fas fa-sliders"></i> Options
                </legend>

                <div class=" form-group">
                    <div class="col-sm-1">
                        <input type="checkbox" class="configKey form-control" id="npd_log_error_weather_station"
                               data-l1key="npd_log_error_weather_station">
                    </div>
                    <label class="col-sm-11 control-label" for="npd_log_error_weather_station"
                           style="text-align: left;">{{Désactiver les messages
                        d'alertes lorsqu'une station est indisponible}}</label>


                </div>


            </fieldset>


            <fieldset>
                <legend>
                    <i class="fas fa-bug"></i> Informations de connexion (debug)
                </legend>


                <div class="form-group">
                    <label class="col-sm-3 control-label" for="npd_client_id"> {{access_token}}</label>
                    <div class="col-sm-6">
                        <input type="text" disabled class="configKey form-control" data-l1key="npd_access_token"
                               id="npd_access_token" placeholder="" autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="npd_client_id"> {{refresh_token}}</label>
                    <div class="col-sm-6">
                        <input type="text" disabled class="configKey form-control" data-l1key="npd_refresh_token"
                               id="npd_refresh_token" placeholder="" autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="npd_client_id"> {{expires_at}}</label>
                    <div class="col-sm-6">
                        <input type="text" disabled class="configKey form-control" data-l1key="npd_expires_at"
                               id="npd_expires_at" placeholder="" autocomplete="off">
                    </div>
                </div>

            </fieldset>

        </div>

    </fieldset>
</form>

<script>


    $('.npd_btn_association_apps_netatmo').on('click', function (e) {
        e.preventDefault();
        $('#div_alert').showAlert({message: '{{Association en cours}}', level: 'warning'});
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
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                window.open(data.result, "_blank");
            }
        });
    })


</script>