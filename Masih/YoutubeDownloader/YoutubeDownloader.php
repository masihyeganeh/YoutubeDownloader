<?php

/**
 * Youtube Downloader
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 1.1
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Masih\YoutubeDownloader;

class YoutubeDownloader
{
	/**
	 * Youtube Video ID
	 * @var string
	 */
	protected $videoId;

	/**
	 * Video info fetched from Youtube
	 * @var object
	 */
	protected $videoInfo = null;

	/**
	 * Path to save videos (without ending slash)
	 * @var string
	 */
	protected $path = 'videos';

	/**
	 * Web client object
	 * @var \Guzzle\Http\Client
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
	 * Instantiates a YoutubeDownloader with a random User-Agent
	 * @param string $videoUrl Full Youtube video url or just video ID
	 * @example var downloader = new YoutubeDownloader('gmFn62dr0D8');
	 * @example var downloader = new YoutubeDownloader('http://www.youtube.com/watch?v=gmFn62dr0D8');
	 */
	public function __construct($videoUrl)
	{
		$this->videoId = $this->getVideoIdFromUrl($videoUrl);
		$this->webClient = new \Guzzle\Http\Client();
		$this->webClient->setUserAgent(\random_uagent());

		$this->onProgress = function ($downloadedBytes, $fileSize) {};
	}

	/**
	 * Cuts video id from absolute Youtube video url
	 * @param  string $videoUrl Full Youtube video url
	 * @return string           Video ID
	 */
	protected function getVideoIdFromUrl($videoUrl)
	{
		$urlPart = parse_url($videoUrl);
		$path = $urlPart['path'];
		if (preg_match('/\/embed\/([^\/\?]*)/i', $path, $temp))
			$videoId = $temp[1];
		elseif (preg_match('/\/watch/i', $path, $temp))
		{
			parse_str($urlPart['query'], $query);
			$videoId = $query['v'];
		}
		else
			$videoId = $videoUrl;

		return $videoId;
	}

	/**
	 * Gets informations of Youtube video
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 * 
	 * @return object Video's title, images, video length, download links, ...
	 */
	public function getVideoInfo()
	{
		$result = array();
		$response = $this->webClient->get('http://www.youtube.com/get_video_info?el=detailpage&ps=default&eurl=&gl=US&hl=en&sts=15888&video_id=' . $this->videoId)->send();
		if (!$response->isSuccessful())
			throw new YoutubeException('Couldn\'t get video details.', 1);

		parse_str($response->getBody(true), $data);
		if (isset($data['status']) && $data['status'] == 'fail')
			throw new YoutubeException($data['reason'], $data['errorcode']);

		$result['title'] = $data['title'];
		$result['image'] = array(
			'max_resolution' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/maxresdefault.jpg',
			'high_quality' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/hqdefault.jpg',
			'medium_quality' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/mqdefault.jpg',
			'standard' => 'http://i1.ytimg.com/vi/' . $this->videoId . '/sddefault.jpg',
			'thumbnails' => array(
				'http://i1.ytimg.com/vi/' . $this->videoId . '/default.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/1.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/2.jpg',
				'http://i1.ytimg.com/vi/' . $this->videoId . '/3.jpg'
			)
		);
		$result['length_seconds'] = $data['length_seconds'];

		$filename = $this->pathSafeFilename($result['title']);

		if (isset($data['ps']) && $data['ps']='live')
		{
			if (!isset($data['hlsvp']))
				throw new YoutubeException('This live event is over.', 2);

			$result['stream_url'] = $data['hlsvp'];
		}
		else
		{
			$stream_maps = explode(',', $data['url_encoded_fmt_stream_map']);
			foreach ($stream_maps as $key => $value) {
				parse_str($value, $stream_maps[$key]);
				
				if (isset($stream_maps[$key]['sig'])) {
					$stream_maps[$key]['url'] .= '&signature=' . $stream_maps[$key]['sig'];
					unset($stream_maps[$key]['sig']);
				}

				$typeParts = explode(';', $stream_maps[$key]['type']);
				$stream_maps[$key]['filename'] = $filename . '.' . $this->getExtension(trim($typeParts[0]));

				$stream_maps[$key] = (object) $stream_maps[$key];
			}
			$result['full_formats'] = $stream_maps;

			$adaptive_fmts = explode(',', $data['adaptive_fmts']);
			foreach ($adaptive_fmts as $key => $value) {
				parse_str($value, $adaptive_fmts[$key]);

				$typeParts = explode(';', $adaptive_fmts[$key]['type']);
				$adaptive_fmts[$key]['filename'] = $filename . '.' . $this->getExtension(trim($typeParts[0]));

				$adaptive_fmts[$key] = (object) $adaptive_fmts[$key];
			}
			$result['adaptive_formats'] = $adaptive_fmts;
		}

		$result['video_url'] = 'http://www.youtube.com/watch?v=' . $this->videoId;

		$result = (object) $result;
		$this->videoInfo = $result;

		return $result;
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
	 * @uses Dflydev\ApacheMimeTypes\FlatRepository Mimetype parser library
	 * @param  string $mimetype Mime type
	 * @return string           File extension of given mime type. it will return "mp4" if no extension could be found
	 */
	protected function getExtension($mimetype)
	{
		$mime = new \Dflydev\ApacheMimeTypes\FlatRepository;
		$extension = 'mp4';
		$extensions = $mime->findExtensions($mimetype);
		if (count($extensions))
			$extension = $extensions[0];

		return $extension;
	}

	/**
	 * Just downloads the given url
	 * @param  string $url  Url of file to download
	 * @param  string $file Path of file to save to
	 * @return object       Downloaded chunk size and bytes that remain
	 */
	protected function downloadFile($url, $file, callable $onProgress)
	{
		$tempFilename = $file . '_temp_' . time();
		$options = array(
			'save_to' => fopen($tempFilename, 'a'),
			'verify' => false,
			'timeout' => 0,
			'connect_timeout' => 50,
			'cookies' => array('url=http://www.youtube.com/watch?v=' . $this->videoId)
		);

		$request = $this->webClient->get($url, array(), $options);
		$request->getCurlOptions()->set('progress', true);
		$request->getEventDispatcher()->addListener('curl.callback.progress', $onProgress);
		$response = $request->send();

		$size = filesize($tempFilename);
		$remained = intval($response->headers['content-length']);

		$fp1 = fopen($file, 'a');
		$fp2 = fopen($tempFilename, 'r');
		while (!feof($fp2)) {
			$data = fread($fp2,1024);
			fwrite($fp1, $data);
		}
		fclose($fp2);
		fclose($fp1);

		unlink($tempFilename);

		return (object) array(
			'size' => $size,
			'remained' => $remained
		);
	}

	/**
	 * Downloads video format by given itag
	 *
	 * @throws YoutubeException If Video ID is wrong or video not exists anymore or it's not viewable anyhow
	 * 
	 * @param  int  $itag   After calling {@see getVideoInfo()}, it returns various formats, each format has it's own itag. if no itag is passed, it will download the best quality of video
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from begining
	 * @return object       Downloaded chunk size and bytes that remain
	 */
	public function download($itag=null, $resume=false)
	{
		if (is_null($this->videoInfo))
		{
			try {
				$this->getVideoInfo();
			} catch (YoutubeException $e) {
				throw $e;
			}
		}

		if (is_null($itag))
		{
			$video = $this->videoInfo->full_formats[0];
			return $this->downloadFull($video->url, $video->filename, $resume);
		}

		foreach ($this->videoInfo->full_formats as $video) {
			if ($video->itag == $itag)
				return $this->downloadFull($video->url, $video->filename, $resume);
		}

		foreach ($this->videoInfo->adaptive_formats as $video) {
			if ($video->itag == $itag)
				return $this->downloadAdaptive($video->url, $video->filename, $resume);
		}
	}

	/**
	 * Downloads full_formats videos given by {@see getVideoInfo()}
	 * @param  string  $url    Video url given by {@see getVideoInfo()}
	 * @param  string  $file   Path of file to save to
	 * @param  boolean $resume If it should resume download if an uncompleted file exists or should download from begining
	 * @return object          Downloaded chunk size and bytes that remain
	 */
	public function downloadFull($url, $file, $resume=false)
	{
		$file = $this->path . DIRECTORY_SEPARATOR . $file;
		if (file_exists($file) && !$resume)
			unlink($file);

		$downloadedBytes = &$this->downloadedBytes;
		$fileSize = &$this->fileSize;
		$onProgress = &$this->onProgress;

		return $this->downloadFile($url, $file, function (\Guzzle\Common\Event $e) use ($downloadedBytes, $fileSize, $onProgress) {
			if (!$e['downloaded'] && !$e['download_size']) return;
			if ($downloadedBytes != $e['downloaded'])
				$onProgress($e['downloaded'], $e['download_size']);

			$downloadedBytes = $e['downloaded'];
			$fileSize = $e['download_size'];
		});
	}

	/**
	 * Downloads adaptive_formats videos given by {@see getVideoInfo()}. in adaptive formats, video and voice are separated.
	 * @param  string  $url           Resource url given by {@see getVideoInfo()}
	 * @param  string  $file          Path of file to save to
	 * @param  boolean $resume        If it should resume download if an uncompleted file exists or should download from begining
	 * @param  integer $maxPartsCount Adaptive formats are not in one piece. It's a good idea to set a max pieces count to avoid unlimited loop
	 * @return object                 Downloaded chunk size and bytes that remain
	 */
	public function downloadAdaptive($url, $file, $resume=false, $maxPartsCount=1024)
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

		$tries = 0;
		while ($tries++ < $maxPartsCount)
		{
			$download = $this->downloadFile($url . '&range=' . $size . '-', $file, function (\Guzzle\Common\Event $e) use ($downloadedBytes, $fileSize, $onProgress)  {
			if (!$e['downloaded'] && !$e['download_size']) return;
			if ($downloadedBytes != $e['downloaded'])
				$onProgress($e['downloaded'], $e['download_size']);

			$downloadedBytes = $e['downloaded'];
			$fileSize = $e['download_size'];
		});

			if ($download->remained <= 0)
				break;

			$size += $download->size;
			
			// Maybe we need to refresh download link each time
		}

		return (object) array(
			'size' => $size,
			'remained' => $download->remained
		);
	}

	/**
	 * Sets downloaded videos path
	 * @param string $path Path to save videos (without ending slash)
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}
}
