# CHANGELOG

## 0.4.0 (2021-05-31)

### Changed

* Package Information (e.g. author, name etc)
* Goutte 4.0
* PHPUnit 9.5
* PHP CS Fixer 3.0

### Removed

* Dropped Support for PHP 7.x

## 0.3.1 (2020-12-23)

### Changed

* Package Information (e.g. author, name etc)
* Fixed "Error: Call to undefined method Symfony\Component\BrowserKit\Response::getStatus()" using PR https://github.com/raulr/google-play-scraper/pull/36

### Removed

* Dropped Support for PHP 5.6 & PHP 7.0

## 0.3.0 (2019-06-16)

### Changed

* Update scraping to the current markup in Play Store website.

## 0.2.0 (2016-11-10)

### Added

* `getSearch` and `getDetailSearch` methods.

### Changed

* Convert schemaless URLs into absolute URLs.

## 0.1.1 (2016-04-09)

### Fixed

* Fix throwing InvalidArgumentException on `Scraper::getApp()` when app page contains a `NULL` byte.
* Remove duplicate license field on composer.json.

## 0.1.0 (2016-01-04)

* Initial release.
