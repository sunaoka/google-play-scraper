<?php

namespace Sunaoka\GooglePlayScraper\Tests;

use Sunaoka\GooglePlayScraper\Exception\NotFoundException;
use Sunaoka\GooglePlayScraper\Scraper;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Raul Rodriguez <raul@raulr.net>
 */
class ScraperTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function getScraper(MockHttpClient $client = null): Mockery\Mock|Scraper
    {
        return Mockery::mock(Scraper::class, [$client])->makePartial();
    }

    public function testSetDelay(): void
    {
        $scraper = $this->getScraper();
        $scraper->setDelay(2000);
        self::assertEquals(2000, $scraper->getDelay());
    }

    public function testSetDefaultLang(): void
    {
        $scraper = $this->getScraper();
        $scraper->setDefaultLang('es');
        self::assertEquals('es', $scraper->getDefaultLang());
    }

    public function testSetDefaultCountry(): void
    {
        $scraper = $this->getScraper();
        $scraper->setDefaultCountry('fr');
        self::assertEquals('fr', $scraper->getDefaultCountry());
    }

    public function testGetCategories(): void
    {
        $client = new MockHttpClient([new MockResponse(file_get_contents(__DIR__ . '/resources/categories.html'))]);
        $scraper = $this->getScraper($client);
        $app = $scraper->getCategories();
        $expected = json_decode(file_get_contents(__DIR__ . '/resources/categories.json'), true);
        self::assertEquals($expected, $app);
        self::assertEquals('https://play.google.com/store/apps?hl=en&gl=us', $scraper->getClient()->getHistory()->current()->getUri());
    }

    public function testGetApp(): void
    {
        $client = new MockHttpClient([new MockResponse(file_get_contents(__DIR__ . '/resources/app1.html'))]);
        $scraper = $this->getScraper($client);
        $app = $scraper->getApp('com.mojang.minecraftpe', 'en', 'us');
        $expected = json_decode(file_get_contents(__DIR__ . '/resources/app1.json'), true);
        self::assertEquals($expected, $app);
        self::assertEquals('https://play.google.com/store/apps/details?id=com.mojang.minecraftpe&hl=en&gl=us', $scraper->getClient()->getHistory()->current()->getUri());
    }

    public function testGetAppIsFree(): void
    {
        $client = new MockHttpClient([new MockResponse(file_get_contents(__DIR__ . '/resources/app2.html'))]);
        $scraper = $this->getScraper($client);
        $app = $scraper->getApp('com.instagram.android', 'zh', 'cn');
        $expected = json_decode(file_get_contents(__DIR__ . '/resources/app2.json'), true);
        self::assertEquals($expected, $app);
        self::assertEquals('https://play.google.com/store/apps/details?id=com.instagram.android&hl=zh&gl=cn', $scraper->getClient()->getHistory()->current()->getUri());
    }

    public function testGetAppNotFound(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $scraper = $this->getScraper($client);
        $this->expectException(NotFoundException::class);
        $scraper->getApp('non.existing.app');
    }

    public function testGetApps(): void
    {
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getApp')
            ->with('app1_id', null, null)
            ->once()
            ->andReturn(['app1_data']);
        $scraper
            ->shouldReceive('getApp')
            ->with('app2_id', null, null)
            ->once()
            ->andReturn(['app2_data']);

        $apps = $scraper->getApps(['app1_id', 'app2_id']);
        $expected = [
            'app1_id' => ['app1_data'],
            'app2_id' => ['app2_data'],
        ];
        self::assertEquals($expected, $apps);
    }

    public function testGetListChunk(): void
    {
        $client = new MockHttpClient([new MockResponse(file_get_contents(__DIR__ . '/resources/list.html'))]);
        $scraper = $this->getScraper($client);
        $list = $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 0, 2, 'en', 'us');
        $expected = json_decode(file_get_contents(__DIR__ . '/resources/list.json'), true);
        self::assertEquals($expected, $list);
        self::assertEquals('https://play.google.com/store/apps/category/GAME_ARCADE/collection/topselling_paid?hl=en&gl=us&start=0&num=2', $scraper->getClient()->getHistory()->current()->getUri());
    }

    public function testGetListChunkStartNotInt(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('InvalidArgumentException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 'zero');
    }

    public function testGetListChunkStartTooBig(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('RangeException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 181);
    }

    public function testGetListChunkNumNotInt(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('InvalidArgumentException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 0, 'ten');
    }

    public function testGetListChunkNumTooBig(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('RangeException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 0, 121);
    }

    public function testGetList(): void
    {
        $expected = range(0, 100);
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getListChunk')
            ->with('topselling_paid', 'GAME_ARCADE', 0, 60, 'en', 'us')
            ->once()
            ->andReturn(array_slice($expected, 0, 60));
        $scraper
            ->shouldReceive('getListChunk')
            ->with('topselling_paid', 'GAME_ARCADE', 60, 60, 'en', 'us')
            ->once()
            ->andReturn(array_slice($expected, 60));

        $apps = $scraper->getList('topselling_paid', 'GAME_ARCADE', 'en', 'us');
        self::assertEquals($expected, $apps);
    }

    public function testGetDetailListChunk(): void
    {
        $expected = [
            'app1_id' => ['app1_data'],
            'app2_id' => ['app2_data'],
        ];
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getListChunk')
            ->with('topselling_paid', 'GAME_ARCADE', 0, 2, 'en', 'us')
            ->once()
            ->andReturn([['id' => 'app1_id'], ['id' => 'app2_id']]);
        $scraper
            ->shouldReceive('getApps')
            ->with(['app1_id', 'app2_id'])
            ->once()
            ->andReturn($expected);

        $apps = $scraper->getDetailListChunk('topselling_paid', 'GAME_ARCADE', 0, 2, 'en', 'us');
        self::assertEquals($expected, $apps);
    }

    public function testGetDetailList(): void
    {
        $expected = [
            'app1_id' => ['app1_data'],
            'app2_id' => ['app2_data'],
        ];
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getList')
            ->with('topselling_paid', 'GAME_ARCADE', 'en', 'us')
            ->once()
            ->andReturn([['id' => 'app1_id'], ['id' => 'app2_id']]);
        $scraper
            ->shouldReceive('getApps')
            ->with(['app1_id', 'app2_id'])
            ->once()
            ->andReturn($expected);

        $apps = $scraper->getDetailList('topselling_paid', 'GAME_ARCADE', 'en', 'us');
        self::assertEquals($expected, $apps);
    }

    public function testGetSearch(): void
    {
        $client = new MockHttpClient([new MockResponse(file_get_contents(__DIR__ . '/resources/search.html'))]);
        $scraper = $this->getScraper($client);
        $search = $scraper->getSearch('unicorns', 'free', '4+', 'en', 'us');
        $expected = json_decode(file_get_contents(__DIR__ . '/resources/search.json'), true);
        self::assertEquals($expected, $search);
        self::assertEquals('https://play.google.com/store/search?q=unicorns&c=apps&hl=en&gl=us&price=1&rating=1', $scraper->getClient()->getHistory()->current()->getUri());
    }

    public function testGetSearchQueryNotString(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('InvalidArgumentException');
        $scraper->getSearch(1.23);
    }

    public function testGetSearchPriceInvalid(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('InvalidArgumentException');
        $scraper->getSearch('unicorns', 0);
    }

    public function testGetSearchRatingInvalid(): void
    {
        $scraper = $this->getScraper();
        $this->expectException('InvalidArgumentException');
        $scraper->getSearch('unicorns', 'all', 0);
    }

    public function testGetDetailSearch(): void
    {
        $expected = [
            'app1_id' => ['app1_data'],
            'app2_id' => ['app2_data'],
        ];
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getSearch')
            ->with('unicorns', 'free', '4+', 'en', 'us')
            ->once()
            ->andReturn([['id' => 'app1_id'], ['id' => 'app2_id']]);
        $scraper
            ->shouldReceive('getApps')
            ->with(['app1_id', 'app2_id'])
            ->once()
            ->andReturn($expected);

        $apps = $scraper->getDetailSearch('unicorns', 'free', '4+', 'en', 'us');
        self::assertEquals($expected, $apps);
    }
}
