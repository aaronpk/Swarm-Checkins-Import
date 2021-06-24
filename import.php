<?php
require('vendor/autoload.php');


if(file_exists('checkins.json')) {
  $info = json_decode(file_get_contents('checkins.json'), true);
} else {

  $access_token = '';

  $params = [
    'v' => '20170319',
    'oauth_token' => $access_token,
    'beforeTimestamp' => strtotime('2016-09-02T08:00:00-0700'),
    'limit' => 250,
    'offset' => 0,
  ];

  $url = 'https://api.foursquare.com/v2/users/self/checkins?'.http_build_query($params);

  $http = new p3k\HTTP();
  $headers = [
    'User-Agent: https://aaronparecki.com/'
  ];
  $response = $http->get($url, $headers);
  $info = json_decode($response['body'], true);

  #file_put_contents('checkins.json', $response['body']);
}


#print_r($info);



if(isset($info['response']['checkins']['items'])) {

  foreach($info['response']['checkins']['items'] as $item) {

    process_checkin($item);

  }

} else {
  echo "Error fetching checkins\n";
}


function process_checkin($item) {

  echo "\n";
  #print_r($item);

  $date = DateTime::createFromFormat('U', $item['createdAt']);
  $offset = $item['timeZoneOffset'] / 60;
  if($offset >= 0)
    $offset = '+'.$offset;
  $date->setTimeZone(new DateTimeZone($offset));

  echo $date->format('c')."\n";

  $filename = 'checkins/' . $date->format('Y/m/d/His') . '.json';
  $folder = dirname($filename);

  echo $filename."\n";
  echo $item['venue']['name']."\n";

  @mkdir($folder, 0755, true);

  if(file_exists($filename)) {
    echo "Already imported $filename\n";
    return;
  }

  file_put_contents($filename, json_encode($item, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
}

