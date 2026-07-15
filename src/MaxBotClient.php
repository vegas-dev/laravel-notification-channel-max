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
        $this->baseUrl = rtrim($baseUrl ?: 'https://platform-api2.max.ru', '/');
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

        $attempt = 0;

        while (true) {
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

                break;
            } catch (ClientException $exception) {
                if ($attempt < 3 && $this->isAttachmentNotReady($exception)) {
                    $attempt++;
                    sleep($attempt * 2);
                    continue;
                }

                throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
            } catch (\Exception $exception) {
                throw CouldNotSendNotification::couldNotCommunicateWithMax($exception);
            }
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        return is_array($data) ? $data : ['raw' => $body];
    }

    protected function isAttachmentNotReady(ClientException $exception): bool
    {
        $data = json_decode((string) $exception->getResponse()->getBody(), true);

        return is_array($data) && ($data['code'] ?? null) === 'attachment.not.ready';
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

    public function uploadAttachment(string $type, string $url): array
    {
        $payload = $this->uploadMedia($type, $url);

        return [
            'type' => $type,
            'payload' => $payload,
        ];
    }

    public function uploadAttachmentFile(string $type, string $path, ?string $filename = null, ?string $mime = null): array
    {
        $payload = $this->uploadMediaFile($type, $path, $filename, $mime);

        return [
            'type' => $type,
            'payload' => $payload,
        ];
    }

    protected function uploadMedia(string $type, string $sourceUrl): array
    {
        if (!in_array($type, ['image', 'video', 'audio', 'file'], true)) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax(
                new \InvalidArgumentException("Unsupported Max upload type: {$type}.")
            );
        }

        $upload = $this->createUpload($type);
        $localFile = $this->downloadMedia($sourceUrl);

        try {
            $uploadResult = $this->uploadFileToMax($upload['url'], $localFile['path'], $localFile['filename'], $localFile['mime']);
        } finally {
            if (is_file($localFile['path'])) {
                unlink($localFile['path']);
            }
        }

        return $this->resolveAttachmentPayload($type, $upload, $uploadResult);
    }

    protected function uploadMediaFile(string $type, string $path, ?string $filename = null, ?string $mime = null): array
    {
        if (!in_array($type, ['image', 'video', 'audio', 'file'], true)) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax(
                new \InvalidArgumentException("Unsupported Max upload type: {$type}.")
            );
        }

        if (!is_file($path) || filesize($path) === 0) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax(
                new \RuntimeException('Max attachment file is missing or empty.')
            );
        }

        $upload = $this->createUpload($type);
        $uploadResult = $this->uploadFileToMax(
            $upload['url'],
            $path,
            $filename ?: basename($path),
            $mime ?: 'application/octet-stream'
        );

        return $this->resolveAttachmentPayload($type, $upload, $uploadResult);
    }

    protected function createUpload(string $type): array
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/uploads', [
                'headers' => [
                    'Authorization' => $this->token,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'type' => $type,
                ],
            ]);
        } catch (ClientException $exception) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
        } catch (\Exception $exception) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax($exception);
        }

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || empty($data['url'])) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax(
                new \RuntimeException('Max API did not return an upload URL.')
            );
        }

        return $data;
    }

    protected function downloadMedia(string $sourceUrl): array
    {
        $path = tempnam(sys_get_temp_dir(), 'max_upload_');

        try {
            $response = $this->client->request('GET', $sourceUrl, [
                'sink' => $path,
            ]);
        } catch (ClientException $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
        } catch (\Exception $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            throw CouldNotSendNotification::couldNotCommunicateWithMax($exception);
        }

        if (!is_file($path) || filesize($path) === 0) {
            if (is_file($path)) {
                unlink($path);
            }

            throw CouldNotSendNotification::couldNotCommunicateWithMax(
                new \RuntimeException('Downloaded Max attachment source is empty.')
            );
        }

        return [
            'path' => $path,
            'filename' => $this->guessFilename($sourceUrl),
            'mime' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
        ];
    }

    protected function uploadFileToMax(string $uploadUrl, string $path, string $filename, string $mime): array
    {
        $stream = null;

        try {
            $stream = fopen($path, 'rb');
            if (!$stream) {
                throw new \RuntimeException('Could not open Max attachment file.');
            }

            $response = $this->client->request('POST', $uploadUrl, [
                'multipart' => [
                    [
                        'name' => 'data',
                        'contents' => $stream,
                        'filename' => $filename,
                        'headers' => [
                            'Content-Type' => $mime,
                        ],
                    ],
                ],
            ]);

        } catch (ClientException $exception) {
            throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
        } catch (\Exception $exception) {
            throw CouldNotSendNotification::couldNotCommunicateWithMax($exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    protected function resolveAttachmentPayload(string $type, array $upload, array $uploadResult): array
    {
        if (in_array($type, ['video', 'audio'], true) && isset($upload['token'])) {
            return ['token' => $upload['token']];
        }

        if (isset($uploadResult['token'])) {
            return ['token' => $uploadResult['token']];
        }

        if (isset($uploadResult['retval']) && is_array($uploadResult['retval'])) {
            return $uploadResult['retval'];
        }

        return $uploadResult;
    }

    protected function guessFilename(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = $path ? basename($path) : '';

        return $filename ?: 'attachment';
    }
}
