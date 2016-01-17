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
