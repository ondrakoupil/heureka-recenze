<?php

namespace OndraKoupil\Heureka;

// src/BaseClient.php 



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



// src/EshopReview.php 



/**
 * Recenze e-shopu
 */
class EshopReview {

	/**
	 * Číslo recenze v rámci souboru, začíná se od 0
	 *
	 * @var number
	 */
	public $index;

	/**
	 * Jedinečné ID recenze (dané Heurékou)
	 *
	 * @var number
	 */
	public $ratingId;

	/**
	 * Jméno autora. Empty string = anonymní.
	 *
	 * @var string
	 */
	public $author;

	/**
	 * Datum a čas napsání recenze
	 *
	 * @var \DateTime
	 */
	public $date;

	/**
	 * Hodnocení celkové - na stupnici 0.5 až 5 hvězdiček
	 *
	 * @var number|null
	 */
	public $ratingTotal;

	/**
	 * Hodnocení délky dodací lhůty - na stupnici 0.5 až 5 hvězdiček
	 *
	 * @var number|null
	 */
	public $ratingDelivery;

	/**
	 * Hodnocení kvality dopravy zboží - na stupnici 0.5 až 5 hvězdiček
	 *
	 * @var number|null
	 */
	public $ratingTransportQuality;

	/**
	 * Hodnocení použitelnosti a přehlednosti e-shopu
	 * na stupnici 0.5 až 5 hvězdiček
	 *
	 * @var number|null
	 */
	public $ratingWebUsability;

	/**
	 * Hodnocení komunikace ze strany e-shopu
	 * na stupnici 0.5 až 5 hvězdiček
	 *
	 * @var number|null
	 */
	public $ratingCommunication;

	/**
	 * Hlavní výhody e-shopu.
	 * Víceřádkový řetězec, zpravidla co řádek, to jeden bod
	 *
	 * @var string
	 */
	public $pros;

	/**
	 * Hlavní nevýhody e-shopu.
	 * Víceřádkový řetězec, zpravidla co řádek, to jeden bod
	 *
	 * @var string
	 */
	public $cons;

	/**
	 * Celkové shrnutí názoru zákazníka na obchod
	 *
	 * @var string
	 */
	public $summary;


	/**
	 * Reakce provozovatele e-shopu na recenzi zákazníka
	 *
	 * @var string
	 */
	public $reaction;

	/**
	 * Číslo objednávky, na níž zákazník psal recenzi
	 *
	 * @var string
	 */
	public $orderId;

	/**
	 * @return array
	 */
	function getAsArray() {
		return get_object_vars($this);
	}

}



// src/EshopReviewsClient.php 



/**
 * Klient umožňující stahovat recenze e-shopu jako takového
 */
class EshopReviewsClient extends BaseClient {

	function getNodeName() {
		return "review";
	}

	public function setKey($key) {

		if (preg_match('~^\w{32}$~', $key)) {
			$this->setSourceAddress("http://www.heureka.cz/direct/dotaznik/export-review.php?key=" . $key);
		} else {
			$this->setSourceAddress($key);
		}

	}

	/**
	 * @ignore
	 */
	public function processElement(\SimpleXMLElement $element, $index) {

		$review = new EshopReview();

		$review->index = $index;
		$review->author = (string)$element->name;
		$review->cons = (string)$element->cons;
		$review->date = new \DateTime();
		$review->date->setTimestamp((int)$element->unix_timestamp);
		$review->orderId = (string)$element->order_id;
		$review->pros = (string)$element->pros;

		if (count($element->communication)) {
			$review->ratingCommunication = (float)$element->communication;
		} else {
			$review->ratingCommunication = null;
		}

		if (count($element->delivery_time)) {
			$review->ratingDelivery = (float)$element->delivery_time;
		} else {
			$review->ratingDelivery = null;
		}

		if (count($element->total_rating)) {
			$review->ratingTotal = (float)$element->total_rating;
		} else {
			$review->ratingTotal = null;
		}

		if (count($element->transport_quality)) {
			$review->ratingTransportQuality = (float)$element->transport_quality;
		} else {
			$review->ratingTransportQuality = null;
		}

		if (count($element->web_usability)) {
			$review->ratingWebUsability = (float)$element->web_usability;
		} else {
			$review->ratingWebUsability = null;
		}

		$review->ratingId = (int)$element->rating_id;
		$review->reaction = (string)$element->reaction;
		$review->summary = (string)$element->summary;

		return $review;

	}





}



