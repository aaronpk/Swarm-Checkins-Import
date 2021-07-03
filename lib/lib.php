<?php

function user_agent() {
  return 'https://github.com/aaronpk/Swarm-Checkins-Import';
}

function load_credentials() {
  if(!file_exists(__DIR__.'/../credentials.json')) {
    echo "You need to first add your Foursquare access token in the file credentials.json. See the readme for details\n";
    die();
  }

  $credentials = json_decode(file_get_contents(__DIR__.'/../credentials.json'), true);
  if(!$credentials || !isset($credentials['foursquare_access_token'])) {
    echo "There was a problem reading the credentials file. See the readme for details\n";
    die();
  }

  return $credentials;
}

function load_user() {
  if(!file_exists(__DIR__.'/../checkins/user.json')) {
    echo "You need to first run the init script to load your user profile. See the readme for details\n";
    die();
  }

  $user = json_decode(file_get_contents(__DIR__.'/../checkins/user.json'), true);
  if(!$user || !isset($user['id'])) {
    echo "There was a problem reading the user file. See the readme for details\n";
    die();
  }

  return $user;
}

function load_mp_config() {
  if(!file_exists(__DIR__.'/../checkins/micropub.json')) {
    return null;
  }

  return json_decode(file_get_contents(__DIR__.'/../checkins/micropub.json'), true);
}


function offset_to_timezone($seconds) {
  if($seconds != 0)
    $tz = new DateTimeZone(($seconds < 0 ? '-' : '+')
     . sprintf('%02d:%02d', abs(floor($seconds / 60 / 60)), (($seconds / 60) % 60)));
  else
    $tz = new DateTimeZone('UTC');
  return $tz;
}




class Foursquare {

  public static function checkinToHEntry($checkin, $user) {

    $date = DateTime::createFromFormat('U', $checkin['createdAt']);
    $tz = offset_to_timezone($checkin['timeZoneOffset'] * 60);
    $date->setTimeZone($tz);

    $entry = [
      'type' => ['h-entry'],
      'properties' => [
        'published' => [$date->format('c')],
        'syndication' => ['https://www.swarmapp.com/user/'.$user['id'].'/checkin/'.$checkin['id']],
      ]
    ];

    if(isset($checkin['private']) && $checkin['private']) {
      $entry['properties']['visibility'] = ['private'];
    }

    if(!empty($checkin['shout'])) {
      $text = $checkin['shout'];

      $entry['properties']['content'] = self::_buildHEntryContent($checkin);

      if($entry['properties']['content'] == ['']) {
        unset($entry['properties']['content']);
      }

      // Include hashtags
      if(preg_match_all('/\B\#(\p{L}+\b)/u', $text, $matches)) {
        $entry['properties']['category'] = $matches[1];
      }
    }

    if(!empty($checkin['photos']['items'])) {
      $photos = [];
      foreach($checkin['photos']['items'] as $p) {
        $photos[] = $p['prefix'].'original'.$p['suffix'];
      }
      $entry['properties']['photo'] = $photos;
    }

    $venue = $checkin['venue'];

    // Include person tags
    if(isset($checkin['with'])) {
      if(!isset($entry['properties']['category']))
        $entry['properties']['category'] = [];

      foreach($checkin['with'] as $with) {
        // Check our users table to find the person's website if they use OwnYourSwarm
        $withHCard = self::foursquareUserToHCard($with);
        $entry['properties']['category'][] = $withHCard;
      }
    }

    $hcard = [
      'type' => ['h-card'],
      'properties' => [
        'name' => [$venue['name']],
        'url' => ['https://foursquare.com/v/'.$venue['id']],
      ]
    ];
    $hcard['value'] = $hcard['properties']['url'][0];

    if(isset($venue['url']) && $venue['url']) {
      $hcard['properties']['url'][] = $venue['url'];
    }

    if(isset($venue['contact'])) {
      if(isset($venue['contact']['twitter'])) {
        $hcard['properties']['url'][] = 'https://twitter.com/'.$venue['contact']['twitter'];
      }
      if(isset($venue['contact']['formattedPhone'])) {
        $hcard['properties']['tel'] = [$venue['contact']['formattedPhone']];
      }
    }

    # Map Foursquare properties to h-card properties
    $props = [
      'latitude' => 'lat',
      'longitude' => 'lng',
      'street-address' => 'address',
      'locality' => 'city',
      'region' => 'state',
      'country-name' => 'country',
      'postal-code' => 'postalCode',
    ];

    foreach($props as $k=>$v) {
      if(isset($venue['location'][$v])) {
        $hcard['properties'][$k] = [$venue['location'][$v]];
      }
    }

    $entry['properties']['checkin'] = [$hcard];

    # Include a location property with h-adr with everything except venue information
    if(isset($hcard['properties']['latitude'])) {
      $hadr = $hcard;
      $hadr['type'] = ['h-adr'];
      unset($hadr['properties']['name']);
      unset($hadr['properties']['url']);
      unset($hadr['properties']['tel']);
      unset($hadr['value']);
      $entry['properties']['location'] = [$hadr];
    }

    # If someone else checked you in, add that as a new property
    if(isset($checkin['createdBy']) && $checkin['createdBy']['id'] != $user['id']) {
      $entry['properties']['checked-in-by'] = [self::foursquareUserToHCard($checkin['createdBy'])];
    }

    return $entry;
  }


