<?php

/**
 * Youtube Downloader
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 2.6
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Masih\YoutubeDownloader;

use Campo\UserAgent;
use Dflydev\ApacheMimeTypes\FlatRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class YoutubeDownloader
{
	/**
	 * Youtube Video ID
	 * @var string
	 */
	protected $videoId;

	/**
	 * Youtube Playlist ID
	 * @var string
	 */
	protected $playlistId;

	/**
	 * Current downloading video index in playlist
	 * @var integer
	 */
	protected $currentDownloadingVideoIndex = 1;

	/**
	 * Is Passed Url a Playlist or a Video
	 * @var bool
	 */
	protected $isPlaylist = true;

	/**
	 * Video info fetched from Youtube
	 * @var object
	 */
	protected $videoInfo = null;

	/**
	 * Playlist info fetched from Youtube
	 * @var object
	 */
	protected $playlistInfo = null;

	/**
	 * Definition of itags
	 * @var object
	 */
	protected $itags = null;

	/**
	 * Default itag number if user not specified any
	 * @var integer
	 */
	protected $defaultItag = null;

	/**
	 * Default caption if user not specified any
	 * @var string
	 */
	protected $defaultCaptionLanguage = 'en';

	/**
	 * Default caption format
	 * @var string
	 */
	protected $captionFormat = 'srt';

	/**
	 * Path to save videos (without ending slash)
	 * @var string
	 */
	protected $path = 'videos';

	/**
	 * Web client object
	 * @var Client
	 */
	protected $webClient;

	/**
	 * Number of downloaded bytes of file
	 * @var integer
	 */
	protected $downloadedBytes;

	/**
	 * Size of file to be download in bytes
	 * @var integer
	 */
	protected $fileSize;

	/**
	 * Callable function that is called on download progress
	 * @var callable
	 * @todo This shows wrong number for files with above 2GB size
	 */
	public $onProgress;

	/**
	 * Callable function that is called on download complete
	 * @var callable
	 */
	public $onComplete;

	/**
	 * Instantiates a YoutubeDownloader with a random User-Agent
	 *
	 * @param  string $videoUrl Full Youtube video url or just video ID
	 * @example var downloader = new YoutubeDownloader('gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/watch?v=gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/embed/gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/v/gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://youtu.be/gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('PLbjM1u8Yb9I0rK4hkPa9TWe4N_idJOnrJ');
	 * @example var downloader = new YoutubeDownloader('https://www.youtube.com/watch?v=7gY_sq9uOmw&list=PLbjM1u8Yb9I0rK4hkPa9TWe4N_idJOnrJ');
	 * @example var downloader = new YoutubeDownloader('https://www.youtube.com/playlist?list=PLbjM1u8Yb9I0rK4hkPa9TWe4N_idJOnrJ');
	 * @example var downloader = new YoutubeDownloader('https://www.youtube.com/embed/videoseries?list=PLbjM1u8Yb9I0rK4hkPa9TWe4N_idJOnrJ');
	 */
	public function __construct($videoUrl)
	{
		$this->webClient = new Client(array(
			'headers' => array('User-Agent' => UserAgent::random())
		));

		$this->onComplete = function ($filePath, $fileSize) {};
		$this->onProgress = function ($downloadedBytes, $fileSize) {};

		$this->videoId = $this->getVideoIdFromUrl($videoUrl);
		$this->playlistId = $this->getPlaylistIdFromUrl($videoUrl);
		$this->playlistInfo = $this->getPlaylistInfo();

		if ($this->playlistInfo === null) {
			$this->isPlaylist = false;
		}
	}

	/**
	 * Returns information about known itags
	 *
	 * @return object           known itags information
	 */
	static public function getItags()
	{
		return json_decode(file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'itags.json'));
	}

	/**
	 * Gets information about a given itag
	 *
	 * @param  int $itag Itag identifier
	 * @return object           Information about itag or null if itag id is unknown
	 */
	public function getItagInfo($itag)
	{
		$itag = '' . $itag;
		$itags = $this->getItags();

		if (property_exists($itags, $itag))
			return $itags->$itag;

		return null;
	}

	/**
	 * Cuts playlist id from absolute Youtube playlist url
	 *
	 * @param  string $playlistUrl Full Youtube playlist url
	 * @return string           Playlist ID
	 */
	protected function getPlaylistIdFromUrl($playlistUrl)
	{
		$playlistId = $playlistUrl;
		$urlPart = parse_url($playlistUrl);

		if (isset($urlPart['query']))
		{
			$query = $this->decodeString($urlPart['query']);

			if (isset($query['list']))
				$playlistId = $query['list'];
			elseif (isset($query['p']))
				$playlistId = $query['p'];
		}

		return $playlistId;
	}

	/**
	 * Cuts video id from absolute Youtube or youtu.be video url
	 *
	 * @param  string $videoUrl Full Youtube or youtu.be video url
	 * @return string           Video ID
	 */
	protected function getVideoIdFromUrl($videoUrl)
	{
		$videoId = $videoUrl;
		$urlPart = parse_url($videoUrl);
		$path = $urlPart['path'];
		if (isset($urlPart['host']) && strtolower($urlPart['host']) == 'youtu.be') {
			if (preg_match('/\/([^\/\?]*)/i', $path, $temp))
				$videoId = $temp[1];
		} else {
			if (preg_match('/\/embed\/([^\/\?]*)/i', $path, $temp))
				$videoId = $temp[1];
			elseif (preg_match('/\/v\/([^\/\?]*)/i', $path, $temp))
				$videoId = $temp[1];
			elseif (preg_match('/\/watch/i', $path, $temp) && isset($urlPart['query']))
			{
				$query = $this->decodeString($urlPart['query']);
				$videoId = $query['v'];
			}
		}

		return $videoId;
	}

	/**
	 * Gets information of Youtube playlist
	 *
	 * @throws YoutubeException If Playlist ID is wrong or not exists anymore or it's not viewable anyhow
	 *
	 * @param bool $getDownloadLinks Also get download links and images for each video
	 * @return object           Playlist's title, author, videos, ...
	 */
	public function getPlaylistInfo($getDownloadLinks=false)
	{
		try {
			$url = 'https://www.youtube.com/list_ajax?style=json&action_get_list=1&list=' . $this->playlistId;
			$response = $this->webClient->get($url);
		} catch (GuzzleException $e) {
			if ($e instanceof ClientException && $e->hasResponse()) {
				if ($e->getResponse()->getStatusCode()) return null;
				throw new YoutubeException($e->getResponse()->getReasonPhrase(), 3);
			}
			else
				throw new YoutubeException($e->getMessage(), 3);
		}

		if ($response->getStatusCode() != 200)
			throw new YoutubeException('Couldn\'t get playlist details.', 1);

		$playlistInfo = json_decode($response->getBody());

		if ($getDownloadLinks)
		{
			foreach ($playlistInfo->video as &$video)
			{
				$this->videoId = $video->encrypted_id;
				try {
                    $videoInfo = $this->getVideoInfo(true);
                } catch (YoutubeException $e) {
				    throw $e;
                }
				$video->image = $videoInfo->image;
				$video->full_formats = $videoInfo->full_formats;
				$video->adaptive_formats = $videoInfo->adaptive_formats;
			}
			$this->videoId = null;
		}

		$playlistInfo->response_type = 'playlist';

		return $playlistInfo;
	}

	/**
	 * Fetches content of passed URL
	 *
	 * @throws YoutubeException If any error happens
	 *
	 * @param  string $url Address of page to fetch
	 * @return ResponseInterface           content of page
	 */
	protected function getUrl($url)
	{
		try {
			$response = $this->webClient->get($url);
		} catch (GuzzleException $e) {
			if ($e instanceof ClientException && $e->hasResponse())
				throw new YoutubeException($e->getResponse()->getReasonPhrase(), 3);
			else
				throw new YoutubeException($e->getMessage(), 3);
		}
		return $response;
	}

	/**
	 * Decodes URL encoded string
	 *
	 * @param  string $input URL encoded string
	 * @return string           decoded string
	 */
	protected function decodeString($input)
	{
		parse_str($input, $result);
		return $result;
	}

    /**
     * Decrypts encrypted signatures (needs V8js extension)
     *
     * @throws YoutubeException If any error happens
     *
     * @param  string $signature Encrypted signature
     * @param  string $code Decryption code
     * @return string           decrypted signature
     */
    protected function decryptSignature($signature, $code)
    {
        if (!class_exists('V8Js') || !class_exists('V8JsException'))
        {
            throw new YoutubeException('Please install V8js [ http://php.net/manual/en/book.v8js.php ] to download encrypted videos.', 11);
        }

        $v8 = new \V8Js();
        $v8->this = new WindowStub();

        $code .= 'signature=PHP.this.$signature("' . $signature . '");';

        try {
            return $v8->executeString($code, 'base.js');
        } catch (\V8JsException $e) {
            throw new YoutubeException($e, 13);
        }
    }

	/**
	 * Gets information of Youtube video
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 *
	 * @param  bool $detailed Show more detailed information
	 * @return object           Video's title, images, video length, download links, ...
	 */
	public function getVideoInfo($detailed=false)
	{
		$result = array();

		try {
			$response = $this->getUrl('https://www.youtube.com/get_video_info?video_id=' . $this->videoId);
		} catch (YoutubeException $e) {
			throw $e;
		}

		if ($response->getStatusCode() != 200)
			throw new YoutubeException('Couldn\'t get video details.', 1);

		$data = $this->decodeString($response->getBody());
		if (isset($data['status']) && $data['status'] == 'fail')
		{
			if ($data['errorcode'] == '150') {
				try {
					$response = $this->getUrl('https://www.youtube.com/watch?v=' . $this->videoId);
				} catch (YoutubeException $e) {
					throw $e;
				}

				if (preg_match('/ytplayer.config\s*=\s*([^\n]+});ytplayer/i', $response->getBody(), $matches))
				{
					$ytconfig = json_decode($matches[1], true);
                    $data = $ytconfig['args'];

                    if (isset($ytconfig['assets']['js']))
                    {
                        $jsAsset = 'https:' . $ytconfig['assets']['js'];
                        try {
                            $response = $this->getUrl($jsAsset);
                        } catch (YoutubeException $e) {
                            throw $e;
                        }
                        $code = $response->getBody();

                        if (!preg_match('/\.sig\|\|([\w\d]+)\(/', $code, $matches)) {
                            throw new YoutubeException('Decryption algorithm is outdated.', 12);
                        }
                        $functionName = $matches[1];
                        $code = str_replace(
                            'var window=this;',
                            'Array.concat=[].concat;Array.slice=[].slice;var window=PHP.this;',
                            $code
                        );
                        $code = str_replace('.prototype.', '.', $code);
                        $code = str_replace('(0,window.decodeURI)', 'window.decodeURI', $code);
                        $code = preg_replace('/^(\w+\.install)\(/m', '$1=function(){};$1(', $code);
                        $code = str_replace(
                            '})(_yt_player);',
                            'window.signature=' . $functionName . ';})(_yt_player);',
                            $code
                        );
                        $data['decryptionCode'] = $code;
                    }
				}
				elseif (preg_match('/\'class="message">([^<]+)<\'/i', $response->getBody(), $matches)) {
					throw new YoutubeException(trim($matches[1]), 10);
				}

			} else
				throw new YoutubeException($data['reason'], $data['errorcode']);
		}

		$result['title'] = $data['title'];
		$result['image'] = array(
			'max_resolution' => 'https://i1.ytimg.com/vi/' . $this->videoId . '/maxresdefault.jpg',
			'high_quality' => 'https://i1.ytimg.com/vi/' . $this->videoId . '/hqdefault.jpg',
			'medium_quality' => 'https://i1.ytimg.com/vi/' . $this->videoId . '/mqdefault.jpg',
			'standard' => 'https://i1.ytimg.com/vi/' . $this->videoId . '/sddefault.jpg',
			'thumbnails' => array(
				'https://i1.ytimg.com/vi/' . $this->videoId . '/default.jpg',
				'https://i1.ytimg.com/vi/' . $this->videoId . '/0.jpg',
				'https://i1.ytimg.com/vi/' . $this->videoId . '/1.jpg',
				'https://i1.ytimg.com/vi/' . $this->videoId . '/2.jpg',
				'https://i1.ytimg.com/vi/' . $this->videoId . '/3.jpg'
			)
		);
		$result['length_seconds'] = $data['length_seconds'];

		$result['captions'] = array();
		if (isset($data['has_cc']) && $data['has_cc'] === 'True')
			$result['captions'] = $this->getCaptions($data, $detailed);

		$filename = $this->pathSafeFilename($result['title']);

		if (isset($data['ps']) && $data['ps'] == 'live')
		{
			if (!isset($data['hlsvp']))
				throw new YoutubeException('This live event is over.', 2);

			$result['stream_url'] = $data['hlsvp'];
		}
		else
		{
			$stream_maps = explode(',', $data['url_encoded_fmt_stream_map']);
			foreach ($stream_maps as $key => $value) {
				$stream_maps[$key] = $this->decodeString($value);

                if (isset($data['decryptionCode']) && isset($stream_maps[$key]['s'])) {
                    try {
                        $stream_maps[$key]['url'] .= '&signature=' . $this->decryptSignature(
                            $stream_maps[$key]['s'], // Encrypted signature,
                            $data['decryptionCode'] // Decryption code
                        );
                    } catch (YoutubeException $e) {
                        throw $e;
                    }
                    unset($stream_maps[$key]['s']);
                } elseif (isset($stream_maps[$key]['sig'])) {
					$stream_maps[$key]['url'] .= '&signature=' . $stream_maps[$key]['sig'];
					unset($stream_maps[$key]['sig']);
				}

				$typeParts = explode(';', $stream_maps[$key]['type']);
				// TODO: Use container of known itags as extension here
				$stream_maps[$key]['filename'] = $filename . '.' . $this->getExtension(trim($typeParts[0]));

				$stream_maps[$key] = (object) $stream_maps[$key];
			}
			$result['full_formats'] = $stream_maps;

			$adaptive_fmts = explode(',', $data['adaptive_fmts']);
			foreach ($adaptive_fmts as $key => $value) {
				$adaptive_fmts[$key] = $this->decodeString($value);

                if (isset($data['decryptionCode']) && isset($adaptive_fmts[$key]['s'])) {
                    try {
                        $adaptive_fmts[$key]['url'] .= '&signature=' . $this->decryptSignature(
                            $adaptive_fmts[$key]['s'], // Encrypted signature,
                            $data['decryptionCode'] // Decryption code
                        );
                    } catch (YoutubeException $e) {
                        throw $e;
                    }
                    unset($adaptive_fmts[$key]['s']);
                }

				$typeParts = explode(';', $adaptive_fmts[$key]['type']);
				// TODO: Use container of known itags as extension here
				$adaptive_fmts[$key]['filename'] = $filename . '.' . $this->getExtension(trim($typeParts[0]));

				$adaptive_fmts[$key] = (object) $adaptive_fmts[$key];
			}
			$result['adaptive_formats'] = $adaptive_fmts;
		}

		if (isset($data['decryptionCode']))
		    unset($data['decryptionCode']);

		$result['video_url'] = 'https://www.youtube.com/watch?v=' . $this->videoId;

		$result = (object) $result;
		$this->videoInfo = $result;

		$result->response_type = 'video';

		return $result;
	}

	/**
	 * Gets information of Youtube video or playlist
	 *
	 * @throws YoutubeException If Video ID or Playlist Id is wrong or not exists anymore or it's not viewable anyhow
	 *
	 * @param bool $getDownloadLinksForPlaylist Also get download links and images for each video of playlists
	 * @return object           Video's title, images, video length, download links, ... or Playlist's title, author, videos, ...
	 */
	public function getInfo($getDownloadLinksForPlaylist=false)
	{
		try {
			if ($this->isPlaylist)
				return $this->getPlaylistInfo($getDownloadLinksForPlaylist);
			return $this->getVideoInfo();
		} catch (YoutubeException $e) {
			throw $e;
		}
	}

	/**
	 * Gets caption of Youtube video and returns it as an object
	 *
	 * @param  array $videoInfo Parsed video info sent by Youtube
	 * @param  bool $detailed Caption track in video info
	 * @return array Captions list
	 */
	protected function getCaptions($videoInfo, $detailed=false) {
		$index = 0;
		$captions = array();
		$captionTracks = explode(',', $videoInfo['caption_tracks']);

		foreach($captionTracks as $captionTrack) {
			$decodedTrack = $this->decodeString($captionTrack);

			$language = $index++;

			if (isset($decodedTrack['lc']))
				$language = $decodedTrack['lc'];

			if (isset($decodedTrack['v']) && $decodedTrack['v'][0] != '.')
				$language = $decodedTrack['v'];

			if ($detailed)
				$captions[$language] = $decodedTrack;
			else
				$captions[$language] = $decodedTrack['n'];
		}

		return $captions;
	}

	/**
	 * Gets caption of Youtube video and returns it as an object
	 *
	 * @param  string $captionTrack Detailed caption track
	 * @return object           Caption as an object
	 */
	protected function parseCaption($captionTrack)
	{
		if (!isset($captionTrack['u'])) return null;

		try {
			$response = $this->webClient->get($captionTrack['u']);
		} catch (GuzzleException $e) {
			if ($e instanceof ClientException && $e->hasResponse()) {
				throw new YoutubeException($e->getResponse()->getReasonPhrase(), 3);
			}
			else
				throw new YoutubeException($e->getMessage(), 3);
		}
		$caption = array();
		$xml = simplexml_load_string($response->getBody());
		foreach ($xml->text as $element) {
			$item = array();
			$item['text'] = html_entity_decode($element . "", ENT_QUOTES | ENT_XHTML);
			$attributes = $element->attributes();
			$item['start'] = floatval($attributes['start'] . "");
			$item['duration'] = floatval(isset($attributes['dur']) ? $attributes['dur'] . "" : '1.0');
			$item['end'] = $item['start'] + $item['duration'];
			array_push($caption, $item);
		}
		$result = array();
		$result['title'] = $captionTrack['n'];
		$result['caption'] = $caption;
		return ((object) $result);
	}

	/**
	 * Converts parsed caption track to desired caption format
	 *
	 * @param  array $captionLines Parsed caption
	 * @param  integer $fps Video frame-per-second, needed for "sub" captions
	 * @param  string $format Caption format, including "srt" (default), "sub", "ass"
	 * @return object           Converted caption and it's file extension
	 */
	protected function formatCaption($captionLines, $fps=24, $format=null)
	{
		$result = array();
		$result['caption'] = '';
		$result['extension'] = 'txt';

		if ($format === null) $format = $this->captionFormat;

		if ($format == 'srt') {
			$result['extension'] = 'srt';

			$sequence = 1;
			foreach ($captionLines as $captionLine) {
				$start = $this->parseFloatTime($captionLine['start']);
				$end = $this->parseFloatTime($captionLine['end']);

				$result['caption'] .= ($sequence++) . "\n";
				$result['caption'] .= str_replace('.', ',', vsprintf('%02d:%02d:%02.3f', $start)) . ' --> ';
				$result['caption'] .= str_replace('.', ',', vsprintf('%02d:%02d:%02.3f', $end)) . "\n";
				$result['caption'] .= $captionLine['text'] . "\n\n";
			}
			$result['caption'] = trim($result['caption']);
		} elseif ($format == 'sub') {
			$result['extension'] = 'sub';

			foreach ($captionLines as $captionLine) {
				$result['caption'] .= '{' . intval($captionLine['start'] * $fps) . '}';
				$result['caption'] .= '{' . intval($captionLine['end'] * $fps) . '}';
				$result['caption'] .= $captionLine['text'] . "\n";
			}
			$result['caption'] = trim($result['caption']);
		} elseif ($format == 'ass') {
			$result['extension'] = 'ass';
			$result['caption'] = <<<EOF
[Script Info]
ScriptType: v4.00+
Collisions: Normal
PlayDepth: 0
Timer: 100,0000
Video Aspect Ratio: 0
WrapStyle: 0
ScaledBorderAndShadow: no

[V4+ Styles]
Format: Name,Fontname,Fontsize,PrimaryColour,SecondaryColour,OutlineColour,BackColour,Bold,Italic,Underline,StrikeOut,ScaleX,ScaleY,Spacing,Angle,BorderStyle,Outline,Shadow,Alignment,MarginL,MarginR,MarginV,Encoding
Style: Default,Arial,16,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,-1,0,0,0,100,100,0,0,1,3,0,2,10,10,10,0
Style: Top,Arial,16,&H00F9FFFF,&H00FFFFFF,&H00000000,&H00000000,-1,0,0,0,100,100,0,0,1,3,0,8,10,10,10,0
Style: Mid,Arial,16,&H0000FFFF,&H00FFFFFF,&H00000000,&H00000000,-1,0,0,0,100,100,0,0,1,3,0,5,10,10,10,0
Style: Bot,Arial,16,&H00F9FFF9,&H00FFFFFF,&H00000000,&H00000000,-1,0,0,0,100,100,0,0,1,3,0,2,10,10,10,0

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text

EOF;
			foreach ($captionLines as $captionLine) {
				$start = $this->parseFloatTime($captionLine['start']);
				$end = $this->parseFloatTime($captionLine['end']);

				$result['caption'] .= 'Dialogue: 0,';
				$result['caption'] .= vsprintf('%d:%02d:%02.2f', $start) . ',';
				$result['caption'] .= vsprintf('%d:%02d:%02.2f', $end) . ',Bot,,0000,0000,0000,,';
				$result['caption'] .= $captionLine['text'] . "\n";
			}
		}

		return ((object) $result);
	}

	/**
	 * Removes unsafe characters from file name
	 *
	 * @param  array $captions Captions data of video
	 * @param  string $language User selected language
	 * @param  string $filename Caption file name
	 */
	protected function downloadCaption($captions, $language, $filename)
	{
		if ($language === null) $language = $this->defaultCaptionLanguage;
		if (!array_key_exists($language, $captions))
			$captionData = array_shift($captions);
		else
			$captionData = $captions[$language];

		$captionData = $this->parseCaption($captionData);
		if ($captionData !== null) {

			$captionData = $this->formatCaption($captionData->caption); // TODO: Video fps is needed here

			$filename = substr($filename, 0, strrpos($filename, '.')) . '.' . $captionData->extension;
			$path = $this->path . DIRECTORY_SEPARATOR . $filename;

			file_put_contents($path, $captionData->caption);
		}
	}

	/**
	 * Removes unsafe characters from file name
	 *
	 * @param  string $string Path unsafe file name
	 * @return string         Path Safe file name
	 *
	 * @todo Use .net framework's Path.GetInvalidPathChars() for a better function
	 */
	protected function pathSafeFilename($string)
	{
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\-]#', '#^\.#');
		return preg_replace($regex, '_', $string);
	}

	/**
	 * Returns file extension of a given mime type
	 *
	 * @uses \Dflydev\ApacheMimeTypes\FlatRepository Mimetype parser library
	 * @param  string $mimetype Mime type
	 * @return string           File extension of given mime type. it will return "mp4" if no extension could be found
	 */
	protected function getExtension($mimetype)
	{
		$mime = new FlatRepository;
		$extension = 'mp4';
		$extensions = $mime->findExtensions($mimetype);
		if (count($extensions))
			$extension = $extensions[0];

		return $extension;
	}

	/**
	 * Just downloads the given url
	 *
	 * @param  string   $url Url of file to download
	 * @param  string   $file Path of file to save to
	 * @param  callable $onProgress Callback to be called on download progress
	 * @param  callable $onFinish Callback to be called on download complete
	 */
	protected function downloadFile($url, $file, callable $onProgress, callable $onFinish)
	{
		$videoFile = fopen($file, 'a');
		$options = array(
			'sink' => $videoFile,
			'verify' => false,
			'timeout' => 0,
			'connect_timeout' => 50,
			'progress' => $onProgress,
			'cookies' => new CookieJar(false, [['url' => 'https://www.youtube.com/watch?v=' . $this->videoId]])
		);

		$request = new Request('get', $url);
		$promise = $this->webClient->sendAsync($request, $options);
		$promise->then(
			function (ResponseInterface $response) use ($videoFile, $file, $onFinish) {
				fclose($videoFile);

				$size = filesize($file);
				$remained = intval((string)$response->getHeader('Content-Length')[0]);

				$onFinish($size, $remained);
			},
			function (GuzzleException $e) {
				if ($e instanceof ClientException && $e->hasResponse())
					throw new YoutubeException($e->getResponse()->getReasonPhrase(), 4);
				else
					throw new YoutubeException($e->getMessage(), 4);
			}
		);
		$promise->wait();
	}

	/**
	 * Downloads video or playlist videos by given itag
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 *
	 * @param  int  $itag   After calling {@see getVideoInfo()}, it returns various formats, each format has it's own itag. if no itag is passed or passed itag is not valid for the video, it will download the best quality of video
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from beginning
	 * @param  mixed $caption Caption language to download or null to download caption of default language. false to prevent download
	 */
	public function download($itag=null, $resume=false, $caption=false)
	{
		if ($itag === null && $this->defaultItag !== null) $itag = $this->defaultItag;
		if (!$this->isPlaylist) {
			try {
				$this->getVideoInfo(true);
			} catch (YoutubeException $e) {
				throw $e;
			}

			$this->downloadVideo($itag, $resume, $caption);
		} else {
			$i = 1;
			foreach ($this->playlistInfo->video as $video)
			{
				$this->currentDownloadingVideoIndex = $i++;
				$this->videoId = $video->encrypted_id;
				try {
					$this->getVideoInfo(true);
				} catch (YoutubeException $e) {
					throw $e;
				}

				$this->downloadVideo($itag, $resume, $caption);
			}

		}
	}

	/**
	 * Downloads selected video by given itag
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 *
	 * @param  int  $itag   After calling {@see getVideoInfo()}, it returns various formats, each format has it's own itag. if no itag is passed or passed itag is not valid for the video, it will download the best quality of video
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from beginning
	 * @param  mixed $caption Caption language to download or null to download caption of default language. false to prevent download
	 */
	protected function downloadVideo($itag=null, $resume=false, $caption=false)
	{
		if ($itag) {
			foreach ($this->videoInfo->full_formats as $video) {
				if ($video->itag == $itag) {
					$this->downloadFull($video->url, $video->filename, $resume);
					if ($caption !== false && count($this->videoInfo->captions))
						$this->downloadCaption($this->videoInfo->captions, $caption, $video->filename);
					return;
				}
			}

			foreach ($this->videoInfo->adaptive_formats as $video) {
				if ($video->itag == $itag) {
					$this->downloadAdaptive($video->url, $video->filename, $video->clen, $resume);
					if ($caption !== false && count($this->videoInfo->captions))
						$this->downloadCaption($this->videoInfo->captions, $caption, $video->filename);
					return;
				}
			}
		}

		$video = $this->videoInfo->full_formats[0];
		$this->downloadFull($video->url, $video->filename, $resume);
		if ($caption !== false && count($this->videoInfo->captions))
			$this->downloadCaption($this->videoInfo->captions, $caption, $video->filename);
	}

	/**
	 * Downloads full_formats videos given by {@see getVideoInfo()}
	 *
	 * @param  string  $url    Video url given by {@see getVideoInfo()}
	 * @param  string  $file   Path of file to save to
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from beginning
	 */
	public function downloadFull($url, $file, $resume=false)
	{
		$file = $this->path . DIRECTORY_SEPARATOR . $file;
		if (file_exists($file) && !$resume)
			unlink($file);

		$downloadedBytes = &$this->downloadedBytes;
		$fileSize = &$this->fileSize;
		$onProgress = &$this->onProgress;
		$onComplete = &$this->onComplete;
		$videosCount = $this->isPlaylist ? count($this->playlistInfo->video) : 1;

		$this->downloadFile(
			$url, $file,
			function ($downloadSize, $downloaded) use ($downloadedBytes, $fileSize, $onProgress, $videosCount) {
				if (!$downloaded && !$downloadSize) return 1;
				if ($downloadedBytes != $downloaded)
					$onProgress($downloaded, $downloadSize, $this->currentDownloadingVideoIndex, $videosCount);

				$downloadedBytes = $downloaded;
				$fileSize = $downloadSize;
				return 0;
			},
			function ($downloadSize) use ($onComplete, $file, $videosCount) {
				$onComplete($file, $downloadSize, $this->currentDownloadingVideoIndex, $videosCount);
			}
		);
	}

	/**
	 * Downloads adaptive_formats videos given by {@see getVideoInfo()}. in adaptive formats, video and voice are separated.
	 *
	 * @param  string  $url           Resource url given by {@see getVideoInfo()}
	 * @param  string  $file          Path of file to save to
	 * @param  integer $completeSize  Completed file size
	 * @param  boolean $resume        If it should resume download if an uncompleted file exists or should download from beginning
	 */
	public function downloadAdaptive($url, $file, $completeSize, $resume=false)
	{
		$file = $this->path . DIRECTORY_SEPARATOR . $file;

		$size = 0;
		if (file_exists($file))
		{
			if ($resume)
				$size += filesize($file);
			else
				unlink($file);
		}

		$downloadedBytes = &$this->downloadedBytes;
		$fileSize = &$this->fileSize;
		$onProgress = &$this->onProgress;
		$onComplete = &$this->onComplete;
		$videosCount = $this->isPlaylist ? count($this->playlistInfo->video) : 1;

		while ($size < $completeSize)
		{
			$this->downloadFile(
				$url . '&range=' . $size . '-' . $completeSize, $file,
				function ($downloadSize, $downloaded) use ($downloadedBytes, $fileSize, $onProgress, $videosCount)  {
					if (!$downloaded && !$downloadSize) return 1;
					if ($downloadedBytes != $downloaded)
						$onProgress($downloaded, $downloadSize, $this->currentDownloadingVideoIndex, $videosCount);

					$downloadedBytes = $downloaded;
					$fileSize = $downloadSize;

					return 0;
				},
				function ($downloadSize) use (&$size) {
					$size += $downloadSize;
				}
			);

			// Maybe we need to refresh download link each time
		}

		$onComplete($file, $size, $this->currentDownloadingVideoIndex, $videosCount);
	}

	/**
	 * Sets downloaded videos path
	 *
	 * @param  string $path Path to save videos (without ending slash)
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}

	/**
	 * Sets default itag
	 *
	 * @param  integer|string $itag Default itag number if user not specified any
	 */
	public function setDefaultItag($itag)
	{
		$this->defaultItag = (string)$itag;
	}

	/**
	 * Sets default caption language
	 *
	 * @param  string $language Default caption language in 2 letters format (e.g. "en")
	 */
	public function setDefaultCaptionLanguage($language)
	{
		$this->defaultCaptionLanguage = (string)$language;
	}

	/**
	 * Sets default caption format
	 *
	 * @param  string $format Caption format, including "srt" (default), "sub", "ass"
	 */
	public function setCaptionFormat($format)
	{
		if (in_array($format, array('srt', 'sub', 'ass')))
			$this->captionFormat = (string)$format;
	}

	/**
	 * Sets downloaded videos path
	 *
	 * @param  float $time Time in float
	 * @return array           Time array [hours, minutes, seconds]
	 */
	protected function parseFloatTime($time)
	{
		$result = array();
		array_push($result, fmod($time, 60));
		$time = intval($time / 60);
		array_push($result, fmod($time, 60));
		$time = intval($time / 60);
		array_push($result, fmod($time, 60));
		return array_reverse($result);
	}
}
