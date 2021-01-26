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


define('__ROOT_PLUGIN__', dirname(dirname(__FILE__)));
require_once(__ROOT_PLUGIN__ . '/../3rdparty/Netatmo-API-PHP/src/Netatmo/autoload.php');


/**
 * Class netatmoPublicData
 *
 *
 */
class netatmoPublicData extends eqLogic
{


    /**
     * Variables
     *
     */
    public static $_netatmoData = null;

    public static $_moduleType = array(
        "NAModule1" => "Température et Humidité",  // Outdoor module. Max : 1.
        "NAModule2" => "Anémomètre",  // Outdoor module. Max : 1.
        "NAModule3" => "Pluviomètre",  // Outdoor module. Max: 1.
        "NAModule4" => "CO2, Température et Humidité",  // Outdoor module. Max: 3.
    );


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

    /**
     * Get Netatomo data from webservice and stored in $_client
     *
     * @return array|mixed
     */
    public function getNetatmoData()
    {

        $scope = Netatmo\Common\NAScopes::SCOPE_READ_STATION;
        $config = array(
            'client_id' => config::byKey('npd_client_id', 'netatmoPublicData'),
            'client_secret' => config::byKey('npd_client_secret', 'netatmoPublicData'),
            'username' => config::byKey('npd_username', 'netatmoPublicData'),
            'password' => config::byKey('npd_password', 'netatmoPublicData'),
            'scope' => $scope,
        );

        $client = new Netatmo\Clients\NAWSApiClient($config);

        //Authentication with Netatmo server (OAuth2)
        try {
            $tokens = $client->getAccessToken();
        } catch (Netatmo\Exceptions\NAClientException $ex) {
            log::add('netatmoPublicData', 'error', print_r("An error happened while trying to retrieve your tokens: " . $ex->getMessage() . "\n", TRUE));
        }

        //Retrieve user's Weather Stations Information
        try {
            //retrieve all stations belonging to the user, and also his favorite ones
            $data = $client->getData(NULL, TRUE);
            log::add('netatmoPublicData', 'info', "FETCH Netatamo API to get new data");
            log::add('netatmoPublicData', 'debug', print_r($client, true));
            return $data;
        } catch (Netatmo\Exceptions\NAClientException $ex) {
            log::add('netatmoPublicData', 'error', print_r("Netatmo webservice : An error occured while retrieving data: " . $ex->getMessage() . "\n", TRUE));
        }

    }

