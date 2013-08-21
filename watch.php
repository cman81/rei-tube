<?php
    include_once('util.inc');
    define('DEFAULT_START', 0);
    define('DEFAULT_END', 0);
    define('DEFAULT_VOLUME', 25);

    // presentation mode by default
    $this_width = 1280;
    $this_height = 720;
    if ($_GET['small'] == '1') {
        $this_width = 640;
        $this_height = 360;
    }

    // sorting buckets: lower number means allows more videos to be played on a given day
    // examples:
    // 1: 1 bucket = every video played every day
    // 5: videos split into 5 buckets, ideal for weekdays (Mon - Fri)
    // 7: videos split into 7 buckets, ideal for daily viewing (Mon - Sun)
    $sorting_buckets = 5;

    // grab a subset of videos to pick from:
    // sort videos by least recently played, take the top half, then randomly select.
// TODO: this is not a secure, password is stored in this file
    $con = mysqli_connect(
        "localhost", // hostname
        "root", // username
        "root", // password
        "reitube" // database
    );
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    $qry = "
        SELECT youtube_vid, volume, start_seconds, end_seconds
        FROM playlist
        WHERE (retire_date IS NULL OR retire_date > '" . date('Y-m-d H:i:s') . "')
        AND (publish_date IS NULL OR publish_date < '" . date('Y-m-d H:i:s') . "')
        ORDER BY last_played_date ASC
    ";
    $result = mysqli_query($con, $qry);

    $playlist = array();
    while($row = mysqli_fetch_array($result)) {
        if ($_GET['all_vids'] == '1') {
            $playlist[] = $row;
        } elseif (integer_hash($row['youtube_vid'], 1, 7) % $sorting_buckets == date('N') % $sorting_buckets) {
            $playlist[] = $row;
        }
    }
    $playlist = array_chunk($playlist, floor(count($playlist) / 2));
    $playlist = $playlist[0];

    // determine which video to play
    $target_video = $playlist[rand(0, count($playlist) - 1)];
    $youtube_vid = $target_video['youtube_vid'];

    // determine start and end points of the video, as well as volume
    $this_start = (is_null($target_video['start_seconds'])) ? DEFAULT_START : $target_video['start_seconds'];
    $this_end = (is_null($target_video['end_seconds'])) ? DEFAULT_END : $target_video['end_seconds'];
    $this_volume = (is_null($target_video['volume'])) ? DEFAULT_VOLUME : $target_video['volume'];

    // update the database, mark this video as played (less likely to play next time)
    if ($_GET['no_log'] != '1') {
        $qry = "
            UPDATE playlist
            SET last_played_date = '" . date('Y-m-d H:i:s') . "'
            WHERE youtube_vid = '" . $youtube_vid . "'
        ";
        mysqli_query($con, $qry);
    }

    mysqli_close($con);
?>

