<?php
/**
 * Тест: Чаты
 *
 * Запуск: php tests/chats.php
 *
 * Проверяет: getAllChats, getChat, editChatInfo, deleteChat (пропускается — необратимо),
 *            sendAction
 *
 * Требует: TEST_CHAT_ID в config.php
 */

require_once __DIR__ . '/helpers.php';

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Чаты');

// ── getAllChats ──────────────────────────────────────────────────────────────

$allChats = test('getAllChats — получение списка всех чатов', function () {
    $result = Bot::getAllChats();
    assert_not_empty($result, 'ответ getAllChats');
    return $result;
});

if ($allChats) {
    $list  = $allChats['chats'] ?? (isset($allChats[0]) ? $allChats : []);
    $count = count($list);
    echo C_DIM . "    Найдено чатов: $count\n" . C_RESET;
}

// ── getChat ─────────────────────────────────────────────────────────────────

$chatInfo = test('getChat — информация о конкретном чате', function () {
    $result = Bot::getChat(TEST_CHAT_ID);
    assert_array($result, 'ответ getChat');
    assert_key($result, 'chat_id');
    assert_equals(TEST_CHAT_ID, $result['chat_id'], 'chat_id должен совпадать');
    return $result;
});

if ($chatInfo) {
    $title = $chatInfo['title'] ?? '(без названия)';
    $type  = $chatInfo['type']  ?? '?';
    echo C_DIM . "    Чат: \"$title\" (тип: $type)\n" . C_RESET;
}

// ── editChatInfo ─────────────────────────────────────────────────────────────

$originalDescription = $chatInfo['description'] ?? '';

test('editChatInfo — изменение описания чата (с восстановлением)', function () use ($originalDescription) {
    $testDesc = 'PHPMaxBot test ' . date('H:i:s');

    $result = Bot::editChatInfo(TEST_CHAT_ID, ['description' => $testDesc]);
    // MAX API returns empty body (no content) on success — just verify no exception was thrown
    assert_not_empty($result !== false, 'вызов editChatInfo');

    // Восстанавливаем исходное описание
    Bot::editChatInfo(TEST_CHAT_ID, ['description' => $originalDescription]);
});

// ── sendAction ───────────────────────────────────────────────────────────────

test('sendAction — отправка статуса "печатает"', function () {
    $result = Bot::sendAction(TEST_CHAT_ID, 'typing_on');
    // API возвращает пустой объект или success при успехе
    assert_not_empty($result !== false, 'вызов sendAction');
});

// ── deleteChat ───────────────────────────────────────────────────────────────

test('deleteChat — удаление чата', function () {
    skip_test('пропущен намеренно — необратимая операция');
});

test_summary();
