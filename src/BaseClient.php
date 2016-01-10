<?php

namespace OndraKoupil\Heureka;

/**
 * Společný základ pro obě třídy *Client
 */
abstract class BaseClient {

	protected $sourceAddress;

	protected $tempFile;
	protected $downloadFinished = false;
	protected $deleteTempFileAfterParsing = true;

	protected $callback = null;

	protected $xml = null;


	/* ------- Getters, Setters ------- */

	abstract function setKey($key);

	/**
	 * Konstruktor umožňuje rovnou nastavit klíč nebo adresu.
	 *
	 * @param string $key
	 */
	function __construct($key = null) {
		if ($key) {
			$this->setKey($key);
		}
	}

	/**
	 * Adresa, z níž se má stahovat XML feed s recenzemi.
	 *
	 * @return string
	 */
	public function getSourceAddress() {
		return $this->sourceAddress;
	}

	/**
	 * Adresa, z níž se má stahovat XML feed s recenzemi.
	 *
	 * @param string $sourceAddress
	 * @return BaseClient
	 */

	public function setSourceAddress($sourceAddress) {
		$this->sourceAddress = $sourceAddress;
		return $this;
	}

	/**
	 * Dočasný soubor
	 *
	 * @return string
	 */
	public function getTempFile() {
		return $this->tempFile;
	}

	/**
	 * Má se dočasný soubor po parsování automaticky vymazat?
	 *
	 * @return bool
	 */
	public function getDeleteTempFileAfterParsing() {
		return $this->deleteTempFileAfterParsing;
	}

	/**
	 * Nastaví dočasný soubor, kam se celý XML feed stáhne.
	 * Použití dočasného souboru redukuje nároky na paměť.
	 *
	 * @param string $tempFile
	 * @param bool $deleteAfterParsing Smazat dočasný soubor automaticky?
	 * @return BaseClient
	 */
	public function setTempFile($tempFile, $deleteAfterParsing = true) {
		$this->tempFile = $tempFile;
		$this->deleteTempFileAfterParsing = $deleteAfterParsing ? true : false;
		$this->downloadFinished = false;
		return $this;
	}

	/**
	 * @return callable
	 */
	public function getCallback() {
		return $this->callback;
	}

	/**
	 * Nastavení callbacku, který se spustí pro každou recenzi.
	 *
	 * @param callable $callback function($recenze) { ... }, kde $recenze je EshopReview nebo ProductReview (podle typu *Client třídy)
	 * @return BaseClient
	 * @throws \InvalidArgumentException
	 */
	public function setCallback($callback) {
		if ($callback and !is_callable($callback)) {
			throw new \InvalidArgumentException("Given callback is not callable.");
		}
		$this->callback = $callback;
		return $this;
	}



	/* ------- Internals ------- */

	abstract function getNodeName();

	abstract function processElement(\SimpleXMLElement $element, $index);

	protected function downloadFile() {

		if (!$this->sourceAddress) {
			throw new \RuntimeException("Source address has not been set, can not download file.");
		}

		$c = curl_init($this->sourceAddress);

		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($c, CURLOPT_TIMEOUT, 30);
		curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);

		if ($this->tempFile) {

			$fh = fopen($this->tempFile, "w");
			if (!$fh) {
				throw new \RuntimeException("Temporary file \"$this->tempFile\" could not be open for writing.");
			}
			curl_setopt($c, CURLOPT_FILE, $fh);

			$downloadSuccess = curl_exec($c);
			if (!$downloadSuccess) {
				throw new \RuntimeException("File could not be downloaded from \"" . $this->sourceAddress . "\"");
			}

			$this->downloadFinished = true;
			curl_close($c);
			fclose($fh);

		} else {

			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			$this->xml = curl_exec($c);

			if ($this->xml === false) {
				throw new \RuntimeException("File could not be downloaded from \"" . $this->sourceAddress . "\"");
			}

			$this->downloadFinished = true;
			curl_close($c);

		}

	}

	protected function processFile() {

		if (!$this->downloadFinished) {
			throw new \RuntimeException("File has not been downloaded yet.");
		}

		$xmlReader = new \XMLReader();
		$mainNodeName = $this->getNodeName();

		if ($this->tempFile) {
			$xmlReader->open($this->tempFile);
		} else {
			$xmlReader->XML($this->xml);
		}

		$elementIndex = 0;

		while (true) {

			$remainsAnything = $xmlReader->read();
			if (!$remainsAnything) break;

			$nodeName = $xmlReader->name;
			$nodeType = $xmlReader->nodeType;

			if ($nodeType !== \XMLReader::ELEMENT or $nodeName !== $mainNodeName) {
				continue;
			}

			$nodeAsString = $xmlReader->readOuterXml();
			if (!$nodeAsString) {
				continue;
			}

			$simpleXmlNode = simplexml_load_string($nodeAsString);
			if (!$simpleXmlNode) {
				continue;
			}

			$review = $this->processElement($simpleXmlNode, $elementIndex);

			if ($this->callback) {
				call_user_func_array($this->callback, array($review));
			}

			$elementIndex++;
		}

	}

	function deleteTempFileIfNeeded() {
		if ($this->tempFile and file_exists($this->tempFile) and $this->deleteTempFileAfterParsing) {
			$deleted = unlink($this->tempFile);
			if (!$deleted) {
				throw new \RuntimeException("Could not clean up the temp file \"$this->tempFile\" - unlink() failed");
			}
		}
	}


	/* ------- Public API ------ */

	/**
	 * Umožní použít vlastní soubor (již stažený dříve) a ne ten z Heuréky.
	 * @param string $filename
	 * @throws \RuntimeException
	 */
	public function useFile($filename) {
		if (!file_exists($filename) or !is_readable($filename)) {
			throw new \RuntimeException("File \"$filename\" is not readable.");
		}
		$this->tempFile = $filename;
		$this->downloadFinished = true;
		$this->deleteTempFileAfterParsing = false;
	}

	/**
	 * Spustit import!
	 */
	public function run() {
		if (!$this->downloadFinished) {
			$this->downloadFile();
		}
		$this->processFile();
		$this->deleteTempFileIfNeeded();
	}

	/**
	 * Stáhnout soubor, ale dál ho nezpracovávat.
	 * @param string $file Kam se má stáhnout? Null = použít nastavený dočasný soubor z setTempFile().
	 */
	public function download($file = null) {
		if ($file) {
			$this->setTempFile($file);
		}

		$this->downloadFile();
	}

}
