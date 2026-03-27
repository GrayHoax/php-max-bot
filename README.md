# PHPMaxBot

PHP библиотека для создания ботов в мессенджере MAX. Поддерживает полное API MAX messenger и предоставляет удобный интерфейс для разработки ботов.

## Особенности

- Простой и интуитивно понятный API
- Поддержка webhook и long polling режимов
- Полная поддержка MAX Bot API
- Встроенные помощники для создания клавиатур и кнопок
- Обработка команд, событий и callback-действий
- Поддержка регулярных выражений для обработчиков
- Обработка исключений и ошибок API
- PSR-4 автозагрузка

## Требования

- PHP >= 7.4
- ext-curl
- ext-json

## Установка

### Через Composer

```bash
composer require grayhoax/phpmaxbot
```

### Вручную

1. Клонируйте репозиторий:
```bash
git clone https://github.com/grayhoax/phpmaxbot.git
```

2. Подключите автозагрузку:
```php
require_once 'phpmaxbot/vendor/autoload.php';
```

## Быстрый старт

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMaxBot\Helpers\Keyboard;

$token = 'your-bot-token';
$bot = new PHPMaxBot($token);

// Обработка команды /start
$bot->command('start', function() {
    return Bot::sendMessage('Привет! Я бот на MAX мессенджере.');
});

// Обработка команды /help
$bot->command('help', function() {
    return Bot::sendMessage('Доступные команды: /start, /help');
});

// Запуск бота
$bot->start();
```

## Основное использование

### Создание бота

```php
$bot = new PHPMaxBot('your-bot-token');
```

### Обработка команд

```php
// Простая команда
$bot->command('start', function() {
    return Bot::sendMessage('Привет!');
});

// Команда с параметром
$bot->command('echo', function($text) {
    return Bot::sendMessage("Вы написали: $text");
});

// Команда с текстовым ответом
$bot->command('hello', 'Привет! Как дела?');
```

### Обработка событий

```php
// Обработка события bot_started
$bot->on('bot_started', function() {
    $update = PHPMaxBot::$currentUpdate;
    $userName = $update['user']['name'];
    return Bot::sendMessage("Добро пожаловать, $userName!");
});

// Обработка создания сообщения
$bot->on('message_created', function() {
    $text = Bot::getText();
    // Ваша логика
});

// Обработка нескольких событий
$bot->on('message_created|message_edited', function() {
    // Обработка обоих событий
});
```

### Обработка callback-кнопок

```php
// Точное совпадение
$bot->action('button_1', function() {
    $update = PHPMaxBot::$currentUpdate;
    $callbackId = $update['callback']['callback_id'];

    return Bot::answerOnCallback($callbackId, [
        'notification' => 'Кнопка нажата!'
    ]);
});

// Regex паттерн
$bot->action('color:(.+)', function($matches) {
    $color = $matches[1];
    $callbackId = PHPMaxBot::$currentUpdate['callback']['callback_id'];

    return Bot::answerOnCallback($callbackId, [
        'message' => [
            'text' => "Выбран цвет: $color"
        ]
    ]);
});
```

### Создание клавиатур

```php
use PHPMaxBot\Helpers\Keyboard;

// Создание inline клавиатуры
$keyboard = Keyboard::inlineKeyboard([
    [
        Keyboard::callback('Кнопка 1', 'btn_1'),
        Keyboard::callback('Кнопка 2', 'btn_2', ['intent' => 'positive'])
    ],
    [
        Keyboard::link('Открыть сайт', 'https://max.ru/')
    ],
    [
        Keyboard::requestContact('Отправить контакт')
    ],
    [
        Keyboard::requestGeoLocation('Отправить геолокацию')
    ]
]);

// Отправка сообщения с клавиатурой
Bot::sendMessage('Выберите действие:', [
    'attachments' => [$keyboard]
]);
```

### Типы кнопок

```php
// Callback кнопка (с обработчиком)
Keyboard::callback('Текст', 'payload_data');
Keyboard::callback('Текст', 'payload_data', ['intent' => 'positive']); // С intent

// Кнопка-ссылка
Keyboard::link('Открыть', 'https://example.com');

// Запрос контакта
Keyboard::requestContact('Отправить контакт');

// Запрос геолокации
Keyboard::requestGeoLocation('Отправить местоположение');

// Создание чата
Keyboard::chat('Создать чат', 'Название чата');
```

## API методы

### Сообщения

```php
// Отправить сообщение в чат
Bot::sendMessageToChat($chatId, 'Текст сообщения', [
    'attachments' => [$keyboard],
    'format' => 'markdown'
]);

// Отправить сообщение пользователю
Bot::sendMessageToUser($userId, 'Текст сообщения');

// Отправить сообщение (автоопределение получателя)
Bot::sendMessage('Текст сообщения');

// Получить сообщение по ID
Bot::getMessage($messageId);

// Получить сообщения чата
Bot::getMessages($chatId, [
    'count' => 10,
    'from' => 0
]);

// Редактировать сообщение
Bot::editMessage($messageId, [
    'text' => 'Новый текст'
]);

