<?php

namespace Vegas\MaxNotificationChannel\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Vegas\MaxNotificationChannel\MaxBotClient;
use Vegas\MaxNotificationChannel\Messages\MaxMessage;

class MaxBotClientTest extends TestCase
{
    public function test_it_sends_the_preview_flag_in_the_query_when_requested(): void
    {
        $http = new MockHandler([new Response(200, [], '{}')]);
        $client = $this->client($http);
        $message = MaxMessage::create('https://example.com/page')->withoutLinkPreview();

        $client->sendMessage($message->content, '-123', $message->payload);

        $request = $http->getLastRequest();
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['chat_id' => '-123', 'disable_link_preview' => 'true'], $query);
        $this->assertSame(['text' => 'https://example.com/page', 'format' => 'markdown'], json_decode((string) $request->getBody(), true));
    }

    public function test_it_uses_the_api_default_when_the_preview_flag_is_not_set(): void
    {
        $http = new MockHandler([new Response(200, [], '{}')]);
        $client = $this->client($http);
        $message = MaxMessage::create('https://example.com/page');

        $client->sendMessage($message->content, '123', $message->payload);

        $request = $http->getLastRequest();
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['user_id' => '123'], $query);
        $this->assertSame(['text' => 'https://example.com/page', 'format' => 'markdown'], json_decode((string) $request->getBody(), true));
    }

    public function test_it_omits_the_preview_flag_when_editing_a_message(): void
    {
        $http = new MockHandler([new Response(200, [], '{}')]);
        $client = $this->client($http);
        $message = MaxMessage::create('https://example.com/page')->withoutLinkPreview();

        $client->editMessage('mid.123', $message->content, '-123', $message->payload);

        $request = $http->getLastRequest();
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['chat_id' => '-123', 'message_id' => 'mid.123'], $query);
        $this->assertSame(['text' => 'https://example.com/page', 'format' => 'markdown'], json_decode((string) $request->getBody(), true));
    }

    private function client(MockHandler $http): MaxBotClient
    {
        return new class($http) extends MaxBotClient
        {
            public function __construct(MockHandler $http)
            {
                parent::__construct('test-token', 'https://max-api.example.com');
                $this->client = new Client(['handler' => $http]);
            }
        };
    }

    public function test_it_can_explicitly_enable_previews_after_disabling_them(): void
    {
        $http = new MockHandler([new Response(200, [], '{}')]);
        $client = $this->client($http);
        $message = MaxMessage::create('https://example.com/page')->withoutLinkPreview()->linkPreview(true);

        $client->sendMessage($message->content, '-123', $message->payload);

        $request = $http->getLastRequest();
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['chat_id' => '-123', 'disable_link_preview' => 'false'], $query);
        $this->assertSame(['text' => 'https://example.com/page', 'format' => 'markdown'], json_decode((string) $request->getBody(), true));
    }
}