// src/ProductReview.php 



/**
 * Recenze produktu
 */
class ProductReview {

	/**
	 * Číslo recenze v rámci souboru, začíná se od 0
	 *
	 * @var number
	 */
	public $index;

	/**
	 * Jedinečné ID recenze (dané Heurékou)
	 *
	 * @var number
	 */
	public $ratingId;

	/**
	 * Jméno autora. Empty string = anonymní.
	 *
	 * @var string
	 */
	public $author;

	/**
	 * Datum a čas napsání recenze
	 *
	 * @var \DateTime
	 */
	public $date;

	/**
	 * Hodnocení produktu na stupnici od 0.5 do 5.
	 *
	 * @var number|null
	 */
	public $rating;

	/**
	 * Hlavní výhody produktu
	 * Víceřádkový řetězec, zpravidla co řádek, to jeden bod
	 *
	 * @var string
	 */
	public $pros;

	/**
	 * Hlavní nevýhody produktu
	 * Víceřádkový řetězec, zpravidla co řádek, to jeden bod
	 *
	 * @var string
	 */
	public $cons;

	/**
	 * Celkové shrnutí názoru zákazníka na produkt
	 *
	 * @var string
	 */
	public $summary;

	/**
	 * ID hodnoceného produktu dle e-shopu.
	 *
	 * Nepochází z Heuréky, jde o výstup z IdResolver funkce, pokud není definována,
	 * je vždy null.
	 *
	 * @var mixed|null
	 * @see Heureka::setIdResolver
	 */
	public $productId;

	/**
	 * Název produktu
	 *
	 * @var string
	 */
	public $productName;

	/**
	 * URL produktu
	 *
	 * @var string
	 */
	public $productUrl;

	/**
	 * Cena produktu (bez DPH)
	 *
	 * @var number
	 */
	public $productPrice;

	/**
	 * EAN produktu
	 *
	 * @var string
	 */
	public $productEan;

	/**
	 * Číslo produktu
	 *
	 * @var string
	 */
	public $productNumber;

	/**
	 * Číslo objednávky, na níž zákazník psal recenzi
	 *
	 * @var string
	 */
	public $orderId;

	/**
	 * @return array
	 */
	function getAsArray() {
		return get_object_vars($this);
	}


}




// src/ProductReviewSummary.php 



/**
 * Shrnutí recenzí jednoho konkrétního produktu
 */
class ProductReviewSummary {

	/**
	 * ID produktu
	 *
	 * @var mixed
	 */
	public $productId;

	/**
	 * Počet recenzí na tento produkt
	 *
	 * @var int
	 */
	public $reviewCount = 0;

	/**
	 * Počet hodnocení na tento produkt.
	 * Počet hodnocení a počet recenzí nemusí nutně být totéž.
	 *
	 * @var int
	 */
	public $ratingCount = 0;

	/**
	 * Průměrné hodnocení na stupnici 0.5 až 5 hvězdiček
	 *
	 * @var float
	 */
	public $averageRating = 0;

	/**
	 * Celkový počet hvězdiček
	 *
	 * @var float
	 */
	public $totalStars = 0;

	/**
	 * Nejlepší hodnocení
	 *
	 * @var float
	 */
	public $bestRating = 0;


	/**
	 * Nejhorší hodnocení
	 *
	 * @var float
	 */
	public $worstRating = 0;

	/**
	 * Datum nejstarší recenze
	 *
	 * @var \DateTime
	 */
	public $oldestReviewDate = null;

