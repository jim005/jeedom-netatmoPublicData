<?php

namespace Netatmo\Clients;


/**
 * NETATMO HEALTY HOME COACH API PHP CLIENT
 *
 * For more details upon NETATMO API Please check https://dev.netatmo.com/doc
 * @author Originally written by Enzo Macri <enzo.macri@netatmo.com>
 */
class NAHomeApiClient extends NAApiClient
{

  /*
   * @type PRIVATE & PARTNER API
   * @param string $device_id
   * @return array of devices
   * @brief Method used to retrieve data for the given healty home coach or all healty home coach linked to the user
   */
   public function getData($device_id = NULL)
   {
       return $this->api('gethomecoachsdata', 'GET', array($device_id));
   }

}

?>
