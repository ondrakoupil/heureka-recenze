<?php

namespace OndraKoupil\Heureka;

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
		$review->ratingCommunication = (float)$element->communication;
		$review->ratingDelivery = (float)$element->delivery_time;
		$review->ratingId = (int)$element->rating_id;
		$review->ratingTotal = (float)$element->total_rating;
		$review->ratingTransportQuality = (float)$element->transport_quality;
		$review->ratingWebUsability = (float)$element->web_usability;
		$review->reaction = (string)$element->reaction;
		$review->summary = (string)$element->summary;

		return $review;

	}





}
