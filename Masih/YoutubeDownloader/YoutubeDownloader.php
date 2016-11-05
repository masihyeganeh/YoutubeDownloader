<?php

/**
 * Youtube Downloader
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 2.1
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
	 * @param  string $videoUrl Full Youtube video url or just video ID
	 * @example var downloader = new YoutubeDownloader('gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/watch?v=gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/embed/gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/v/gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://youtu.be/gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('PLbjM1u8Yb9I043pxcgwtv3KY9_6iL-Dsd');
	 * @example var downloader = new YoutubeDownloader('https://www.youtube.com/watch?v=7gY_sq9uOmw&list=PLbjM1u8Yb9I043pxcgwtv3KY9_6iL-Dsd');
	 * @example var downloader = new YoutubeDownloader('https://www.youtube.com/playlist?list=PLbjM1u8Yb9I043pxcgwtv3KY9_6iL-Dsd');
	 * @example var downloader = new YoutubeDownloader('https://www.youtube.com/embed/videoseries?list=PLbjM1u8Yb9I043pxcgwtv3KY9_6iL-Dsd');
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
	 * @return object           known itags information
	 */
	public function getItags()
	{
		if ($this->itags === null)
			$this->itags = json_decode(file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'itags.json'));

		return $this->itags;
	}

	/**
	 * Gets information about a given itag
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
				$videoInfo = $this->getVideoInfo();
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
	 * Gets information of Youtube video
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 *
	 * @return object           Video's title, images, video length, download links, ...
	 */
	public function getVideoInfo()
	{
		$result = array();

		try {
			$response = $this->getUrl('http://www.youtube.com/get_video_info?video_id=' . $this->videoId);
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
				}
				elseif (preg_match('/\'class="message">([^<]+)<\'/i', $response->getBody(), $matches)) {
					throw new YoutubeException(trim($matches[1]), 10);
				}

			} else
				throw new YoutubeException($data['reason'], $data['errorcode']);
		}

		$result['title'] = $data['title'];
		$result['image'] = array(
			'max_resolution' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/maxresdefault.jpg',
			'high_quality' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/hqdefault.jpg',
			'medium_quality' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/mqdefault.jpg',
			'standard' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/sddefault.jpg',
			'thumbnails' => array(
				'http://i1.ytimg.com/vi/' . $this->videoId . '/default.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/0.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/1.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/2.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/3.jpg'
			)
		);
		$result['length_seconds'] = $data['length_seconds'];

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

				if (isset($stream_maps[$key]['sig'])) {
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

				$typeParts = explode(';', $adaptive_fmts[$key]['type']);
				// TODO: Use container of known itags as extension here
				$adaptive_fmts[$key]['filename'] = $filename . '.' . $this->getExtension(trim($typeParts[0]));

				$adaptive_fmts[$key] = (object) $adaptive_fmts[$key];
			}
			$result['adaptive_formats'] = $adaptive_fmts;
		}

		$result['video_url'] = 'http://www.youtube.com/watch?v=' . $this->videoId;

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
		if ($this->isPlaylist)
			return $this->getPlaylistInfo($getDownloadLinksForPlaylist);
		return $this->getVideoInfo();
	}

	/**
	 * Gets caption of Youtube video and returns it as an object
	 *
	 * @param  string $captionTrack Caption track in video info
	 * @return object           Caption as an object
	 *
	 */
	protected function parseCaption($captionTrack)
	{
		$captionTrackData = $this->decodeString($captionTrack);
		if (!isset($captionTrackData['u'])) return null;

		try {
			$response = $this->webClient->get($captionTrackData['u']);
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
			$item['text'] = $element . "";
			$attributes = $element->attributes();
			$item['start'] = floatval($attributes['start'] . "");
			$item['duration'] = floatval($attributes['dur'] . "");
			$item['end'] = $item['start'] + $item['duration'];
			array_push($caption, $item);
		}
		$result = array();
		$result['title'] = $captionTrackData['n'];
		$result['caption'] = $caption;
		return ((object) $result);
	}

	/**
	 * Removes unsafe characters from file name
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
	 * @param  string   $url Url of file to download
	 * @param  string   $file Path of file to save to
	 * @param  callable $onProgress Callback to be called on download progress
	 * @param  callable $onFinish Callback to be called on download complete
	 */
	protected function downloadFile($url, $file, callable $onProgress, callable $onFinish)
	{
		$tempFilename = $file . '_temp_' . time();
		$tempFile = fopen($tempFilename, 'a');
		$options = array(
			'sink' => $tempFile,
			'verify' => false,
			'timeout' => 0,
			'connect_timeout' => 50,
			'progress' => $onProgress,
			'cookies' => new CookieJar(false, [['url' => 'http://www.youtube.com/watch?v=' . $this->videoId]])
		);

		$request = new Request('get', $url);
		$promise = $this->webClient->sendAsync($request, $options);
		$promise->then(
			function (ResponseInterface $response) use ($tempFile, $tempFilename, $file, $onFinish) {
				fclose($tempFile);

				$size = filesize($tempFilename);
				$remained = intval((string)$response->getHeader('Content-Length')[0]);

				// Appending downloaded file to existing one (Continuing uncomplete files)
				$fp1 = fopen($file, 'a');
				$fp2 = fopen($tempFilename, 'r');
				while (!feof($fp2)) {
					$data = fread($fp2, 1024);
					fwrite($fp1, $data);
				}
				fclose($fp2);
				fclose($fp1);

				unlink($tempFilename);

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
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from begining
	 */
	public function download($itag=null, $resume=false)
	{
		if (!$this->isPlaylist) {
			if (is_null($this->videoInfo)) {
				try {
					$this->getVideoInfo();
				} catch (YoutubeException $e) {
					throw $e;
				}
			}

			$this->downloadVideo($itag, $resume);
		} else {
			$i = 1;
			foreach ($this->playlistInfo->video as $video)
			{
				$this->currentDownloadingVideoIndex = $i++;
				$this->videoId = $video->encrypted_id;
				try {
					$this->getVideoInfo();
				} catch (YoutubeException $e) {
					throw $e;
				}

				$this->downloadVideo($itag, $resume);
			}

		}
	}

	/**
	 * Downloads selected video by given itag
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 *
	 * @param  int  $itag   After calling {@see getVideoInfo()}, it returns various formats, each format has it's own itag. if no itag is passed or passed itag is not valid for the video, it will download the best quality of video
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from begining
	 */
	protected function downloadVideo($itag=null, $resume=false)
	{
		if ($itag) {
			foreach ($this->videoInfo->full_formats as $video) {
				if ($video->itag == $itag) {
					$this->downloadFull($video->url, $video->filename, $resume);
					return;
				}
			}

			foreach ($this->videoInfo->adaptive_formats as $video) {
				if ($video->itag == $itag) {
					$this->downloadAdaptive($video->url, $video->filename, $video->clen, $resume);
					return;
				}
			}
		}

		$video = $this->videoInfo->full_formats[0];
		$this->downloadFull($video->url, $video->filename, $resume);
	}

	/**
	 * Downloads full_formats videos given by {@see getVideoInfo()}
	 * @param  string  $url    Video url given by {@see getVideoInfo()}
	 * @param  string  $file   Path of file to save to
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from begining
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
	 * @param  string  $url           Resource url given by {@see getVideoInfo()}
	 * @param  string  $file          Path of file to save to
	 * @param  integer $completeSize  Completed file size
	 * @param  boolean $resume        If it should resume download if an uncompleted file exists or should download from begining
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
	 * @param  string $path Path to save videos (without ending slash)
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}
}
