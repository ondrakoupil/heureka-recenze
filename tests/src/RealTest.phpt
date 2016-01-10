<?php

use OndraKoupil\Heureka\EshopReviewsClient;
use OndraKoupil\Heureka\EshopReview;
use Tester\Assert;

include "../bootstrap.php";
include "../real-config.php";

if (!isset($eshopTestKey) or !$eshopTestKey) {
	Tester\Environment::skip("See tests/real-config.php and fill in your eshop key to perform real E2E tests.");
}

class RealTest extends \OndraKoupil\Testing\FilesTestCase {

	public $key;

	function testDownloadAndEshopReviews() {

		$client = new EshopReviewsClient($this->key);

		$file1 = $this->getTempDir() . "/eshop-reviews-1.xml";
		$client->download($file1);
		Assert::true(file_exists($file1));
		Assert::same("<?xml", file_get_contents($file1, false, null, 0, 5));
		$client->deleteTempFileIfNeeded();
		Assert::false(file_exists($file1));

		$file2 = $this->getTempDir() . "/eshop-reviews-2.xml";
		$client->setTempFile($file2);

		$numberOfCalls = 0;
		$client->setCallback(function(EshopReview $r) use (&$numberOfCalls) {
			$numberOfCalls++;
		});

		$client->run();

		Assert::true($numberOfCalls > 0);

		// Without temp file
		$previousNumberOfCalls = $numberOfCalls;
		$numberOfCalls2 = 0;
		$client->setTempFile(false);
		$client->setCallback(function(EshopReview $r) use (&$numberOfCalls2) {
			$numberOfCalls2 += 2;
		});
		$client->run();

		Assert::true($numberOfCalls2 == $previousNumberOfCalls * 2);

	}

	function testProductReviews() {

		$client = new EshopReviewsClient($this->key);

		$file1 = $this->getTempDir() . "/prod-reviews-1.xml";
		$client->download($file1);
		Assert::true(file_exists($file1));
		Assert::same("<?xml", file_get_contents($file1, false, null, 0, 5));
		$client->deleteTempFileIfNeeded();
		Assert::false(file_exists($file1));

		$file2 = $this->getTempDir() . "/prod-reviews-2.xml";
		$client->setTempFile($file2);

		$numberOfCalls = 0;
		$client->setCallback(function(EshopReview $r) use (&$numberOfCalls) {
			$numberOfCalls++;
		});

		$client->run();

		Assert::true($numberOfCalls > 0);

	}

}

$test = new RealTest();
$test->key = $eshopTestKey;

$test->run();