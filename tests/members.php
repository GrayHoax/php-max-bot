<?php
/**
 * Тест: Участники чата
 *
 * Запуск: php tests/members.php
 *
 * Проверяет: getChatMembers, getChatAdmins, getChatMembership,
 *            addChatAdmin/removeChatAdmin (требуют прав администратора)
 *
 * Требует: TEST_CHAT_ID в config.php
 * Опционально: TEST_USER_ID для тестов управления администраторами
 *
 * Примечание: addChatMembers/removeChatMember/leaveChat не тестируются автоматически —
 *             эти операции изменяют состав чата и требуют ручного восстановления.
 */

require_once __DIR__ . '/helpers.php';

use PHPMaxBot\Exceptions\ApiException;

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Участники чата');

// ── getChatMembers ───────────────────────────────────────────────────────────

$members = test('getChatMembers — список участников чата', function () {
    $result = Bot::getChatMembers(TEST_CHAT_ID);
    assert_not_empty($result, 'ответ getChatMembers');
    return $result;
});

if ($members) {
    $list  = $members['members'] ?? (isset($members[0]) ? $members : []);
    $count = count($list);
    echo C_DIM . "    Участников: $count\n" . C_RESET;
}

// ── getChatAdmins ────────────────────────────────────────────────────────────

$admins = test('getChatAdmins — список администраторов чата', function () {
    try {
        $result = Bot::getChatAdmins(TEST_CHAT_ID);
        assert_not_empty($result, 'ответ getChatAdmins');
        return $result;
    } catch (ApiException $e) {
        skip_test('нет прав: ' . $e->getMessage());
    }
});

if ($admins) {
    $list  = $admins['members'] ?? (isset($admins[0]) ? $admins : []);
    $count = count($list);
    echo C_DIM . "    Администраторов: $count\n" . C_RESET;
}

// ── getChatMembership ────────────────────────────────────────────────────────

$membership = test('getChatMembership — членство бота в чате', function () {
    $result = Bot::getChatMembership(TEST_CHAT_ID);
    assert_not_empty($result, 'ответ getChatMembership');
    return $result;
});

if ($membership) {
    $role = $membership['role'] ?? '?';
    echo C_DIM . "    Роль бота: $role\n" . C_RESET;
}

// ── addChatAdmin / removeChatAdmin ───────────────────────────────────────────

if (TEST_USER_ID) {
    $adminAdded = test('addChatAdmin — назначение пользователя администратором', function () {
        try {
            $result = Bot::addChatAdmin(TEST_CHAT_ID, TEST_USER_ID);
            assert_not_empty($result, 'ответ addChatAdmin');
            return true;
        } catch (ApiException $e) {
            skip_test('нет прав: ' . $e->getMessage());
        }
    });

    if ($adminAdded) {
        test('removeChatAdmin — снятие прав администратора', function () {
            $result = Bot::removeChatAdmin(TEST_CHAT_ID, TEST_USER_ID);
            assert_not_empty($result, 'ответ removeChatAdmin');
        });
    } else {
        test('removeChatAdmin — снятие прав администратора', function () {
            skip_test('addChatAdmin не выполнен');
        });
    }
} else {
    test('addChatAdmin — назначение администратора', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
    test('removeChatAdmin — снятие прав администратора', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
}

// ── addChatMembers / removeChatMember ────────────────────────────────────────

test('addChatMembers — добавление участника в чат', function () {
    skip_test('пропущен намеренно — изменяет состав чата');
});

test('removeChatMember — удаление участника из чата', function () {
    skip_test('пропущен намеренно — изменяет состав чата');
});

test('leaveChat — выход бота из чата', function () {
    skip_test('пропущен намеренно — необратимая операция');
});

test_summary();
