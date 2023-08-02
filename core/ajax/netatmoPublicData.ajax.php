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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();

    // From button on Configuration page
    if (init('action') == 'createEquipmentsAndCommands') {

        //@@todo : ajouter un message d'attente en JS, background orange

        // Get data from Netatmo : create equipment.
        netatmoPublicData::createEquipmentsAndCommands();

        // Run task cron : get sensor's value
        netatmoPublicData::cron15();

        // success
        ajax::success();
    }


    if (init('action') == 'associationAppsNetatmo') {
        try {
            $redirectURI = network::getNetworkAccess('external') . '/plugins/netatmoPublicData/core/php/AuthorizationCodeGrant.php';
            config::save('npd_connection_method', 'ownApp', 'netatmoPublicData');
            ajax::success($redirectURI);
        } catch (Exception $e) {
            ajax::error(displayException($e), $e->getCode());
        }
    }

    // From button on Configuration page
    if (init('action') == 'appHostedSaveTokens') {

        if ($_POST['tokens']['state'] == "ok") {
            config::save('npd_access_token', $_POST['tokens']['npd_access_token'], 'netatmoPublicData');
            config::save('npd_refresh_token', $_POST['tokens']['npd_refresh_token'], 'netatmoPublicData');
            config::save('npd_expires_at', $_POST['tokens']['npd_expires_at'], 'netatmoPublicData');
            config::save('npd_connection_method', 'hostedApp', 'netatmoPublicData');
        }

        // success
        ajax::success();
    }


    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
