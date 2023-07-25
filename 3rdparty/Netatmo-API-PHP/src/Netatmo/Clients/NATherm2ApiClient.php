<?php

namespace Netatmo\Clients;


/**
 * NETATMO HEALTY HOME COACH API PHP CLIENT
 *
 * For more details upon NETATMO API Please check https://dev.netatmo.com/doc
 * @author Originally written by Enzo Macri <enzo.macri@netatmo.com>
 */
class NATherm2ApiClient extends NAApiClient
{

  /*
   * @type PRIVATE & PARTNER API
   * @param string $home_id: Id of the home
   * @param string $gateway_types: Array of desired gateway
   * @return array of home topology object
   * @brief Method used to retrieve user's homes and their topology
   */
   public function getData($home_id = NULL, $gateway_types = NULL)
   {
       if(!is_null($home_id)) $params['home_id'] = $home_id;
       if(!is_null($gateway_types)) $params['gateway_types'] = $gateway_types;

       return $this->api('homesdata', 'GET', $params);
   }

  /*
   * @type PRIVATE & PARTNER API
   * @param string $home_id: id of home
   * @param string $device_types: Array of device type
   * @return array of home
   * @brief Method used to get the current status and data measured for all home devices
   */
   public function getStatus($home_id, $device_types = NULL)
   {
       $params = array('home_id' => $home_id);
       if(!is_null($device_types)) $params['device_types'] = $device_types;

       return $this->api('homestatus', 'GET', $params);
   }

  /*
   * @type PRIVATE & PARTNER API
   * @param string $home_id: id of home
   * @param string $mode: Heating mode
   * @param string $endtime: timestamp
   * @brief Method used to set the home heating system to use schedule/away/frost guard mode
   */
   public function setThermMode($home_id, $mode, $endtime = NULL)
   {
       $params = array("home_id" => $home_id,
                       "mode" => $mode);
       if(!is_null($endtime)) $params['endtime'] = $endtime;

       return $this->api('setthermmode', 'POST', $params);
   }

  /*
   * @type PRIVATE & PARTNER API
   * @param string $home_id: id of home
   * @param string $room_id: id of room
   * @param string $scale: step between measurements
   * @param string $type: type of requested measurements
   * @return data from a room
   * @brief Method used to retrieve data from a Room
   */
   public function getRoomMeasure($home_id, $room_id, $scale, $type)
   {
       $params = array("home_id" => $home_id,
                       "room_id" => $room_id,
                       "scale" => $scale,
                       "type" => $type);

       return $this->api('getroommeasure', 'GET', $params);
   }

  /*
   * @type PRIVATE & PARTNER API
   * @param string $schedule_id: ID of the schedule to switch on
   * @param string $home_id: ID of the home
   * @brief Method used to apply a specific schedule
   */
   public function switchHomeSchedule($schedule_id, $home_id)
   {
       $params = array("schedule_id" => $schedule_id,
                       "home_id" => $home_id);

       return $this->api('switchhomeschedule', 'POST', $params);
   }

  /*
   * @type PRIVATE & PARTNER API
   * @param string $home_id: id of home
   * @param string $room_id: id of room
   * @param string $mode: The mode you are applying to this room
   * @param string $temp: Manual temperature to apply
   * @param string $endtime: End of this manual setpoint
   * @brief Method used to set a manual temperature to a room. or switch back to home mode
   */
   public function setRoomThermpoint($home_id, $room_id, $mode, $temp = NULL, $endtime = NULL)
   {
       $params = array("home_id" => $home_id,
                       "room_id" => $room_id,
                       "mode" => $mode);
       if(!is_null($temp)) $params['temp'] = $temp;
       if(!is_null($endtime)) $params['endtime'] = $endtime;

       return $this->api('setroomthermpoint', 'GET', $params);
   }
}

?>
