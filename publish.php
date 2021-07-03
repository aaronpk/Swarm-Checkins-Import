<?php
chdir(__DIR__);
require('vendor/autoload.php');

$credentials = load_credentials();
$user = load_user();
$mpconfig = load_mp_config();

$all = false;
if(isset($argv[1]) && $argv[1] == '--all')
  $all = true;

$checkins = glob('checkins/*/*/*/*.json');

foreach($checkins as $filename) {

  echo $filename."\n";

  // Check if there is a .txt file that means this has already been imported
  $txt_filename = str_replace('.json', '.txt', $filename);
  if(file_exists($txt_filename)) {
    echo "\tAlready published\n\n";
    continue;
  }

  $checkin = json_decode(file_get_contents($filename), true);

  $entry = Foursquare::checkinToHEntry($checkin, $user);

  $photos = [];

  // If they have a Media endpoint, upload photos there and replace the photo URLs with their own
  if(isset($mpconfig['media-endpoint'])) {
    if(isset($entry['properties']['photo'])) {

      foreach($checkin['photos']['items'] as $photo) {
        $photo_filename = dirname($filename).'/'.$photo['id'].'.jpg';
        echo "\tUploading photo to media endpoint: ".$photo_filename."\n";
        $response = media_post($photo_filename, $mpconfig['media-endpoint'], $credentials['micropub_access_token']);
        if(isset($response['headers']['Location'])) {
          $photos[] = $response['headers']['Location'];
          echo "\t".$response['headers']['Location']."\n";
        } else {
          echo "\tError uploading photo\n";
        }
      }

      $entry['properties']['photo'] = $photos;
    }
  }

  $response = micropub_post($entry, $credentials['micropub_endpoint'], $credentials['micropub_access_token']);

  if(!in_array($response['code'], ['200', '201', '202'])) {
    echo "There was an error publishing this checkin\n";
  }

  if(isset($response['headers']['Location'])) {
    echo $response['headers']['Location']."\n\n";

    $status = $response['headers']['Location']."\n"
      .implode("\n", $photos)."\n";
    file_put_contents($txt_filename, $status);

  } else {
    echo "No Location was returned from the Micropub endpoint. This checkin was not published.\n";
    print_r($response);
  }

  if(!$all) {
    echo "Stopping after one checkin. To import all, run again with --all\n";
    die();
  }
}



function media_post($filename, $endpoint, $token) {
  $multipart = new p3k\Multipart();
  $multipart->addFile('file', $filename, 'image/jpeg');

  $http = new p3k\HTTP();
  $headers = [
    'User-Agent: https://github.com/aaronpk/Swarm-Checkins-Import',
    'Authorization: Bearer '.$token,
    'Content-Type: '.$multipart->contentType(),
  ];
  $body = $multipart->data();
  $response = $http->post($endpoint, $body, $headers);

  return $response;
}


function micropub_post($params, $endpoint, $token) {
  $http = new p3k\HTTP();
  $headers = [
    'User-Agent: https://github.com/aaronpk/Swarm-Checkins-Import',
    'Authorization: Bearer '.$token,
    'Content-Type: application/json',
  ];
  $body = json_encode($params, JSON_UNESCAPED_SLASHES);
  $response = $http->post($endpoint, $body, $headers);

  return $response;
}