    /**
     * Create equipments (and their commands) from Netatmo favorites stations
     *
     * @throws Exception
     */
    public static function createEquipmentsAndCommands()
    {

        log::add('netatmoPublicData', 'debug', __FUNCTION__);
        self::$_netatmoData = self::getNetatmoData();


        // Loop over Favorites Stations, from Netatmo
        $npd_equipment_favorite_logicalId = array();
        foreach (self::$_netatmoData['devices'] as $device) { //array multi scope


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

            $eqLogic->setConfiguration('type', $device['type']);
            $eqLogic->setConfiguration('_id', $device['_id']);
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
                self::createCmdCustom($eqLogic, $device, "Pression", "pressure", 'WEATHER_PRESSURE', 'tile', 'tile', 3, null, '880', '1100', 'hPa');
                $widget_line++;

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
                        if (in_array("Temperature", $module['data_type']) and $module['reachable'] === true ) {
                            self::createCmdCustom($eqLogic, $device, "Température", "temperature", 'TEMPERATURE', 'HygroThermographe', 'tile', 2, null, '-100', '100', '°C');
                        }
                        // Humidity Command
                        if (in_array("Humidity", $module['data_type']) and $module['reachable'] === true ) {

                            self::createCmdCustom($eqLogic, $device, "Humidité", "humidity", 'HUMIDITY', 'HygroThermographe', 'tile', 2, null, '0', '100', '%');

                        }
                        // Wind Command
                        if (in_array("Wind", $module['data_type']) and $module['reachable'] === true ) {

                            self::createCmdCustom($eqLogic, $device, 'Vitesse du vent', 'windstrength', 'WEATHER_WIND_SPEED', 'tile', 'tile', 10, 1, '0', '200', 'km/h');
                            self::createCmdCustom($eqLogic, $device, 'Direction du vent', 'windangle', 'WIND_DIRECTION', 'compass', 'compass', 11, null, '0', '360', '°');
                            self::createCmdCustom($eqLogic, $device, 'Vitesse des rafales', 'guststrength', 'WEATHER_WIND_SPEED', 'tile', 'tile', 15, 1, '0', '200', 'km/h');
                            self::createCmdCustom($eqLogic, $device, 'Direction des rafales', 'gustangle', 'WIND_DIRECTION', 'compass', 'compass', 16, null, '0', '360', '°');
                            $widget_line++;
                            $widget_line++;

                        }
                        // Rain Command
                        if (in_array("Rain", $module['data_type']) and $module['reachable'] === true ) {
                            self::createCmdCustom($eqLogic, $device, "Pluie", "rain", 'RAIN_CURRENT', 'rain', 'rain', 20, 1, '0', '100', 'mm');
                            self::createCmdCustom($eqLogic, $device, "Pluie (1h)", "sum_rain_1", 'RAIN_CURRENT', 'rain', 'rain', 21, null, '0', '100', 'mm');
                            self::createCmdCustom($eqLogic, $device, "Pluie (Journée)", "sum_rain_24", 'RAIN_CURRENT', 'rain', 'rain', 22, null, '0', '1100', 'mm');
                            $widget_line++;
                        }
                    }
                }
            }


            /*
             * Adjust widget size (width and height)
             *
             * For V3 :
             * width : 392px
             * 1 lines => height : 92px
             * 3 lines => height : 232px
             * 4 lines => height : 272px
             *
             * For V4 :
             * width : 312px
             * 1 lines => height : 152px
             * 3 lines => height : 352px
             * 4 lines => height : 452px
             */
            if ($new_equipment) {

                if ((float)getVersion(null) < 4) {
                    log::add('netatmoPublicData', 'debug', "Jeedom v3  ( < v4 )");
                    $eqLogic->setDisplay('width', '392px');
                    switch ($widget_line) {
                        case 1:
                            $eqLogic->setDisplay('height', '92px');
                            break;
                        case 3:
                            $eqLogic->setDisplay('height', '232px');
                            break;
                        default:
                            $eqLogic->setDisplay('height', '272px');
                    }
                } else {
                    log::add('netatmoPublicData', 'debug', "Jeedom v4 ( >= v4 )");
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
            log::add('netatmoPublicData', 'debug', 'Equipment ' . $equipmentLogicalID . ' disabled !');


            // message::add('networks', __('Echec du ping sur : ', __FILE__) . $this->getHumanName(), '', 'pingFailed' . $this->getId());

        }

    }

    /**
     * Update all commands values with Netatmo latest values.
     */
    public function updateValues()
    {


        if (empty(self::$_netatmoData)) {
            log::add('netatmoPublicData', 'debug', "Variable with Netatmo's data is empty... so need to be fetched.");
            self::$_netatmoData = self::getNetatmoData();
        }

        // security
        if (!is_array(self::$_netatmoData['devices'])) {   // security
            return;
        }

        // Loop over Netatmo's data
        foreach (self::$_netatmoData['devices'] as $device) {

            // If Equipment LogicialId ($this...) is not in $device, move to the next one !
            if ($device['_id'] != $this->getLogicalId()) {
                log::add('netatmoPublicData', 'debug', "SKIP this value, 'cause : " . $device['_id'] . " !=  " . $this->getLogicalId());
                continue;
            }

            log::add('netatmoPublicData', 'info', "Update values for Equipment : " . $this->getName() . " ( LogicalID : " . $this->getLogicalId() . " )");


            //Pressure (from the main station)
            log::add('netatmoPublicData', 'debug', ' -- start device [Main]', $this->getLogicalId());

            if ($device['reachable'] == false) {
                message::add('netatmoPublicData', $this->getHumanName() . ' - device ' . $device['type'] . ' ( ' .  self::$_moduleType[$device['type']] . ' ) is not reachable !', '', $this->getId());
                log::add('netatmoPublicData', 'debug', ' - device not reachable, SKIP', $this->getLogicalId());
                continue;
            }

            if (!empty($device['dashboard_data']['Pressure'])
                && is_float($device['dashboard_data']['Pressure'])
                && $device['dashboard_data']['Pressure'] > 0
            ) { // security

                $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['time_utc']);
                $this->checkAndUpdateCmd('pressure', $device['dashboard_data']['Pressure'], $collectDate);
                log::add('netatmoPublicData', 'info', " - Update value => Pressure (module : " . $device['_id'] . ") = " . $device['dashboard_data']['Pressure']);
            }
            log::add('netatmoPublicData', 'debug', ' -- end device [Main]', $this->getLogicalId());


            // For each-sub modules
            if (is_array($device['modules'])) {   // security

                foreach ($device['modules'] as $module) {

                    log::add('netatmoPublicData', 'debug', ' -- start device [module]', $this->getLogicalId());

                    if ($module['reachable'] == false) {
                        message::add('netatmoPublicData', $this->getHumanName() . ' - module ' . $module['type'] . ' ( ' .  self::$_moduleType[$module['type']] . ' ' .  $module['_id'] . ' ) is not reachable !', '', $this->getId());
                        log::add('netatmoPublicData', 'debug', ' - module not reachable, SKIP', $this->getLogicalId());
                        continue;
                    }

                    if (is_array($module['data_type']) && is_array($module['dashboard_data'])) {  // security

                        $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['time_utc']);

                        // Temperature Command
                        if (in_array("Temperature", $module['data_type'])
                            && is_numeric($module['dashboard_data']['Temperature'])
                        ) {

                            $this->checkAndUpdateCmd('temperature', $module['dashboard_data']['Temperature'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Temperature (module : " . $module['_id'] . ") = " . $module['dashboard_data']['Temperature']);

                        }

                        // Humidity Command
                        if (in_array("Humidity", $module['data_type'])
                            && is_numeric($module['dashboard_data']['Humidity'])
                            && $module['dashboard_data']['Humidity'] > 0) {  // security

                            $this->checkAndUpdateCmd('humidity', $module['dashboard_data']['Humidity'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Humidity (module : " . $module['_id'] . ") = " . $module['dashboard_data']['Humidity']);

                        }

                        // Rain Command
                        if (in_array("Rain", $module['data_type'])
                            && is_numeric($module['dashboard_data']['Rain'])) {  // security

                            $this->checkAndUpdateCmd('rain', $module['dashboard_data']['Rain'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Rain (module : " . $module['_id'] . ") = " . $module['dashboard_data']['Rain']);

                            $this->checkAndUpdateCmd('sum_rain_1', $module['dashboard_data']['sum_rain_1'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => sum_rain_1 (module : " . $module['_id'] . ") = " . $module['dashboard_data']['sum_rain_1']);

                            $this->checkAndUpdateCmd('sum_rain_24', $module['dashboard_data']['sum_rain_24'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => sum_rain_24 (module : " . $module['_id'] . ") = " . $module['dashboard_data']['sum_rain_24']);

                        }

                        // Wind : WindStrength + WindAngle
                        if (in_array("Wind", $module['data_type'])
                            && $module['dashboard_data']['WindStrength'] > 0) {  // security

                            $this->checkAndUpdateCmd('windstrength', $module['dashboard_data']['WindStrength'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => WindStrength (module : " . $module['_id'] . ") = " . $module['dashboard_data']['WindStrength']);

                            $this->checkAndUpdateCmd('windangle', $module['dashboard_data']['WindAngle'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => WindAngle (module : " . $module['_id'] . ") = " . $module['dashboard_data']['WindAngle']);

                            $this->checkAndUpdateCmd('guststrength', $module['dashboard_data']['GustStrength'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => GustStrength (module : " . $module['_id'] . ") = " . $module['dashboard_data']['GustStrength']);

                            $this->checkAndUpdateCmd('gustangle', $module['dashboard_data']['GustAngle'], $collectDate); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => GustAngle (module : " . $module['_id'] . ") = " . $module['dashboard_data']['GustAngle']);


                            //@@todo : si valeur est reachable == false , alors loggé NULL ou FALSE.

                        }
                    }
                    log::add('netatmoPublicData', 'debug', ' -- end device [module]', $this->getLogicalId());
                }
            }
        }

        // Update Widget
        $this->refreshWidget();
    }

    /**
     * Create command 'refresh'
     *
     * @param $eqLogic
     * @throws Exception
     */
    public static function createCmdRefresh($eqLogic)
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

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " refresh created !");

    }


    /**
     * Create custom command
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdCustom($eqLogic, $device, $name, $logicalId, $setGeneric_type = null, $template_dashboard = 'tile', $template_mobile = 'tile', $order = null, $forceReturnLineBefore = null, $minValue = null, $maxValue = null, $unite = null)
    {
        // Rain
        $NetatmoInfo = $eqLogic->getCmd(null, $logicalId);
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
            $NetatmoInfo->setConfiguration('historyPurge', '-1 month');

            $NetatmoInfo->setIsVisible(true);
            $NetatmoInfo->setIsHistorized(true);

            // For V3, don't use new widgets
            if ((float)getVersion(null) < 4 and in_array($template_dashboard, array('rain', 'HygroThermographe', 'compass'))) {
                $template_dashboard = 'tile';
                $template_mobile = 'tile';
            }
            $NetatmoInfo->setTemplate('dashboard', $template_dashboard);
            $NetatmoInfo->setTemplate('mobile', $template_mobile);

            if ($forceReturnLineBefore) {
                $NetatmoInfo->setDisplay('forceReturnLineBefore', '1');
            }
            if ($template_dashboard == "HygroThermographe") {
                $NetatmoInfo->setDisplay('parameters', array('scale' => '0.5'));
            }
        }
        $NetatmoInfo->setName(__($name, __FILE__));
        $NetatmoInfo->setLogicalId($logicalId);
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
        $NetatmoInfo->setConfiguration('maxValue', $maxValue);
        $NetatmoInfo->setConfiguration('minValue', $minValue);

        $NetatmoInfo->setOrder($order);
        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');

        $NetatmoInfo->setUnite($unite);
        $NetatmoInfo->setGeneric_type($setGeneric_type);

        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " " . $name . " created !");
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