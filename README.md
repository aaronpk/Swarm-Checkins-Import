Swarm Checkins Import
=====================

Import your Foursquare/Swarm checkins to your Micropub-enabled website


## Installation

You'll need PHP and [Composer](https://getcomposer.org) to install this project.

From the project folder, run:

```
composer install
```


## Usage

There are three parts to the full flow:

* Import checkins from Foursquare
* Import photos from Foursquare
* Publish checkins to your website via Micropub


### Authenticate

You'll need to get an access token to fetch your data from Foursquare, as well as an access token to publish to your website. You can do this manually yourself, or you can use OwnYourSwarm to get both access tokens.

Log in to <https://ownyourswarm.p3k.io> and set it up as if you were going to use it to import your future checkins.

Then visit this page:

<https://ownyourswarm.p3k.io/user/details>

This page will show you your Foursquare and Micropub access tokens. Copy the configuration from the lower text area and save it in a file called `credentials.json`.

If you don't want OwnYourSwarm to automatically publish future checkins to your website, click the "Disconnect Foursquare" button at the bottom of your dashboard page.


### Import


Run the initial import script which will create JSON files for each of your Swarm checkins in the `checkins` folder.

```
php import-checkins.php
```

This will start with the most recent checkin and page back through your account until it can't find any more checkins.

Next, you'll want to download all the photos from the checkins as well. This will take considerably longer than the first one which is why it's a separate step.

```
php import-photos.php
```


### Publish





## Storage Format

Checkins are saved as JSON files in the `checkins` folder, in subfolders for the year/month/day. The filename is the hour/minute/second of the checkin, in local time to the checkin. For example:

```
checkins/
        /2015/
             /02/
                /20/
                   /155627.json
                   /161314.json
                   /184800.json
                /21/
                   /081200.json
                   /082807.json
```

The JSON file is the exact API response from Foursquare for the checkin. For example:

```
{
    "id": "54e7c9ab498e77e63f4eb5c6",
    "createdAt": 1424476587,
    "type": "checkin",
    "entities": [],
    "shout": "Picking up food!",
    "timeZoneOffset": -480,
    "venue": {
        "id": "4a85dc38f964a52072ff1fe3",
        "name": "Great Harvest Bread",
        "contact": {
            "phone": "5032248583",
            "formattedPhone": "(503) 224-8583",
            "twitter": "greatharvest"
        },
        "location": {
            "address": "810 SW 2nd Ave",
            "crossStreet": "SW Yamhill St",
            "lat": 45.51723237255434,
            "lng": -122.67498414631359,
            "postalCode": "97204",
            "cc": "US",
            "city": "Portland",
            "state": "OR",
            "country": "United States",
            "formattedAddress": [
                "810 SW 2nd Ave (SW Yamhill St)",
                "Portland, OR 97204"
            ]
        },
        "categories": [
            {
                "id": "4bf58dd8d48988d16a941735",
                "name": "Bakery",
                "pluralName": "Bakeries",
                "shortName": "Bakery",
                "icon": {
                    "prefix": "https://ss3.4sqi.net/img/categories_v2/food/bakery_",
                    "suffix": ".png"
                },
                "primary": true
            }
        ],
        ...
    },
    ...
}
```

Photos are saved next to the JSON files and are named with the `photo_id` property from the API.

If the checkin has one or more photos, there will be a `photos` array in the checkin JSON:

```
    "id": "55198007498eb9d85e8e5234",
    "createdAt": 1427734535,
    "type": "checkin",
    ...
    "photos": {
        "count": 1,
        "items": [
            {
                "id": "5519800a498e845f01aee3fb",
                "createdAt": 1427734538,
                "source": {
                    "name": "Swarm for iOS",
                    "url": "https://www.swarmapp.com"
                },
                "prefix": "https://fastly.4sqi.net/img/general/",
                "suffix": "/59164_t3BNST0CcWIG7KJD0D0IvLDDAgpoZnmII9lrVLqlKqU.jpg",
                "width": 1440,
                "height": 1920,
                "demoted": false,
                "user": {
                    "id": "59164",
                    "firstName": "aaronpk",
                    "gender": "male",
                    "countryCode": "US",
                    "relationship": "self",
                    "photo": {
                        "prefix": "https://fastly.4sqi.net/img/user/",
                        "suffix": "/59164_AwCOMSY2_cGQ0vElNLiFchw0KwiHBrmiHtuau30m-ykdT3z8io8tClE1M2t2oNw5EGVDeff34.jpg"
                    }
                },
                "visibility": "public"
            }
        ],
        "layout": {
            "name": "single"
        }
    },
```

The photo will be saved as `{id}.jpg` in the same folder as the checkin. For example, `checkins/2015/03/30/5519800a498e845f01aee3fb.jpg`.

