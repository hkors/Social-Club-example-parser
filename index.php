<?php
/* Functions */

/**
 * Function to print out possible debug
 * information.
 * @param  {String} $msg input message
 */
function debug($msg){
  global $config;

  if ($config->debug) {
    echo('[DEBUG] ' . $msg . PHP_EOL);
  }
}

/**
 * Function to print out possible trace
 * information and prepend a [TRACE]
 * prefix.
 * @param  {String} $msg input message
 */
function trace($msg){
  global $config;

  if ($config->trace) {
    $arr = explode("\n", $msg);

    foreach ($arr as $key => $value) {
      $arr[$key] = '[TRACE] ' . $arr[$key];
    }

    $msg = implode(PHP_EOL, $arr);
    echo($msg);
  }
}

/**
 * Function to die in case of possible errors.
 * @param  {String} $msg input message
 */
function error($msg){
  die('[ERROR] ' . $msg . PHP_EOL);
}

/**
 * Check for existing cookie jar, will return
 * 'true' if the file is found.
 * @return {BOOL}
 */
function checkExistingCookieJar(){
  if (file_exists('cookie_jar.txt')) {
    debug('[checkExistingCookieJar] Found existing cookie file.');
    return true;
  } else {
    debug('[checkExistingCookieJar] Couldn\'t find existing cookie file.');
    return false;
  }
}

/**
 * Check input string for possible captcha, will
 * return 'false' if there was no captacha request
 * detected, otherwise it'll die with an error msg.
 * @param {String} $input
 * @return {BOOL}
 */
function checkForCaptchaRequest($input){
  if (strpos($input, "showRecaptcha: true")) {
    debug('[checkForCaptchaRequest] Captcha request in $input variable detected.');
    trace('[checkForCaptchaRequest] <!-- START -->');
    trace('[checkForCaptchaRequest] '.$input);
    trace('[checkForCaptchaRequest] <!-- STOP -->');
    error('[checkForCaptchaRequest] Captcha request detected. Sign into SocialClub using a desktop browser from this machine to please ReCaptcha. Then retry using this parser.');
    return true;
  } else {
    debug('[checkForCaptchaRequest] No captcha request detected.');
    // Check for possible login errors
    if (strpos($input, "gError = 'There has been an error, please retry!'")) {
      debug('[checkForCaptchaRequest] Found an unexpected error in $input variable.');
      trace('[checkForCaptchaRequest] <!-- START -->');
      trace('[checkForCaptchaRequest] '.$input);
      trace('[checkForCaptchaRequest] <!-- STOP -->');
      error('[checkForCaptchaRequest] Unexpected error. Wrong credentials?');
      return true;
    } else {
      return false;
    }
  }
}

/**
 * Check for empty data, will return 'true'
 * if empty/fake data was detected
 * @param {String} $input
 * @return {BOOL}
 */
function checkForEmptyData($input){
  if (strpos($input, "Play Time: 0h 0m 0s")) {
    debug('[checkForEmptyData] Empty/fake data detected.');
    return true;
  } else {
    debug('[checkForEmptyData] No empty/fake data detected.');
    return false;
  }
}

/**
 * Function to sign into SocialClub and store the authorized
 * cookie in our cookie jar. Will return 'true' if the
 * authentication was successful.
 * @return {Bool} State of authentication
 */
