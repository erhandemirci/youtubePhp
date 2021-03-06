<?php

/// ~ Change these values! ~ ///

// YouTube API key
const APIKEY = "AIzaSyA3QOaQHNl87Hwi-qZuNezQVNkr2XTar-Y";

// YouTube channel ID(s)
// Can be multiple channels - eg: `array("aaaaaaaaaaaaaaaaaaaa", "bbbbbbbbbbbbbbbbbbbb")`
const CHANNELIDS = array("UC1zAttFQKikWoKH3Vb39ETA");

// Secret - must match ytnotify_subscribe script; should be reasonably hard to guess
const SECRET = "QGWSOO8C3XK2";

// Discord webhook URL
const WEBHOOKURL = "https://discord.com/api/webhooks/964995518327566456/kjbZKWtyHgj22HJFk3_02l76l5DiD07WzGA9og0y7BI3g3mpd3VxkHjsCOb35lUClkcv";

///   ///   ///  ///   ///   ///



/// Optionally change these values ///

// Use a gaming.youtube.com link for livestreams instead of normal youtube
const PREFER_GAMING_LINK = true;

// Send a notification for livestreams that have just ended, with a link to watch
const NOTIFY_COMPLETED_LIVESTREAMS = true;

///   ///   ///  ///   ///   ///




// Respond to verification at time of subscribe
if (array_key_exists('hub_challenge', $_GET)) {
    foreach (CHANNELIDS as $chid) {
        if ($_GET['hub_topic'] == "https://www.youtube.com/xml/feeds/videos.xml?channel_id=$chid") {
            // Topic is correct, die with challenge reply
            die($_GET['hub_challenge']);
        }
    }
    // We did not request this topic, die with no data
    die();
}

// File to save the last publish time to
$LATEST_FILE = "ytnotify.latest";

$data = file_get_contents("php://input");

// Verify signature
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE'];
if ($sig && strpos($sig, "sha1=") === 0) {
    // Trim sha1= from start
    $sig = substr($sig, 5);
    // Compute what the signature should be
    $goodsig = hash_hmac('sha1', $data, SECRET);
    // Finally, die if they don't match
    if ($sig !== $goodsig) {
        die();
    }
} else {
    die();
}

$xml = simplexml_load_string($data) or die("Error: Cannot create object");
$id = $xml->entry->children("http://www.youtube.com/xml/schemas/2015")->videoId;

// First, determine if this is a livestream or not, and the status of the livestream
$url = "https://www.googleapis.com/youtube/v3/videos?part=liveStreamingDetails&id=$id&maxResults=1&key=" . APIKEY;
$json = json_decode(file_get_contents($url), true);
$item = $json['items'][0];
$isFinishedLiveStream = false;
$isInProgressLiveStream = false;
if (array_key_exists('liveStreamingDetails', $item)) {
    $stream = $item['liveStreamingDetails'];
    if ($stream['actualStartTime'] != null) {
        // This is/was a livestream
        if ($stream['actualEndTime'] != null) {
            // This was a livestream that is now finished
            $isFinishedLiveStream = true;
        } else {
            // This is a livestream that is currently LIVE
            $isInProgressLiveStream = true;
        }
    } else {
        // This is an upcoming livestream that hasn't gone live yet.
        // It can be very dangerous: when a stream ends, a new ID will be
        // generated for the next stream, with the publish time set to NOW.
        // Discard completely.
        die();
    }
}
$isLiveStream = ($isFinishedLiveStream || $isInProgressLiveStream);


$inputdate = "";
if ($isInProgressLiveStream) {
    $inputdate = $stream['actualStartTime'];
} else if ($isFinishedLiveStream) {
    if (NOTIFY_COMPLETED_LIVESTREAMS) {
        $inputdate = $stream['actualEndTime'];
    }
} else {
    $inputdate = $xml->entry->published;
}

$notify = false;
if ($inputdate != "") {
    $latest = file_get_contents($LATEST_FILE);
    if ($latest == "") {
        // No last known video, so send the notification and hope for the best D:
        $notify = true;
    } else {
        // Test dates
        $pubdate = date_create($inputdate);
        $latestdate = date_create($latest);
        if ($pubdate > $latestdate) {
            // It's newer, notify!
            $notify = true;
        }
    }
}

if ($notify) {
    // Prepare the POST input
    $msg = "";
    if ($isInProgressLiveStream) {
        $msg = "\xf0\x9f\x94\xb4 **Livestream started!** \xf0\x9f\x94\xb4";
    } else if ($isFinishedLiveStream) {
        $msg = "A finished livestream is now available as a video:";
    } else {
        $msg = "\xf0\x9f\x8e\x9e **NEW VIDEO!** \xf0\x9f\x8e\x9e";
    }

    if ($isInProgressLiveStream && PREFER_GAMING_LINK) {
        $msg .= "\nhttps://gaming.youtube.com/watch?v=$id";
    } else {
        $msg .= "\nhttps://www.youtube.com/watch?v=$id";
    }

    $data = json_encode(array(
        'content' => $msg
    ));
    
    
    function sendMessage(){
        $content = array(
            "en" => 'English Message'
            );
        
        $fields = array(
            'app_id' => "5eb5a37e-b458-11e3-ac11-000c2940e62c",
            'filters' => array(array("field" => "tag", "key" => "level", "relation" => "=", "value" => "10"),array("operator" => "OR"),array("field" => "amount_spent", "relation" => "=", "value" => "0")),
            'data' => array("foo" => "bar"),
            'contents' => $content
        );
        
        $fields = json_encode($fields);
        print("\nJSON sent:\n");
        print($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                                   'Authorization: Basic NGEwMGZmMjItY2NkNy0xMWUzLTk5ZDUtMDAwYzI5NDBlNjJj'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    $response = sendMessage();
    $return["allresponses"] = $response;
    $return = json_encode( $return);
    
    print("\n\nJSON received:\n");
    print($return);
    print("\n");
   
  /*

    // cURL away!
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => WEBHOOKURL,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json;charset=UTF-8'
        ),
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => TRUE
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    // Save latest date to file
    file_put_contents($LATEST_FILE, $inputdate);
    */
}

?>
