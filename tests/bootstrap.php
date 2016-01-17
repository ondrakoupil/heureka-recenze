<?php

require __DIR__ . "/../vendor/autoload.php";

define("TMP_TEST_DIR", __DIR__ . "/temp");
date_default_timezone_set("Europe/Prague");

\Tester\Environment::setup();
