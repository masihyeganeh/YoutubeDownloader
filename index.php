<?php
set_time_limit(0); // Downloading a video may take a lot time

include 'vendor/autoload.php';

$youtube = new YoutubeDownloader('gmFn62dr0D8');

$result = $youtube->getVideoInfo();
header('Content-type: application/json');
print json_encode($result);

//$youtube->download(); die;