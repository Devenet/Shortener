<?php

namespace Core;

/*
  This Software is licenced To Ahmed Saad, Egypt <a7mad.sa3d.2014@gmail.com> 2013-2016
  Attribution-ShareAlike 4.0 International

  You can use this software under the following :

  conditions:
    - include-copyright
    - document-changes
    - same-license

  permissions:
    - commercial-use
    - modifications
    - distribution
    - private-use

  limitations:
    - no-liability
    - trademark-use
    - patent-use
*/

class UserAgent
{

  /**
   * Current Client UserAgent Instance
   * Usefull if class instantiated again, properties will be assigned
   * from this stored instance, avoiding re processing
   * 
   * @var null||Object
   */
  protected static $clientInstance = null;

  /**
   * User Full Agent String
   * @var string
   */
  protected $string = '';

  /**
   * Browser Name
   * @var string
   */
  protected $browserName = '';

  /**
   * Browser Version
   * @var string
   */
  protected $browserVersion = '';

  /**
   * System String Part Inside User Agent String
   * @var string
   */
  protected $systemString = '';

  /**
   * System Platform Name
   * @var string
   */
  protected $osPlatform = '';

  /**
   * Operating System Version
   * @var string
   */
  protected $osVersion = '';

  /**
   * Short Representation of Operating System Version
   * @var string
   */
  protected $osShortVersion = '';

  /**
   * Is Client Device Mobile Phone
   * @var boolean
   */
  protected $isMobile = false;

  /**
   * Mobile Device Name
   * @var string
   */
  protected $mobileName = '';

  public function __construct($user_agent_string)
  {
    if (empty($user_agent_string)) return;

    $this->string = $user_agent_string;
    $this->analyzeString();
  }

  /**
   * Analyze User Agent String
   * @return Void
   */
  private function analyzeString()
  {
    // Analaysing User Agent String

    // Check if Mobile
    if (strpos($this->string, 'Mobile') !== false || strpos($this->string, 'Android') !== false)
      $this->isMobile = 1;

    // Analyze Browser
    $this->analyzeBrowser();

    // Analyze System String
    $this->analyzePlatform();
  }

  /**
   * set Browser Properties, and also set systemString Property
   * 
   * @return Void
   */
  private function analyzeBrowser()
  {

    //							  1/ver 		  2/sys 	if Version/ exists			 3/ver       |OR| 4/sys 					 5/ver
    if (preg_match('/(?:Opera\/([0-9\.\w]+)\s\((.+?)\)(?:(?=.*Version\/).*Version\/([0-9\.\w]+)|.*))|\((.+?)\).+?Opera(?:[\s\/]([0-9\.\w]+))?/', $this->string, $opera))
    {
      // Opera

      $this->browserName = 'Opera';
      $this->browserVersion = isset($opera[5]) ? $opera[5] : (isset($opera[3]) ? $opera[3] : (isset($opera[1]) ? $opera[1] : ''));

      $this->systemString = isset($opera[4]) ? $opera[4] : $opera[2];
    } //									  1/ver 						2/sys 		   |OR| 3/sys				  	 4/ver											  		1/sys 				  	2/ver
    else if (preg_match('/(?:MSIE\s+([0-9\.\w]+)(?:(?=.+Win).+?(Win.+[0-9\.\w]+)|.*))|\((.+?);.+?Trident.*?rv:([0-9\.\w]+)/i', $this->string, $ie))
    {
      // IE			

      $this->browserName = 'Internet Explorer';
      $this->browserVersion = isset($ie[4]) ? $ie[4] : $ie[1];

      $this->systemString = isset($ie[3]) ? $ie[3] : (isset($ie[2]) ? $ie[2] : '');
    } // 					 1/sys			  			  2/ver							    	  3/ver
    else if (preg_match('/\((.+?)(?:(?=.*rv:)[\s;]+rv:([\.\d\w]+)|\)).+?Gecko.+?Firefox[\s\/]?([\w\d\.]+)?/i', $this->string, $mozilla))
    {
      // Firefox

      $this->browserName = 'Mozilla Firefox';
      $this->browserVersion = isset($mozilla[3]) ? $mozilla[3] : $mozilla[2];

      $this->systemString = $mozilla[1];
    } //						 	1/sys				2/ver 	|OR|		 3/ver 		  4/sys 	if Version defined			  5/ver
    else if (preg_match('/(?:\((.+?)\).+?Chrome\/([\d\.\w]+))|Chrome\/([\d\.\w]+).+?\((.+?)\)(?:(?=.*Version\/).+?Version\/([\d\w\.]+)|.*?)/i', $this->string, $chrome))
    {
      // Chrome

      $this->browserName = 'Google Chrome';
      $this->browserVersion = isset($chrome[5]) ? $chrome[5] : (isset($chrome[3]) ? $chrome[3] : $chrome[2]);

      $this->systemString = isset($chrome[4]) ? $chrome[4] : (isset($chrome[1]) ? $chrome[1] : '');
    } // 							1/sys 	  MUST			if we have Version/			  2/ver 		  if we have Safari/		 3/ver
    else if (preg_match('/\((.+?)\).+?AppleWebKit(?:(?=.*Version\/).*?Version\/([\d\w\.]+)|.*?)(?:(?=.*Safari\/).*?Safari\/([\d\w\.]+)|.*?)/i', $this->string, $safari))
    {
      // Safari

      $this->browserName = /*( $this->isMobile ) ? 'Android Browser' :*/ 'Safari';
      $this->browserVersion = isset($safari[3]) ? $safari[3] : (isset($safari[2]) ? $safari[2] : '');

      $this->systemString = $safari[1];
    }
    else
    {
      // Unknown Browser

      // set System String
      $a = strpos($this->string, '(') + 1;
      $b = strpos($this->string, ')');

      $this->systemString = trim(mb_substr($this->string, $a, $b - $a));
    }
  }

