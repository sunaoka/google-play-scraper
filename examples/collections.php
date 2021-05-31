<?php

include __DIR__.'/../vendor/autoload.php';

use Sunaoka\GooglePlayScraper\Scraper;

$scraper = new Scraper();

$collections = $scraper->getCollections();
var_export($collections);
