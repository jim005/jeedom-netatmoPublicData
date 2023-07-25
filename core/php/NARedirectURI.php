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
use GuzzleHttp\Client;

$jsonData = file_get_contents("php://input");
$display = 'none';
$prefix = 'rejected';


if (!is_null($jsonData) && !empty($jsonData)) {
    log::add('netatmoPublicData', 'info', 'redirectURI:: jsonData:' . var_export($jsonData, true));
} elseif (empty($_SERVER['QUERY_STRING'])) {
} else {
    $display = 'initial';

    log::add('netatmoPublicData', 'debug', 'redirectURI:: QUERY_STRING:' . var_export($_SERVER['QUERY_STRING'], true));

    parse_str($_SERVER['QUERY_STRING'], $output);
    log::add('netatmoPublicData', 'debug', 'redirectURI:: output:' . var_export($output, true));

    try {

        $client = new Client();
        $response = $client->request('POST', 'https://api.netatmo.com/oauth2/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => config::byKey('npd_client_id', 'netatmoPublicData'),
                'client_secret' => config::byKey('npd_client_secret', 'netatmoPublicData'),
                'code' => $output['code'],
                'redirect_uri' => network::getNetworkAccess('external') . '/plugins/netatmoPublicData/core/php/NARedirectURI.php',
                'scope' => 'read_station'
            ]
        ]);

    } catch (GuzzleHttp\Exception\ClientException $ex) {
        log::add('netatmoPublicData', 'error', 'redirectURI:: ex:' . var_export('GuzzleHttp\Exception\ClientException' . ' - ' . get_class($ex) . ' - ' . $ex->getCode() . ' - ' . $ex->getMessage(), true));
        throw $ex;
    }

    log::add('netatmoPublicData', 'debug', 'redirectURI:: response:' . var_export($response, true));

    $data = json_decode($response->getBody()->getContents(), true);
    log::add('netatmoPublicData', 'info', 'redirectURI:: body:' . var_export($data, true));

    config::save('npd_access_token', $data['access_token'], 'netatmoPublicData');
    config::save('npd_refresh_token', $data['refresh_token'], 'netatmoPublicData');
    config::save('npd_expires_at', time() + $data['expires_in'] - 30, 'netatmoPublicData');

    $prefix = 'approved';
}
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex">
</head>
<body>
<div style="margin: auto;width: 100%;text-align: center;font-size: 10rem;">
    <?php
    if ($prefix === "approved") {
        echo "✅";
    } else {
        echo "❌";
    }
    ?>
</div>
</body>
</html>