  /**
   * Set Platform and System Properties
   * 
   * @return Void
   */
  private function analyzePlatform()
  {

    // Analyze Platform Information

    if ($this->systemString)
    {
      // Mobile
      if ($this->isMobile)
      {
        // No Arch for Mobiles
        $this->osArch = null;

        //		  					1/ver 						 2/device
        if (preg_match('/Android.*?([\d\.]+)(?:(?=.*?Build).+?\b([\w\d\_\-\s]+)\b\sBuild|.*?)/i', $this->systemString, $info))
        {
          // Android 

          $this->osPlatform = 'Android';
          $this->osVersion = $this->osVersion = $info[1];
          $this->mobileName = isset($info[2]) ? $info[2] : '';

          $this->setOSShortVersionWithout(2);
        } // 							1/device_name 					2/ver
        else if (preg_match('/((?:iPhone)|(?:iPad)|(?:iPod)).+?OS\s([\d\_\w\.]+)/i', $this->systemString, $info))
        {
          // IOS

          $this->osPlatform = 'iOS';
          $this->osVersion = $this->osShortVersion = str_replace('_', '.', $info[2]);
          $this->mobileName = $info[1];
        } // 												1/ver 	 if1 			2/brand 			 if2				3/ Model   else2  else1
        else if (preg_match('/Windows\sPhone\s(?:OS\s)?([\d\_\w\.]+)(?: (?=.*?(NOKIA|SAMSUNG|LG)) .+?\2 (?: (?=.{4,}$) .*?\b([\w\d\-\s]+)\b|.*?  )|.*?)/x', $this->systemString, $info))
        {
          // Windows Phone

          $this->osPlatform = 'Windows Phone';
          $this->osVersion = $this->osShortVersion = str_replace('_', '.', $info[1]);
          $this->mobileName = @$info[2] . ' ' . @$info[3];
        }
      }            // Computer
      else if (strpos($this->systemString, 'Macintosh') !== false)
      {
        // Macintosh
        $this->osPlatform = 'Macintosh';

        if (preg_match('/(\w+)\sMac\sOS\sX\s?([\d_\.]+)?/i', $this->systemString, $info))
        {
          $this->osVersion = isset($info[2]) ? str_replace('_', '.', $info[2]) : '';

          // Parse Short version
          $this->setOSShortVersionWithout(0);
        }
      }            // Windows OR compatible
      else if (strpos($this->systemString, 'Windows') !== false || strpos($this->systemString, 'compatible'))
      {
        // Windows
        $this->osPlatform = 'Windows';

        // 									1/ver
        if (preg_match('/Windows\s(?:NT\s)?([\.\d]+)/i', $this->systemString, $info))
          $this->osShortVersion = $this->osVersion = $info[1];
      }
      else if (strpos($this->systemString, 'X11') !== false || strpos($this->systemString, 'Linux') !== false)
      {
        // Linux
        $this->osPlatform = 'Linux';
      }
    } // End of systemString exists

  }

  /**
   * Set OS Short Version Number, according to excluded version part index
   * 
   * @param Integer $excluded_index excluded version part index, 0 based
   */
  private function setOSShortVersionWithout($excluded_index)
  {
    // Parse Short version

    if ($excluded_index === null)
    {
      $this->osShortVersion = $this->osVersion;
      return;
    }

    $parts = explode('.', $this->osVersion);

    unset($parts[$excluded_index]);

    $this->osShortVersion = implode('.', $parts);
  }

  /**
   * Magic Getter
   *
   * get Protected Properties Value
   * 
   * @param  string $property called Property name
   * @return mixed           property value
   */
  public function __get($property)
  {

    if (property_exists($this, $property))
      return $this->$property;

    else
      throw new \RuntimeException('Property Does not Exists: ' . __CLASS__ . '::' . $property);
  }
}
