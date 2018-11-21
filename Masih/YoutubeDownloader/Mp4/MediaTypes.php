<?php

/**
 * Media Types Enum
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 2.9.6
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Masih\YoutubeDownloader\Mp4;

class MediaTypes extends Enum {
	const Movie = 0;
	const Music = 1;
	const Audiobook = 2;
	const MusicVideo = 6;
	const ShortFilm = 9;
	const TVShow = 10;
	const Booklet = 11;
	const Ringtone = 14;
}