function renewAuthentication() {
  global $defaultHeaders, $username, $password;
  /* Request to parse __RequestVerificationToken */

  // Initiate curl request
  debug('[renewAuthentication] Sending request to parse the login form.');
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,"http://socialclub.rockstargames.com/");
  curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie_jar.txt");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch, CURLOPT_ENCODING , "");
  curl_setopt($ch, CURLOPT_HTTPHEADER,
    array_merge(
      $defaultHeaders
    )
  );

  // Store buffer and unset curl variables
  $buffer = curl_exec($ch);
  curl_close ($ch);
  unset($ch);

  // Store __RequestVerificationToken
  $parsed_rvt = str_get_html($buffer)->find('input[name=__RequestVerificationToken]', 0)->value;
  debug('[renewAuthentication] Received RequestVerificationToken: \''.$parsed_rvt.'\'.');

  /* Request to sign in and store authorization cookie */

  // Initiate curl request
  debug('[renewAuthentication] Sending authentication request.');
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie_jar.txt");
  curl_setopt($ch, CURLOPT_URL,"https://socialclub.rockstargames.com/profile/signin");
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie_jar.txt");
  curl_setopt($ch, CURLOPT_COOKIEFILE, "cookie_jar.txt");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch, CURLOPT_ENCODING , "");
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, "login=".$username."&password=".$password."&__RequestVerificationToken=".$parsed_rvt);
  curl_setopt($ch, CURLOPT_HTTPHEADER,
    array_merge(
      $defaultHeaders,
      array(
        'Content-Type: application/x-www-form-urlencoded'
      )
    )
  );

  // Store buffer and unset curl variables
  $buffer = curl_exec ($ch);
  curl_close ($ch);
  unset($ch);

  // Check if there is no captcha request by R*
  if (!checkForCaptchaRequest($buffer)) {
    debug('[renewAuthentication] Successfuly authenticated.');
    return true;
  }
}

/**
 * Function to request to get actual informations using
 * authorized session in the cookie file
 * @return {String} SocialClub response
 */
function parseActualInformation(){
  global $target, $defaultHeaders;

  // Initiate curl request
  debug('[parseActualInformation] Sending request to parse actual data.');
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch, CURLOPT_ENCODING , "gzip");
  curl_setopt($ch, CURLOPT_COOKIEFILE, "cookie_jar.txt");
  curl_setopt($ch, CURLOPT_URL,"http://socialclub.rockstargames.com/games/gtav/career/overviewAjax?character=Freemode&nickname=".$target."&slot=Freemode&gamerHandle=&gamerTag=&_=".time()."000");
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER,
    array_merge(
      $defaultHeaders,
      array(
        'Accept-Encoding: gzip, deflate',
      )
    )
  );
  $buffer = curl_exec ($ch);
  curl_close ($ch);
  unset($ch);

  return $buffer;
}

/**
 * Calculate the actual percentage of a progress bar stat
 * @param  {String} $domData The input DOM
 * @param  {String} $statsType The type of stat to look for
 * @return {Integer} The average value in percent
 */
function calculateProgressbarStats($domData, $statsType){
  // Find <li> that contains the actual percentages of our progressbar stat
  $statsDom = str_get_html($domData)->find('h5[plaintext^='.$statsType.']', 0)->next_sibling();
  $percentages = 0;

  // For each progress bar part (5 parts)
  for ($i=0; $i < 5; $i++) {
    // Parse and substitute actual value
    $value = $statsDom->find('span',$i)->plaintext;
    $value = str_replace('% ', '', $value);
    $value = ($value) ? $value : '0';
    // Add to $percentages variable
    $percentages += $value;
  }

  // Get average by dividing through 5
  return $percentages / 5;
}

/**
 * Constructs the array for all recent activities
 * @param  {String} $domData The input DOM
 * @return {Array} Constructed array including activities
 */
function getRecentActivity($domData){
  // Store partial DOM
  $activities = str_get_html($domData)->find('#recentActivity ul li[data-type=award]');
  $array = [];

  foreach($activities as $activity) {
     // Parse information
    $subArray = array(
      'name' => (string)$activity->{'data-name'},
      'type' => (string)$activity->{'data-award'},
      'image' => (string)$activity->find('img', 0)->src
    );
    // And store in main $array
    array_push($array, $subArray);
  }

  // Return array
  return $array;
}

/* Core */

// Require SimpleHTMLDOM library
require('lib/simplehtmldom.php');

// Set default headers
$defaultHeaders = array(
  'Pragma: no-cache',
  'Accept-Encoding: gzip, deflate, sdch',
  'Accept-Language: en-US,en;q=0.8,en-US;q=0.6,en;q=0.4',
  'Upgrade-Insecure-Requests: 1',
  'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2460.0 Safari/537.36',
  'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
  'Cache-Control: no-cache',
  'Connection: keep-alive'
);

// Check for config file
if (!file_exists('config.json')) {
  error('Error: No configuration file found. Make sure to copy the default one to \'config.json\'');
} else {
  $config = json_decode(file_get_contents('config.json'));
  debug('Configuration file loaded.');
}

// Load credentials from config
$username = $config->username;
$password = $config->password;

