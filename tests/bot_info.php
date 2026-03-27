<?php
/**
 * Тест: Информация о боте
 *
 * Запуск: php tests/bot_info.php
 *
 * Проверяет: getMyInfo, editMyInfo, setMyCommands, deleteMyCommands
 */

require_once __DIR__ . '/helpers.php';

test_header('Информация о боте');

// ── getMyInfo ───────────────────────────────────────────────────────────────

$botInfo = test('getMyInfo — получение информации о боте', function () {
    $result = Bot::getMyInfo();
    assert_array($result, 'ответ getMyInfo');
    assert_key($result, 'user_id');
    assert_key($result, 'name');
    assert_key($result, 'is_bot');
    assert_equals(true, $result['is_bot'], 'is_bot должен быть true');
    assert_not_empty($result['user_id'], 'user_id');
    return $result;
});

if ($botInfo) {
    echo C_DIM . "    Бот: {$botInfo['name']} (ID: {$botInfo['user_id']})\n" . C_RESET;
}

// ── editMyInfo ──────────────────────────────────────────────────────────────

$originalDescription = $botInfo['description'] ?? '';

test('editMyInfo — изменение описания (с восстановлением)', function () use ($originalDescription) {
    $testDesc = 'PHPMaxBot test ' . date('H:i:s');

    $result = Bot::editMyInfo(['description' => $testDesc]);
    assert_array($result, 'ответ editMyInfo');
    assert_key($result, 'user_id');

    // Восстанавливаем исходное описание
    Bot::editMyInfo(['description' => $originalDescription]);
});

// ── setMyCommands ───────────────────────────────────────────────────────────

test('setMyCommands — установка команд бота', function () {
    $commands = [
        ['name' => 'start', 'description' => 'Запустить бота'],
        ['name' => 'help',  'description' => 'Помощь'],
    ];
    $result = Bot::setMyCommands($commands);
    assert_array($result, 'ответ setMyCommands');
    assert_key($result, 'user_id');
});

// ── deleteMyCommands ────────────────────────────────────────────────────────

test('deleteMyCommands — удаление всех команд бота', function () {
    $result = Bot::deleteMyCommands();
    assert_array($result, 'ответ deleteMyCommands');
    assert_key($result, 'user_id');
});

test_summary();
