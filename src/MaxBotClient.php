<?php

namespace Vegas\MaxNotificationChannel;

use GuzzleHttp\Client;
use Vegas\MaxNotificationChannel\Exceptions\CouldNotSendNotification;
use GuzzleHttp\Exception\ClientException;

class MaxBotClient
{
    protected $client;
    protected $baseUrl;
    protected $token;
    protected $defaultChatId;

    public function __construct(string $token, ?string $baseUrl = null, ?string $defaultChatId = null)
    {
        $this->token = $token;
        $this->baseUrl = rtrim($baseUrl ?: 'https://platform-api.max.ru', '/');
        $this->defaultChatId = $defaultChatId;
        $this->client = new Client([
            'timeout' => 15,
            'http_errors' => true,
        ]);
    }

    public function sendMessage(string $text, $chatId = null, array $payload = []): array
    {
        $chatId = $chatId ?: $this->defaultChatId;

        if (!$this->token) {
            throw new \RuntimeException('MAX_BOT_TOKEN is not configured.');
        }

        if (!$chatId) {
            throw new \RuntimeException('MAX_CHAT_ID is not configured.');
        }

        $query = [];
        if (is_numeric($chatId) && (int)$chatId < 0) {
            $query['chat_id'] = $chatId;
        } else {
            $query['user_id'] = $chatId;
        }

        $bodyPayload = array_merge([
            'text' => $text,
        ], $payload);

        // Убираем только null значения на верхнем уровне
        $bodyPayload = array_filter($bodyPayload, function ($value) {
            return $value !== null;
        });

        try {
            $response = $this->client->post($this->baseUrl . '/messages', [
                'headers' => [
                    'Authorization' => $this->token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'query' => $query,
                'body' => json_encode($bodyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (ClientException $exception) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
        } catch (\Exception $exception) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax($exception);
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        return is_array($data) ? $data : ['raw' => $body];
    }
}
