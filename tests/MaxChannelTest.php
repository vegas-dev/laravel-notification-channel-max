<?php

namespace Vegas\MaxNotificationChannel\Tests;

use PHPUnit\Framework\TestCase;
use Vegas\MaxNotificationChannel\MaxBotClient;
use Vegas\MaxNotificationChannel\MaxChannel;
use Vegas\MaxNotificationChannel\Messages\MaxMessage;
use Illuminate\Notifications\Notification;
use Mockery;
use Illuminate\Contracts\Events\Dispatcher;

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
            ->with('Test message', '12345', [])
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
            ->with('String message', '12345', [])
            ->andReturn(['id' => 'bar']);

        $response = $this->channel->send($notifiable, $notification);

        $this->assertEquals(['id' => 'bar'], $response);
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
