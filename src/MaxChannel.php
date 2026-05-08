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
            foreach ($message->uploads as $upload) {
                try {
                    if (!empty($upload['path'])) {
                        $message->addAttachment($this->client->uploadAttachmentFile(
                            $upload['type'],
                            $upload['path'],
                            $upload['filename'] ?? null,
                            $upload['mime'] ?? null
                        ));
                    } else {
                        $message->addAttachment($this->client->uploadAttachment($upload['type'], $upload['url']));
                    }
                } catch (CouldNotSendNotification $e) {
                    $this->addUploadFallback($message, $upload);

                    Log::warning('Max attachment upload error: ' . $e->getMessage(), [
                        'notification' => get_class($notification),
                        'notifiable' => get_class($notifiable),
                        'upload' => $upload,
                        'exception' => $e,
                    ]);
                }
            }

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

    protected function addUploadFallback(MaxMessage $message, array $upload): void
    {
        if (($upload['type'] ?? null) === 'audio' && !empty($upload['url'])) {
            $message->button('Запись разговора', $upload['url']);
        }
    }
}
