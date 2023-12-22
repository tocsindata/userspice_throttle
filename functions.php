<?php
//Please don't load functions system-wide if you don't need them system-wide.
// To make your plugin more efficient on resources, consider only loading resources that need to be loaded when they need to be loaded.
// For instance, you can do
// $currentPage = currentPage();
// if($currentPage == 'admin.php'){ //The administrative dashboard
//   bold("<br>See! I am only loading this when I need it!");
// }
// // Also, please wrap your functions in if(!function_exists())
// if(!function_exists('demoFunction')) {
//   function demoFunction(){ }
// }

function Throttle(){
global $db_table_prefix ;
global $settings ;
$db = DB::getInstance();
$ip = getVisitorIp();
if(!is_null($settings->throttle)) {
$throttle_settings = json_decode($settings->throttle, true) ;
} else {
$throttle_settings = array();
$throttle_settings['minutes'] = 5; // 5 = 5 minutes
$throttle_settings['max_visits'] = 300; // 300 = 300 visits per 5 minutes max
$throttle_settings['whitelist'] = array("127.0.0.1", "::1"); // IPs that should always be in the whitelist both IPv4 and IPv6 accepted 127.0.0.1 ... ::1 localhost should always be in this list

$sql = "UPDATE `settings` SET `throttle` = '".json_encode($throttle_settings)."' WHERE `settings`.`id` = ".$settings->id.";";
$db->query($sql) ;
}

$sql = "DELETE FROM `".$db_table_prefix."trottle`
WHERE whitelisted = 0 AND timestamp < NOW() - INTERVAL ".$throttle_settings['minutes']." MINUTE;";
$db->query($sql) ;

$sql = "DELETE FROM `".$db_table_prefix."trottle`
WHERE whitelisted = 1 AND timestamp < NOW() - INTERVAL 30 DAY;";
$db->query($sql) ;

foreach($throttle_settings['whitelist'] as $whiteip) {
$sql = "INSERT INTO `".$db_table_prefix."trottle` (`ip`, `whitelisted`, `timestamp`, `visits`, `throttled`) 
        VALUES ('".$whiteip."', '1', CURRENT_TIMESTAMP, '1', '0') 
        ON DUPLICATE KEY UPDATE `visits` = `visits` + 1,
            `whitelisted` = '1';";
$db->query($sql) ;
}

if(hasPerm(2)) {
$sql = "INSERT INTO `".$db_table_prefix."trottle` (`ip`, `whitelisted`, `timestamp`, `visits`, `throttled`) 
        VALUES ('".$ip."', '0', CURRENT_TIMESTAMP, '".$whitelist."', '0') 
        ON DUPLICATE KEY UPDATE `visits` = '0',
            `whitelisted` = '1';";
} else {
$sql = "INSERT INTO `".$db_table_prefix."trottle` (`ip`, `whitelisted`, `timestamp`, `visits`, `throttled`) 
        VALUES ('".$ip."', '0', CURRENT_TIMESTAMP, '".$whitelist."', '0') 
        ON DUPLICATE KEY UPDATE `visits` = `visits` + 1 ;";
}
$db->query($sql) ;

$sql = "UPDATE `".$db_table_prefix."trottle` SET `visits` = '0' WHERE `whitelisted` = 1";
$db->query($sql) ;

$sql = "UPDATE `".$db_table_prefix."trottle` SET `throttled` = '1' WHERE `whitelisted` = 0 AND `visits` > ".(int) $throttle_settings['max_visits']." AND  `".$db_table_prefix."trottle`.`ip` = '".$ip."';";
$db->query($sql) ;

if(ThrottleVisitor($throttle_settings, $ip)) {
echo '
<html><head></head><body>
<h1>Rate Limit Exceeded</h1>

<p>Sorry, you\'ve reached the limit for the number of requests allowed within a certain time frame. Please wait a moment and try again later. If the issue persists, consider checking your usage or contact support for assistance.</p>

<p>Thank you for your understanding.</p></body></html>
';
exit();
}

return ;
}