	/**
	 * Datum nejmladší recenze
	 *
	 * @var \DateTime
	 */
	public $newestReviewDate = null;

	/**
	 * Jednotlivé recenze, které se tohoto produktu týkají
	 *
	 * @var array of ProductReview
	 */
	public $reviews = array();

}



// src/ProductReviewsClient.php 



/**
 * Klient umožňující stahovat recenze jednotlivých produktů
 */
class ProductReviewsClient extends BaseClient {

	protected $idResolver;

	protected $saveSummary = false;
	protected $saveGroupedReviews = false;

	/**
	 * @ignore
	 */
	function getNodeName() {
		return "product";
	}

	/**
	 * @param string $key Heuréka klíč (32 znaků) anebo celá adresa pro stahování importu
	 * @param \DateTime $from Volitelně lze omezit, odkdy chceš recenze stáhnout. Max 6 měsíců zpátky. Funguje jen zadáš-li jako $key 32znakový klíč.
	 */
	function __construct($key = null, \DateTime $from = null) {
		parent::__construct($key);
		$this->setKey($key, $from);
	}

	/**
	 *
	 * @param string $key Heuréka klíč (32 znaků) anebo celá adresa pro stahování importu
	 * @param \DateTime $from Volitelně lze omezit, odkdy chceš recenze stáhnout. Max 6 měsíců zpátky. Funguje jen zadáš-li jako $key 32znakový klíč.
	 */
	public function setKey($key, \DateTime $from = null) {

		$fromPart = "";
		if ($from) {
			$fromPart = "&from=" . $from->format("Y-m-d H:i:s");
		}

		if (preg_match('~^\w{32}$~', $key)) {
			$this->setSourceAddress("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=" . $key . $fromPart);
		} else {
			$this->setSourceAddress($key);
		}

	}

	/**
	 * @ignore
	 */
	function processFile() {
		$this->idResolverCache = array();
		$this->summary = array();
		return parent::processFile();
	}

	/**
	 * @ignore
	 */
	public function processElement(\SimpleXMLElement $element, $index) {

		$review = new ProductReview();

		$reviewElement = $element->reviews->review[0];

		$review->index = $index;

		$review->author = (string)$reviewElement->name;
		$review->cons = (string)$reviewElement->cons;
		$review->date = new \DateTime();
		$review->date->setTimestamp((int)$reviewElement->unix_timestamp);
		$review->orderId = (string)$element->order_id;
		$review->productName = (string)$element->product_name;
		$review->productUrl = (string)$element->url;
		$review->productPrice = (float)$element->price;
		$review->productEan = (string)$element->ean;
		$review->productNumber = (string)$element->productno;
		$review->pros = (string)$reviewElement->pros;
		$review->ratingId = (int)$reviewElement->rating_id;
		$review->summary = (string)$reviewElement->summary;

		if (count($reviewElement->rating) > 0) {
			$review->rating = (float)$reviewElement->rating;
		} else {
			$review->rating = null;
		}

		$prodId = $this->resolveId($review);
		$review->productId = $prodId;

		if ($prodId) {
			$this->addReviewToSummary($prodId, $review);
		}

		return $review;

	}

	/**
	 * @return callable|null
	 */
	public function getIdResolver() {
		return $this->idResolver;
	}

	/**
	 * Nastaví funkci odpovědnou za převedení informací o produktu na jeho jednoznačné ID.
	 * Tuto funkci je třeba implementovat, aby dobře fungovaly summary.
	 * @param callable|null $idConverter
	 * @return ProductReviewsClient
	 * @throws \InvalidArgumentException
	 */
	public function setIdResolver($idConverter) {

		if ($idConverter and !is_callable($idConverter)) {
			throw new \InvalidArgumentException("Given callback is not callable.");
		}

		$this->idResolver = $idConverter;
		return $this;
	}

	/**
	 * Mají se ukládat summary?
	 * @return bool
	 */
	public function getSaveSummary() {
		return $this->saveSummary;
	}

