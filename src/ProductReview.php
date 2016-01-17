<?php

namespace OndraKoupil\Heureka;

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

}

