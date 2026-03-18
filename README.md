# Канал уведомлений Max для Laravel

[![Последняя версия на Packagist](https://img.shields.io/packagist/v/vegas/laravel-notification-channel-max.svg?style=flat-square)](https://packagist.org/packages/vegas/laravel-notification-channel-max)
[![Всего скачиваний](https://img.shields.io/packagist/dt/vegas/laravel-notification-channel-max.svg?style=flat-square)](https://packagist.org/packages/vegas/laravel-notification-channel-max)
[![Лицензия](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Этот пакет позволяет легко отправлять уведомления через [Max](https://platform-api.max.ru) в Laravel.

## Содержание

- [Установка](#установка)
- [Настройка сервиса Max](#настройка-сервиса-max)
- [Использование](#использование)
- [Доступные методы сообщения](#доступные-методы-сообщения)
- [Список изменений](#список-изменений)
- [Тестирование](#тестирование)
- [Безопасность](#безопасность)
- [Участие в разработке](#участие-в-разработке)
- [Авторы](#авторы)
- [Лицензия](#лицензия)

## Установка

Вы можете установить пакет через composer:

```bash
composer require vegas/laravel-notification-channel-max
```

## Настройка сервиса Max

Добавьте токен вашего бота Max в файл `config/services.php`:

```php
// config/services.php
...
'max-bot-api' => [
    'token' => env('MAX_BOT_TOKEN'),
],
...
```

Вы также можете установить идентификатор чата/пользователя по умолчанию в этом же файле, если это необходимо, хотя обычно он передается через модель `Notifiable`.

## Использование

Вы можете использовать канал в методе `via()` внутри вашего уведомления:

```php
use Vegas\MaxNotificationChannel\MaxChannel;
use Vegas\MaxNotificationChannel\Messages\MaxMessage;
use Illuminate\Notifications\Notification;

class NewLead extends Notification
{
    public function via($notifiable)
    {
        return [MaxChannel::class];
    }

    public function toMax($notifiable)
    {
        return MaxMessage::create("Новая заявка на сайте!")
            ->to('12345678')
            ->button('Посмотреть заявку', url('/admin/leads/'.$notifiable->id));
    }
}
```

В вашей модели `Notifiable` убедитесь, что вы добавили метод `routeNotificationForMax()`, который возвращает ID чата или ID пользователя, куда должно быть отправлено уведомление (если вы не указываете `->to()` явно):

```php
public function routeNotificationForMax()
{
    return $this->max_chat_id;
}
```

## Доступные методы сообщения

- `content(string)`: Установить текст сообщения.
- `to(string)`: Установить ID получателя (чата или пользователя). Если указано в уведомлении, переопределяет метод `routeNotificationForMax`.
- `button(string $text, string $url, int $row = 0)`: Добавить кнопку со ссылкой.
- `link(string)`: Добавить URL-ссылку к сообщению (в API это `link_url`).
- `notify(bool)`: Установить флаг уведомления (звуковой сигнал/пуш).
- `format(string)`: Установить формат сообщения (`markdown`, `html` или `plain`).

## Список изменений

Пожалуйста, смотрите [CHANGELOG](CHANGELOG.md) для получения дополнительной информации о последних изменениях.

## Тестирование

```bash
composer test
```

Пакет поставляется с набором тестов с использованием PHPUnit. Убедитесь, что вы установили все зависимости разработки (`composer install`).

## Безопасность

Если вы обнаружите какие-либо проблемы, связанные с безопасностью, пожалуйста, используйте трекер задач.

## Авторы

- [Vegas](https://github.com/vegas-dev)
- [Все участники](../../contributors)

## Лицензия

Лицензия MIT. Пожалуйста, смотрите [файл лицензии](LICENSE) для получения дополнительной информации.