// In case there is no default TZ, set one
if (!@date_default_timezone_get()) {
  date_default_timezone_set("Europe/Berlin");
}

// Check for possible "target" argument
$target = (isset($argv[1])) ? $argv[1] : "";

// Authenticate if there is no cookie jar file
if (!checkExistingCookieJar()){
  renewAuthentication();
}

// Try to parse data using existing cookie file
$data = parseActualInformation();

// If the string contains empty/fake data ...
if (checkForEmptyData($data)) {
  // ... try to renew the authentication
  if (renewAuthentication()) {
    // ... and actually parse the informations again
    $data = parseActualInformation();
  }
}

// Check for occurance of sectionBlockedStats container
if (!empty(str_get_html($data)->find('#sectionBlockedStats'))) {
  error('Error: Player/target is not sharing game stats.');
}

// Parse data and store in temporary object
$parsed = array(
  'general' => array(
    'rank' => (int)str_get_html($data)->find('.rankHex h3', 0)->innertext,
    'xp' => (string)str_replace(' RP ', '', str_get_html($data)->find('.rankXP .clearfix .left', 0)->plaintext),
    'play-time' => (string)str_replace('Play Time: ', '', str_get_html($data)->find('.rankBar h4', 0)->innertext),
    'money' => array(
      'cash' => (string)str_get_html($data)->find('#cash-value', 0)->innertext,
      'bank' => (string)str_get_html($data)->find('#bank-value', 0)->innertext
    )
  ),
  'crew' => array(
    'name' => (string)str_get_html($data)->find('.crewCard .clearfix .left h3 a', 0)->plaintext,
    'tag' => (string)trim(str_get_html($data)->find('.crewCard .clearfix .left .crewTag span', 0)->plaintext),
    'emblem' => (string)str_get_html($data)->find('.crewCard .clearfix .avatar', 0)->src
  ),
  'freemode' => array(
    'races' => array(
      'wins' => (int)str_get_html($data)->find('p[data-name=Races]', 0)->{'data-win'},
      'losses' => (int)str_get_html($data)->find('p[data-name=Races]', 0)->{'data-loss'},
      'time' => (string)str_get_html($data)->find('p[data-name=Races]', 0)->{'data-extra'}
    ),
    'deathmatches' => array(
      'wins' => (int)str_get_html($data)->find('p[data-name=Deathmatches]', 0)->{'data-win'},
      'losses' => (int)str_get_html($data)->find('p[data-name=Deathmatches]', 0)->{'data-loss'},
      'time' => (string)str_get_html($data)->find('p[data-name=Deathmatches]', 0)->{'data-extra'}
    ),
    'parachuting' => array(
      'wins' => (int)str_get_html($data)->find('p[data-name=Parachuting]', 0)->{'data-win'},
      'losses' => (int)str_get_html($data)->find('p[data-name=Parachuting]', 0)->{'data-loss'},
      'perfect-landing' => (int)str_get_html($data)->find('p[data-name=Parachuting]', 0)->{'data-extra'}
    ),
    'darts' => array(
      'wins' => (int)str_get_html($data)->find('p[data-name=Darts]', 0)->{'data-win'},
      'losses' => (int)str_get_html($data)->find('p[data-name=Darts]', 0)->{'data-loss'},
      'six-darter' => (int)str_get_html($data)->find('p[data-name=Darts]', 0)->{'data-extra'}
    ),
    'tennis' => array(
      'wins' => (int)str_get_html($data)->find('p[data-name=Tennis]', 0)->{'data-win'},
      'losses' => (int)str_get_html($data)->find('p[data-name=Tennis]', 0)->{'data-loss'},
      'aces' => (int)str_get_html($data)->find('p[data-name=Tennis]', 0)->{'data-extra'}
    ),
    'golf' => array(
      'wins' => (int)str_get_html($data)->find('p[data-name=Golf]', 0)->{'data-win'},
      'losses' => (int)str_get_html($data)->find('p[data-name=Golf]', 0)->{'data-loss'},
      'hole-in-one' => (bool)(str_get_html($data)->find('p[data-name=Golf]', 0)->{'data-extra'} === 'No') ? false : true,
    ),
  ),
  'money' => array(
    'total' => array(
      'spent' => (string)str_get_html($data)->find('#cashSpent p', 0)->plaintext,
      'earned' => (string)str_get_html($data)->find('#cashEarned p', 0)->plaintext
    ),
    'earnedby' => array(
      'jobs' => (string)str_get_html($data)->find('.cash-val[data-name=Jobs]', 0)->{'data-cash'},
      'shared' => (string)str_get_html($data)->find('.cash-val[data-name=Shared]', 0)->{'data-cash'},
      'betting' => (string)str_get_html($data)->find('.cash-val[data-name=Betting]', 0)->{'data-cash'},
      'car-sales' => (string)str_get_html($data)->find('.cash-val[data-name=Car Sales]', 0)->{'data-cash'},
      'picked-up' => (string)str_get_html($data)->find('.cash-val[data-name=Picked Up]', 0)->{'data-cash'},
      'other' => (string)str_get_html($data)->find('.cash-val[data-name=Other]', 0)->{'data-cash'}
    )
  ),
  'stats' => array(
    'stamina' => (int)calculateProgressbarStats($data, 'Stamina'),
    'stealth' => (int)calculateProgressbarStats($data, 'Stealth'),
    'lung-capacity' => (int)calculateProgressbarStats($data, 'Lung Capacity'),
    'flying' => (int)calculateProgressbarStats($data, 'Flying'),
    'shooting' => (int)calculateProgressbarStats($data, 'Shooting'),
    'strength' => (int)calculateProgressbarStats($data, 'Strength'),
    'driving' => (int)calculateProgressbarStats($data, 'Driving'),
    'mental-state' => (int)calculateProgressbarStats($data, 'Mental State')
  ),
  'criminalrecord' => array(
    'cops-killed' => (string)str_get_html($data)->find('h5[plaintext^=Cops killed]', 0)->next_sibling()->plaintext,
    'wanted-stars' => (string)str_get_html($data)->find('h5[plaintext^=Wanted stars attained]', 0)->next_sibling()->plaintext,
    'time-wanted' => (string)str_get_html($data)->find('h5[plaintext^=Time Wanted]', 0)->next_sibling()->plaintext,
    'stolen-vehicles' => (string)str_get_html($data)->find('h5[plaintext^=Vehicles Stolen]', 0)->next_sibling()->plaintext,
    'cars-exported' => (string)str_get_html($data)->find('h5[plaintext^=Cars Exported]', 0)->next_sibling()->plaintext,
    'store-holdups' => (string)str_get_html($data)->find('h5[plaintext^=Store Hold Ups]', 0)->next_sibling()->plaintext
  ),
  'favourite-weapon' => array(
    'name' => (string)str_get_html($data)->find('#faveWeaponWrapper .imageHolder h4', 0)->plaintext,
    'image' => (string)str_get_html($data)->find('#faveWeaponWrapper .imageHolder img', 0)->src,
    'stats' => array(
      'damage' => (int)str_replace('% ', '', str_get_html($data)->find('.weaponStats tr span', 0)->plaintext),
      'fire-rate' => (int)str_replace('% ', '', str_get_html($data)->find('.weaponStats tr span', 1)->plaintext),
      'accuracy' => (int)str_replace('% ', '', str_get_html($data)->find('.weaponStats tr span', 2)->plaintext),
      'range' => (int)str_replace('% ', '', str_get_html($data)->find('.weaponStats tr span', 3)->plaintext),
      'clip-size' => (int)str_replace('% ', '', str_get_html($data)->find('.weaponStats tr span', 4)->plaintext)
    ),
    'kills' => (int)str_get_html($data)->find('h5[plaintext^=Kills]', 0)->next_sibling()->plaintext,
    'headshots' => (int)str_get_html($data)->find('h5[plaintext^=Headshots]', 0)->next_sibling()->plaintext,
    'accuracy' => (float)str_replace('%', '', str_get_html($data)->find('h5[plaintext^=Accuracy]', 0)->next_sibling()->plaintext),
    'time-held' => (string)str_get_html($data)->find('h5[plaintext^=Time held]', 0)->next_sibling()->plaintext
  ),
  'recent-activity' => getRecentActivity($data)
);

// Encode data object as JSON and print out
echo json_encode($parsed, JSON_PRETTY_PRINT);

// Exit without errors
exit(0);
