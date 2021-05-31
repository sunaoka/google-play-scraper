<?php

include __DIR__.'/../vendor/autoload.php';

use Sunaoka\GooglePlayScraper\Scraper;

$scraper = new Scraper();

$collection = $argv[1] ?? 'topselling_free';
$category = $argv[2] ?? 'SOCIAL';
$apps = $scraper->getList($collection, $category);
var_export($apps);