<!--
You are free to copy and use this sample in accordance with the terms of the
Apache license (http://www.apache.org/licenses/LICENSE-2.0.html)
-->

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>YouTube Player API Sample</title>
    <link rel="stylesheet" type="text/css" href="reset.css">
    <link rel="stylesheet" type="text/css" href="global.css">
    <?php if ($_GET['debug'] == '1'): ?>
        <style type="text/css">
            #videoInfo { display: inherit; }
        </style>
    <?php endif; ?>
    <style type="text/css">
        .videoContainer {
            width: <?= $this_width ?>px;
            height: <?= $this_height ?>px;
        }
    </style>
    <script src="//www.google.com/jsapi" type="text/javascript"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="global.js"></script>
    <script src="vAlignPlugin.js"></script>
    <script type="text/javascript">
        google.load("swfobject", "2.1");
    </script>
    <script type="text/javascript">
        /*
         * Chromeless player has no controls.
         */

        // Update a particular HTML element with a new value
        function updateHTML(elmId, value) {
            document.getElementById(elmId).innerHTML = value;
        }

        // This function is called when an error is thrown by the player
        function onPlayerError(errorCode) {
            console.log("An error occured of type:" + errorCode);
            window.location.reload();
        }

        // This function is called when the player changes state
        function onPlayerStateChange(newState) {
            updateHTML("playerState", newState);
            if (newState == 0) {
                window.location.reload();
            }
        }

        // Display information about the current state of the player
        function updatePlayerInfo() {
            // Also check that at least one function exists since when IE unloads the
            // page, it will destroy the SWF before clearing the interval.
            if(ytplayer && ytplayer.getDuration) {
                updateHTML("videoQuality", ytplayer.getPlaybackQuality());
                updateHTML("videoDuration", ytplayer.getDuration());
                updateHTML("videoCurrentTime", ytplayer.getCurrentTime());
                updateHTML("bytesTotal", ytplayer.getVideoBytesTotal());
                updateHTML("startBytes", ytplayer.getVideoStartBytes());
                updateHTML("bytesLoaded", ytplayer.getVideoBytesLoaded());
                updateHTML("volume", ytplayer.getVolume());
            }
            if (<?= $this_end ?> != 0) {
                if (ytplayer.getCurrentTime() > <?= $this_end ?>) {
                    window.location.reload();
                }
            }
        }

        // Allow the user to set the volume from 0-100
        function setVideoVolume() {
            var volume = parseInt(document.getElementById("volumeSetting").value);
            if(isNaN(volume) || volume < 0 || volume > 100) {
                alert("Please enter a valid volume between 0 and 100.");
            }
            else if(ytplayer){
                ytplayer.setVolume(volume);
            }
        }

        function playVideo() {
            if (ytplayer) {
                ytplayer.playVideo();
            }
        }

        function pauseVideo() {
            if (ytplayer) {
                ytplayer.pauseVideo();
            }
        }

        function muteVideo() {
            if(ytplayer) {
                ytplayer.mute();
            }
        }

        function unMuteVideo() {
            if(ytplayer) {
                ytplayer.unMute();
            }
        }


        // This function is automatically called by the player once it loads
        function onYouTubePlayerReady(playerId) {
            ytplayer = document.getElementById("ytPlayer");
            // This causes the updatePlayerInfo function to be called every 250ms to
            // get fresh data from the player
            setInterval(updatePlayerInfo, 250);
            updatePlayerInfo();
            ytplayer.addEventListener("onStateChange", "onPlayerStateChange");
            ytplayer.addEventListener("onError", "onPlayerError");
            //Load an initial video into the player
            ytplayer.cueVideoById("<?= $youtube_vid ?>");

            <?php if ($this_start > 0): ?>
                ytplayer.seekTo(<?= $this_start ?>);
            <?php endif; ?>
            ytplayer.setVolume(<?= $this_volume ?>);
            <?php if ($_GET['hd'] == '1'): ?>
                ytplayer.setPlaybackQuality('hd1080');
            <?php endif; ?>
            ytplayer.playVideo();
        }

        // The "main method" of this sample. Called when someone clicks "Run".
        function loadPlayer() {
            // Lets Flash from another domain call JavaScript
            var params = { allowScriptAccess: "always" };
            // The element id of the Flash embed
            var atts = { id: "ytPlayer" };
            // All of the magic handled by SWFObject (http://code.google.com/p/swfobject/)
            swfobject.embedSWF("http://www.youtube.com/apiplayer?" +
                "version=3&enablejsapi=1&playerapiid=player1",
                "videoDiv", "<?= $this_width ?>", "<?= $this_height ?>", "9", null, null, params, atts);
        }
        function _run() {
            loadPlayer();
        }
        google.setOnLoadCallback(_run);
    </script>
</head>
<body style="font-family: Arial;border: 0 none;">
<div class="videoContainer">
    <div id="videoDiv">Loading...</div>
</div>
<div id="videoInfo">
    <p>VID: <?= $youtube_vid ?></p>
    <p>Quality: <span id="videoQuality">--</span></p>
    <p>Player state: <span id="playerState">--</span></p>
    <p>Current Time: <span id="videoCurrentTime">--:--</span> | Duration: <span id="videoDuration">--:--</span></p>
    <p>Bytes Total: <span id="bytesTotal">--</span> | Start Bytes: <span id="startBytes">--</span> | Bytes Loaded: <span id="bytesLoaded">--</span></p>
    <p>Controls: <a href="javascript:void(0);" onclick="playVideo();">Play</a> | <a href="javascript:void(0);" onclick="pauseVideo();">Pause</a> | <a href="javascript:void(0);" onclick="muteVideo();">Mute</a> | <a href="javascript:void(0);" onclick="unMuteVideo();">Unmute</a></p>
    <p><input id="volumeSetting" type="text" size="3" />&nbsp;<a href="javascript:void(0)" onclick="setVideoVolume();">&lt;- Set Volume</a> | Volume: <span id="volume">--</span></p>
</div>
</body>
</html>