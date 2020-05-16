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
        <div class="form-group">
            <label class="col-sm-2 control-label" for="npd_client_id"> {{Client ID}}</label>
            <div class="col-sm-3">
                <input type="text" class="configKey form-control" data-l1key="npd_client_id" id="npd_client_id"
                       placeholder="" autocomplete="off"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label" for="npd_client_secret">{{Client secret}}</label>
            <div class="col-sm-3">
                <input type="text" class="configKey form-control" data-l1key="npd_client_secret" id="npd_client_secret"
                       placeholder="" autocomplete="off"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label" for="npd_username">{{Nom d'utilisateur}}</label>
            <div class="col-sm-3">
                <input type="email" class="configKey form-control" data-l1key="npd_username" id="npd_username"
                       placeholder="email@example.com" autocomplete="email"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label" for="npd_password">{{Mot de passe}}</label>
            <div class="col-sm-3">
                <input type="password" class="configKey form-control" id="npd_password" data-l1key="npd_password"
                       autocomplete="off"/>
            </div>
        </div>


        <div class="form-group">
            <label class="col-sm-2 control-label" for="npd_btn_sync">{{Synchroniser}}</label>
            <div class="col-lg-2">
                <button class="btn btn-warning" type="submit" id="npd_btn_sync">{{Synchroniser mes stations
                    favorites}}
                </button>
            </div>
        </div>

    </fieldset>
</form>


<script>
    $('#npd_btn_sync').on('click', function (e) {
        e.preventDefault();
        $('#div_alert').showAlert({message: '{{Please wait...}}', level: 'warning'});
        $.ajax({
            type: "POST",
            url: "plugins/netatmoPublicData/core/ajax/netatmoPublicData.ajax.php",
            data: {
                action: "syncWithNetatmo",
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
            }
        });
    });