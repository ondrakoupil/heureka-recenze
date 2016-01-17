<?php

namespace OndraKoupil\Heureka;

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

	function getAsArray() {
		return get_object_vars($this);
	}

}
