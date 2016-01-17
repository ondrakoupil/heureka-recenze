<?php

use OndraKoupil\Heureka\ProductReviewsClient;
use OndraKoupil\Heureka\ProductReview;
use OndraKoupil\Heureka\ProductReviewSummary;
use OndraKoupil\Testing\Assert as OKAssert;
use Tester\Assert;

include "../bootstrap.php";

class ProductReviewsClientTest extends \Tester\TestCase {

	function testConstruct() {

		$client = new ProductReviewsClient("abcdeABCDE1234567890123456789012");
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=abcdeABCDE1234567890123456789012", $client->getSourceAddress());

		$client = new ProductReviewsClient("abcdeABCDE-234567890123456789012");
		Assert::same("abcdeABCDE-234567890123456789012", $client->getSourceAddress());

		$client = new ProductReviewsClient("abcdeABCDE");
		Assert::same("abcdeABCDE", $client->getSourceAddress());

		$client = new ProductReviewsClient("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=abcdeABCDE1234567890123456789012");
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=abcdeABCDE1234567890123456789012", $client->getSourceAddress());

		$client = new ProductReviewsClient();
		Assert::same(null, $client->getSourceAddress());

		// Now with time parametere
		$client = new ProductReviewsClient("abcdeABCDE1234567890123456789012", new DateTime("2015-01-01"));
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=abcdeABCDE1234567890123456789012&from=2015-01-01 00:00:00", $client->getSourceAddress());

		$client = new ProductReviewsClient("abcdeABCDE1234567890123456789012", new DateTime("now"));
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=abcdeABCDE1234567890123456789012&from=" . date("Y-m-d H:i:s"), $client->getSourceAddress());

		$client = new ProductReviewsClient("blbost", new DateTime("now"));
		Assert::same("blbost", $client->getSourceAddress());

	}


	function testGetSet() {

		$client = new ProductReviewsClient();

		$client->setSourceAddress("aaa");
		Assert::same("aaa", $client->getSourceAddress());

		$client->setKey("abcdeABCDE1234567890123456789012");
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-product-review.php?key=abcdeABCDE1234567890123456789012", $client->getSourceAddress());

		$client->setTempFile("abcde.xml");
		Assert::same("abcde.xml", $client->getTempFile());
		Assert::true($client->getDeleteTempFileAfterParsing());

		$client->setTempFile("abcde.xml", false);
		Assert::same("abcde.xml", $client->getTempFile());
		Assert::false($client->getDeleteTempFileAfterParsing());

		$client->setTempFile("abcde.xml", true);
		$client->setTempFile(false);
		Assert::false($client->getTempFile());

		$client->useFile(__DIR__ . "/../example-data/product-reviews.xml");
		Assert::same(__DIR__ . "/../example-data/product-reviews.xml", $client->getTempFile());
		Assert::false($client->getDeleteTempFileAfterParsing());

		Assert::exception(function() use ($client) {
			$client->useFile("nonexistent-file.xml");
		}, 'RuntimeException');

		Assert::same(__DIR__ . "/../example-data/product-reviews.xml", $client->getTempFile());

	}

	function testParse() {

		$client = new ProductReviewsClient();
		$client->useFile(__DIR__ . "/../example-data/product-reviews.xml");

		$reviews = array();

		$client->setCallback(function(ProductReview $review) use (&$reviews) {
			$reviews[] = $review;
		});

		$client->run();

		Assert::same(5, count($reviews));

		Assert::same(290.00, $reviews[0]->productPrice);
		Assert::same('14370', $reviews[1]->orderId);
		Assert::same('Stojan notový Pecka MSP-008 RD', $reviews[2]->productName);
		Assert::same(5948610, $reviews[3]->ratingId);
		Assert::same('vhodná pro začátečníky', $reviews[4]->summary);
		Assert::same('cena=kvalita', $reviews[3]->pros);
		Assert::same('', $reviews[1]->cons);
		Assert::same('', $reviews[1]->pros);
		Assert::same('kalamajka', $reviews[2]->author);
		Assert::same("zatím to vypadá dobře\nza ty peníze je to dobrý kšeft\nsnad se nerozpadne", $reviews[2]->pros);
		Assert::same(date("Y-m-d H:i:s", 1452012942), $reviews[3]->date->format("Y-m-d H:i:s"));


		// ID resolver was not set, so no summaries can be constructed

		$summary = $client->getAllSummaries();
		Assert::type('array', $summary);
		Assert::same(0, count($summary));

		Assert::same(array(), $client->getAllProductIds());

		Assert::null($client->getSummaryOfProduct(12345));

		$arr = $reviews[0]->getAsArray();
		foreach($arr as $index=>$val) {
			Assert::same($reviews[0]->$index, $val);
		}


	}


