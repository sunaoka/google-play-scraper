<?php

include __DIR__.'/../vendor/autoload.php';

use Sunaoka\GooglePlayScraper\Scraper;

$scraper = new Scraper();

$query = $argv[1] ?? 'unicorns';
$apps = $scraper->getSearch($query);
var_export($apps);
