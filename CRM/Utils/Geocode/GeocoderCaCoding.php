<?php
/*
  +--------------------------------------------------------------------+
  | CiviCRM Geocoder.ca Geocoding module                               |
  +--------------------------------------------------------------------+
  | Copyright Beltline Urban Society / Bow Valley Technology Services  |
  | (c) 2018                                                           |
  +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright Beltline Urban Society / Bow Valley Technology Services (c) 2018
 *
 */

/**
 * Class that uses Geocode.ca API to retrieve the lat/long of an address
 *
 * This CiviCRM extension requests geodata from geocoder.ca. Please  
 */
class CRM_Utils_Geocode_GeocoderCaCoding {

  /**
   * API server
   *
   * @var string
   * @static
   */
  static protected $_server = 'geocoder.ca';

  /**
   * uri of service
   *
   * @var string
   * @static
   */
  static protected $_uri = '/';

 /**
   * Function that takes an address object and gets the latitude / longitude for this
   * address. Note that at a later stage, we could make this function also clean up
   * the address into a more valid format
   *
   * @param array $values
   * @param bool $stateName
   *
   * @return bool
   *   true if we modified the address, false otherwise
   */
  public static function format(&$values, $stateName = FALSE) {
    // we need a valid country, else we ignore
    if (empty($values['country'])) {
      return FALSE;
    }
    $config = CRM_Core_Config::singleton();
    $add = '';
    if (!empty($values['street_address'])) {
      $add = str_replace('', '+', $values['street_address']);
    }
    $city = CRM_Utils_Array::value('city', $values);
    if ($city) {
      $add .= ' ' . str_replace('', '+', $city);
    }
    if (!empty($values['state_province']) || (!empty($values['state_province_id']) && $values['state_province_id'] != 'null')) {
      if (!empty($values['state_province_id'])) {
        $stateProvince = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince', $values['state_province_id']);
      }
      else {
        if (!$stateName) {
          $stateProvince = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince',
            $values['state_province'],
            'name',
            'abbreviation'
          );
        }
        else {
          $stateProvince = $values['state_province'];
        }
      }
      // dont add state twice if replicated in city (happens in NZ and other countries, CRM-2632)
      if ($stateProvince != $city) {
        $add .= ' ' . str_replace('', '+', $stateProvince);
      }
    }
    if (!empty($values['postal_code'])) {
      $add .= ' ' . str_replace('', '+', $values['postal_code']);
    }
    if (!empty($values['country'])) {
      $add .= ' ' . str_replace('', '+', $values['country']);
    }
  
    $add = urlencode($add);

    if (!empty($config->geoAPIKey)) {
      $add .= '&key=' . urlencode($config->geoAPIKey);
    }

    $query = 'https://' . self::$_server . self::$_uri . "?locate=" . $add . "&geoit=xml";

    require_once 'HTTP/Request.php';
    $request = new HTTP_Request($query);
    $request->sendRequest();
    $string = $request->getResponseBody();
    libxml_use_internal_errors(TRUE);
    $xml = simplexml_load_string($string);
    CRM_Utils_Hook::geocoderFormat('GeocoderCa', $values, $xml);

    if ($xml === FALSE) {
      // Exceeded requests limit maybe?
      CRM_Core_Error::debug_var('Geocoding failed.  Message from Geocoder.ca:', $string);
      return FALSE;
    }

    if($xml->standard->confidence > 0.5) {
      $values['geo_code_1'] = (float) $xml->latt;
      $values['geo_code_2'] = (float) $xml->longt;
      return TRUE;
    } 
    elseif(isset($xml->error)) {
      CRM_Core_Error::debug_var("Geocoding failed. Message from Geocoder.ca: ({$xml->error->code})", (string ) $xml->error->description);
      $values['geo_code_1'] = $values['geo_code_2'] = 'null';
      $values['geo_code_error'] = $xml->error->description;
      return FALSE;
    }
  }

}
