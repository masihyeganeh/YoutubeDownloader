<?php
set_time_limit(0); // Downloading a video may take a lot time

include 'vendor/autoload.php';
use \Masih\YoutubeDownloader\YoutubeDownloader;

$youtube = new YoutubeDownloader('gmFn62dr0D8');

// $result = $youtube->getVideoInfo();
// header('Content-type: application/json');
// print json_encode($result);


// Uncomment lines below to test downloding video


// $youtube->onComplete = function ($fileSize) {
// 	echo 'Downloading of ' . $fileSize . ' bytes has been completed.' . "\n";
// };

// $youtube->onProgress = function ($downloadedBytes, $fileSize) {
// 	if ($fileSize > 0)
// 		echo 'Downloaded ' . $downloadedBytes . ' of ' . $fileSize . ' bytes [%' . number_format($downloadedBytes * 100 / $fileSize, 2) . '].' . "\n";
// 	else
// 		echo 'Downloading...'; // File size is unknown, so just keep downloading
// };

// $youtube->download();
