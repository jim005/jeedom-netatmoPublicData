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

// @@todo : Pour test sur la version 4.4 beta - en attendant la gestion native des lib composer
// https://community.jeedom.com/t/debut-de-la-migration-vers-composer-en-live/109920/5?u=jim005
if (!class_exists('League\OAuth2\Client\Provider\GenericProvider')) {
    require_once __DIR__ . "/../../vendor/autoload.php";
}

$provider = new League\OAuth2\Client\Provider\GenericProvider([
    'clientId' => config::byKey('npd_client_id', 'netatmoPublicData'),
    'clientSecret' => config::byKey('npd_client_secret', 'netatmoPublicData'),
    'redirectUri' => network::getNetworkAccess('external') . '/plugins/netatmoPublicData/core/php/AuthorizationCodeGrant.php',
    'urlAuthorize' => 'https://api.netatmo.com/oauth2/authorize',
    'urlAccessToken' => 'https://api.netatmo.com/oauth2/token',
    'urlResourceOwnerDetails' => 'https://service.example.com/resource'
]);


$npd_oauth2state = config::byKey('npd_oauth2state', 'netatmoPublicData');

// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl([
        'scope' => ['read_station']
    ]);

    // Get the state generated for you and store it to the session.
    config::save('npd_oauth2state', $provider->getState(), 'netatmoPublicData');
    log::add('netatmoPublicData', 'debug', 'oauth2state :' . $provider->getState());

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || empty($npd_oauth2state) || $_GET['state'] !== $npd_oauth2state) {

    if (isset($npd_oauth2state)) {
        config::save('npd_oauth2state', null, 'netatmoPublicData');
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // We have an access token, which we may use in authenticated
        // requests against the service provider's API.
        config::save('npd_access_token', $accessToken->getToken(), 'netatmoPublicData');
        config::save('npd_refresh_token', $accessToken->getRefreshToken(), 'netatmoPublicData');
        config::save('npd_expires_at', $accessToken->getExpires(), 'netatmoPublicData');

        echo "✅";
        echo " - <a href=\"" . network::getNetworkAccess('external') . "/index.php?v=d&p=plugin&id=netatmoPublicData\">retour</a>";

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}