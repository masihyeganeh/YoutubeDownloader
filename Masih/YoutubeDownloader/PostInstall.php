<?php

/**
 * Youtube Downloader Exception
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 2.8.6
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Masih\YoutubeDownloader;

class PostInstall
{
	public static function postPackageInstall(Event $event)
	{
		$basePath = dirname(__DIR__);
		static::fixZendMp3FileLockBug($basePath);
	}

	public static function fixZendMp3FileLockBug($basePath)
	{
		$boxFilePath = implode(DIRECTORY_SEPARATOR, [
			$basePath, 'vendor', 'debach', 'zend-mp3', 'lib', 'Zend', 'Media', 'Iso14496', 'Box.php'
		]);

		$content = file_get_contents($boxFilePath);
		$content = str_replace('unset($this->_boxes);', '//unset($this->_boxes);', $content);
		file_put_contents($boxFilePath, $content);
	}
}
