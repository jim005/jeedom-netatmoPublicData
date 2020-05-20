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

if (!class_exists('netatmoApi')) {
    define('__ROOT_PLUGIN__', dirname(dirname(__FILE__)));
    require_once(__ROOT_PLUGIN__ . '/../3rdparty/Netatmo-API-PHP/src/Netatmo/autoload.php');
}

/**
 * Class netatmoPublicData
 *
 *
 */
class netatmoPublicData extends eqLogic
{


    /**
     * Netatmo data
     *
     */
    public static $_netatmoData = null;


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
//            handleError("An error happened while trying to retrieve your tokens: " . $ex->getMessage() . "\n", TRUE);
        }

        //Retrieve user's Weather Stations Information
        try {
            //retrieve all stations belonging to the user, and also his favorite ones
            $data = $client->getData(NULL, TRUE);
            log::add('netatmoPublicData', 'info', "Fetch Netatamo API to get new data");
            log::add('netatmoPublicData', 'debug', print_r($client, true));
            return $data;
        } catch (Netatmo\Exceptions\NAClientException $ex) {
            log::add('netatmoPublicData', 'error', print_r("Netatmo webservice : An error occured while retrieving data: " . $ex->getMessage() . "\n", TRUE));
//            handleError("An error occured while retrieving data: " . $ex->getMessage() . "\n", TRUE);
        }

    }

    /**
     * Create equipments (and their commands) from Netatmo favorites stations
     *
     * @throws Exception
     */
    public static function syncWithNetatmo()
    {

        log::add('netatmoPublicData', 'debug', __FUNCTION__);
        self::$_netatmoData = self::getNetatmoData();


        // Loop over Favorites Stations, from Netatmo
        $npd_equipment_favorite_logicalId = array();
        foreach (self::$_netatmoData['devices'] as $device) { //array multi scope

            // Stored LogicalID for later use
            $npd_equipment_favorite_logicalId[] = $device['_id'];

            // Get Equipment data from Jeedom, by device_id
            $eqLogic = eqLogic::byLogicalId($device['_id'], 'netatmoPublicData');

            // Unknown Equipment ==> new station
            if (!is_object($eqLogic) || $eqLogic->getLogicalId() != $device['_id']) {
                $eqLogic = new netatmoPublicData();
                $eqLogic->setName($device['station_name'] . " *");
                $eqLogic->setCategory('heating', 1);
                $eqLogic->setIsVisible(1);
            }

            $eqLogic->setIsEnable(1);

            // Save Station as an Equipment
            $eqLogic->setEqType_name('netatmoPublicData');
            $eqLogic->setLogicalId($device['_id']);

            $eqLogic->setConfiguration('type', $device['type']);
            $eqLogic->setConfiguration('_id', $device['_id']);

            // @@todo : adjust widget sizes with sum of command  : more command to show, more place is required.
            $eqLogic->setDisplay('height', '152px');
            $eqLogic->setDisplay('width', '600px');

            $eqLogic->save();

            log::add('netatmoPublicData', 'debug', "Equipment : " . $device['station_name'] . " (LogicalID : " . $device['_id'] . ") created !");

            $eqLogic = self::byId($eqLogic->getId());

            // Create Commands : "refresh"
            self::createCmdRefresh($eqLogic);


            // Create Commands for each Equipment (depending of sensors available)
            //@@todo : switch() optimization ?
            // Main Station
            if (is_array($device['data_type']) && in_array("Pressure", $device['data_type'])) {
                self::createCmdPressure($eqLogic, $device);
            }
            // For each-sub modules
            if (is_array($device['modules'])) {
                foreach ($device['modules'] as $module) {
                    if (is_array($module['data_type'])) {
                        // // Temperature Command
                        if (in_array("Temperature", $module['data_type'])) {
                            self::createCmdTemperature($eqLogic, $device);
                        }
                        // Humidity Command
                        if (in_array("Humidity", $module['data_type'])) {
                            self::createCmdHumidity($eqLogic, $device);
                        }
                        // Rain Command
                        if (in_array("Rain", $module['data_type'])) {
                            self::createCmdRain($eqLogic, $device);
                        }
                        // Wind Command
                        if (in_array("Wind", $module['data_type'])) {
                            self::createCmdWindStrength($eqLogic, $device);
                            self::createCmdWindAngle($eqLogic, $device);
                        }
                    }
                }
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
        }

    }

    /**
     * Update all commands values with Netatmo latest values.
     */
    public function updateNetatmoPublicData()
    {


        if (empty(self::$_netatmoData)) {
            log::add('netatmoPublicData', 'debug', "Variable with Netatmo's data is empty... so need to be fetched.");
            self::$_netatmoData = self::getNetatmoData();
        }

        // security
//        if (is_array(self::$_client['devices'])) {   // security
//            return;
//        }

        // Loop over Netatmo's data
        foreach (self::$_netatmoData['devices'] as $device) {

            // If Equipment LogicialId ($this...) is not in $device, move to the next one !
            if ($device['_id'] != $this->getLogicalId()) {
                continue;
            }

            log::add('netatmoPublicData', 'info', "Update values for Equipment : " . $this->getName() . " (LogicalID : " . $this->getLogicalId());


            //Pressure (from the main station)
            if (!empty($device['dashboard_data']['Pressure'])
                && $device['reachable'] == true
                && is_float($device['dashboard_data']['Pressure'])
                && $device['dashboard_data']['Pressure'] > 0
            ) { // security

                $this->checkAndUpdateCmd('pressure', $device['dashboard_data']['Pressure']);
                log::add('netatmoPublicData', 'debug', " - Update value => Pressure (module : " . $device['_id'] . ") = " . $device['dashboard_data']['Pressure']);

            }


            // For each-sub modules
            if (is_array($device['modules'])) {   // security

                foreach ($device['modules'] as $module) {

                    log::add('netatmoPublicData', 'debug', ' -- start device[module]', $this->getLogicalId());

                    if (is_array($module['data_type'])) {  // security


                        // Temperature Command
                        if (in_array("Temperature", $module['data_type'])
                            && $module['reachable'] == true
                            && is_numeric($module['dashboard_data']['Temperature'])
                        ) {

                            $this->checkAndUpdateCmd('temperature', $module['dashboard_data']['Temperature']); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Temperature (module : " . $module['_id'] . ") = " . $module['dashboard_data']['Temperature']);

                        }

                        // Humidity Command
                        if (in_array("Humidity", $module['data_type'])
                            && $module['reachable'] == true
                            && is_numeric($module['dashboard_data']['Humidity'])
                            && $module['dashboard_data']['Humidity'] > 0) {  // security

                            $this->checkAndUpdateCmd('humidity', $module['dashboard_data']['Humidity']); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Humidity (module : " . $module['_id'] . ") = " . $module['dashboard_data']['Humidity']);

                        }

                        // Rain Command
                        if (in_array("Rain", $module['data_type'])
                            && $module['reachable'] == true
                            && is_numeric($module['dashboard_data']['Rain'])) {  // security

                            $this->checkAndUpdateCmd('rain', $module['dashboard_data']['Rain']); // Update value
                            log::add('netatmoPublicData', 'info', " - Update value => Rain (module : " . $module['_id'] . ") = " . $module['dashboard_data']['Rain']);

                        }

                        // Wind : WindStrength + WindAngle
                        if (in_array("Wind", $module['data_type'])
                            && $module['reachable'] == true) {

                            // WindStrength Command
                            if (is_numeric($module['dashboard_data']['WindStrength'])
                                && $module['dashboard_data']['WindStrength'] > 0) {  // security

                                $this->checkAndUpdateCmd('windstrength', $module['dashboard_data']['WindStrength']); // Update value
                                log::add('netatmoPublicData', 'info', " - Update value => WindStrength (module : " . $module['_id'] . ") = " . $module['dashboard_data']['WindStrength']);

                            }

                            // WindAngle Command
                            if (is_numeric($module['dashboard_data']['WindAngle'])
                                && $module['dashboard_data']['WindAngle'] > 0) {  // security

                                $this->checkAndUpdateCmd('windangle', $module['dashboard_data']['WindAngle']); // Update value
                                log::add('netatmoPublicData', 'info', " - Update value => WindAngle (module : " . $module['_id'] . ") = " . $module['dashboard_data']['WindAngle']);

                            }
                        }
                    }
                    log::add('netatmoPublicData', 'debug', ' -- end device[module]', $this->getLogicalId());
                }
            }
        }
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

        $NetatmoInfo->setType('action');
        $NetatmoInfo->setSubType('other');
        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " refresh created !");

    }

    /**
     * Create command 'temperature'
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdTemperature($eqLogic, $device)
    {
        // Temperature
        $NetatmoInfo = $eqLogic->getCmd(null, 'temperature');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Température', __FILE__));
        $NetatmoInfo->setLogicalId('temperature');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
        $NetatmoInfo->getConfiguration('maxValue', '100');
        $NetatmoInfo->getConfiguration('minValue', '-100');
        $NetatmoInfo->getConfiguration('historyPurge', '-1 month');

        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');
        $NetatmoInfo->setIsVisible(true);
        $NetatmoInfo->setIsHistorized(true);

        $NetatmoInfo->setUnite('°C');
        $NetatmoInfo->setGeneric_type('TEMPERATURE');
        $NetatmoInfo->setTemplate('dashboard', 'HygroThermographe');
        $NetatmoInfo->setTemplate('mobile', 'tile');
        $NetatmoInfo->setDisplay('parameters', array('scale' => '0.5'));

        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " temperature created !");

    }

    /**
     * Create command 'humidity'
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdHumidity($eqLogic, $device)
    {
        // Humidity

        $NetatmoInfo = $eqLogic->getCmd(null, 'humidity');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Humidité', __FILE__));
        $NetatmoInfo->setLogicalId('humidity');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);;
        $NetatmoInfo->getConfiguration('maxValue', '100');
        $NetatmoInfo->getConfiguration('minValue', '0');
        $NetatmoInfo->getConfiguration('historyPurge', '-1 month');

        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');
        $NetatmoInfo->setIsVisible(true);
        $NetatmoInfo->setIsHistorized(true);

        $NetatmoInfo->setUnite('%');
        $NetatmoInfo->setGeneric_type('HUMIDITY');
        $NetatmoInfo->setTemplate('dashboard', 'HygroThermographe');
        $NetatmoInfo->setTemplate('mobile', 'tile');
        $NetatmoInfo->setDisplay('parameters', array('scale' => '0.5'));

        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " humidity created !");

    }

    /**
     * Create command 'pressure'
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdPressure($eqLogic, $device)
    {
        // Pressure
        $NetatmoInfo = $eqLogic->getCmd(null, 'pressure');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Pression', __FILE__));
        $NetatmoInfo->setLogicalId('pressure');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
        $NetatmoInfo->getConfiguration('maxValue', '1100');
        $NetatmoInfo->getConfiguration('minValue', '880');
        $NetatmoInfo->getConfiguration('historyPurge', '-1 month');

        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');
        $NetatmoInfo->setIsVisible(true);
        $NetatmoInfo->setIsHistorized(true);

        $NetatmoInfo->setUnite('hPa');  // hPa == mbar
        $NetatmoInfo->setGeneric_type('WEATHER_PRESSURE');
        $NetatmoInfo->setTemplate('dashboard', 'tile');
        $NetatmoInfo->setTemplate('mobile', 'tile');
        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " pressure created !");
    }

    /**
     * Create command 'rain'
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdRain($eqLogic, $device)
    {
        // Rain
        $NetatmoInfo = $eqLogic->getCmd(null, 'rain');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Rain', __FILE__));
        $NetatmoInfo->setLogicalId('rain');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
//        $NetatmoInfo->getConfiguration('maxValue', 1100);
        $NetatmoInfo->getConfiguration('minValue', '0');
        $NetatmoInfo->getConfiguration('historyPurge', '-1 month');

        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');
        $NetatmoInfo->setIsVisible(true);
        $NetatmoInfo->setIsHistorized(true);

        $NetatmoInfo->setUnite('mm');
        $NetatmoInfo->setGeneric_type('RAIN_CURRENT');
        $NetatmoInfo->setTemplate('dashboard', 'rain');
        $NetatmoInfo->setTemplate('mobile', 'rain');
        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " rain created !");
    }

    /**
     * Create command 'windstrength'
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdWindStrength($eqLogic, $device)
    {
        // Wind
        $NetatmoInfo = $eqLogic->getCmd(null, 'windstrength');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Vitesse du vent', __FILE__));
        $NetatmoInfo->setLogicalId('windstrength');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
//        $NetatmoInfo->getConfiguration('maxValue', 1100);
        $NetatmoInfo->getConfiguration('minValue', '0');
        $NetatmoInfo->getConfiguration('historyPurge', '-1 month');

        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');
        $NetatmoInfo->setIsVisible(true);
        $NetatmoInfo->setIsHistorized(true);

        $NetatmoInfo->setUnite('km/h');
        $NetatmoInfo->setGeneric_type('WEATHER_WIND_SPEED');
        $NetatmoInfo->setTemplate('dashboard', 'tile');
        $NetatmoInfo->setTemplate('mobile', 'tile');
        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " WindStrength created !");
    }


    /**
     * Create command 'windangle'
     *
     * @param $eqLogic
     * @param $device
     * @throws Exception
     */
    public static function createCmdWindAngle($eqLogic, $device)
    {
        // Wind
        $NetatmoInfo = $eqLogic->getCmd(null, 'windangle');
        if (!is_object($NetatmoInfo)) {
            $NetatmoInfo = new netatmoPublicDataCmd();
        }
        $NetatmoInfo->setName(__('Direction du vent', __FILE__));
        $NetatmoInfo->setLogicalId('windangle');
        $NetatmoInfo->setEqLogic_id($eqLogic->getId());

        $NetatmoInfo->setConfiguration('_id', $device['_id']);
        $NetatmoInfo->setConfiguration('type', $device['type']);
        $NetatmoInfo->getConfiguration('maxValue', '360');
        $NetatmoInfo->getConfiguration('minValue', '0');
        $NetatmoInfo->getConfiguration('historyPurge', '-1 month');

        $NetatmoInfo->setType('info');
        $NetatmoInfo->setSubType('numeric');
        $NetatmoInfo->setIsVisible(true);
        $NetatmoInfo->setIsHistorized(true);

        $NetatmoInfo->setUnite('°');
        $NetatmoInfo->setGeneric_type('WIND_DIRECTION');
        $NetatmoInfo->setTemplate('dashboard', 'compass');
        $NetatmoInfo->setTemplate('mobile', 'compass');
        $NetatmoInfo->save();

        log::add('netatmoPublicData', 'debug', " - Command : " . $NetatmoInfo->getId() . " WindAngle created !");
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
            $this->getEqLogic()->updateNetatmoPublicData();
        }
        return false;
    }

}