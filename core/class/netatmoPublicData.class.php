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

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

if (!defined('__ROOT_PLUGIN__')) {
    define('__ROOT_PLUGIN__', dirname(dirname(__FILE__)));
}

// Aucune dépendance Composer : les appels HTTP et OAuth2 passent par curl (voir httpRequest()).
// Le plugin embarquait auparavant Guzzle et league/oauth2-client. Plusieurs plugins Jeedom
// tournent dans le même processus PHP (cron15 notamment) et chaque vendor/autoload.php s'insère
// en tête de la pile d'autoload : un Guzzle 6 livré par un autre plugin (SmartLife/Tuya,
// NetatmoPro...) fournissait alors une partie des classes GuzzleHttp\* à la place des nôtres.
// D'où « Call to undefined method GuzzleHttp\Handler\Proxy::wrapTlsFallback() » ou
// « Undefined class constant MAJOR_VERSION ». Sans bibliothèque tierce, la collision est impossible.

/**
 * Class netatmoPublicData
 *
 */
class netatmoPublicData extends eqLogic
{

    /**
     * Variables
     */
    public static $_netatmoData = null;

    public static $_moduleType = array(
        "NAModule1" => "Température et Humidité",  // Outdoor module. Max : 1.
        "NAModule2" => "Anémomètre",  // Outdoor module. Max : 1.
        "NAModule3" => "Pluviomètre",  // Outdoor module. Max: 1.
        "NAModule4" => "CO2, Température et Humidité",  // Outdoor module. Max: 3.
    );
    public static $_encryptConfigKey = array('npd_client_secret', 'npd_password');

    /**
     * Call every 15 min, by Jeedom Core
     */
    public static function cron15()
    {

        // Loop over Equipment
        foreach (self::byType('netatmoPublicData') as $netatmoPublicData) {

            // Only if Equipment is enable.
            if ($netatmoPublicData->getIsEnable() == 1) {

                // Call command 'refresh', to update values
                $cmd = $netatmoPublicData->getCmd(null, 'refresh');
                if (!is_object($cmd)) {
                    continue;
                }
                $cmd->execCmd();
            }
        }
    }

    const NETATMO_AUTHORIZE_URL = 'https://api.netatmo.com/oauth2/authorize';
    const NETATMO_TOKEN_URL = 'https://api.netatmo.com/oauth2/token';
    const NETATMO_STATIONS_URL = 'https://api.netatmo.com/api/getstationsdata';
    const HOSTED_APP_TOKENS_URL = 'https://gateway.websenso.net/flux/netatmo/getTokens.php';
    const HTTP_USER_AGENT = 'Jeedom/4 netatmoPublicData';

