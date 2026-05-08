<?php

namespace Vegas\MaxNotificationChannel\Tests;

use PHPUnit\Framework\TestCase;
use Vegas\MaxNotificationChannel\MaxBotClient;
use Vegas\MaxNotificationChannel\MaxChannel;
use Vegas\MaxNotificationChannel\Messages\MaxMessage;
use Illuminate\Notifications\Notification;
use Mockery;
use Illuminate\Contracts\Events\Dispatcher;
use Vegas\MaxNotificationChannel\Exceptions\CouldNotSendNotification;
use Illuminate\Support\Facades\Log;

class MaxChannelTest extends TestCase
{
    protected $client;
    protected $events;
    protected $channel;

    protected function setUp(): void
    {
        $this->client = Mockery::mock(MaxBotClient::class);
        $this->events = Mockery::mock(Dispatcher::class);
        $this->channel = new MaxChannel($this->client, $this->events);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_it_sends_notification()
    {
        $notifiable = new TestNotifiable();
        $notification = new TestNotification();

        $this->client->shouldReceive('sendMessage')
            ->once()
            ->with('Test message', '12345', ['format' => 'markdown'])
            ->andReturn(['id' => 'foo']);

        $response = $this->channel->send($notifiable, $notification);

        $this->assertEquals(['id' => 'foo'], $response);
    }

    public function test_it_sends_notification_with_string_message()
    {
        $notifiable = new TestNotifiable();
        $notification = new TestStringNotification();

        $this->client->shouldReceive('sendMessage')
            ->once()
            ->with('String message', '12345', ['format' => 'markdown'])
            ->andReturn(['id' => 'bar']);

        $response = $this->channel->send($notifiable, $notification);

        $this->assertEquals(['id' => 'bar'], $response);
    }

    public function test_it_uploads_attachments_before_sending()
    {
        $notifiable = new TestNotifiable();
        $notification = new TestUploadNotification();

        $this->client->shouldReceive('uploadAttachment')
            ->once()
            ->with('audio', 'https://example.com/call.mp3')
            ->andReturn([
                'type' => 'audio',
                'payload' => ['token' => 'token'],
            ]);

        $this->client->shouldReceive('sendMessage')
            ->once()
            ->with('Message with audio', '12345', [
                'format' => 'markdown',
                'attachments' => [
                    [
                        'type' => 'audio',
                        'payload' => ['token' => 'token'],
                    ],
                ],
            ])
            ->andReturn(['id' => 'baz']);

        $response = $this->channel->send($notifiable, $notification);

        $this->assertEquals(['id' => 'baz'], $response);
    }

    public function test_it_sends_message_with_link_when_upload_fails()
    {
        $notifiable = new TestNotifiable();
        $notification = new TestUploadNotification();

        $this->client->shouldReceive('uploadAttachment')
            ->once()
            ->with('audio', 'https://example.com/call.mp3')
            ->andThrow(CouldNotSendNotification::couldNotCommunicateWithMax(
                new \RuntimeException('Upload failed')
            ));

        Log::shouldReceive('warning')->once();

        $this->client->shouldReceive('sendMessage')
            ->once()
            ->with('Message with audio', '12345', [
                'format' => 'markdown',
                'attachments' => [
                    [
                        'type' => 'inline_keyboard',
                        'payload' => [
                            'buttons' => [
                                [
                                    [
                                        'type' => 'link',
                                        'text' => 'Запись разговора',
                                        'url' => 'https://example.com/call.mp3',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->andReturn(['id' => 'fallback']);

        $response = $this->channel->send($notifiable, $notification);

        $this->assertEquals(['id' => 'fallback'], $response);
    }
}

class TestNotifiable
{
    public function routeNotificationFor($channel)
    {
        return '12345';
    }
}

class TestNotification extends Notification
{
    public function toMax($notifiable)
    {
        return MaxMessage::create('Test message');
    }
}

class TestStringNotification extends Notification
{
    public function toMax($notifiable)
    {
        return 'String message';
    }
}

class TestUploadNotification extends Notification
{
    public function toMax($notifiable)
    {
        return MaxMessage::create('Message with audio')
            ->audio('https://example.com/call.mp3');
    }
}