function ThrottleVisitor($throttle_settings, $ip) {
global $db_table_prefix ;
global $settings ;
$db = DB::getInstance();
$throttle = 0 ;
		$sql = "SELECT COUNT(*) as `throttle` FROM `".$db_table_prefix."trottle` WHERE `ip` LIKE '".$ip."' AND `throttled` = 1";
		$db->query($sql);
		foreach ($db->results() as $record) {
		$throttle = $record->throttle ;
		}
	
		if($throttle == 1) {
		return true ;
		}
return false;
}

function getVisitorIp() {
    $ip = '';

    // Check for IPv6 address first
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // If the IP is a list (due to proxies), take the first IP
    if (strpos($ip, ',') !== false) {
        $ipList = explode(',', $ip);
        $ip = trim($ipList[0]);
    }

    // Validate and sanitize the IP address
    $ip = filter_var($ip, FILTER_VALIDATE_IP);

    return addslashes($ip);
}

function ThrottleForm($throttle_settings) {
global $db_table_prefix ;
global $settings ;
$db = DB::getInstance();

if(!empty($_POST)){
    $token = $_POST['csrf'];
    if(!Token::check($token)){
      include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
    }

    $fields = [
      'tmin'=>Input::get('tmin'),
      'tvis'=>Input::get('tvis'),
      'whiteip'=>Input::get('whiteip'),
    ];


$throttle_settings = array();
$throttle_settings['minutes'] = (int) $fields['tmin']; // 5 = 5 minutes
$throttle_settings['max_visits'] = (int) $fields['tvis']; // 300 = 300 visits per 5 minutes max
$throttle_settings['whitelist'] = array("127.0.0.1", "::1"); 
$tmp = $fields['whiteip'] ;
foreach($tmp as $new_ip) {
		if(!empty($new_ip) && isValidIP($new_ip)) {
		$throttle_settings['whitelist'][] = $new_ip ;
		}
}
	$throttle_settings['whitelist'] = array_unique($throttle_settings['whitelist']);
	$json = json_encode($throttle_settings);
   $db->update('settings',1,['throttle'=>$json]); // future proof with $settings->id for multi-sites for 1?
   Redirect::to('admin.php?view=plugins_config&plugin=throttle&msg=Settings saved');
  }

$out = '
<p><b>Current setting allows a visitor to the site '.$throttle_settings['max_visits'].' times every '.$throttle_settings['minutes'].' minutes, based on IP.</b></p>

<form class="form" method="post">
<input type="hidden" name="csrf" value="'.Token::generate().'">
  <div class="form-group row">
    <label for="text1" class="col-2 col-form-label" >Minutes</label> 
    <div class="col-2">
      <input id="tmin" name="tmin" type="number" class="form-control" value="'.$throttle_settings['minutes'].'" min="5" max="4320">
    </div>
  </div>
  <div class="form-group row">
    <label for="text" class="col-2 col-form-label">Visits</label> 
    <div class="col-2">
      <input id="tvis" name="tvis" type="number" class="form-control" value="'.$throttle_settings['max_visits'].'" min="300" max="5000">
    </div>
  </div> '.WhiteListIPForm($throttle_settings).'
  <div class="form-group row">
    <div class="offset-2 col-2">
      <button name="submit" type="submit" class="btn btn-primary">Submit</button>
    </div>
  </div>
</form>' ;

return $out ;
}

function WhiteListIPForm($throttle_settings) {

$out = '<h3>White List IPs:</h3>' ;
$i= 1 ;
$whitelist_ips = $throttle_settings['whitelist'] ;
foreach($whitelist_ips as $wip){
$out .= '
  <div class="form-group row">
    <label class="col-2"></label> 
    <div class="col-2">
      <input id="whiteip-'.$i.'" name="whiteip['.$i.']" type="text" class="form-control" value="'.$wip.'">
    </div>
  </div>
' ;
$i++ ;
}

$out .= '
  <div class="form-group row">
    <label class="col-2"></label> 
    <div class="col-2">
      <input id="whiteip-'.$i.'" name="whiteip['.$i.']" type="text" class="form-control" placeholder="new IP" value="">
    </div>
  </div>
' ;

return $out ;
}

function isValidIP($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return true;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return true;
    }
    return false;
}

