# Quick Start Guide

Быстрое руководство по началу работы с PHPMaxBot.

## Установка

### 1. Через Composer (рекомендуется)

```bash
composer require grayhoax/phpmaxbot
```

### 2. Вручную

```bash
git clone https://github.com/grayhoax/phpmaxbot.git
cd phpmaxbot
composer install
```

## Получение токена бота

1. Откройте MAX мессенджер
2. Найдите @BotFather или бот для создания ботов
3. Создайте нового бота и получите токен
4. Сохраните токен в безопасном месте

## Создание простого бота

Создайте файл `bot.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

// Ваш токен от BotFather
$token = 'YOUR_BOT_TOKEN_HERE';

// Создаем экземпляр бота
$bot = new PHPMaxBot($token);

// Обработка команды /start
$bot->command('start', function() {
    Bot::sendMessage('Привет! Я ваш первый MAX бот!');
});

// Обработка команды /help
$bot->command('help', function() {
    Bot::sendMessage('Доступные команды: /start, /help');
});

// Запускаем бота
$bot->start();
```

## Запуск бота

### Long Polling (режим разработки)

Запустите из командной строки:

```bash
php bot.php
```

Бот начнет опрашивать сервер MAX и обрабатывать обновления.

### Webhook (продакшен)

1. Разместите `bot.php` на веб-сервере с HTTPS
2. Зарегистрируйте webhook через API:

```php
Bot::createSubscription('https://example.com/bot.php', [
    'message_created',
    'message_callback',
    'bot_started'
]);
```

3. MAX будет отправлять обновления на ваш URL

Для отмены подписки:

```php
Bot::deleteSubscription('https://example.com/bot.php');
```

## Добавление клавиатуры

```php
use PHPMaxBot\Helpers\Keyboard;

$bot->command('menu', function() {
    $keyboard = Keyboard::inlineKeyboard([
        [
            Keyboard::callback('Кнопка 1', 'btn_1'),
            Keyboard::callback('Кнопка 2', 'btn_2')
        ],
        [
            Keyboard::link('Открыть сайт', 'https://max.ru/')
        ]
    ]);

    Bot::sendMessage('Выберите действие:', [
        'attachments' => [$keyboard]
    ]);
});

// Обработка нажатия кнопки
$bot->action('btn_1', function() {
    $callbackId = PHPMaxBot::$currentUpdate['callback']['callback_id'];
    Bot::answerOnCallback($callbackId, [
        'notification' => 'Вы нажали кнопку 1!'
    ]);
});
```

## Обработка событий

```php
// Когда пользователь впервые запускает бота
$bot->on('bot_started', function($payload) {
    $userName = PHPMaxBot::$currentUpdate['user']['name'];
    Bot::sendMessage("Добро пожаловать, $userName!");
});

// Когда создается новое текстовое сообщение
$bot->on('message_created', function() {
    $text = Bot::getText();
    if ($text && strpos($text, '/') !== 0) {
        Bot::sendMessage("Получено сообщение: $text");
    }
});
```

## Обработка входящих вложений

Когда пользователь нажимает кнопку `requestContact` или `requestGeoLocation`, бот получает сообщение с вложением. Используйте `onAttachment()` — он срабатывает раньше общего `on('message_created')`:

```php
use PHPMaxBot\Helpers\Keyboard;

// Отправить клавиатуру с запросом контакта и геолокации
$bot->command('share', function() {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestContact('Отправить контакт')],
        [Keyboard::requestGeoLocation('Отправить геолокацию')],
    ]);
    Bot::sendMessage('Поделитесь данными:', ['attachments' => [$keyboard]]);
});

// Обработать полученный контакт (данные в payload)
$bot->onAttachment('contact', function($attachment) {
    $name = trim(
        ($attachment['payload']['max_info']['first_name'] ?? '') . ' ' .
        ($attachment['payload']['max_info']['last_name']  ?? '')
    );
    Bot::sendMessage("Контакт получен: $name");
});

// Обработать полученную геолокацию (поля прямо в вложении, без payload)
$bot->onAttachment('location', function($attachment) {
    Bot::sendMessage("Геолокация: {$attachment['latitude']}, {$attachment['longitude']}");
});
```

## Переменные окружения (рекомендуется)

Создайте файл `.env`:

```
BOT_TOKEN=your_actual_token_here
```

В коде:

```php
$token = getenv('BOT_TOKEN');
if (!$token) {
    die("Please set BOT_TOKEN environment variable\n");
}

$bot = new PHPMaxBot($token);
```

## Примеры

Посмотрите готовые примеры в папке `examples/`:

- `simple-bot.php` - Простой бот с командами
- `keyboard-bot.php` - Бот с клавиатурами, кнопками и обработкой вложений
- `attachments-bot.php` - Обработка всех типов входящих вложений
- `media-bot.php` - Отправка изображений, видео, аудио и файлов

Запуск примера:

```bash
export BOT_TOKEN=your_token
php examples/simple-bot.php
```

## Отладка

Включить отладку:

```bash
php bot.php  # Debug включен по умолчанию
```

Выключить отладку:

```bash
php bot.php --quiet
# или
php bot.php -q
```

## Обработка ошибок

```php
use PHPMaxBot\Exceptions\ApiException;

try {
    Bot::sendMessage('Привет!');
} catch (ApiException $e) {
    echo "Ошибка API: " . $e->getMessage() . "\n";
    echo "Код ошибки: " . $e->getApiErrorCode() . "\n";
}
```

## Следующие шаги

1. Изучите [README.md](README.md) для полной документации
2. Посмотрите [примеры](examples/) для вдохновения
3. Прочитайте [CHANGELOG.md](CHANGELOG.md) для истории версий
4. Ознакомьтесь с [MAX API документацией](https://dev.max.ru/docs-api)

## Частые вопросы

### Как отправить сообщение конкретному пользователю?

```php
Bot::sendMessageToUser($userId, 'Привет!');
```

### Как получить ID чата?

```php
$bot->on('message_created', function() {
    $chatId = PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'];
    Bot::sendMessage("ID этого чата: $chatId");
});
```

### Как обработать команду с параметрами?

```php
$bot->command('say', function($text) {
    if (empty($text)) {
        Bot::sendMessage('Использование: /say <текст>');
    } else {
        Bot::sendMessage($text);
    }
});
```

### Как использовать regex для команд?

```php
$bot->regex('/^\/cmd_(\d+)$/', function($matches) {
    $number = $matches[1];
    Bot::sendMessage("Команда с номером: $number");
});
```

## Поддержка

Если возникли проблемы:

1. Проверьте логи (Debug режим)
2. Убедитесь, что токен правильный
3. Проверьте версию PHP (требуется >= 7.4)
4. Создайте issue на GitHub

Удачи в разработке ботов!
