<?php

namespace Vegas\MaxNotificationChannel\Tests;

use PHPUnit\Framework\TestCase;
use Vegas\MaxNotificationChannel\Messages\MaxMessage;

class MaxMessageTest extends TestCase
{
    public function test_it_has_markdown_format_by_default()
    {
        $message = new MaxMessage();
        $this->assertEquals('markdown', $message->payload['format']);
    }

    public function test_it_can_be_instantiated()
    {
        $message = new MaxMessage();
        $this->assertInstanceOf(MaxMessage::class, $message);
    }

    public function test_it_can_set_content()
    {
        $message = MaxMessage::create('Hello');
        $this->assertEquals('Hello', $message->content);

        $message->content('World');
        $this->assertEquals('World', $message->content);
    }

    public function test_it_can_set_recipient()
    {
        $message = MaxMessage::create()->to('123');
        $this->assertEquals('123', $message->chatId);
    }

    public function test_it_can_set_payload_options()
    {
        $message = MaxMessage::create()
            ->notify(true)
            ->format('markdown');

        $this->assertEquals(true, $message->payload['notify']);
        $this->assertEquals('markdown', $message->payload['format']);
    }

    public function test_it_can_add_buttons()
    {
        $message = MaxMessage::create()
            ->button('Click Me', 'https://example.com');

        $this->assertCount(1, $message->payload['attachments']);
        $this->assertEquals('inline_keyboard', $message->payload['attachments'][0]['type']);
        $this->assertEquals('Click Me', $message->payload['attachments'][0]['payload']['buttons'][0][0]['text']);
        $this->assertEquals('https://example.com', $message->payload['attachments'][0]['payload']['buttons'][0][0]['url']);
    }

    public function test_it_can_add_multiple_buttons_in_rows()
    {
        $message = MaxMessage::create()
            ->button('Btn 1', 'https://1.com', 0)
            ->button('Btn 2', 'https://2.com', 0)
            ->button('Btn 3', 'https://3.com', 1);

        $buttons = $message->payload['attachments'][0]['payload']['buttons'];
        $this->assertCount(2, $buttons[0]);
        $this->assertCount(1, $buttons[1]);
    }

    public function test_it_replaces_local_domains()
    {
        $message = MaxMessage::create()
            ->button('Test', 'http://my-app.local/test')
            ->link('http://my-app.local/link');

        $this->assertEquals('http://my-app.ru/test', $message->payload['attachments'][0]['payload']['buttons'][0][0]['url']);
        $this->assertEquals('http://my-app.ru/link', $message->payload['link_url']);
    }

    public function test_it_converts_to_array()
    {
        $message = MaxMessage::create('Hello')
            ->to('123')
            ->notify(false);

        $array = $message->toArray();

        $this->assertEquals('Hello', $array['text']);
        $this->assertEquals(false, $array['notify']);
    }
}
