<?php

namespace Vegas\MaxNotificationChannel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;

class MaxServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MaxBotClient::class, function ($app) {
            $config = $app['config']['services.max-bot-api'] ?? [];

            return new MaxBotClient(
                $config['token'] ?? '',
                $config['base_url'] ?? null,
                $app['config']['services.max-channels.default.id'] ?? null
            );
        });

        Notification::extend('max', function ($app) {
            return $app->make(MaxChannel::class);
        });
    }
}
