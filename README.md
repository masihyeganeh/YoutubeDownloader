Youtube Downloader
==================

Youtube video downloader



Installation
------------

Youtube Downloader is PSR-0 compliant and can be installed using [composer](http://getcomposer.org/).  Simply add `masih/youtubedownloader` to your composer.json file. 
```
    {
        "require": {
            "masih/youtubedownloader": "*"
        }
    }
```

and run `composer update` command

Usage
-----

### Video infos

To get video infos, you should instantiate `YoutubeDownloader` with video url or video id.
for example for `http://youtube.com/watch?v=gmFn62dr0D8`, video id is `gmFn62dr0D8`

```
<?php
include 'vendor/autoload.php';

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

```
<?php
set_time_limit(0); // Downloading a video may take a lot time

include 'vendor/autoload.php';

$youtube = new YoutubeDownloader('gmFn62dr0D8');

$youtube->download();
```


License
-------

MIT, see LICENSE.
