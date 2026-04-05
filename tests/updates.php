<?php
/**
 * Тест: Получение обновлений (Long Polling)
 *
 * Запуск: php tests/updates.php
 *
 * Проверяет: getUpdates с различными параметрами.
 * Также выводит последние обновления — удобно для первоначальной настройки:
 *   узнать TEST_CHAT_ID и TEST_USER_ID.
 */

require_once __DIR__ . '/helpers.php';

test_header('Обновления (Long Polling / getUpdates)');

// ── getUpdates без параметров ─────────────────────────────────────────────────

$updates = test('getUpdates — базовый запрос', function () {
    $result = Bot::getUpdates();
    assert_array($result, 'ответ getUpdates');
    assert_key($result, 'updates');
    return $result;
});

if ($updates !== null) {
    $count  = count($updates['updates'] ?? []);
    $marker = $updates['marker'] ?? '—';
    echo C_DIM . "    Обновлений: $count, маркер: $marker\n" . C_RESET;

    // Выводим последние обновления с chat_id / user_id — помогает заполнить config.php
    if (!empty($updates['updates'])) {
        echo C_DIM . "\n    Последние обновления (для настройки config.php):\n" . C_RESET;
        foreach (array_slice($updates['updates'], -5) as $upd) {
            $type   = $upd['update_type'] ?? '?';
            $chatId = $upd['message']['recipient']['chat_id']
                   ?? $upd['chat']['chat_id']
                   ?? null;
            $userId = $upd['message']['sender']['user_id']
                   ?? $upd['user']['user_id']
                   ?? $upd['callback']['sender']['user_id']
                   ?? null;
            $parts  = ["тип: $type"];
            if ($chatId) {
                $parts[] = "chat_id: $chatId";
            }
            if ($userId) {
                $parts[] = "user_id: $userId";
            }
            echo C_DIM . '      • ' . implode(', ', $parts) . "\n" . C_RESET;
        }
        echo "\n";
    }
}

// ── getUpdates с фильтром типов ───────────────────────────────────────────────

test('getUpdates — фильтр по типам обновлений', function () {
    $types  = ['message_created', 'message_callback', 'bot_started'];
    $result = Bot::getUpdates($types);
    assert_array($result, 'ответ getUpdates с типами');
    assert_key($result, 'updates');

    foreach ($result['updates'] as $upd) {
        if (isset($upd['update_type']) && !in_array($upd['update_type'], $types, true)) {
            throw new \RuntimeException(
                "Получен неожиданный тип обновления: {$upd['update_type']}"
            );
        }
    }
});

// ── getUpdates с маркером ─────────────────────────────────────────────────────

test('getUpdates — с маркером для получения следующей страницы', function () use ($updates) {
    if (empty($updates['marker'])) {
        skip_test('маркер недоступен (нет обновлений)');
    }
    $result = Bot::getUpdates([], ['marker' => $updates['marker']]);
    assert_array($result, 'ответ getUpdates с маркером');
    assert_key($result, 'updates');
});

// ── getUpdates с ограничением количества ─────────────────────────────────────

test('getUpdates — с параметром limit', function () {
    $result = Bot::getUpdates([], ['limit' => 3]);
    assert_array($result, 'ответ getUpdates с limit');
    assert_key($result, 'updates');

    $count = count($result['updates']);
    if ($count > 3) {
        throw new \RuntimeException("Вернулось $count обновлений при limit=3");
    }
});

test_summary();
