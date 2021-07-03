<?php
chdir(__DIR__);
require('vendor/autoload.php');

$credentials = load_credentials();
$access_token = $credentials['foursquare_access_token'];

$params = [
  'v' => '20170319',
  'oauth_token' => $access_token,
];

$url = 'https://api.foursquare.com/v2/users/self?'.http_build_query($params);

$http = new p3k\HTTP();
$headers = [
  'User-Agent: '.user_agent(),
];
$response = $http->get($url, $headers);
$info = json_decode($response['body'], true);

if(isset($info['response']['user'])) {
  $user = $info['response']['user'];

  file_put_contents('checkins/user.json', json_encode($user, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
  echo "Saved user info to checkins/user.json\n";
}


$headers = [
  'User-Agent: '.user_agent(),
  'Authorization: Bearer '.$credentials['micropub_access_token'],
];
$response = $http->get($credentials['micropub_endpoint'].'?q=config', $headers);
$mpconfig = json_decode($response['body'], true);

if(!$mpconfig) {
  echo "Error fetching Micropub config info\n";
} else {
  file_put_contents('checkins/micropub.json', json_encode($mpconfig, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
  echo "Saved Micropub config to checkins/micropub.json\n";
}

