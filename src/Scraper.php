<?php

namespace Sunaoka\GooglePlayScraper;

use Sunaoka\GooglePlayScraper\Exception\NotFoundException;
use Sunaoka\GooglePlayScraper\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Raul Rodriguez <raul@raulr.net>
 */
class Scraper
{
    protected const BASE_URL = 'https://play.google.com';

    protected Client $client;
    protected int $delay = 1000;
    protected float $lastRequestTime;
    protected string $lang = 'en';
    protected string $country = 'us';

    public function __construct(HttpClientInterface $client = null)
    {
        $this->client = new Client($client);
    }

    public function setDelay($delay): void
    {
        $this->delay = (int)$delay;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function setDefaultLang($lang): void
    {
        $this->lang = $lang;
    }

    public function getDefaultLang(): string
    {
        return $this->lang;
    }

    public function setDefaultCountry($country): void
    {
        $this->country = $country;
    }

    public function getDefaultCountry(): string
    {
        return $this->country;
    }

    public function getCategories(): array
    {
        $crawler = $this->request('apps', [
            'hl' => 'en',
            'gl' => 'us',
        ]);

        $collections = $crawler
            ->filter('.LNKfBf a')
            ->reduce(function ($node) {
                return preg_match('/store\/apps\/category\/[A-Z_]+$/', $node->attr('href')) === 1;
            })
            ->each(function ($node) {
                $href = $node->attr('href');
                $hrefParts = explode('/', $href);
                $collection = end($hrefParts);
                $collection = preg_replace('/\?.*$/', '', $collection);

                return $collection;
            });
        $collections = array_unique($collections);

        return $collections;
    }

    public function getCollections(): array
    {
        return [
            'topselling_free',
            'topselling_paid',
            'topselling_new_free',
            'topselling_new_paid',
            'topgrossing',
            'movers_shakers',
        ];
    }

    public function getApp($id, $lang = null, $country = null): array
    {
        $lang = $lang ?? $this->lang;
        $country = $country ?? $this->country;

        $params = [
            'id' => $id,
            'hl' => $lang,
            'gl' => $country,
        ];
        $crawler = $this->request(['apps', 'details'], $params);

        $info = [
            'id'               => null,
            'url'              => null,
            'image'            => null,
            'title'            => null,
            'author'           => null,
            'author_link'      => null,
            'categories'       => [],
            'price'            => null,
            'screenshots'      => [],
            'description'      => null,
            'description_html' => null,
            'rating'           => 0.0,
            'votes'            => 0,
            'last_updated'     => null,
            'size'             => null,
            'downloads'        => null,
            'version'          => null,
            'supported_os'     => null,
            'content_rating'   => null,
            'whatsnew'         => null,
            'video_link'       => null,
            'video_image'      => null,
        ];

        $info['id'] = $id;
        $info['url'] = $crawler->filter('link[rel="alternate"]')->first()->attr('href');
        $info['image'] = $this->getAbsoluteUrl($crawler->filter('[itemprop="image"]')->attr('src'));
        $info['title'] = $crawler->filter('[itemprop="name"] > span')->text();
        $authorNode = $crawler->filter('a.hrTbp.R8zArc')->first();
        if ($authorNode->count()) {
            $info['author'] = $authorNode->text();
            $info['author_link'] = $this->getAbsoluteUrl($authorNode->attr('href'));
        }
        $info['categories'] = $crawler->filter('[itemprop="genre"]')->each(function ($node) {
            return $node->text();
        });
        $priceNode = $crawler->filter('[itemprop="offers"] > [itemprop="price"]');
        if ($priceNode->count()) {
            $price = $priceNode->attr('content');
            $info['price'] = $price === '0' ? null : $price;
        }
        $info['screenshots'] = $crawler->filter('[data-screenshot-item-index] img')->each(function ($node) {
            return $this->getAbsoluteUrl($node->attr('data-src') ?: $node->attr('src'));
        });
        $desc = $this->cleanDescription($crawler->filter('[itemprop="description"] > span > div'));
        $info['description'] = $desc['text'];
        $info['description_html'] = $desc['html'];
        $ratingNode = $crawler->filter('.BHMmbe');
        if ($ratingNode->count()) {
            $info['rating'] = (float)str_replace(',', '.', $ratingNode->text());
        }
        $votesNode = $crawler->filter('.EymY4b > span[aria-label]');
        if ($votesNode->count()) {
            $info['votes'] = (int)str_replace([',', '.', ' '], '', $votesNode->text());
        }
        $extraInfoNodes = $crawler->filter('.hAyfc > .htlgb');
        if ($extraInfoNodes->count() && $extraInfoNodes->first()->filter('div > img:first-child')->count()) {
            $startingExtraInfoNode = 1; // Skip family library node.
        } else {
            $startingExtraInfoNode = 0;
        }
        $extraInfoNodes->slice($startingExtraInfoNode, $startingExtraInfoNode + 6)->each(function ($node) use (&$info) {
            $nodeText = $node->text();
            $nodeText = str_replace("\xc2\xa0", ' ', $nodeText); // convert non breaking to normal space
            if (is_null($info['last_updated']) && preg_match('/20\d\d/', $nodeText)) {
                $info['last_updated'] = $nodeText;
            } elseif (is_null($info['size']) && preg_match('/^[\d,\. ]+[MG]$/', $nodeText)) {
                $info['size'] = $nodeText;
            } elseif (is_null($info['downloads']) && preg_match('/^[\d,\. ]+\+$/', $nodeText)) {
                $info['downloads'] = $nodeText;
            } elseif (is_null($info['version']) && preg_match('/^[\d\.]+$/', $nodeText)) {
                $info['version'] = $nodeText;
            } elseif (is_null($info['supported_os']) && preg_match('/^(\d+\.)+\d+.+$/', $nodeText)) {
                $info['supported_os'] = $nodeText;
            } elseif (is_null($info['content_rating']) && $node->filter('div > .htlgb > div')->count()) {
                $info['content_rating'] = $node->filter('div > .htlgb > div')->first()->text();
            }
        });
        $whatsnewNode = $crawler->filter('[itemprop="description"] > span')->eq(1);
        if ($whatsnewNode->count()) {
            $whatsnew = $this->cleanDescription($whatsnewNode);
            $info['whatsnew'] = $whatsnew['text'];
        }
        $videoNode = $crawler->filter('[data-trailer-url]');
        if ($videoNode->count()) {
            $info['video_link'] = $this->getAbsoluteUrl($videoNode->attr('data-trailer-url'));
            $info['video_image'] = $this->getAbsoluteUrl($videoNode->ancestors()->filter('img')->attr('src'));
        }

        return $info;
    }

    public function getApps($ids, $lang = null, $country = null): array
    {
        $ids = (array)$ids;
        $apps = [];

        foreach ($ids as $id) {
            $apps[$id] = $this->getApp($id, $lang, $country);
        }

        return $apps;
    }

    public function getListChunk($collection, $category = null, $start = 0, $num = 60, $lang = null, $country = null): array
    {
        $lang = $lang ?? $this->lang;
        $country = $country ?? $this->country;

        if (!is_int($start)) {
            throw new \InvalidArgumentException('"start" must be an integer');
        }
        if ($start < 0 || $start > 180) {
            throw new \RangeException('"start" must be a number between 0 and 180');
        }
        if (!is_int($num)) {
            throw new \InvalidArgumentException('"num" must be an integer');
        }
        if ($num < 0 || $num > 120) {
            throw new \RangeException('"num" must be a number between 0 and 120');
        }

        $path = ['apps'];
        if ($category) {
            array_push($path, 'category', $category);
        }
        array_push($path, 'collection', $collection);
        $params = [
            'hl'    => $lang,
            'gl'    => $country,
            'start' => $start,
            'num'   => $num,
        ];
        $crawler = $this->request($path, $params);

        return $this->parseAppList($crawler);
    }

    public function getList($collection, $category = null, $lang = null, $country = null): array
    {
        $lang = $lang ?? $this->lang;
        $country = $country ?? $this->country;
        $start = 0;
        $num = 60;
        $apps = [];

        do {
            $appsChunk = $this->getListChunk($collection, $category, $start, $num, $lang, $country);
            $apps = array_merge($apps, $appsChunk);
            $start += $num;
        } while (count($appsChunk) === $num && $start <= 180);

        return $apps;
    }

    public function getDetailListChunk($collection, $category = null, $start = 0, $num = 60, $lang = null, $country = null): array
    {
        $apps = $this->getListChunk($collection, $category, $start, $num, $lang, $country);
        $ids = array_map(static function ($app) {
            return $app['id'];
        }, $apps);

        return $this->getApps($ids);
    }

    public function getDetailList($collection, $category = null, $lang = null, $country = null): array
    {
        $apps = $this->getList($collection, $category, $lang, $country);
        $ids = array_map(static function ($app) {
            return $app['id'];
        }, $apps);

        return $this->getApps($ids);
    }

    public function getSearch($query, $price = 'all', $rating = 'all', $lang = null, $country = null): array
    {
        $lang = $lang ?? $this->lang;
        $country = $country ?? $this->country;
        $priceValues = [
            'all'  => null,
            'free' => 1,
            'paid' => 2,
        ];
        $ratingValues = [
            'all' => null,
            '4+'  => 1,
        ];

        if (!is_string($query) || empty($query)) {
            throw new \InvalidArgumentException('"query" must be a non empty string');
        }

        if (array_key_exists($price, $priceValues)) {
            $price = $priceValues[$price];
        } else {
            throw new \InvalidArgumentException('"price" must contain one of the following values: ' . implode(', ', array_keys($priceValues)));
        }

        if (array_key_exists($rating, $ratingValues)) {
            $rating = $ratingValues[$rating];
        } else {
            throw new \InvalidArgumentException('"rating" must contain one of the following values: ' . implode(', ', array_keys($ratingValues)));
        }

        $path = ['search'];
        $params = [
            'q'  => $query,
            'c'  => 'apps',
            'hl' => $lang,
            'gl' => $country,
        ];
        if ($price) {
            $params['price'] = $price;
        }
        if ($rating) {
            $params['rating'] = $rating;
        }

        $crawler = $this->request($path, $params);
        return $this->parseSearchAppList($crawler);
    }

    public function getDetailSearch($query, $price = 'all', $rating = 'all', $lang = null, $country = null): array
    {
        $apps = $this->getSearch($query, $price, $rating, $lang, $country);
        $ids = array_map(static function ($app) {
            return $app['id'];
        }, $apps);

        return $this->getApps($ids);
    }

    protected function request($path, array $params = []): Crawler
    {
        // handle delay
        if (!empty($this->delay) && !empty($this->lastRequestTime)) {
            $currentTime = microtime(true);
            $delaySecs = $this->delay / 1000;
            $delay = max(0, $delaySecs - $currentTime + $this->lastRequestTime);
            usleep($delay * 1000000);
        }
        $this->lastRequestTime = microtime(true);

        if (is_array($path)) {
            $path = implode('/', $path);
        }
        $path = ltrim($path, '/');
        $path = rtrim('/store/' . $path, '/');
        $url = self::BASE_URL . $path;
        $query = http_build_query($params);
        if ($query) {
            $url .= '?' . $query;
        }
        $crawler = $this->client->request('GET', $url);
        $status_code = $this->client->getResponse()->getStatusCode();
        if ($status_code === 404) {
            throw new NotFoundException('Requested resource not found');
        } elseif ($status_code !== 200) {
            throw new RequestException(sprintf('Request failed with "%d" status code', $status_code), $status_code);
        }

        return $crawler;
    }

    protected function getAbsoluteUrl($url): string
    {
        $urlParts = parse_url($url);
        $baseParts = parse_url(self::BASE_URL);
        $absoluteParts = array_merge($baseParts, $urlParts);

        $absoluteUrl = $absoluteParts['scheme'] . '://' . $absoluteParts['host'];
        if (isset($absoluteParts['path'])) {
            $absoluteUrl .= $absoluteParts['path'];
        } else {
            $absoluteUrl .= '/';
        }
        if (isset($absoluteParts['query'])) {
            $absoluteUrl .= '?' . $absoluteParts['query'];
        }
        if (isset($absoluteParts['fragment'])) {
            $absoluteUrl .= '#' . $absoluteParts['fragment'];
        }

        return $absoluteUrl;
    }

    protected function parseAppList(Crawler $crawler): array
    {
        return $crawler->filter('.card')->each(function ($node) {
            $app = [];
            $app['id'] = $node->attr('data-docid');
            $app['url'] = self::BASE_URL . $node->filter('a')->attr('href');
            $app['title'] = $node->filter('a.title')->attr('title');
            $app['image'] = $this->getAbsoluteUrl($node->filter('img.cover-image')->attr('data-cover-large'));
            $app['author'] = $node->filter('a.subtitle')->attr('title');
            $ratingNode = $node->filter('.current-rating');
            if (!$ratingNode->count()) {
                $rating = 0.0;
            } elseif (preg_match('/\d+(\.\d+)?/', $node->filter('.current-rating')->attr('style'), $matches)) {
                $rating = (float)$matches[0] * 0.05;
            } else {
                throw new \RuntimeException('Error parsing rating');
            }
            $app['rating'] = $rating;
            $priceNode = $node->filter('.display-price');
            if (!$priceNode->count()) {
                $price = null;
            } elseif (!preg_match('/\d/', $priceNode->text())) {
                $price = null;
            } else {
                $price = $priceNode->text();
            }
            $app['price'] = $price;

            return $app;
        });
    }

    protected function parseSearchAppList(Crawler $crawler): array
    {
        return $crawler->filter('.WHE7ib')->each(function ($node) {
            $app = [];
            $app['url'] = $this->getAbsoluteUrl($node->filter('a.poRVub')->attr('href'));
            $app['id'] = substr($app['url'], strpos($app['url'], '=') + 1);
            $app['title'] = $node->filter('.b8cIId.ReQCgd.Q9MA7b')->attr('title');
            $app['image'] = $this->getAbsoluteUrl($node->filter('img')->attr('data-src'));
            $app['author'] = $node->filter('.b8cIId.ReQCgd.KoLSrc a div')->text();
            $ratingNode = $node->filter('.pf5lIe [aria-label]');
            if (!$ratingNode->count()) {
                $rating = 0.0;
            } elseif (preg_match('/\d+([.,]\d+)?/', $ratingNode->attr('aria-label'), $matches)) {
                $rating = (float)str_replace(',', '.', $matches[0]);
            } else {
                throw new \RuntimeException('Error parsing rating');
            }
            $app['rating'] = $rating;
            $priceNode = $node->filter('.VfPpfd.ZdBevf.i5DZme span');
            if (!$priceNode->count()) {
                $price = null;
            } elseif (!preg_match('/\d/', $priceNode->text())) {
                $price = null;
            } else {
                $price = $priceNode->text();
            }
            $app['price'] = $price;

            return $app;
        });
    }

    protected function cleanDescription(Crawler $descriptionNode): array
    {
        $descriptionNode->filter('a')->each(function ($node) {
            $domElement = $node->getNode(0);
            $href = $domElement->getAttribute('href');
            while (str_starts_with($href, 'https://www.google.com/url?q=')) {
                $parts = parse_url($href);
                parse_str($parts['query'], $query);
                $href = $query['q'];
            }
            $domElement->setAttribute('href', $href);
        });
        $html = $descriptionNode->html();
        $text = trim($this->convertHtmlToText($descriptionNode->getNode(0)));

        return [
            'html' => $html,
            'text' => $text,
        ];
    }

    protected function convertHtmlToText(\DOMNode $node): array|string|null
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/', ' ', $node->wholeText);
        } else {
            $text = '';

            foreach ($node->childNodes as $childNode) {
                $text .= $this->convertHtmlToText($childNode);
            }

            $text = match ($node->nodeName) {
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'div' => "\n\n" . $text . "\n\n",
                'li' => '- ' . $text . "\n",
                'br' => $text . "\n",
                default => $text,
            };

            $text = preg_replace('/\n{3,}/', "\n\n", $text);
        }

        return $text;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
