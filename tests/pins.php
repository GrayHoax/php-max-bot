<?php
/**
 * Тест: Закреплённые сообщения
 *
 * Запуск: php tests/pins.php
 *
 * Проверяет: pinMessage, getPinnedMessage, unpinMessage
 *
 * Требует: TEST_CHAT_ID в config.php
 * Примечание: бот должен иметь права на закрепление сообщений в чате.
 */

require_once __DIR__ . '/helpers.php';

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Закреплённые сообщения');

$testMid = null;

// ── Подготовка: отправка сообщения для закрепления ────────────────────────────

$testMid = test('Подготовка — отправка тестового сообщения', function () {
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] сообщение для закрепления — ' . date('H:i:s'));
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});

if ($testMid) {
    echo C_DIM . "    mid: $testMid\n" . C_RESET;
}

// ── pinMessage ────────────────────────────────────────────────────────────────

test('pinMessage — закрепление сообщения', function () use ($testMid) {
    if (!$testMid) {
        skip_test('тестовое сообщение не создано');
    }
    $result = Bot::pinMessage(TEST_CHAT_ID, $testMid);
    assert_not_empty($result !== false, 'вызов pinMessage');
});

// ── getPinnedMessage после закрепления ───────────────────────────────────────

test('getPinnedMessage — получение закреплённого сообщения', function () use ($testMid) {
    if (!$testMid) {
        skip_test('тестовое сообщение не создано');
    }
    $result = Bot::getPinnedMessage(TEST_CHAT_ID);
    assert_not_empty($result, 'ответ getPinnedMessage');
});

// ── unpinMessage ──────────────────────────────────────────────────────────────

test('unpinMessage — открепление сообщения', function () use ($testMid) {
    if (!$testMid) {
        skip_test('тестовое сообщение не создано');
    }
    $result = Bot::unpinMessage(TEST_CHAT_ID);
    assert_not_empty($result !== false, 'вызов unpinMessage');
});

// ── Очистка ───────────────────────────────────────────────────────────────────

if ($testMid) {
    test('Очистка — удаление тестового сообщения', function () use ($testMid) {
        Bot::deleteMessage($testMid);
    });
}

test_summary();
