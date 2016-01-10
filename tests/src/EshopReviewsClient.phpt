<?php

use OndraKoupil\Heureka\EshopReviewsClient;
use OndraKoupil\Heureka\EshopReview;
use Tester\Assert;

include "../bootstrap.php";

class EshopReviewsClientTest extends \Tester\TestCase {

	function testConstruct() {

		$client = new EshopReviewsClient("abcdeABCDE1234567890123456789012");
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-review.php?key=abcdeABCDE1234567890123456789012", $client->getSourceAddress());

		$client = new EshopReviewsClient("abcdeABCDE-234567890123456789012");
		Assert::same("abcdeABCDE-234567890123456789012", $client->getSourceAddress());

		$client = new EshopReviewsClient("abcdeABCDE");
		Assert::same("abcdeABCDE", $client->getSourceAddress());

		$client = new EshopReviewsClient("http://www.heureka.cz/direct/dotaznik/export-review.php?key=abcdeABCDE1234567890123456789012");
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-review.php?key=abcdeABCDE1234567890123456789012", $client->getSourceAddress());

		$client = new EshopReviewsClient();
		Assert::same(null, $client->getSourceAddress());

	}

	function testGetSet() {

		$client = new EshopReviewsClient();

		$client->setSourceAddress("aaa");
		Assert::same("aaa", $client->getSourceAddress());

		$client->setKey("abcdeABCDE1234567890123456789012");
		Assert::same("http://www.heureka.cz/direct/dotaznik/export-review.php?key=abcdeABCDE1234567890123456789012", $client->getSourceAddress());

		$client->setKey("abcdeABCDE");
		Assert::same("abcdeABCDE", $client->getSourceAddress());

		$client->setTempFile("abcde.xml");
		Assert::same("abcde.xml", $client->getTempFile());
		Assert::true($client->getDeleteTempFileAfterParsing());

		$client->setTempFile("abcde.xml", false);
		Assert::same("abcde.xml", $client->getTempFile());
		Assert::false($client->getDeleteTempFileAfterParsing());

		$client->setTempFile("abcde.xml", true);
		$client->setTempFile(false);
		Assert::false($client->getTempFile());

		$client->useFile(__DIR__ . "/../example-data/eshop-reviews.xml");
		Assert::same(__DIR__ . "/../example-data/eshop-reviews.xml", $client->getTempFile());
		Assert::false($client->getDeleteTempFileAfterParsing());

		Assert::exception(function() use ($client) {
			$client->useFile("nonexistent-file.xml");
		}, 'RuntimeException');

		Assert::same(__DIR__ . "/../example-data/eshop-reviews.xml", $client->getTempFile());

	}

	function testParse() {

		$client = new EshopReviewsClient();

		Assert::exception(function() use ($client) {
			$client->run();
		}, 'RuntimeException'); //Address not set yet

		$client->useFile(__DIR__ . "/../example-data/eshop-reviews.xml");

		$reviews = array();

		$client->setCallback(function(EshopReview $review) use (&$reviews) {
			$reviews[] = $review;
		});

		$client->run();

		Assert::same(5, count($reviews));

		Assert::same("14343", $reviews[0]->orderId);
		Assert::same(140332011, $reviews[1]->ratingId);
		Assert::same("", $reviews[2]->author);
		Assert::same("bročka", $reviews[3]->author);
		Assert::same(range(0,4), array_map(function($r) { return $r->index; }, $reviews));

		Assert::same(date("Y-m-d H:i:s", 1452012918), $reviews[0]->date->format("Y-m-d H:i:s"));

		Assert::type('\OndraKoupil\Heureka\EshopReview', $reviews[4]);
		Assert::type('array', $reviews[4]->getAsArray());
		$arr = $reviews[2]->getAsArray();
		foreach($arr as $index=>$value) {
			Assert::same($reviews[2]->$index, $value);
		}
		Assert::same(3.5, $reviews[2]->ratingWebUsability);
		Assert::same("rychlé dodání\ndobré ceny", $reviews[1]->pros);
		Assert::same("nevím o žádných", $reviews[1]->cons);
		Assert::same("spokojena", $reviews[1]->summary);
		Assert::same("", $reviews[3]->cons);
		Assert::same("byl to německý výrobek", $reviews[2]->reaction);
		Assert::same("žlutý kůň Úpěl Ďábelské ódy", $reviews[4]->cons);

	}


}

$test = new EshopReviewsClientTest();

$test->run();