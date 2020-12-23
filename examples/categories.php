<?php

include __DIR__.'/../vendor/autoload.php';

use CSTayyab\GooglePlayScraper\Scraper;

$scraper = new Scraper();

$categories = $scraper->getCategories();
var_export($categories);
