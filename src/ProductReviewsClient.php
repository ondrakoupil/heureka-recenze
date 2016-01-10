<?php

namespace OndraKoupil\Heureka;

class ProductReviewsClient extends BaseClient {

	protected $idResolver;

	protected $saveSummary = false;
	protected $saveGroupedReviews = false;

	function getNodeName() {
		return "product";
	}

	function __construct($key = null, \DateTime $from = null) {
		parent::__construct($key);
		$this->setKey($key, $from);
	}

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

	function processFile() {
		$this->idResolverCache = array();
		$this->summary = array();
		return parent::processFile();
	}

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
		$review->rating = (float)$reviewElement->rating;
		$review->ratingId = (int)$reviewElement->rating_id;
		$review->summary = (string)$reviewElement->summary;

		$prodId = $this->resolveId($review);
		$review->productId = $prodId;

		if ($prodId) {
			$this->addReviewToSummary($prodId, $review);
		}

		return $review;

	}

	public function getIdResolver() {
		return $this->idResolver;
	}

	public function setIdResolver($idConverter) {

		if ($idConverter and !is_callable($idConverter)) {
			throw new \InvalidArgumentException("Given callback is not callable.");
		}

		$this->idResolver = $idConverter;
		return $this;
	}

	public function getSaveSummary() {
		return $this->saveSummary;
	}

	public function getSaveGroupedReviews() {
		return $this->saveGroupedReviews;
	}

	public function setSaveSummary($saveSummary, $groupedReviews = true) {
		$this->saveSummary = $saveSummary ? true : false;
		$this->saveGroupedReviews = $groupedReviews ? true : false;
		return $this;
	}


	/* ------------- ID resolving ------------ */

	protected $idResolverCache = array();

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
		$summary->totalStars += $review->rating;
		$summary->averageRating = round($summary->totalStars / $summary->reviewCount, 1);

		if (!$summary->bestRating or $summary->bestRating < $review->rating) {
			$summary->bestRating = $review->rating;
		}
		if (!$summary->worstRating or $summary->worstRating > $review->rating) {
			$summary->worstRating = $review->rating;
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
	 * @return array
	 */
	function getAllProductIds() {
		return array_keys( $this->summary );
	}

	/**
	 * @return array of ProductReviewSummary
	 */
	function getAllSummaries() {
		return $this->summary;
	}

	/**
	 * @param mixed $productId
	 * @return ProductReviewSummary
	 */
	function getSummaryOfProduct($productId) {
		return isset($this->summary[$productId]) ? $this->summary[$productId] : null;
	}

	/**
	 * @param mixed $productId
	 * @return array of ProductReviewSummary
	 */
	function getReviewsOfProduct($productId) {
		return isset($this->summary[$productId]) ? ($this->summary[$productId]->reviews) : array();
	}

}
