<?php

namespace Sunaoka\GooglePlayScraper;

use Goutte\Client as BaseClient;
use Symfony\Component\BrowserKit\Response;

class Client extends BaseClient
{
    protected function filterResponse($response): Response
    {
        $content = str_replace(chr(0), '', $response->getContent());
        $newResponse = new Response(
            $content,
            $response->getStatusCode(),
            $response->getHeaders()
        );

        return $newResponse;
    }
}
