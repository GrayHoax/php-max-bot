<?php
/**
 * Тест: Клавиатуры и кнопки
 *
 * Запуск: php tests/keyboard.php
 *
 * Проверяет отправку сообщений со всеми типами кнопок:
 *   callback, link, message, request_contact, request_geo_location, open_app, chat
 *   а также многорядные клавиатуры и intent у callback-кнопок.
 *
 * Все отправленные тестовые сообщения удаляются в конце.
 *
 * Требует: TEST_CHAT_ID в config.php
 */

require_once __DIR__ . '/helpers.php';

if (!class_exists('PHPMaxBot\\Helpers\\Keyboard')) {
    require_once __DIR__ . '/../src/Helpers/Keyboard.php';
}

use PHPMaxBot\Helpers\Keyboard;

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Клавиатуры и кнопки');

$sentMids = [];

// ── callback кнопки ──────────────────────────────────────────────────────────

$mid = test('callback — кнопки с intent (positive / negative)', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [
            Keyboard::callback('✓ Да', 'answer:yes', ['intent' => 'positive']),
            Keyboard::callback('✗ Нет', 'answer:no',  ['intent' => 'negative']),
            Keyboard::callback('Нейтрально', 'answer:maybe'),
        ],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] callback кнопки:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── link кнопка ──────────────────────────────────────────────────────────────

$mid = test('link — кнопка-ссылка', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::link('Открыть MAX', 'https://max.ru/')],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] link кнопка:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── message кнопка ───────────────────────────────────────────────────────────

$mid = test('message — кнопка отправки текста от пользователя', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::message('Подтвердить', 'Подтверждаю заказ')],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] message кнопка:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── request_contact ───────────────────────────────────────────────────────────

$mid = test('request_contact — кнопка запроса контакта', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestContact('Отправить контакт')],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] request_contact кнопка:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── request_geo_location ──────────────────────────────────────────────────────

$mid = test('request_geo_location — кнопка запроса геолокации', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestGeoLocation('Отправить геолокацию')],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] request_geo_location кнопка:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── open_app кнопка ───────────────────────────────────────────────────────────

$mid = test('open_app — кнопка открытия мини-приложения', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::open_app('Открыть приложение', 'https://max.ru/')],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] open_app кнопка:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── chat кнопка ───────────────────────────────────────────────────────────────

$mid = test('chat — кнопка создания нового чата', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::chat('Создать группу', 'Тестовая группа')],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] chat кнопка:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── Многорядная смешанная клавиатура ──────────────────────────────────────────

$mid = test('inlineKeyboard — многорядная смешанная клавиатура', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [
            Keyboard::callback('Вариант A', 'opt:a', ['intent' => 'positive']),
            Keyboard::callback('Вариант B', 'opt:b'),
            Keyboard::callback('Вариант C', 'opt:c', ['intent' => 'negative']),
        ],
        [
            Keyboard::message('Выбрать автоматически', 'Автовыбор'),
        ],
        [
            Keyboard::link('Подробнее', 'https://max.ru/'),
        ],
    ]);
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] смешанная многорядная клавиатура:', [
        'attachments' => [$keyboard],
    ]);
    assert_key($result, 'mid');
    return $result['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── Очистка ───────────────────────────────────────────────────────────────────

if (!empty($sentMids)) {
    echo "\n";
    foreach ($sentMids as $cleanMid) {
        test("deleteMessage — удаление тестового сообщения ($cleanMid)", function () use ($cleanMid) {
            Bot::deleteMessage($cleanMid);
        });
    }
}

test_summary();
