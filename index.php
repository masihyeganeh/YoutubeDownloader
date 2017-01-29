<?php
set_time_limit(0); // Downloading a video may take a lot time

include 'vendor/autoload.php';
use \Masih\YoutubeDownloader\YoutubeDownloader;

$youtube = new YoutubeDownloader('gmFn62dr0D8');

$result = $youtube->getInfo();
header('Content-type: application/json');
print json_encode($result);


// Uncomment lines below to test downloading video


//$youtube->onComplete = function ($filePath, $fileSize, $index, $count) {
//    if ($count > 1) echo '[' . $index . ' of ' . $count . ' videos] ';
//    echo PHP_EOL . 'Downloading of ' . $fileSize . ' bytes has been completed. It is saved in ' . $filePath . PHP_EOL;
//};
//
//$youtube->onProgress = function ($downloadedBytes, $fileSize, $index, $count) {
//    if ($count > 1) echo '[' . $index . ' of ' . $count . ' videos] ';
//    if ($fileSize > 0)
//        echo "\r" . 'Downloaded ' . $downloadedBytes . ' of ' . $fileSize . ' bytes [%' . number_format($downloadedBytes * 100 / $fileSize, 2) . '].';
//    else
//        echo "\r" . 'Downloading...'; // File size is unknown, so just keep downloading
//};

//$youtube->download();
