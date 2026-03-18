<?php

namespace Vegas\MaxNotificationChannel\Exceptions;

use Exception;
use GuzzleHttp\Exception\ClientException;

class CouldNotSendNotification extends Exception
{
    public static function serviceRespondedWithAnError(ClientException $exception)
    {
        $statusCode = $exception->getResponse()->getStatusCode();
        $description = $exception->getMessage();

        if ($result = json_decode($exception->getResponse()->getBody(), true)) {
            $description = $result['message'] ?? $description;
        }

        return new static("Max API responded with an error `{$statusCode} - {$description}`");
    }

    public static function couldNotCommunicateWithMax($exception)
    {
        return new static("The communication with Max failed because `{$exception->getMessage()}`");
    }
}
