<?php

/**
 * Mp4 Editor
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 2.9.6
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Masih\YoutubeDownloader\Mp4;

//use \CFPropertyList\CFPropertyList;
//use \CFPropertyList\CFDictionary;
//use \CFPropertyList\CFString;
//use \CFPropertyList\CFArray;

class Mp4
{
	protected $mp4;
	protected $suppressNotices;
	protected $iTunesContainers;
	protected $iTunesPlistAttributes;

	public function __construct($file, $suppressNotices=true)
	{
		$this->mp4 = new \Zend_Media_Iso14496($file);
		$this->suppressNotices = $suppressNotices;
		$this->iTunesPlistAttributes = array();

		$this->disableNotices();

		// Remove iods box because it is newer than our code and we don't support it
		$movie = $this->mp4->moov;
		if ($movie->hasBox('iods'))
			$movie->removeBox($movie->iods);

		$this->iTunesContainers = $this->mp4->moov->udta->meta->ilst;
		$this->enableNotices();
	}

	public function exceptionsErrorHandler($severity, $message, $filename, $lineno) {
		if ($severity == E_NOTICE)
			return; // There is a notice in "vendor/debach/zend-mp3/lib/Zend/Media/Iso14496/Box.php:533"
		if (error_reporting() == 0)
			return;
		if (error_reporting() & $message)
			throw new \ErrorException($message, 0, $severity, $filename, $lineno);
	}

	protected function disableNotices() {
		if ($this->suppressNotices)
			set_error_handler(array($this, 'exceptionsErrorHandler'));
	}

	protected function enableNotices() {
		if ($this->suppressNotices)
			set_error_handler(null);
	}

	protected function addOrChangeMetaData($key, $value, $type=\Zend_Media_Iso14496_Box_Data::STRING) {
		$this->disableNotices();

		$key = str_pad($key, 4, chr(169), STR_PAD_LEFT);

		$container = new \Zend_Media_Iso14496_Box_Ilst_Container($key);
		$container->data->setValue($value, $type);
		$this->iTunesContainers->addBox($container);

		$this->enableNotices();
	}

	public function setTrackName($value) {
		$this->addOrChangeMetaData('nam', $value);
	}

	public function setArtist($value) {
		$this->addOrChangeMetaData('ART', $value);
	}

	public function setAlbumArtist($value) {
		$this->addOrChangeMetaData('aART', $value);
	}

	public function setAlbum($value) {
		$this->addOrChangeMetaData('alb', $value);
	}

	public function setGrouping($value) {
		$this->addOrChangeMetaData('grp', $value);
	}

	public function setPublicationYear($value) {
		$this->addOrChangeMetaData('day', $value);
	}

	public function setTrackNumber($track, $totalTracks) {
		$this->addOrChangeMetaData('trkn', $this->number($totalTracks, 16) . $this->number($track, 16));
	}

	public function setDiscNumber($disc, $totalDiscs) {
		$this->addOrChangeMetaData('disk', $this->number($totalDiscs, 16) . $this->number($disc, 16));
	}

	public function setBPMTempo($value) {
		$this->addOrChangeMetaData('tmpo', $value);
	}

	public function setComposer($value) {
		$this->addOrChangeMetaData('wrt', $value);
	}

	public function setComments($value) {
		$this->addOrChangeMetaData('cmt', $value);
	}

	// ID3v1Genres int or string
	public function setGenre($genre) {
		if (is_numeric($genre)) {
			$this->addOrChangeMetaData('gnre', $this->number($genre + 1, 16));
			// $this->addOrChangeMetaData('gnre', ID3v1Genres::of($genre));
		} else
			$this->addOrChangeMetaData('gen', $genre);
	}

	public function setIsPartOfACompilation($value=false) {
		$this->addOrChangeMetaData('cpil', $value ? 1 : 0, \Zend_Media_Iso14496_Box_Data::INTEGER);
	}

	public function setShowName($value) { // television
		$this->addOrChangeMetaData('tvsh', $value);
	}

	public function setTrackSortName($value) {
		$this->addOrChangeMetaData('sonm', $value);
	}

	public function setArtistSortName($value) {
		$this->addOrChangeMetaData('soar', $value);
	}

	public function setAlbumArtistSortName($value) {
		$this->addOrChangeMetaData('soaa', $value);
	}

	public function setAlbumSortName($value) {
		$this->addOrChangeMetaData('soal', $value);
	}

	public function setComposerSortName($value) {
		$this->addOrChangeMetaData('soco', $value);
	}

	public function setShowSortName($value) {
		$this->addOrChangeMetaData('sosn', $value);
	}

	public function setLyrics($value) {
		$this->addOrChangeMetaData('lyr', $value);
	}

	public function setCover($imageData) {
		$this->addOrChangeMetaData('covr', $imageData, \Zend_Media_Iso14496_Box_Data::JPEG);
	}

	public function setSoftwareInformation($value) {
		$this->addOrChangeMetaData('too', $value);
	}

	public function setMediaType($value) {
		$this->addOrChangeMetaData('stik', $this->number($value, 8));
	}

	public function setLongDescription($value) {
		$this->addOrChangeMetaData('ldes', $value);
	}

	public function setEpisodeID($value) {
		$this->addOrChangeMetaData('tven', $value);
	}

	public function setTVNetwork($value) {
		$this->addOrChangeMetaData('tvnn', $value);
	}

	public function setTVEpisode($value) {
		$this->addOrChangeMetaData('tves', $value, \Zend_Media_Iso14496_Box_Data::INTEGER); // 32 Bits
	}

	public function setTVSeason($value) {
		$this->addOrChangeMetaData('tvsn', $value, \Zend_Media_Iso14496_Box_Data::INTEGER); // 32 Bits
	}

	public function setDate($timestamp) {
		$dateString = explode('+', gmdate('c', $timestamp));
		$this->addOrChangeMetaData('day', $dateString[0] . 'Z');
	}

	public function setPurchaseDate($timestamp) {
		$dateString = explode('+', gmdate('c', $timestamp));
		$this->addOrChangeMetaData('purd', $dateString[0] . 'Z');
	}

	public function setShortDescription($value) {
		if (function_exists('mb_substr'))
			$value = mb_substr($value, 0, 256);
		else
			$value = substr($value, 0, 256);
		$this->addOrChangeMetaData('desc', $value);
	}

	public function setActors($value)  {
		$this->iTunesPlistAttributes['cast'] = $value;
	}

	public function setDirectors($value)  {
		$this->iTunesPlistAttributes['directors'] = $value;
	}

	public function setProducers($value)  {
		$this->iTunesPlistAttributes['producers'] = $value;
	}

	public function setScreenwriters($value)  {
		$this->iTunesPlistAttributes['screenwriters'] = $value;
	}

	public function addOrChangeiTunesRating($rating) {
		// https://searchcode.com/codesearch/view/28588917/
		// TODO: ---- mean=com.apple.iTunes name=iTunEXTC <Zend_Media_Iso14496_Box_Data>mpaa|PG|200|</Zend_Media_Iso14496_Box_Data>
	}

	protected function addOrChangePlistMetaData() {
		if (count($this->iTunesPlistAttributes) === 0) return;

		// TODO: ---- mean=com.apple.iTunes name=iTunMOVI <Zend_Media_Iso14496_Box_Data>$this->makePlist();<Zend_Media_Iso14496_Box_Data>
	}

	protected function makePlist() {
		/*
		$plist = new CFPropertyList();
		$plist->add($dict = new CFDictionary());

		foreach ($this->iTunesPlistAttributes as $name => $value) {
			$dict->add($name, $itemArray = new CFArray());
			$itemDict = new CFDictionary();
			$itemDict->add('name', new CFString($value));
			$itemArray->add($itemDict);
		}

		return $plist->toXML(true);
		*/
	}

	private function number($num, $bits) {
		// There is no standard for numbers. VLC can show this at least
		return str_pad(chr($num), $bits/8, chr(0), STR_PAD_LEFT);
	}

	/**
	 * @throws \Zend_Media_Iso14496_Exception
	 */
	public function save() {
		try {
			$this->saveAs(null);
		} catch (\Zend_Media_Iso14496_Exception $e) {
			throw $e;
		}
	}

	/**
	 * @param $ouputFile
	 * @throws \Zend_Media_Iso14496_Exception
	 */
	public function saveAs($ouputFile) {
		$this->addOrChangePlistMetaData();

		$this->disableNotices();
		try {
			$this->mp4->write($ouputFile);
		} catch (\Zend_Media_Iso14496_Exception $e) {
			$this->enableNotices();
			throw $e;
		}
		$this->enableNotices();
	}

	public function __destruct()
	{
		if ($this->mp4) {
			$this->mp4->__destruct();
			$this->mp4 = null;
		}
	}

	public function getFPS() {
		$movieHeader = $this->mp4->moov->mvhd;
		$samplesTable = $this->mp4->moov->trak->mdia->minf->stbl->stts->getTimeToSampleTable();
		$sampleSizes = $samplesTable[1]['sampleCount'];
		return (($sampleSizes * $movieHeader->getTimescale()) / (double)$movieHeader->getDuration());
	}
}