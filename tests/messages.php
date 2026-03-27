<?php
/**
 * Тест: Сообщения
 *
 * Запуск: php tests/messages.php
 *
 * Проверяет:
 *   sendMessageToChat, sendMessageToUser, sendMessage (с явным chat_id/user_id),
 *   getMessage, getMessages, editMessage, deleteMessage,
 *   sendMessageToChat с форматированием markdown/html
 *
 * Требует: TEST_CHAT_ID в config.php
 * Опционально: TEST_USER_ID для тестов личных сообщений
 */

require_once __DIR__ . '/helpers.php';

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Сообщения');

$chatMid = null;
$userMid = null;

// ── sendMessageToChat ───────────────────────────────────────────────────────

$chatMid = test('sendMessageToChat — отправка текста в чат', function () {
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] sendMessageToChat — ' . date('H:i:s'));
    assert_array($result, 'ответ sendMessageToChat');
    assert_key($result, 'mid');
    assert_not_empty($result['mid'], 'mid');
    return $result['mid'];
});

if ($chatMid) {
    echo C_DIM . "    mid: $chatMid\n" . C_RESET;
}

// ── sendMessageToUser ───────────────────────────────────────────────────────

if (TEST_USER_ID) {
    $userMid = test('sendMessageToUser — отправка текста пользователю', function () {
        $result = Bot::sendMessageToUser(TEST_USER_ID, '[тест] sendMessageToUser — ' . date('H:i:s'));
        assert_array($result, 'ответ sendMessageToUser');
        assert_key($result, 'mid');
        return $result['mid'];
    });
} else {
    test('sendMessageToUser — отправка текста пользователю', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
}

// ── sendMessage с явным chat_id ─────────────────────────────────────────────

test('sendMessage — с явным chat_id в параметре extra', function () {
    $result = Bot::sendMessage('[тест] sendMessage c chat_id — ' . date('H:i:s'), [
        'chat_id' => TEST_CHAT_ID,
    ]);
    assert_key($result, 'mid');
    Bot::deleteMessage($result['mid']);
});

// ── sendMessage с явным user_id ─────────────────────────────────────────────

if (TEST_USER_ID) {
    test('sendMessage — с явным user_id в параметре extra', function () {
        $result = Bot::sendMessage('[тест] sendMessage c user_id — ' . date('H:i:s'), [
            'user_id' => TEST_USER_ID,
        ]);
        assert_key($result, 'mid');
        Bot::deleteMessage($result['mid']);
    });
} else {
    test('sendMessage — с явным user_id в параметре extra', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
}

// ── Форматирование: markdown ────────────────────────────────────────────────

test('sendMessageToChat — markdown форматирование', function () {
    $result = Bot::sendMessageToChat(
        TEST_CHAT_ID,
        '**Жирный** _курсив_ ~~зачёркнутый~~ `код` — [ссылка](https://max.ru/) — тест markdown',
        ['format' => 'markdown']
    );
    assert_key($result, 'mid');
    Bot::deleteMessage($result['mid']);
});

// ── Форматирование: html ────────────────────────────────────────────────────

test('sendMessageToChat — html форматирование', function () {
    $result = Bot::sendMessageToChat(
        TEST_CHAT_ID,
        '<b>Жирный</b> <i>курсив</i> <del>зачёрк.</del> <code>код</code> — <a href="https://max.ru/">ссылка</a> — тест html',
        ['format' => 'html']
    );
    assert_key($result, 'mid');
    Bot::deleteMessage($result['mid']);
});

// ── getMessage ──────────────────────────────────────────────────────────────

test('getMessage — получение сообщения по ID', function () use ($chatMid) {
    if (!$chatMid) {
        skip_test('sendMessageToChat не выполнен');
    }
    $result = Bot::getMessage($chatMid);
    assert_not_empty($result, 'ответ getMessage');
});

// ── getMessages ─────────────────────────────────────────────────────────────

test('getMessages — список последних сообщений чата', function () {
    $result = Bot::getMessages(TEST_CHAT_ID, ['count' => 5]);
    assert_not_empty($result, 'ответ getMessages');
});

// ── editMessage ─────────────────────────────────────────────────────────────

test('editMessage — редактирование текста сообщения', function () use ($chatMid) {
    if (!$chatMid) {
        skip_test('sendMessageToChat не выполнен');
    }
    $result = Bot::editMessage($chatMid, [
        'text' => '[тест] editMessage — отредактировано ' . date('H:i:s'),
    ]);
    assert_not_empty($result, 'ответ editMessage');
});

// ── deleteMessage: chat ─────────────────────────────────────────────────────

test('deleteMessage — удаление сообщения в чате', function () use ($chatMid) {
    if (!$chatMid) {
        skip_test('sendMessageToChat не выполнен');
    }
    $result = Bot::deleteMessage($chatMid);
    assert_not_empty($result, 'ответ deleteMessage');
});

// ── deleteMessage: user ─────────────────────────────────────────────────────

if ($userMid) {
    test('deleteMessage — удаление сообщения пользователю', function () use ($userMid) {
        $result = Bot::deleteMessage($userMid);
        assert_not_empty($result, 'ответ deleteMessage');
    });
}

test_summary();
