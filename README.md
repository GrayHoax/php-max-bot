# PHPMaxBot

![Привлекательное изображение для репозитория](https://github.com/GrayHoax/php-max-bot/raw/master/assets/repo-preview-image.png)

PHP библиотека для создания ботов в мессенджере MAX. Поддерживает полное API MAX messenger и предоставляет удобный интерфейс для разработки ботов.

## Особенности

- Простой и интуитивно понятный API
- Поддержка webhook и long polling режимов
- Полная поддержка MAX Bot API
- Встроенные помощники для создания клавиатур и кнопок
- Обработка команд, событий, callback-действий и входящих вложений
- Поддержка регулярных выражений для обработчиков
- Обработка исключений и ошибок API
- PSR-4 автозагрузка

## ⚠️ Важно: требования к webhook и режиму работы

> **С 25 мая 2026 года MAX прекращает поддержку приёма вебхуков по HTTP и самоподписных сертификатов.**
> Все продакшн-боты должны принимать обновления по **HTTPS** с сертификатом от **доверенного центра сертификации** (Let's Encrypt, коммерческие CA и т.д.). Подписки с HTTP-URL или невалидным сертификатом перестанут работать.
>
> Чтобы переключиться на новый URL или обновить подписку, используйте текущий метод [`Bot::createSubscription()`](#подписки-webhook) — повторный вызов с тем же URL заменит её настройки.

> **Long Polling не подходит для production.**
> Получение обновлений через `getUpdates` (long polling) ограничено по скорости запросов и сроку хранения событий на сервере MAX. Используйте его только при локальной разработке и отладке — в продакшене переключайтесь на **Webhook**.

Кратко:

| Окружение | Рекомендуемый режим | Требования |
|---|---|---|
| Разработка / отладка | Long Polling (CLI) | — |
| Staging / Production | Webhook | HTTPS + доверенный сертификат |

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
    $update   = PHPMaxBot::$currentUpdate;
    $userId   = $update['user']['user_id'];
    $userName = $update['user']['first_name'];
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

### Обработка входящих вложений

Когда пользователь нажимает кнопку `requestContact` или `requestGeoLocation` — или отправляет медиафайл — бот получает событие `message_created` с вложением (attachment). Используйте `onAttachment($type, $handler)` для обработки конкретного типа.

Обработчик получает **полный массив вложения** `$attachment`. Расположение данных зависит от типа:

| Тип | Данные в `payload` | Прямые поля вложения |
|---|---|---|
| `image` | `photo_id`, `token`, `url` | — |
| `video` | `url`, `token` | — |
| `audio` | `url`, `token` | — |
| `file` | `url`, `token` | `filename`, `size` |
| `sticker` | `url`, `code` | `width`, `height` |
| `contact` | `vcf_info`, `max_info` | — |
| `inline_keyboard` | `buttons` | — |
| `share` | `url` | — |
| `location` | **нет** | `latitude`, `longitude` |

```php
// Геолокация — данные прямо в вложении, без payload
$bot->onAttachment('location', function($attachment) {
    $lat = $attachment['latitude'];
    $lon = $attachment['longitude'];
    return Bot::sendMessage("Ваши координаты: $lat, $lon");
});

// Контакт — данные в payload
$bot->onAttachment('contact', function($attachment) {
    $firstName = $attachment['payload']['max_info']['first_name'] ?? 'Unknown';
    $lastName  = $attachment['payload']['max_info']['last_name']  ?? '';
    $vcf       = $attachment['payload']['vcf_info'] ?? null;
    return Bot::sendMessage("Контакт: " . trim("$firstName $lastName"));
});

// Изображение — URL и токен в payload
$bot->onAttachment('image', function($attachment) {
    $url   = $attachment['payload']['url'];
    $token = $attachment['payload']['token'];
    return Bot::sendMessage("Получено фото: $url");
});

// Файл — payload содержит url/token, прямые поля — имя и размер
$bot->onAttachment('file', function($attachment) {
    $filename = $attachment['filename'] ?? 'file';
    $size     = $attachment['size'] ?? 0;
    $url      = $attachment['payload']['url'];
    return Bot::sendMessage("Файл: $filename ($size байт)");
});

// Стикер — payload содержит url/code, прямые поля — размер
$bot->onAttachment('sticker', function($attachment) {
    $code = $attachment['payload']['code'];
    return Bot::sendMessage("Стикер: $code");
});
```

Обработчики `onAttachment` срабатывают раньше общего `on('message_created')`. Для каждого типа регистрируется один обработчик.

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

// Открытие мини-приложения
Keyboard::open_app('Открыть приложение', 'https://example.com/app');

// Отправка текстового сообщения от имени пользователя
Keyboard::message('Подтвердить', 'Да, подтверждаю');
```

## Отправка медиафайлов

PHPMaxBot предоставляет три уровня API для работы с файлами — от одного вызова до полного ручного контроля.

### ⚠️ Ограничения MAX на состав вложений

Сервер MAX накладывает жёсткое ограничение на вложения типа `file`:

> **В одном сообщении может быть только одно вложение типа `file`, и рядом с ним не может быть никаких других вложений, кроме `inline_keyboard`.**

| Состав сообщения | Результат |
|------------------|-----------|
| 1 × `image` | ✅ |
| несколько `image` / `video` / `audio` в любом сочетании | ✅ |
| 1 × `file` | ✅ |
| 1 × `file` + `inline_keyboard` | ✅ |
| 2 × `file` | ❌ `400 proto.payload` |
| `file` + `image` (или `video` / `audio` / `location` / `share`) | ❌ `400 proto.payload` |

Ответ сервера при нарушении:

```json
{"code":"proto.payload","message":"Must be only one file attachment in message"}
```

Библиотека проверяет состав вложений **до** обращения к API и бросает `MaxBotException` с понятным текстом — так ошибка не превращается в «сообщение просто не пришло». Чтобы отправить смешанный набор, используйте [`Bot::sendAttachmentsToChat()`](#пакетная-отправка-нескольких-вложений), который сам разложит его на допустимые сообщения.

### ⚠️ Файл обрабатывается асинхронно

MAX принимает загрузку файла раньше, чем заканчивает его обработку. Сообщение, отправленное сразу после `Bot::upload('file', ...)`, отклоняется:

```json
{"code":"attachment.not.ready","message":"Key: errors.process.attachment.file.not.processed"}
```

Библиотека обрабатывает это сама: при получении `attachment.not.ready` отправка автоматически повторяется с линейно растущей паузой. Поведение настраивается:

```php
PHPMaxBot::$attachmentRetries    = 5;    // число повторов (0 — отключить)
PHPMaxBot::$attachmentRetryDelay = 500;  // базовая пауза, мс: 500, 1000, 1500, ...
```

Если повторы исчерпаны, бросается `ApiException` с кодом `attachment.not.ready`.

### Как это работает

Загрузка файла в MAX состоит из двух шагов: сначала запрашивается URL загрузки, затем файл передаётся на этот URL. Способ получения токена вложения зависит от типа файла:

| Тип | Шаг 1 `uploadFile()` | Шаг 2 `uploadFileToUrl()` | Откуда токен |
|-----|----------------------|--------------------------|--------------|
| `image`, `file` | возвращает только `url` | передаёт файл → возвращает `token` | из ответа шага 2 |
| `video`, `audio` | возвращает `url` **и** `token` | передаёт файл (слот завершается) | из ответа шага 1 |

Все высокоуровневые методы скрывают эту разницу — вы просто передаёте файл.

---

### Уровень 1 — высокоуровневые методы (рекомендуется)

Один вызов: библиотека сама получает URL, загружает файл и отправляет сообщение.

#### Отправка в чат

```php
// Изображение
Bot::sendImageToChat($chatId, '/path/to/photo.jpg', 'Подпись');
Bot::sendImageToChat($chatId, '/path/to/photo.jpg', 'Подпись', 'image/jpeg');

// Видео
Bot::sendVideoToChat($chatId, '/path/to/video.mp4', 'Подпись');

// Аудио
Bot::sendAudioToChat($chatId, '/path/to/audio.mp3', 'Подпись');

// Документ / произвольный файл
Bot::sendFileToChat($chatId, '/path/to/document.pdf', 'Подпись');

// Любой тип через универсальный метод
Bot::sendMediaToChat($chatId, 'image', '/path/to/photo.jpg', 'Подпись');
```

#### Отправка пользователю

```php
Bot::sendImageToUser($userId, '/path/to/photo.jpg', 'Подпись');
Bot::sendVideoToUser($userId, '/path/to/video.mp4', 'Подпись');
Bot::sendAudioToUser($userId, '/path/to/audio.mp3', 'Подпись');
Bot::sendFileToUser($userId,  '/path/to/doc.pdf',   'Подпись');

// Универсальный метод
Bot::sendMediaToUser($userId, 'video', '/path/to/video.mp4', 'Подпись');
```

#### Сигнатура методов

```php
// Специализированные (image / video / audio / file)
Bot::sendImageToChat($chatId, $filePath, $caption = '', $mimeType = null, $extra = []);
Bot::sendImageToUser($userId, $filePath, $caption = '', $mimeType = null, $extra = []);
// sendVideo*, sendAudio*, sendFile* — аналогичны

// Универсальные
Bot::sendMediaToChat($chatId, $type, $filePath, $caption = '', $mimeType = null, $extra = []);
Bot::sendMediaToUser($userId, $type, $filePath, $caption = '', $mimeType = null, $extra = []);
```

Параметр `$extra` принимает те же опции, что и `sendMessageToChat()` / `sendMessageToUser()` (`format`, дополнительные `attachments` и т.д.).

---

### Уровень 2 — получение токена, ручная отправка

Используйте этот вариант, когда нужен токен до отправки сообщения — например, чтобы вложить файл в ответ на callback.

```php
// Получить токен — библиотека выбирает правильный шаг в зависимости от типа.
// Каждому вложению нужна своя переменная: тип токена должен совпадать
// с 'type' в attachments, иначе сообщение будет отклонено.
$imageToken = Bot::upload('image', '/path/to/photo.jpg');
$videoToken = Bot::upload('video', '/path/to/video.mp4');
$audioToken = Bot::upload('audio', '/path/to/audio.mp3');
$fileToken  = Bot::upload('file',  '/path/to/doc.pdf');

// Использовать токен в сообщении
Bot::sendMessageToChat($chatId, 'Фото', [
    'attachments' => [
        ['type' => 'image', 'payload' => ['token' => $imageToken]],
    ],
]);

// Файл — ТОЛЬКО отдельным сообщением (см. «Ограничения MAX на состав вложений»)
Bot::sendMessageToChat($chatId, 'Документ', [
    'attachments' => [
        ['type' => 'file', 'payload' => ['token' => $fileToken]],
    ],
]);
```

> ⚠️ Частая ошибка: положить в одно сообщение и картинку, и файл. MAX ответит
> `400 proto.payload` — библиотека перехватит это раньше и бросит `MaxBotException`.

---

### Пакетная отправка нескольких вложений

Когда нужно отправить сразу несколько вложений, из которых часть — файлы, используйте
`sendAttachmentsToChat()` / `sendAttachmentsToUser()`. Метод сам разложит набор на
допустимые сообщения: все не-файловые вложения уходят одним сообщением, каждый файл —
отдельным.

```php
$responses = Bot::sendAttachmentsToChat($chatId, [
    ['type' => 'image', 'path' => '/path/to/photo1.jpg'],
    ['type' => 'image', 'path' => '/path/to/photo2.jpg'],
    ['type' => 'file',  'path' => '/path/to/doc.pdf'],
    ['type' => 'file',  'path' => '/path/to/report.xlsx'],
], 'Подпись');

// Отправлено 3 сообщения:
//   1) [image, image] с подписью «Подпись»
//   2) [file] doc.pdf
//   3) [file] report.xlsx
// $responses — массив ответов API, по одному на сообщение
```

Элемент набора описывается ключами:

| Ключ | Обязателен | Описание |
|------|------------|----------|
| `type` | да | `image`, `video`, `audio`, `file` |
| `path` | да¹ | путь к локальному файлу — будет загружен автоматически |
| `token` | да¹ | готовый токен, если файл уже загружен через `Bot::upload()` |
| `mime` | нет | MIME-тип; по умолчанию определяется по расширению |

¹ нужно указать либо `path`, либо `token`.

Подпись (`$caption`) ставится на первое сообщение, а `$extra['attachments']`
(обычно клавиатура) — на последнее:

```php
Bot::sendAttachmentsToChat($chatId, $items, 'Подпись', [
    'attachments' => [$keyboard],   // окажется на последнем сообщении
]);
```

Сигнатуры:

```php
Bot::sendAttachmentsToChat($chatId, array $items, $caption = '', $extra = []);
Bot::sendAttachmentsToUser($userId, array $items, $caption = '', $extra = []);
```

---

### Уровень 3 — полный ручной контроль

Когда нужен доступ к сырым ответам каждого шага.

#### image / file — токен из ответа на загрузку

```php
// Шаг 1: запросить URL загрузки (токена ещё нет)
$uploadInfo = Bot::uploadFile('image');
// $uploadInfo['url'] — адрес для загрузки файла

// Шаг 2: загрузить файл → получить токен
$uploaded = Bot::uploadFileToUrl($uploadInfo['url'], '/path/to/photo.jpg', 'image/jpeg');
// $uploaded['token'] — токен вложения

// Шаг 3: отправить сообщение
Bot::sendMessageToChat($chatId, 'Фото', [
    'attachments' => [
        ['type' => 'image', 'payload' => ['token' => $uploaded['token']]],
    ],
]);
```

#### video / audio — токен из первого ответа

```php
// Шаг 1: запросить URL + получить токен сразу
$uploadInfo = Bot::uploadFile('video');
// $uploadInfo['url']   — адрес для загрузки файла
// $uploadInfo['token'] — токен вложения (уже здесь!)

// Шаг 2: загрузить файл (завершить слот)
Bot::uploadFileToUrl($uploadInfo['url'], '/path/to/video.mp4', 'video/mp4');

// Шаг 3: отправить сообщение, используя токен из шага 1
Bot::sendMessageToChat($chatId, 'Видео', [
    'attachments' => [
        ['type' => 'video', 'payload' => ['token' => $uploadInfo['token']]],
    ],
]);
```

---

### Полный пример: бот с командами `/photo` и `/video`

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$bot = new PHPMaxBot('your-bot-token');

$bot->command('photo', function () {
    return Bot::sendImageToChat(
        PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'],
        __DIR__ . '/files/photo.jpg',
        'Вот ваше фото!'
    );
});

$bot->command('video', function () {
    return Bot::sendVideoToChat(
        PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'],
        __DIR__ . '/files/video.mp4',
        'Вот ваше видео!'
    );
});

$bot->start();
```

Смотрите также пример `examples/media-bot.php`.

---

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
// В групповом чате отправляет в чат, в личном диалоге — пользователю
Bot::sendMessage('Текст сообщения');

// Явно указать получателя через $extra
Bot::sendMessage('Текст', ['chat_id' => $chatId]);
Bot::sendMessage('Текст', ['user_id' => $userId]);

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

> ⚠️ Сообщение с вложением `file` не может содержать других вложений, кроме
> `inline_keyboard` — см. [«Ограничения MAX на состав вложений»](#️-ограничения-max-на-состав-вложений).
> `sendMessageToChat()`, `sendMessageToUser()` и `editMessage()` проверяют это
> до обращения к API и бросают `MaxBotException`.

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

// Удалить чат
Bot::deleteChat($chatId);

// Получить участников чата
Bot::getChatMembers($chatId);

// Добавить участников
Bot::addChatMembers($chatId, [$userId1, $userId2]);

// Удалить участника
Bot::removeChatMember($chatId, $userId);

// Получить администраторов
Bot::getChatAdmins($chatId);

// Назначить администратора
Bot::addChatAdmin($chatId, $userId);

// Снять администратора
Bot::removeChatAdmin($chatId, $userId);

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

### Видео

```php
// Получить информацию о видео по токену
Bot::getVideo($videoToken);
```

### Подписки (Webhook)

> **С 25.05.2026 URL обязан быть HTTPS с сертификатом от доверенного CA.** Запросы на HTTP-адреса и адреса с самоподписными сертификатами будут отклоняться.

```php
// Получить список активных подписок
Bot::getSubscriptions();

// Создать webhook-подписку (URL обязан быть HTTPS)
Bot::createSubscription('https://example.com/webhook', [
    'message_created',
    'message_callback',
    'bot_started'
]);

// Обновить подписку — повторный вызов createSubscription с тем же URL
// заменит её настройки (список событий)
Bot::createSubscription('https://example.com/webhook', [
    'message_created',
    'message_callback',
    'bot_started',
    'user_added',
]);

// Удалить подписку
Bot::deleteSubscription('https://example.com/webhook');
```

### Загрузка и отправка файлов (краткий справочник)

```php
// Отправить изображение в чат (один вызов)
Bot::sendImageToChat($chatId, '/path/to/photo.jpg', 'Подпись');

// Отправить видео пользователю
Bot::sendVideoToUser($userId, '/path/to/video.mp4', 'Подпись');

// Отправить пачку вложений (файлы уйдут отдельными сообщениями)
Bot::sendAttachmentsToChat($chatId, [
    ['type' => 'image', 'path' => '/path/to/photo.jpg'],
    ['type' => 'file',  'path' => '/path/to/doc.pdf'],
], 'Подпись');
```

Полное описание — в разделе [«Отправка медиафайлов»](#отправка-медиафайлов).
Ограничения на состав вложений — [«Ограничения MAX на состав вложений»](#️-ограничения-max-на-состав-вложений).

### Действия

```php
// Отправить действие (печатает, отправляет файл и т.д.)
Bot::sendAction($chatId, 'typing_on');
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

`PHPMaxBot::start()` сам определяет режим:

- запуск через CLI (`php bot.php`) → **Long Polling**
- запуск через веб-сервер (HTTP-запрос приходит на скрипт) → **Webhook**

### Long Polling (только для разработки)

```bash
php bot.php
```

Бот опрашивает `getUpdates` в цикле. Подходит для локальной отладки.

> ⚠️ **Long Polling не подходит для production.** Метод `getUpdates` ограничен по скорости и сроку хранения событий — при нагрузке часть обновлений может быть пропущена. На staging и production используйте Webhook.

### Webhook (production)

Требования:

1. Публичный URL по **HTTPS** (HTTP больше не поддерживается с 25.05.2026).
2. Сертификат от **доверенного центра** (Let's Encrypt, ZeroSSL, коммерческие CA). Самоподписные сертификаты больше не поддерживаются.
3. Скрипт должен отвечать на `POST`-запросы от MAX и возвращать `2xx`.

Минимальный webhook-скрипт (например, `public/webhook.php`):

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$bot = new PHPMaxBot(getenv('BOT_TOKEN'));

// Настройка обработчиков...
$bot->command('start', fn() => Bot::sendMessage('Привет!'));

// При POST-запросе библиотека сама прочитает тело и обработает обновление
$bot->start();
```

Регистрация webhook (выполняется один раз — отдельным скриптом или из админки):

```php
Bot::createSubscription('https://example.com/webhook.php', [
    'message_created',
    'message_callback',
    'bot_started',
]);
```

Готовый пример: [`examples/webhook-bot.php`](examples/webhook-bot.php).

#### Обновление подписки

Чтобы сменить URL или список событий — просто вызовите `createSubscription` повторно с новым URL (старую при необходимости удалите через `deleteSubscription`):

```php
// Снять старую (опционально — если меняется URL)
Bot::deleteSubscription('https://old.example.com/webhook.php');

// Зарегистрировать новую
Bot::createSubscription('https://new.example.com/webhook.php', [
    'message_created',
    'message_callback',
]);
```

#### Проверка SSL-сертификата при исходящих запросах

По умолчанию библиотека отключает `CURLOPT_SSL_VERIFYPEER` / `CURLOPT_SSL_VERIFYHOST` для исходящих запросов к API MAX (это удобно при разработке). **В production обязательно включите проверку** — см. раздел [«Настройка параметров cURL»](#настройка-параметров-curl):

```php
$bot = new PHPMaxBot($token, [
    'curlOptions' => [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ],
]);
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

### Ошибки внутри обработчиков

Исключение, вылетевшее из обработчика, перехватывается фреймворком и передаётся в лог,
а не в ответ HTTP: **тело ответа на webhook MAX отбрасывает**, поэтому ошибка, выведенная
туда через `echo`, исчезла бы бесследно — со стороны это выглядит как «бот молча ничего
не отправил».

Поведение по режимам:

| Режим | Что происходит с ошибкой обработчика |
|-------|--------------------------------------|
| Webhook | пишется в лог, вебхуку возвращается `200` (чтобы MAX не повторял заведомо падающий запрос) |
| Long Polling | пишется в лог и в консоль; обработка остальных обновлений продолжается |

По умолчанию сообщения уходят в `error_log()`. Свой обработчик:

```php
PHPMaxBot::$errorHandler = function ($message, $exception) {
    file_put_contents('/var/log/maxbot.log', date('c') . ' ' . $message . "\n", FILE_APPEND);
};
```

В строку лога попадают класс исключения, текст, а для `ApiException` — ещё и код ошибки
MAX с HTTP-статусом и контекстом:

```
PHPMaxBot error [webhook] PHPMaxBot\Exceptions\ApiException: MAX API Error: ... (api_code=attachment.not.ready, http_code=400) context={"endpoint":"messages",...}
```

## Доступ к текущему обновлению

```php
// Получить полные данные обновления
$update = PHPMaxBot::$currentUpdate;

// Вспомогательные методы
$type = Bot::type();              // Тип обновления
$text = Bot::getText();           // Текст сообщения
$callbackData = Bot::getCallbackData(); // Данные callback
$contact = Bot::getContact(); // vCard (если пользователь поделился контактом)
$sender  = Bot::getSender(); // Данные отправителя (id, имя, etc)
```

## Типы обновлений

Доступные типы обновлений для фильтрации:

- `message_created` - Создано новое сообщение
- `message_edited` - Сообщение отредактировано
- `message_removed` - Сообщение удалено
- `message_callback` - Нажата callback-кнопка
- `bot_started` - Бот запущен пользователем
- `bot_stopped` - Пользователь остановил бота
- `bot_added` - Бот добавлен в чат
- `bot_removed` - Бот удален из чата
- `user_added` - Пользователь добавлен в чат
- `user_removed` - Пользователь удален из чата
- `chat_title_changed` - Название чата изменено
- `dialog_removed` - Диалог удален пользователем

### Структура обновлений: получение userId и chatId

Разные типы обновлений имеют разную структуру. Пути к идентификаторам:

| Тип обновления | userId | chatId |
|---|---|---|
| `message_created` | `$update['message']['sender']['user_id']` | `$update['message']['recipient']['chat_id']` ¹ |
| `message_edited` | `$update['message']['sender']['user_id']` | `$update['message']['recipient']['chat_id']` ¹ |
| `message_callback` | `$update['callback']['sender']['user_id']` | `$update['callback']['message']['recipient']['chat_id']` ¹ |
| `message_removed` | `$update['user_id']` | `$update['chat_id']` |
| `bot_started` | `$update['user']['user_id']` | `$update['chat_id']` |
| `bot_stopped` | `$update['user']['user_id']` | `$update['chat_id']` |
| `bot_added` | `$update['user']['user_id']` | `$update['chat_id']` |
| `bot_removed` | `$update['user']['user_id']` | `$update['chat_id']` |
| `user_added` | `$update['user']['user_id']` | `$update['chat_id']` |
| `user_removed` | `$update['user']['user_id']` | `$update['chat_id']` |
| `chat_title_changed` | `$update['user']['user_id']` | `$update['chat_id']` |
| `dialog_removed` | `$update['user']['user_id']` | `$update['chat_id']` |

¹ Поле `chat_id` в объекте `recipient` присутствует только для **групповых чатов**. В личном диалоге оно отсутствует — для ответа используйте `sender.user_id`.

Примеры:

```php
// message_created — отправитель и чат
$bot->on('message_created', function() {
    $update = PHPMaxBot::$currentUpdate;
    $userId = $update['message']['sender']['user_id'] ?? null;
    // групповой чат:
    $chatId = $update['message']['recipient']['chat_id'] ?? null;
    // тип чата: 'dialog' | 'chat' | 'channel'
    $chatType = $update['message']['recipient']['chat_type'] ?? null;
});

// message_callback — кто нажал кнопку и где
$bot->action('my_button', function() {
    $update     = PHPMaxBot::$currentUpdate;
    $userId     = $update['callback']['sender']['user_id'];
    $callbackId = $update['callback']['callback_id'];
    // сообщение с кнопкой — в $update['callback']['message'], не в $update['message']
    $messageId  = $update['callback']['message']['body']['mid'] ?? null;
    $chatId     = $update['callback']['message']['recipient']['chat_id'] ?? null;
    return Bot::answerOnCallback($callbackId, ['notification' => 'OK']);
});

// bot_started — кто запустил бота
$bot->on('bot_started', function() {
    $update    = PHPMaxBot::$currentUpdate;
    $userId    = $update['user']['user_id'];
    $firstName = $update['user']['first_name'];
    $chatId    = $update['chat_id'];        // ID личного диалога
    $payload   = $update['payload'] ?? null; // deeplink-параметр
});

// user_added — кто добавлен и кем
$bot->on('user_added', function() {
    $update    = PHPMaxBot::$currentUpdate;
    $userId    = $update['user']['user_id'];    // добавленный пользователь
    $chatId    = $update['chat_id'];
    $inviterId = $update['inviter_id'] ?? null; // кто добавил
});

// user_removed — кто удалён и кем
$bot->on('user_removed', function() {
    $update  = PHPMaxBot::$currentUpdate;
    $userId  = $update['user']['user_id'];  // удалённый пользователь
    $chatId  = $update['chat_id'];
    $adminId = $update['admin_id'] ?? null; // кто удалил
});

// chat_title_changed — кто сменил название
$bot->on('chat_title_changed', function() {
    $update   = PHPMaxBot::$currentUpdate;
    $userId   = $update['user']['user_id'];
    $chatId   = $update['chat_id'];
    $newTitle = $update['title'];
});

// message_removed — прямые поля, без вложенного объекта user
$bot->on('message_removed', function() {
    $update    = PHPMaxBot::$currentUpdate;
    $userId    = $update['user_id'];    // не $update['user']['user_id']
    $chatId    = $update['chat_id'];
    $messageId = $update['message_id'];
});

// bot_added — бот добавлен в чат или канал
$bot->on('bot_added', function() {
    $update    = PHPMaxBot::$currentUpdate;
    $userId    = $update['user']['user_id']; // кто добавил
    $chatId    = $update['chat_id'];
    $isChannel = $update['is_channel'] ?? false;
});
```

Указать типы обновлений:

```php
$bot->start([
    'message_created',
    'message_callback',
    'bot_started'
]);
```

## Примеры

| Файл | Что демонстрирует |
|---|---|
| `sample.php` | Полный пример с командами, клавиатурами и вложениями |
| `examples/simple-bot.php` | Команды, события, регулярные выражения |
| `examples/keyboard-bot.php` | Inline-клавиатуры, callback-кнопки, запрос контакта и геолокации |
| `examples/attachments-bot.php` | Обработка всех типов входящих вложений через `onAttachment()` |
| `examples/media-bot.php` | Отправка изображений, видео, аудио и файлов |
| `examples/webhook-bot.php` | Production-режим: HTTPS webhook + регистрация подписки |

Запуск любого примера:

```bash
export BOT_TOKEN=your_token
php examples/attachments-bot.php
```

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

## Настройка параметров cURL

Библиотека позволяет задать любые параметры cURL, которые будут применяться к каждому запросу к API.

> **Защищённые параметры** — `CURLOPT_URL`, `CURLOPT_RETURNTRANSFER`, `CURLOPT_CUSTOMREQUEST`, `CURLOPT_HTTPHEADER`, `CURLOPT_POSTFIELDS` — всегда устанавливаются библиотекой и не могут быть переопределены.  
> **SSL-параметры** (`CURLOPT_SSL_VERIFYHOST`, `CURLOPT_SSL_VERIFYPEER`) по умолчанию отключены, но могут быть переопределены.

### Способ 1: через второй параметр конструктора (рекомендуется)

```php
$bot = new PHPMaxBot('your-bot-token', [
    'curlOptions' => [
        CURLOPT_TIMEOUT        => 30,     // Таймаут запроса (секунды)
        CURLOPT_CONNECTTIMEOUT => 10,     // Таймаут подключения (секунды)
        CURLOPT_PROXY          => 'http://proxy.example.com:8080',
        CURLOPT_SSL_VERIFYPEER => true,   // Включить проверку SSL-сертификата
        CURLOPT_SSL_VERIFYHOST => 2,      // Включить проверку хоста SSL
    ],
    'debug' => false,                     // Можно задать и debug здесь
]);
```

### Способ 2: через статическое свойство (можно менять в любой момент)

```php
$bot = new PHPMaxBot('your-bot-token');

PHPMaxBot::$curlOptions = [
    CURLOPT_TIMEOUT => 30,
    CURLOPT_PROXY   => 'http://proxy.example.com:8080',
];
```

### Примеры конфигураций

**Работа через прокси:**
```php
$bot = new PHPMaxBot($token, [
    'curlOptions' => [
        CURLOPT_PROXY        => 'http://proxy.example.com:8080',
        CURLOPT_PROXYUSERPWD => 'user:password',
    ],
]);
```

**Строгая проверка SSL (для продакшн-среды):**
```php
$bot = new PHPMaxBot($token, [
    'curlOptions' => [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO         => '/etc/ssl/certs/ca-certificates.crt',
    ],
]);
```

**Ограничение таймаутов:**
```php
$bot = new PHPMaxBot($token, [
    'curlOptions' => [
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
    ],
]);
```

## Лицензия

GPL-3.0

## Автор

GrayHoax <grayhoax@grayhoax.ru>

## Ссылки

- [MAX Messenger](https://max.ru/)
- [MAX Bot API Documentation](https://dev.max.ru/docs-api)

## Поддержка

Если у вас возникли проблемы или вопросы, создайте issue на GitHub.
