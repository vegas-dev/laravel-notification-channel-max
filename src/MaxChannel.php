<?php

namespace Vegas\MaxNotificationChannel;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Vegas\MaxNotificationChannel\Messages\MaxMessage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Vegas\MaxNotificationChannel\Exceptions\CouldNotSendNotification;

class MaxChannel
{
    protected $client;
    protected $events;

    public function __construct(MaxBotClient $client, Dispatcher $events)
    {
        $this->client = $client;
        $this->events = $events;
    }

    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toMax($notifiable);

        if (is_string($message)) {
            $message = MaxMessage::create($message);
        }

        if (!$message instanceof MaxMessage) {
            return null;
        }

        $chatId = $message->chatId ?: $notifiable->routeNotificationFor('max', $notification);

        try {
            return $this->client->sendMessage(
                $message->content,
                $chatId,
                $message->payload
            );
        } catch (CouldNotSendNotification $e) {
            $this->events->dispatch(new NotificationFailed(
                $notifiable,
                $notification,
                'max',
                ['message' => $e->getMessage(), 'exception' => $e]
            ));

            Log::error('Max notification error: ' . $e->getMessage(), [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
                'exception' => $e
            ]);

            throw $e;
        }
    }
}
