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

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../class/netatmoPublicData.class.php';

// Flux OAuth2 « authorization code » de la méthode ownApp, sans bibliothèque tierce :
// voir netatmoPublicData::getOAuthAuthorizationUrl() et requestOAuthTokens().

if (isset($_GET['error'])) {
    die(htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'));
}

$npd_oauth2state = config::byKey('npd_oauth2state', 'netatmoPublicData');

// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Valeur anti-CSRF, vérifiée au retour de Netatmo.
    $state = bin2hex(random_bytes(16));
    config::save('npd_oauth2state', $state, 'netatmoPublicData');
    log::add('netatmoPublicData', 'debug', 'oauth2state :' . $state);

    // Redirect the user to the authorization URL.
    header('Location: ' . netatmoPublicData::getOAuthAuthorizationUrl($state));
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || empty($npd_oauth2state) || !is_string($_GET['state']) || !hash_equals((string)$npd_oauth2state, $_GET['state'])) {

    if (!empty($npd_oauth2state)) {
        config::save('npd_oauth2state', null, 'netatmoPublicData');
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        netatmoPublicData::saveOAuthTokens(netatmoPublicData::requestOAuthTokens(array(
            'grant_type' => 'authorization_code',
            'code' => (string)$_GET['code'],
        )));
        config::save('npd_connection_method', 'ownApp', 'netatmoPublicData');
        config::save('npd_oauth2state', null, 'netatmoPublicData');

        echo "✅";
        echo " - <a href=\"" . network::getNetworkAccess('external') . "/index.php?v=d&p=plugin&id=netatmoPublicData\">retour</a>  => Pensez à rafraichir votre page du plugin";
        echo "<br />";

    } catch (\Exception $e) {

        // Failed to get the access token.
        log::add('netatmoPublicData', 'error', $e->getMessage());
        exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));

    }

}