	function testSummaries() {

		$client = new ProductReviewsClient();
		$client->useFile(__DIR__ . "/../example-data/product-reviews.xml");
		Assert::false($client->getSaveSummary());
		$client->setSaveSummary(true);
		Assert::true($client->getSaveSummary());
		Assert::true($client->getSaveGroupedReviews());

		$calls = 0;
		$lastReview = null;

		$client->setIdResolver(function(ProductReview $r) use (&$calls, &$lastReview) {
			$calls++;
			$lastReview = $r;
			return $r->productUrl;
		});

		$client->run();

		Assert::same(4, $calls);


		Assert::same(4, count($client->getAllProductIds()));
		Assert::same(4, count($client->getAllSummaries()));

		OKAssert::arraySame(
			array(
				"http://www.someeshop.cz/sopranova-zobcova-fletna-drevena-mollenhauer.html",
				"http://www.someeshop.cz/stojan-notovy-pecka.html",
				"http://www.someeshop.cz/klasicka-kytara-44-pecka.html",
				"http://www.someeshop.cz/klasicka-kytara-44-pecka-nat.html"
			),
			$client->getAllProductIds()
		);

		Assert::type('Ondrakoupil\Heureka\ProductReviewSummary', $client->getSummaryOfProduct('http://www.someeshop.cz/stojan-notovy-pecka.html'));
		Assert::null($client->getSummaryOfProduct('abcde'));

		$summary = $client->getSummaryOfProduct('http://www.someeshop.cz/stojan-notovy-pecka.html');
		Assert::same(2, $summary->reviewCount);
		Assert::same(4.0, $summary->averageRating);
		Assert::same(5.0, $summary->bestRating);
		Assert::same(3.0, $summary->worstRating);
		Assert::same(2, count($summary->reviews));
		Assert::same(5957872, $summary->reviews[1]->ratingId);
		Assert::same(date("Y-m-d H:i:s", 1452356372), $summary->newestReviewDate->format("Y-m-d H:i:s"));
		Assert::same(date("Y-m-d H:i:s", 1452316372), $summary->oldestReviewDate->format("Y-m-d H:i:s"));
		Assert::same(8.0, $summary->totalStars);
		Assert::same('http://www.someeshop.cz/stojan-notovy-pecka.html', $summary->productId);

		$summaries = $client->getAllSummaries();
		$summary = $summaries["http://www.someeshop.cz/sopranova-zobcova-fletna-drevena-mollenhauer.html"];

		Assert::same(5.0, $summary->averageRating);
		Assert::same(1, $summary->reviewCount);
		Assert::same("http://www.someeshop.cz/sopranova-zobcova-fletna-drevena-mollenhauer.html", $summary->productId);

		Assert::same($lastReview, $client->getSummaryOfProduct('http://www.someeshop.cz/klasicka-kytara-44-pecka-nat.html')->reviews[0]);
	}

	function testWithNullRatings() {
		$client = new ProductReviewsClient();
		$client->useFile(__DIR__ . "/../example-data/product-reviews-null.xml");
		$client->setSaveSummary(true, true);
		$client->setIdResolver(function(ProductReview $r) {
			return "aaa";
		});
		$client->run();

		$reviews = $client->getSummaryOfProduct("aaa");

		Assert::equal(3, $reviews->reviewCount);
		Assert::equal(2, $reviews->ratingCount);
		Assert::equal(4.0, $reviews->averageRating);
		Assert::equal(3.0, $reviews->reviews[0]->rating);
		Assert::equal(5.0, $reviews->reviews[1]->rating);
		Assert::null($reviews->reviews[2]->rating);
	}


}

$test = new ProductReviewsClientTest();

$test->run();
