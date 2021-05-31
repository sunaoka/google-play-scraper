<?php

include __DIR__.'/../vendor/autoload.php';

use CSTayyab\GooglePlayScraper\Scraper;

$scraper = new Scraper();

$id = $argv[1] ?? 'com.google.android.youtube';
$app = $scraper->getApp($id);
var_export($app);
