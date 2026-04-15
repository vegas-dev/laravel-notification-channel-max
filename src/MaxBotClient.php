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
        return $this->request('POST', '/messages', $text, $chatId, $payload);
    }

    public function editMessage(string $messageId, string $text, $chatId = null, array $payload = []): array
    {
        return $this->request('PUT', '/messages', $text, $chatId, $payload, [
            'message_id' => $messageId,
        ]);
    }

    protected function request(string $method, string $uri, string $text, $chatId = null, array $payload = [], array $extraQuery = []): array
    {
        $chatId = $chatId ?: $this->defaultChatId;

        if (!$this->token) {
            throw new \RuntimeException('MAX_BOT_TOKEN is not configured.');
        }

        if (!$chatId) {
            throw new \RuntimeException('MAX_CHAT_ID is not configured.');
        }

        $query = array_merge($this->resolveRecipientQuery($chatId), $extraQuery);
        $bodyPayload = $this->buildBodyPayload($text, $payload);

        try {
            $response = $this->client->request($method, $this->baseUrl . $uri, [
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

    protected function resolveRecipientQuery($chatId): array
    {
        if (is_numeric($chatId) && (int)$chatId < 0) {
            return ['chat_id' => $chatId];
        }

        return ['user_id' => $chatId];
    }

    protected function buildBodyPayload(string $text, array $payload = []): array
    {
        $bodyPayload = array_merge([
            'text' => $text,
        ], $payload);

        // Убираем только null значения на верхнем уровне
        return array_filter($bodyPayload, function ($value) {
            return $value !== null;
        });
    }
}
