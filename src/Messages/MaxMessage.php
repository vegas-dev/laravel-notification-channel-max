<?php

namespace Vegas\MaxNotificationChannel\Messages;

class MaxMessage
{
    public $content;
    public $chatId;
    public $payload = [
        'format' => 'markdown',
    ];

    public static function create(string $content = ''): self
    {
        return new static($content);
    }

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function to(string $chatId): self
    {
        $this->chatId = $chatId;
        return $this;
    }

    public function notify(bool $value = true): self
    {
        $this->payload['notify'] = $value;
        return $this;
    }

    public function format(string $value): self
    {
        $this->payload['format'] = $value;
        return $this;
    }

    public function link(?string $value): self
    {
        if (empty($value)) {
            return $this;
        }

        if (strpos($value, 'http') !== 0) {
            $appUrl = rtrim(config('app.url'), '/');
            $value = $appUrl . '/' . ltrim($value, '/');
        }

        // Если домен .local, заменим его на .ru (для локальной разработки)
        if (strpos($value, '.local') !== false) {
            $value = str_replace('.local', '.ru', $value);
        }

        $this->payload['link_url'] = $value;
        return $this;
    }

    public function button(string $text, string $url, int $row = 0): self
    {
        if (empty($url)) {
            return $this;
        }

        if (strpos($url, 'http') !== 0) {
            $appUrl = rtrim(config('app.url'), '/');
            $url = $appUrl . '/' . ltrim($url, '/');
        }

        // Если домен .local, заменим его на .ru (для локальной разработки)
        if (strpos($url, '.local') !== false) {
            $url = str_replace('.local', '.ru', $url);
        }

        if (!isset($this->payload['attachments'])) {
            $this->payload['attachments'] = [];
        }

        $inlineKeyboard = null;
        foreach ($this->payload['attachments'] as $key => $attachment) {
            if (($attachment['type'] ?? '') === 'inline_keyboard') {
                $inlineKeyboard = &$this->payload['attachments'][$key];
                break;
            }
        }

        if (!$inlineKeyboard) {
            $this->payload['attachments'][] = [
                'type' => 'inline_keyboard',
                'payload' => [
                    'buttons' => []
                ]
            ];
            $inlineKeyboard = &$this->payload['attachments'][count($this->payload['attachments']) - 1];
        }

        if (!isset($inlineKeyboard['payload']['buttons'][$row])) {
            $inlineKeyboard['payload']['buttons'][$row] = [];
        }

        $inlineKeyboard['payload']['buttons'][$row][] = [
            'type' => 'link',
            'text' => $text,
            'url' => $url,
        ];

        return $this;
    }

    public function attachments(array $value): self
    {
        $this->payload['attachments'] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return array_merge([
            'text' => $this->content,
        ], $this->payload);
    }
}