	/**
	 * Mají se ukládat do summary i všechny recenze?
	 * @return bool
	 */
	public function getSaveGroupedReviews() {
		return $this->saveGroupedReviews;
	}

	/**
	 * Mají se průběžně ukládat summary? Umožní po proběhnutí importu
	 * pracovat s shrnujícími daty.
	 *
	 * @param bool $saveSummary
	 * @param bool $groupedReviews Mají se ukládat i všechny recenze?
	 *
	 * @return ProductReviewsClient
	 */
	public function setSaveSummary($saveSummary, $groupedReviews = true) {
		$this->saveSummary = $saveSummary ? true : false;
		$this->saveGroupedReviews = $groupedReviews ? true : false;
		return $this;
	}


	/* ------------- ID resolving ------------ */

	protected $idResolverCache = array();

	/**
	 * Vyhodnotí ID produktu
	 *
	 * @param ProductReview $review
	 * @return mixed|null
	 */
	public function resolveId(ProductReview $review) {

		if (!$this->idResolver) {
			return null;
		}

		$str = $review->productName . "|" . $review->productNumber . "|" . $review->productPrice . "|" . $review->productUrl;
		$hash = md5($str);

		if (array_key_exists($hash, $this->idResolverCache)) {
			return $this->idResolverCache[$hash];
		}

		$resolvedId = call_user_func_array($this->idResolver, array($review));
		$this->idResolverCache[$hash] = $resolvedId;

		return $resolvedId;

	}


	/* --------- Summary functions ---------- */

	protected $summary = array();

	/**
	 * @ignore
	 */
	protected function addReviewToSummary($productId, ProductReview $review) {
		if (!$productId) {
			return;
		}

		if (!$this->saveSummary) {
			return;
		}

		if (!isset($this->summary[$productId])) {

			$summary = new ProductReviewSummary();
			$summary->productId = $productId;
			$this->summary[$productId] = $summary;

		}

		$summary = $this->summary[$productId];

		$summary->reviewCount++;

		if ($review->rating !== null) {
			$summary->ratingCount++;
			$summary->totalStars += $review->rating;
			$summary->averageRating = round($summary->totalStars / $summary->ratingCount, 1);

			if (!$summary->bestRating or $summary->bestRating < $review->rating) {
				$summary->bestRating = $review->rating;
			}
			if (!$summary->worstRating or $summary->worstRating > $review->rating) {
				$summary->worstRating = $review->rating;
			}
		}

		if (!$summary->newestReviewDate or $summary->newestReviewDate < $review->date) {
			$summary->newestReviewDate = $review->date;
		}
		if (!$summary->oldestReviewDate or $summary->oldestReviewDate > $review->date) {
			$summary->oldestReviewDate = $review->date;
		}

		if ($this->saveGroupedReviews) {
			$summary->reviews[] = $review;
		}

	}

	/**
	 * Vrátí pole ID všech produktů, které v datech byly.
	 *
	 * @return array
	 */
	function getAllProductIds() {
		return array_keys( $this->summary );
	}

	/**
	 * Vrát všechny summary jako pole.
	 *
	 * @return array of ProductReviewSummary
	 */
	function getAllSummaries() {
		return $this->summary;
	}

	/**
	 * Vrací summary pro konkrétní produkt, null když takový není nalezen.
	 *
	 * @param mixed $productId
	 * @return ProductReviewSummary|null
	 */
	function getSummaryOfProduct($productId) {
		return isset($this->summary[$productId]) ? $this->summary[$productId] : null;
	}

	/**
	 * Vrátí všechny recenze daného produktu jako pole. Prázdné pole, nemá-li žádné recenze.
	 *
	 * @param mixed $productId
	 * @return array of ProductReviewSummary
	 */
	function getReviewsOfProduct($productId) {
		return isset($this->summary[$productId]) ? ($this->summary[$productId]->reviews) : array();
	}

}



