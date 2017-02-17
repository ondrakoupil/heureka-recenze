<?php

namespace OndraKoupil\Heureka;

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
			$this->setSourceAddress("https://www.heureka.cz/direct/dotaznik/export-product-review.php?key=" . $key . $fromPart);
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

	protected function isSpecialEmptyResponse($fileContent) {
		return preg_match('/^INFO: No product reviews/u', $fileContent);
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
