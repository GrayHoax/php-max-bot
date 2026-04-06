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
use PHPMaxBot\Exceptions\ApiException;

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
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
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
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
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
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
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
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
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
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── open_app кнопка ───────────────────────────────────────────────────────────

$mid = test('open_app — кнопка открытия мини-приложения', function () {
    try {
        $keyboard = Keyboard::inlineKeyboard([
            [Keyboard::open_app('Открыть приложение', 'https://max.ru/')],
        ]);
        $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] open_app кнопка:', [
            'attachments' => [$keyboard],
        ]);
        assert_key($result['message']['body'], 'mid');
        return $result['message']['body']['mid'];
    } catch (ApiException $e) {
        skip_test('API ошибка (mini-app URL не зарегистрирован): ' . $e->getMessage());
    }
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── chat кнопка ───────────────────────────────────────────────────────────────

$mid = test('chat — кнопка создания нового чата', function () {
    try {
        $keyboard = Keyboard::inlineKeyboard([
            [Keyboard::chat('Создать группу', 'Тестовая группа')],
        ]);
        $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] chat кнопка:', [
            'attachments' => [$keyboard],
        ]);
        assert_key($result['message']['body'], 'mid');
        return $result['message']['body']['mid'];
    } catch (ApiException $e) {
        skip_test('API ошибка: ' . $e->getMessage());
    }
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
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
}

// ── onAttachment — ручной тест ────────────────────────────────────────────────

$mid = test('onAttachment — отправить клавиатуру для ручного тестирования', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestContact('Отправить контакт')],
        [Keyboard::requestGeoLocation('Отправить геолокацию')],
    ]);
    $instruction = "[тест] onAttachment: нажмите одну из кнопок ниже и убедитесь, "
        . "что бот (keyboard-bot.php) ответил данными вложения.";
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, $instruction, [
        'attachments' => [$keyboard],
    ]);
    assert_key($result['message']['body'], 'mid');
    echo "\n" . C_YELLOW . C_BOLD
        . "  ! Инструкция: нажмите кнопку «Отправить контакт» или «Отправить геолокацию»\n"
        . "    в чате выше. При запущенном keyboard-bot.php бот должен ответить данными вложения.\n"
        . C_RESET;
    return $result['message']['body']['mid'];
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
