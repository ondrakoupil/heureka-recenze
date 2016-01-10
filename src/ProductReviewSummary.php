<?php

namespace OndraKoupil\Heureka;

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