    /**
     * Requête HTTP minimale, en curl natif.
     *
     * Ne lève d'exception qu'en cas d'échec réseau (DNS, timeout, TLS...). Un statut HTTP
     * d'erreur (4xx, 5xx) est renvoyé tel quel : c'est à l'appelant de le traiter.
     *
     * @param string $method GET ou POST
     * @param string $url
     * @param array $query Paramètres ajoutés à l'URL
     * @param array $headers En-têtes, sous la forme 'Nom' => 'valeur'
     * @param array|null $form Corps application/x-www-form-urlencoded (POST)
     * @return array{code: int, body: string}
     * @throws Exception
     */
    private static function httpRequest($method, $url, array $query = array(), array $headers = array(), array $form = null)
    {
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headerLines = array();
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => self::HTTP_USER_AGENT,
            CURLOPT_HTTPHEADER => $headerLines,
        );
        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form === null ? array() : $form, '', '&');
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80000) {
            // Sans effet depuis PHP 8.0 et déprécié en 8.5 : la ressource est libérée seule.
            curl_close($ch);
        }

        if ($body === false) {
            throw new Exception('Erreur réseau vers ' . parse_url($url, PHP_URL_HOST) . ' : ' . $error);
        }

        return array('code' => $code, 'body' => (string)$body);
    }

    /**
     * URL de retour OAuth2 déclarée auprès de Netatmo pour la méthode « ownApp ».
     *
     * @return string
     */
    public static function getOAuthRedirectUri()
    {
        return network::getNetworkAccess('external') . '/plugins/netatmoPublicData/core/php/AuthorizationCodeGrant.php';
    }

    /**
     * URL d'autorisation Netatmo (étape 1 du flux « authorization code »).
     *
     * @param string $state Valeur anti-CSRF, à conserver pour la vérifier au retour
     * @return string
     */
    public static function getOAuthAuthorizationUrl($state)
    {
        return self::NETATMO_AUTHORIZE_URL . '?' . http_build_query(array(
                'client_id' => config::byKey('npd_client_id', 'netatmoPublicData'),
                'redirect_uri' => self::getOAuthRedirectUri(),
                'scope' => 'read_station',
                'state' => $state,
                'response_type' => 'code',
                'approval_prompt' => 'auto',
            ), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Appel au point de terminaison oauth2/token de Netatmo.
     *
     * @param array $grantParams grant_type et paramètres associés (code, refresh_token)
     * @return array Réponse décodée, contenant au moins access_token
     * @throws Exception Si Netatmo refuse la demande ou répond de façon inexploitable
     */
    public static function requestOAuthTokens(array $grantParams)
    {
        $response = self::httpRequest('POST', self::NETATMO_TOKEN_URL, array(), array('Accept' => 'application/json'), array(
                'client_id' => config::byKey('npd_client_id', 'netatmoPublicData'),
                'client_secret' => config::byKey('npd_client_secret', 'netatmoPublicData'),
                'redirect_uri' => self::getOAuthRedirectUri(),
            ) + $grantParams);

        $data = json_decode($response['body'], true);

        if (!is_array($data) || !empty($data['error']) || empty($data['access_token'])) {
            $detail = 'HTTP ' . $response['code'];
            if (is_array($data) && !empty($data['error'])) {
                $detail .= ' - ' . (is_string($data['error']) ? $data['error'] : json_encode($data['error']));
                if (!empty($data['error_description'])) {
                    $detail .= ' : ' . $data['error_description'];
                }
            }
            throw new Exception('Netatmo a refusé la demande de token (' . $detail . ')');
        }

        return $data;
    }

    /**
     * Enregistre les tokens renvoyés par oauth2/token.
     *
     * @param array $tokens
     */
    public static function saveOAuthTokens(array $tokens)
    {
        config::save('npd_access_token', $tokens['access_token'], 'netatmoPublicData');
        // Netatmo renvoie normalement un nouveau refresh_token ; à défaut, on garde l'actuel.
        if (!empty($tokens['refresh_token'])) {
            config::save('npd_refresh_token', $tokens['refresh_token'], 'netatmoPublicData');
        }
        config::save('npd_expires_at', isset($tokens['expires_in']) ? time() + (int)$tokens['expires_in'] : null, 'netatmoPublicData');
    }

    /**
     * Request new tokens
     */
    public static function getNetatmoTokens()
    {

        $npd_connection_method = config::byKey('npd_connection_method', 'netatmoPublicData', 'ownApp');
        log::add('netatmoPublicData', 'debug', $npd_connection_method);

        if ($npd_connection_method === "ownApp") {

            log::add('netatmoPublicData', 'debug', 'Récupération nouveaux tokens - ownApp');

            try {
                self::saveOAuthTokens(self::requestOAuthTokens(array(
                    'grant_type' => 'refresh_token',
                    'refresh_token' => config::byKey('npd_refresh_token', 'netatmoPublicData'),
                )));
            } catch (\Exception $e) {
                log::add('netatmoPublicData', 'error', 'Renouvellement du token Netatmo en échec : ' . $e->getMessage());
            }

        }

        if ($npd_connection_method === "hostedApp") {

            log::add('netatmoPublicData', 'debug', 'Récupération nouveaux tokens - hostedApp');

            $jeedom_id = crypt(jeedom::getApiKey('netatmoPublicData'), "OnExposePasCetteInfoInterne");
            $npd_local_version = update::byLogicalId('netatmoPublicData')->getLocalVersion();

            try {
                $response = self::httpRequest('GET', self::HOSTED_APP_TOKENS_URL, array(
                    'refresh' => 1,
                    'jeedom_id' => $jeedom_id,
                ), array(
                    'Referer' => 'Jeedom/' . $npd_local_version . ' ' . $jeedom_id,
                ));
            } catch (\Exception $e) {
                log::add('netatmoPublicData', 'error', 'Renouvellement du token (application hébergée) en échec : ' . $e->getMessage());
                return;
            }

            if ($response['code'] !== 200) {
                log::add('netatmoPublicData', 'error', 'Renouvellement du token (application hébergée) en échec : HTTP ' . $response['code']);
                return;
            }

            $content_array = json_decode($response['body'], true);

            if (is_array($content_array) && isset($content_array['state']) && $content_array['state'] === "ok") {
                config::save('npd_access_token', $content_array['npd_access_token'], 'netatmoPublicData');
                config::save('npd_refresh_token', $content_array['npd_refresh_token'], 'netatmoPublicData');
                config::save('npd_expires_at', $content_array['npd_expires_at'], 'netatmoPublicData');
            }

        }
    }

    /**
     * Retrieve user's Weather Stations data.
     *
     * Méthode statique et non plus fonction déclarée dans le corps de getNetatmoData() : une
     * fonction imbriquée est déclarée dans l'espace global, et le second appel de la méthode
     * dans la même requête provoquait « Cannot redeclare performRequestWithToken() ».
     *
     * @param string $npd_access_token
     * @param bool $retryOn403 Autorise une seule relance après renouvellement du token
     * @return array|false
     */
    private static function performRequestWithToken($npd_access_token, $retryOn403 = true)
    {
        try {
            $response = self::httpRequest('GET', self::NETATMO_STATIONS_URL, array(
                'get_favorites' => 'true',
                'access_token' => $npd_access_token,
            ));
        } catch (\Exception $e) {
            // Network issue, timeout, DNS...
            log::add('netatmoPublicData', 'error', "Appel API Netatmo en échec : " . $e->getMessage());
            return false;
        }

        if ($response['code'] == 403 && $retryOn403) {
            // Token refusé : on en demande un nouveau et on rejoue la requête une seule fois.
            netatmoPublicData::getNetatmoTokens();
            $npd_access_token = config::byKey('npd_access_token', 'netatmoPublicData');

            return self::performRequestWithToken($npd_access_token, false);
        }

        if ($response['code'] >= 400 || $response['code'] === 0) {
            log::add('netatmoPublicData', 'error', "Error Status Code : " . $response['code']);
            return false;
        }

        $content_array = json_decode($response['body'], true);

        return is_array($content_array) ? $content_array : false;
    }

    /**
     * Get Netatmo data from webservice and stored in $_client
     *
     * @return array|mixed
     */
    public static function getNetatmoData()
    {

        // Cast explicite : config::byKey() renvoie '' (et non null) quand la clé est absente,
        // par exemple après un « Débrancher » ou sur une installation neuve. En PHP 8,
        // time() - '' lève une TypeError fatale (« Unsupported operand types »).
        $npd_expires_at = (int)config::byKey('npd_expires_at', 'netatmoPublicData', 0);

        log::add('netatmoPublicData', 'debug', "Token valid until: " . print_r($npd_expires_at, true) . " - in " . print_r(round(($npd_expires_at - time()) / 60), true) . " minute(s)");

        // Request new tokens, if expired (or never obtained)
        if ($npd_expires_at <= 0 || $npd_expires_at < time()) {
            netatmoPublicData::getNetatmoTokens();
        }

        // Initial call
        $npd_access_token = config::byKey('npd_access_token', 'netatmoPublicData');
        $content_array = self::performRequestWithToken($npd_access_token);

        if (!empty($content_array['body'])) {

            log::add('netatmoPublicData', 'info', "FETCH Netatmo API to get new data");
            log::add('netatmoPublicData', 'debug', print_r($content_array, true));

            return $content_array['body'];
        }

        return false;
    }


    /**
     * Create equipments (and their commands) from Netatmo favorites stations
     *
     * @throws Exception
     */
    public
    static function createEquipmentsAndCommands()
    {

        log::add('netatmoPublicData', 'debug', __FUNCTION__);
        self::$_netatmoData = self::getNetatmoData();


        // security : getNetatmoData() renvoie false si l'API est injoignable ou le token invalide
        if (!is_array(self::$_netatmoData) || !isset(self::$_netatmoData['devices']) || !is_array(self::$_netatmoData['devices'])) {
            log::add('netatmoPublicData', 'error', __('Aucune donnée reçue de Netatmo : synchronisation abandonnée.', __FILE__));
            return;
        }

        // Loop over Favorites Stations, from Netatmo
        $npd_equipment_favorite_logicalId = array();
        foreach (self::$_netatmoData['devices'] as $device) { //array multi scope

            // Réinitialisé à chaque itération : sans cela le drapeau restait à true pour tous les
            // équipements suivants, qui héritaient du dimensionnement de widget d'une autre station.
            $new_equipment = false;


            // Security : manage only NAMain station type
            if (!in_array($device['type'], array('NAMain'))) {
                log::add('netatmoPublicData', 'debug', "SKIP : this device " . $device['_id'] . " has not the type NAMain  (but :  " . $device['type'] . " )");
                continue;
            }

            // Stored LogicalID for later use
            $npd_equipment_favorite_logicalId[] = $device['_id'];

            // Get Equipment data from Jeedom, by device_id
            $eqLogic = eqLogic::byLogicalId($device['_id'], 'netatmoPublicData');

            // Unknown Equipment ==> new station
            if (!is_object($eqLogic) || $eqLogic->getLogicalId() != $device['_id']) {
                $eqLogic = new netatmoPublicData();
                $eqLogic->setName($device['place']['city'] . " : " . $device['station_name'] . " ( " . $device['_id'] . " ) *");
                $eqLogic->setIsVisible(1);

                $new_equipment = true;
            }

            $eqLogic->setIsEnable(1);

            // Save Station as an Equipment
            $eqLogic->setEqType_name('netatmoPublicData');
            $eqLogic->setLogicalId($device['_id']);
            $eqLogic->setConfiguration('_id', $device['_id']);
            $eqLogic->setConfiguration('type', $device['type']);
            $eqLogic->setConfiguration('station_name', $device['station_name']);
            $eqLogic->setConfiguration('city', $device['place']['city']);
            $eqLogic->setTimeout(60);

            $eqLogic->save();

            log::add('netatmoPublicData', 'debug', "Equipment : " . $device['station_name'] . " (LogicalID : " . $device['_id'] . ") created !");

            // Create Commands : "refresh"
            self::createCmdRefresh($eqLogic);

            // Widget's size, count line to display
            $widget_line = 0;

            // Create Commands for each Equipment (depending of sensors available)
            //@@todo : switch() optimization ?
            // Main Station
            if (is_array($device['data_type']) && in_array("Pressure", $device['data_type'])) {

                // Creating Commands
                if ($device['reachable'] === true) {
                    self::createCmdCustom($eqLogic, $device, "Pression", "pressure", 'WEATHER_PRESSURE', 'tile', 'tile', 3, null, '880', '1100', 'hPa');
                    $widget_line++;
                } // Deleting Existing Commands useless
                else {
                    self::removeCmdCustom($eqLogic, $device, 'pressure');
                }
            }

            //@@todo : créer les Command pour les données Privées
            // Pour les données "Privé" (réservé au propriétaire)
            // valeurs en complément peuvent être récupérer
            //        "Temperature",
            //        "CO2",
            //        "Humidity",
            //        "Noise",


            // For each-sub modules
            if (is_array($device['modules'])) {
                foreach ($device['modules'] as $module) {

                    // Security : manage only few module type
                    if (!in_array($module['type'], array('NAModule1', 'NAModule2', 'NAModule3'))) {
                        log::add('netatmoPublicData', 'debug', "SKIP : this module " . $module['_id'] . " has not the type excepted  (but :  " . $module['type'] . " )");
                        continue;
                    }


                    //@@todo : créer les Command pour les données Privés
                    // issue du sous-module NAModule4. Attention, il peut y avoir jusqu'à 3 de ce type de module
                    // donnée possible
                    //
                    //  "Temperature",
                    //  "CO2",
                    //  "Humidity"


                    if (is_array($module['data_type'])) {
                        // // Temperature Command
                        if (in_array("Temperature", $module['data_type'])) {

                            // Creating Commands
                            if ($module['reachable'] === true) {

                                self::createCmdCustom($eqLogic, $device, "Température", "temperature", 'TEMPERATURE', 'HygroThermographe', 'tile', 2, null, '-100', '100', '°C');
                            } // Deleting Existing Commands useless
                            else {

                                self::removeCmdCustom($eqLogic, $device, 'temperature');
                            }
                        }
                        // Humidity Command
                        if (in_array("Humidity", $module['data_type'])) {

                            // Creating Commands
                            if ($module['reachable'] === true) {

                                self::createCmdCustom($eqLogic, $device, "Humidité", "humidity", 'HUMIDITY', 'HygroThermographe', 'tile', 2, null, '0', '100', '%');
                            } // Deleting Existing Commands useless
                            else {

                                self::removeCmdCustom($eqLogic, $device, 'humidity');
                            }
                        }
                        // Wind Command
                        if (in_array("Wind", $module['data_type'])) {

                            // Creating Commands
                            if ($module['reachable'] === true) {
                                //     if (1 == 1) {  // debug

                                self::createCmdCustom($eqLogic, $device, 'Vitesse du vent', 'windstrength', 'WEATHER_WIND_SPEED', 'tile', 'tile', 10, 1, '0', '200', 'km/h');
                                self::createCmdCustom($eqLogic, $device, 'Direction du vent', 'windangle', 'WIND_DIRECTION', 'compass', 'compass', 11, null, '0', '360', '°');
                                self::createCmdCustom($eqLogic, $device, 'Vitesse des rafales', 'guststrength', 'WEATHER_WIND_SPEED', 'tile', 'tile', 15, 1, '0', '200', 'km/h');
                                self::createCmdCustom($eqLogic, $device, 'Direction des rafales', 'gustangle', 'WIND_DIRECTION', 'compass', 'compass', 16, null, '0', '360', '°');
                                $widget_line++;
                                $widget_line++;
                            } // Deleting Existing Commands useless
                            else {

                                self::removeCmdCustom($eqLogic, $device, 'windstrength');
                                self::removeCmdCustom($eqLogic, $device, 'windangle');
                                self::removeCmdCustom($eqLogic, $device, 'guststrength');
                                self::removeCmdCustom($eqLogic, $device, 'gustangle');
                            }
                        }

                        // Rain Command
                        if (in_array("Rain", $module['data_type'])) {

                            // Creating Commands
                            if ($module['reachable'] === true) {

                                self::createCmdCustom($eqLogic, $device, "Pluie", "rain", 'RAIN_CURRENT', 'rain', 'rain', 20, 1, '0', '10', 'mm');
                                self::createCmdCustom($eqLogic, $device, "Pluie (1h)", "sum_rain_1", 'RAIN_CURRENT', 'rain', 'rain', 21, null, '0', '20', 'mm');
                                self::createCmdCustom($eqLogic, $device, "Pluie (Journée)", "sum_rain_24", 'RAIN_CURRENT', 'rain', 'rain', 22, null, '0', '50', 'mm');
                                $widget_line++;
                            } // Deleting Existing Commands useless
                            else {

                                self::removeCmdCustom($eqLogic, $device, 'rain');
                                self::removeCmdCustom($eqLogic, $device, 'sum_rain_1');
                                self::removeCmdCustom($eqLogic, $device, 'sum_rain_24');
                            }
                        }
                    }
                }
            }

            /*
             * Adjust widget size (width and height)
             * width : 312px
             * 1 lines => height : 152px
             * 3 lines => height : 352px
             * 4 lines => height : 452px
             */
            if ($new_equipment) {

                $eqLogic->setDisplay('width', '312px');
                switch ($widget_line) {
                    case 1:
                        $eqLogic->setDisplay('height', '152px');
                        break;
                    case 3:
                        $eqLogic->setDisplay('height', '352px');
                        break;
                    default:
                        $eqLogic->setDisplay('height', '452px');
                }

                $eqLogic->save();
            }
        }


        // Remove un-favorite Equipment in Jeedom
        $plugin = plugin::byId('netatmoPublicData');
        $eqLogics = eqLogic::byType($plugin->getId());

        // Get equipment already in Jeedom
        $npd_equipment_in_jeedom = array();
        foreach ($eqLogics as $eqLogic) {
            $npd_equipment_in_jeedom[] = $eqLogic->getLogicalId();
        }
        log::add('netatmoPublicData', 'debug', print_r($npd_equipment_in_jeedom, true));

        // Find diff
        $npd_equipement_removed_from_favorite = array_diff($npd_equipment_in_jeedom, $npd_equipment_favorite_logicalId);

        // Disabled Equipment
        foreach ($npd_equipement_removed_from_favorite as $equipmentLogicalID) {
            $eqLogic = eqLogic::byLogicalId($equipmentLogicalID, 'netatmoPublicData');
            $eqLogic->setIsEnable(0);
            $eqLogic->save();
            log::add('netatmoPublicData', 'debug', "Equipment disabled (because un-star from Netatmo) : $equipmentLogicalID ");
        }
    }

    /**
     * Update all commands values with Netatmo latest values.
     */
    public
    function updateValues()
    {

        if (empty(self::$_netatmoData)) {
            log::add('netatmoPublicData', 'debug', "Variable with Netatmo's data is empty... so need to be fetched.");
            self::$_netatmoData = self::getNetatmoData();
        }

        // security
        if (!is_array(self::$_netatmoData) || !isset(self::$_netatmoData['devices']) || !is_array(self::$_netatmoData['devices'])) {
            return;
        }

        $netatmo = self::$_netatmoData['devices'];

        // Target Equipment, on Netatmo data
        $netatmo_array_key = array_search($this->getLogicalId(), array_column($netatmo, '_id'));

        // La station n'est plus dans les favoris Netatmo : sans ce garde, $netatmo[false] est
        // résolu en $netatmo[0] et les array_column() plus bas reçoivent null, ce qui lève une
        // TypeError fatale en PHP 8 (simple warning en PHP 7.4).
        if ($netatmo_array_key === false) {
            log::add('netatmoPublicData', 'debug', "SKIP : station " . $this->getLogicalId() . " absente des données Netatmo");
            return;
        }

        // Sous-modules de la station (absents si la station n'en déclare aucun)
        $netatmo_modules = isset($netatmo[$netatmo_array_key]['modules']) && is_array($netatmo[$netatmo_array_key]['modules'])
            ? $netatmo[$netatmo_array_key]['modules']
            : array();

        // Loops over all Commands in this Equipment.
        foreach ($this->getCmd() as $cmd) {


            switch ($cmd->getLogicalId()) {


                // NAModule1 (temperature +  humidity )
                case "temperature":
                    $module_type = "NAModule1";

                    $NAModule1_array_key = array_search($module_type, array_column($netatmo_modules, 'type'));

                    if ($NAModule1_array_key !== false) {

                        $netatmo_module = $netatmo_modules[$NAModule1_array_key];  // shortcut

                        if ($netatmo_module['reachable'] == true) {

                            $collectDate = date('Y-m-d H:i:s', $netatmo_module['dashboard_data']['time_utc']);

                            // temperature
                            $this->checkAndUpdateCmd('temperature', $netatmo_module['dashboard_data']['Temperature'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Temperature (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['Temperature']);

                            // humidity
                            $this->checkAndUpdateCmd('humidity', $netatmo_module['dashboard_data']['Humidity'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Humidity (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['Humidity']);
                        } else {
                            self::sendErrorMessage($this, $netatmo_module, $module_type);
                        }
                    }

                    break;


                //  NAModule2  ( windstrength, windangle, guststrength, gustangle )
                case "windstrength":

                    $module_type = "NAModule2";

                    $NAModule2_array_key = array_search($module_type, array_column($netatmo_modules, 'type'));

                    if ($NAModule2_array_key !== false) {


                        $netatmo_module = $netatmo_modules[$NAModule2_array_key];  // shortcut

                        if ($netatmo_module['reachable'] == true) {


                            $collectDate = date('Y-m-d H:i:s', $netatmo_module['dashboard_data']['time_utc']);

                            // windstrength
                            $this->checkAndUpdateCmd('windstrength', $netatmo_module['dashboard_data']['WindStrength'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => WindStrength (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['WindStrength']);

                            // windangle
                            $this->checkAndUpdateCmd('windangle', $netatmo_module['dashboard_data']['WindAngle'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => WindAngle (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['WindAngle']);


                            // guststrength
                            $this->checkAndUpdateCmd('guststrength', $netatmo_module['dashboard_data']['GustStrength'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => GustStrength (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['GustStrength']);

                            // gustangle
                            $this->checkAndUpdateCmd('gustangle', $netatmo_module['dashboard_data']['GustAngle'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => GustAngle (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['GustAngle']);
                        } else {
                            self::sendErrorMessage($this, $netatmo_module, $module_type);
                        }
                    }


                    break;


                //  NAModule3  ( rain, sum_rain_1, sum_rain_24 )
                case "rain":

                    $module_type = "NAModule3";

                    $NAModule3_array_key = array_search($module_type, array_column($netatmo_modules, 'type'));


                    if ($NAModule3_array_key !== false) {

                        $netatmo_module = $netatmo_modules[$NAModule3_array_key];  // shortcut


                        if ($netatmo_module['reachable'] == true) {
                            $collectDate = date('Y-m-d H:i:s', $netatmo_module['dashboard_data']['time_utc']);

                            // rain
                            $this->checkAndUpdateCmd('rain', $netatmo_module['dashboard_data']['Rain'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Rain (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['Rain']);

                            // sum_rain_1
                            $this->checkAndUpdateCmd('sum_rain_1', $netatmo_module['dashboard_data']['sum_rain_1'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => sum_rain_1 (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['sum_rain_1']);


                            // sum_rain_24
                            $this->checkAndUpdateCmd('sum_rain_24', $netatmo_module['dashboard_data']['sum_rain_24'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => sum_rain_24 (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['sum_rain_24']);
                        } else {
                            self::sendErrorMessage($this, $netatmo_module, $module_type);
                        }
                    }

                    break;


                //    NAMain
                case "pressure":

                    $module_type = "NAMain";

                    $netatmo_module = $netatmo[$netatmo_array_key];  // shortcut

                    if ($netatmo_module['reachable'] == true) {

                        $collectDate = date('Y-m-d H:i:s', $netatmo_module['dashboard_data']['time_utc']);

                        // Pressure
                        $this->checkAndUpdateCmd('pressure', $netatmo_module['dashboard_data']['Pressure'], $collectDate); // Update value
                        log::add('netatmoPublicData', 'info', " - Update value => Pressure (module $module_type : " . $netatmo_module['_id'] . ") = " . $netatmo_module['dashboard_data']['Pressure']);
                    } else {
                        self::sendErrorMessage($this, $netatmo_module, $module_type);
                    }

                    break;
            }
        }


        // @@todo : inform user of new sensors available from theirs favorites stations. ex : an new anemometer has been added...
        // Loops overs Netatmo data, and find missing Command => Send Message.

        // Update Widget
        $this->refreshWidget();
    }

    /**
     * @param $netatmo_module
     * @param $module_type
     */
    public
    static function sendErrorMessage($eqLogic, $netatmo_module, $module_type)
    {

        // Record message, if user didn't disabled it this notification
        if (config::byKey('npd_log_error_weather_station', 'netatmoPublicData') != 1) {
            // $_moduleType ne référence que les sous-modules NAModule* : le type NAMain, qui passe
            // aussi par ici via la commande 'pressure', retombe sur son libellé brut.
            $moduleLabel = isset(self::$_moduleType[$netatmo_module['type']])
                ? self::$_moduleType[$netatmo_module['type']]
                : $netatmo_module['type'];

            $message = $eqLogic->getHumanName() . ' - module ' . $moduleLabel
                . ' ( ' . $netatmo_module['type'] . ' ' . $netatmo_module['_id'] . ' ) is not reachable ! '
                . 'You could : wait, remove those alerts on configuration page or even consider to remove commands linked ( click on '
                . '<a href="index.php?v=d&m=netatmoPublicData&p=netatmoPublicData">Synchronise</a>, '
                . 'then Commands linked will be removed ).';

            message::add('netatmoPublicData', $message, '', $eqLogic->getId());
        }

        log::add('netatmoPublicData', 'debug', " - module $module_type not reachable, SKIP", $eqLogic->getLogicalId());
    }

    /**
     * Create command 'refresh'
     *
     * @param $eqLogic
     * @throws Exception
     */
    public
    static function createCmdRefresh($eqLogic)
    {
        // Refresh
        $NetatmoInfo = $eqLogic->getCmd(null, 'refresh');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Refresh', __FILE__));
        $NetatmoInfo->setLogicalId('refresh');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());


        $NetatmoInfo->setOrder(0);
        $NetatmoInfo->setType('action');
        $NetatmoInfo->setSubType('other');
        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command created : Refresh " . $NetatmoInfo->getId());
    }


    /**
     * Create custom command
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public
    static function createCmdCustom($eqLogic, $device, $name, $logicalId, $setGeneric_type = null, $template_dashboard = 'tile', $template_mobile = 'tile', $order = null, $forceReturnLineBefore = null, $minValue = null, $maxValue = null, $unite = null)
    {
        // Rain
        $NetatmoInfo = $eqLogic->getCmd(null, $logicalId);
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
            $NetatmoInfo->setConfiguration('historyPurge', '-1 month');

            $NetatmoInfo->setIsVisible(true);
            $NetatmoInfo->setIsHistorized(true);

            $NetatmoInfo->setTemplate('dashboard', $template_dashboard);
            $NetatmoInfo->setTemplate('mobile', $template_mobile);

            if ($forceReturnLineBefore) {
                $NetatmoInfo->setDisplay('forceReturnLineBefore', '1');
            }
            if ($template_dashboard == "HygroThermographe") {
                $NetatmoInfo->setDisplay('parameters', array('scale' => '0.5'));
            }

            // Leave user to configure to local weather condition
            $NetatmoInfo->setConfiguration('maxValue', $maxValue);
        }
        $NetatmoInfo->setName(__($name, __FILE__));
        $NetatmoInfo->setLogicalId($logicalId);
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
        $NetatmoInfo->setConfiguration('minValue', $minValue);

        $NetatmoInfo->setOrder($order);
        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');

        $NetatmoInfo->setUnite($unite);
        $NetatmoInfo->setGeneric_type($setGeneric_type);

        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command created : " . $NetatmoInfo->getId() . " " . $name);
    }


    /**
     * Remove custom command
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public
    static function removeCmdCustom($eqLogic, $device, $logicalId)
    {

        $cmdToRemove = $eqLogic->getCmd(null, $logicalId);
        if (is_object($cmdToRemove)) {

            $cmdToRemove->remove();
            log::add('netatmoPublicData', 'debug', " - Command removed ( because is not reachable) : " . $cmdToRemove->getId() . " " . $device['type'] . "  " . $device['_id']);
        }
    }
}


/**
 * Class netatmoPublicDataCmd
 */
class netatmoPublicDataCmd extends cmd
{

    /**
     * execute function
     *
     * @param array $_options
     * @return bool
     */
    public function execute($_options = array())
    {
        // If 'click' on 'refresh' command
        if ($this->getLogicalId() == 'refresh') {
            log::add('netatmoPublicData', 'debug', "Call 'refresh' command for this object " . print_r($this, true));
            $this->getEqLogic()->updateValues();
        }
        return false;
    }
}
