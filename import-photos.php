<?php
chdir(__DIR__);
require('vendor/autoload.php');

$checkins = glob('checkins/*/*/*/*.json');

foreach($checkins as $filename) {

  $checkin = json_decode(file_get_contents($filename), true);

  if(isset($checkin['photos']['items'])) {
    foreach($checkin['photos']['items'] as $photo) {

      process_photo($filename, $checkin, $photo);

    }
  }

}


function process_photo($filename, $checkin, $photo) {

  $folder = dirname($filename);
  $photo_filename = $folder . '/' . $photo['id'] . '.jpg';

  echo $folder."\n";

  $url = $photo['prefix'] . 'original' . $photo['suffix'];
  echo $url."\n";


  $fp = fopen($photo_filename, 'w');

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_exec($ch);
  curl_close($ch);

  echo "Saved to $photo_filename\n\n";
}