  private static function _buildHEntryContent($checkin) {
    $text = $checkin['shout'];

    if(isset($checkin['with'])) {
      // Remove "with X" if that is the only text in the shout
      $withStr = 'with ';
      foreach($checkin['with'] as $i=>$with) {
        $withStr .= ($i == 0 ? $with['firstName'] : ', '.$with['firstName']);
      }
      if(trim($text) == trim($withStr)) {
        $text = '';
      }
    }

    $html = $text;
    if($text && $checkin['entities']) {
      $html = self::replaceLinkedEntities($html, $checkin['entities']);
    }

    if($text == $html) {
      $content = [$text];
    } else {
      $content = [
        ['value'=>$text, 'html'=>$html]
      ];
    }

    return $content;
  }

  public static function checkinHasContent($checkin) {
    $shout = isset($checkin['shout']) ? $checkin['shout'] : '';
    if($shout) {
      $shout = preg_replace('/^with .+$/', '', $shout);
    }
    return !empty($checkin['photos']['items']) || $shout;
  }

  private static function replaceLinkedEntities($text, $entities) {
    // Encode the string as JSON, which turns it into a string like
    // "Snack \ud83d\udc69\ud83c\udffb\u200d\ud83c\udfa4 with Asha"
    $json = json_encode($text);
    // Split the JSON-encoded string to separate all the \uXXXX characters
    if(preg_match_all('/(\\\u[a-h0-9]{4}|\\\"|.)/', trim($json,'"'), $matches)) {
      $chars = $matches[0];
    }

    $offsets = []; // Keep track of which offsets have been modified
    foreach($entities as $entity) {
      if($entity['type'] == 'user') {
        $s = $entity['indices'][0];
        $e = $entity['indices'][1];

        $offsets[] = $s;
        $offsets[] = $e-1;

        // Replace the text at the start and end offset with a hyperlink
        $chars[$s] = json_encode('<a href=\"' . self::url_for_user($entity['id']) . '\">' . $chars[$s]);
        $chars[$e-1] = json_encode($chars[$e-1] . '</a>');
      }
    }

    $json = '';
    // Put the JSON string back together
    foreach($chars as $i=>$c) {
      if(in_array($i, $offsets)) {
        $json .= json_decode($c);
      } else {
        $json .= $c;
      }
    }

    // JSON decode the string to get the final result
    $html = json_decode('"'.$json.'"');

    return $html;
  }

  private static function url_for_user($id) {
    return 'https://foursquare.com/user/'.$id;
  }

  public static function foursquareUserToHCard($user) {
    $person_urls = ['https://foursquare.com/user/'.$user['id']];
    return [
      'type' => ['h-card'],
      'properties' => [
        'url' => $person_urls,
        'name' => [$user['firstName']],
        'photo' => [$user['photo']['prefix'].'300x300'.$user['photo']['suffix']]
      ],
      'value' => $person_urls[0]
    ];
  }

}