// Удалить сообщение
Bot::deleteMessage($messageId);
```

### Чаты

```php
// Получить все чаты
Bot::getAllChats();

// Получить чат по ID
Bot::getChat($chatId);

// Получить чат по ссылке
Bot::getChatByLink($chatLink);

// Редактировать информацию о чате
Bot::editChatInfo($chatId, [
    'title' => 'Новое название'
]);

// Получить участников чата
Bot::getChatMembers($chatId);

// Добавить участников
Bot::addChatMembers($chatId, [$userId1, $userId2]);

// Удалить участника
Bot::removeChatMember($chatId, $userId);

// Получить администраторов
Bot::getChatAdmins($chatId);

// Покинуть чат
Bot::leaveChat($chatId);
```

### Закрепленные сообщения

```php
// Получить закрепленное сообщение
Bot::getPinnedMessage($chatId);

// Закрепить сообщение
Bot::pinMessage($chatId, $messageId);

// Открепить сообщение
Bot::unpinMessage($chatId);
```

### Бот

```php
// Получить информацию о боте
Bot::getMyInfo();

// Редактировать информацию о боте
Bot::editMyInfo([
    'name' => 'Новое имя',
    'description' => 'Описание'
]);

// Установить команды бота
Bot::setMyCommands([
    ['name' => 'start', 'description' => 'Запустить бота'],
    ['name' => 'help', 'description' => 'Помощь']
]);

// Удалить команды
Bot::deleteMyCommands();
```

### Действия

```php
// Отправить действие (печатает, отправляет файл и т.д.)
Bot::sendAction($chatId, 'typing');
```

### Callback ответы

```php
// Ответить на callback с уведомлением
Bot::answerOnCallback($callbackId, [
    'notification' => 'Готово!'
]);

// Ответить с изменением сообщения
Bot::answerOnCallback($callbackId, [
    'message' => [
        'text' => 'Новый текст',
        'attachments' => [$newKeyboard]
    ]
]);
```

### Формат сообщений

```php
// MarkDown
$bot->setFormat('markdown');
$bot->setFormat('md');
// HTML
$bot->setFormat('html');
// Простой текст
$bot->setFormat();
$bot->setFormat(false);
```

[Подробнее про форматирование](https://dev.max.ru/docs-api#Форматирование%20текста)

## Запуск бота

### Long Polling (режим CLI)

```bash
php bot.php
```

Бот автоматически определит CLI режим и запустит long polling.

### Webhook

Разместите файл бота на веб-сервере, доступном по HTTPS. MAX будет отправлять обновления на ваш URL.

```php
$bot = new PHPMaxBot($token);
// Настройка обработчиков...
$bot->start();
```

## Обработка исключений

```php
use PHPMaxBot\Exceptions\ApiException;
use PHPMaxBot\Exceptions\MaxBotException;

try {
    Bot::sendMessage('Привет!');
} catch (ApiException $e) {
    // Ошибка API MAX
    echo "API Error: " . $e->getMessage();
    echo "Error Code: " . $e->getApiErrorCode();
} catch (MaxBotException $e) {
    // Общая ошибка PHPMaxBot
    echo "Error: " . $e->getMessage();
    print_r($e->getContext());
}
```

## Доступ к текущему обновлению

```php
// Получить полные данные обновления
$update = PHPMaxBot::$currentUpdate;

// Вспомогательные методы
$type = Bot::type();              // Тип обновления
$text = Bot::getText();           // Текст сообщения
$callbackData = Bot::getCallbackData(); // Данные callback
$callbackData = Bot::getContact(); // vCard (если пользователь поделился понтактом)
$callbackData = Bot::getSender(); // Данные отправителя (id, имя, etc)
```

## Типы обновлений

Доступные типы обновлений для фильтрации:

- `message_created` - Создано новое сообщение
- `message_edited` - Сообщение отредактировано
- `message_removed` - Сообщение удалено
- `message_callback` - Нажата callback-кнопка
- `bot_started` - Бот запущен пользователем
- `bot_added` - Бот добавлен в чат
- `bot_removed` - Бот удален из чата
- `user_added` - Пользователь добавлен в чат
- `user_removed` - Пользователь удален из чата
- `chat_title_changed` - Название чата изменено

Указать типы обновлений:

```php
$bot->start([
    'message_created',
    'message_callback',
    'bot_started'
]);
```

## Примеры

Смотрите файл `sample.php` для полного примера использования.

## Debug режим

```php
// Включить debug (по умолчанию включен в CLI)
PHPMaxBot::$debug = true;

// Выключить debug
PHPMaxBot::$debug = false;

// Или через CLI параметры
php bot.php --quiet  // Выключить debug
php bot.php -q       // Короткая форма
```

## Лицензия

GPL-3.0

## Автор

GrayHoax <grayhoax@grayhoax.ru>

## Ссылки

- [MAX Messenger](https://max.ru/)
- [MAX Bot API Documentation](https://platform-api.max.ru/docs/)

## Поддержка

Если у вас возникли проблемы или вопросы, создайте issue на GitHub.
