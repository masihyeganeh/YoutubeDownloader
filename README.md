Youtube Downloader
==================

Youtube video downloader

[![Build Status](https://travis-ci.org/masihyeganeh/YoutubeDownloader.svg?branch=master)](https://travis-ci.org/masihyeganeh/YoutubeDownloader)
[![Latest Stable Version](https://poser.pugx.org/masih/youtubedownloader/v/stable)](https://packagist.org/packages/masih/youtubedownloader)
[![Latest Unstable Version](https://poser.pugx.org/masih/youtubedownloader/v/unstable)](https://packagist.org/packages/masih/youtubedownloader)
[![Coverage Status](https://coveralls.io/repos/github/masihyeganeh/YoutubeDownloader/badge.svg?branch=master)](https://coveralls.io/github/masihyeganeh/YoutubeDownloader?branch=master)
[![Total Downloads](https://poser.pugx.org/masih/youtubedownloader/downloads)](https://packagist.org/packages/masih/youtubedownloader)
[![Dependency Status](https://www.versioneye.com/user/projects/5281d3db632baca88e000127/badge.svg)](https://www.versioneye.com/user/projects/5281d3db632baca88e000127)
[![License](https://poser.pugx.org/masih/youtubedownloader/license)](https://packagist.org/packages/masih/youtubedownloader)


Installation
------------

Youtube Downloader is PSR-0 compliant and can be installed using [composer](http://getcomposer.org/).  Simply add `masih/youtubedownloader` to your composer.json file. 
```json
    {
        "require": {
            "masih/youtubedownloader": "~1.4"
        }
    }
```

and run `composer update` command

Needs PHP 5.5 or newer. Tested with PHP 5.5, 5.6, 7.0, hhvm and nightly

Usage
-----

### Video infos

To get video infos, you should instantiate `YoutubeDownloader` with video url or video id.
for example for `http://youtube.com/watch?v=gmFn62dr0D8`, video id is `gmFn62dr0D8`

```php
<?php
include 'vendor/autoload.php';

use Masih\YoutubeDownloader\YoutubeDownloader;

$youtube = new YoutubeDownloader('gmFn62dr0D8');

$result = $youtube->getVideoInfo();

header('Content-type: application/json');
print json_encode($result);
```

the `getVideoInfo()` method will return an object containing video title, images, url and `itag` of all formats (full and adaptive), ...
   
Video formats are in two category; Full & adaptive
in Full formats, videos and sounds are muxed and are in one file. but in Adaptive formats, videos and sounds are in separated urls.
Each format has it's own `itag`. it's just an identifier

   
### Download video

the `download()` method gets `itag` of a format and downloads it.
if no `itag` is passed, it will download highest quality of Full format.

```php
<?php
set_time_limit(0); // Downloading a video may take a lot time

include 'vendor/autoload.php';

use Masih\YoutubeDownloader\YoutubeDownloader;

$youtube = new YoutubeDownloader('gmFn62dr0D8');

$youtube->download();
```

   
### Download progress

Download progress is available via `onProgress` parameter.
it's a closure and has two parameters `$downloadedBytes` and `$fileSize`.

```php
<?php
$youtube = new YoutubeDownloader('gmFn62dr0D8');


$youtube->onProgress = function ($downloadedBytes, $fileSize) {
	if ($fileSize > 0)
		echo 'Downloaded ' . $downloadedBytes . ' of ' . $fileSize . ' bytes [%' . number_format($downloadedBytes * 100 / $fileSize, 2) . '].' . "\n";
	else
		echo 'Downloading...'; // File size is unknown, so just keep downloading
};


$youtube->download();
```
   
### Download complete

Download complete event is available via `onComplete` parameter.
it's a closure and has two parameters `$filePath` and `$fileSize`.

```php
<?php
$youtube = new YoutubeDownloader('gmFn62dr0D8');


$youtube->onComplete = function ($filePath, $fileSize) {
	echo 'Downloading of ' . $fileSize . ' bytes has been completed. It is saved in ' . $filePath . "\n";
};


$youtube->download();
```

   
License
-------

MIT, see LICENSE.
