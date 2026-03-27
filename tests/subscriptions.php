<?php
/**
 * Тест: Подписки
 *
 * Запуск: php tests/subscriptions.php
 *
 * В long polling режиме проверяется только getSubscriptions (чтение).
 * createSubscription и deleteSubscription требуют HTTPS-сервер (webhook режим)
 * и поэтому пропускаются.
 */

require_once __DIR__ . '/helpers.php';

test_header('Подписки (Webhook)');

// ── getSubscriptions ──────────────────────────────────────────────────────────

$subs = test('getSubscriptions — список активных подписок', function () {
    $result = Bot::getSubscriptions();
    assert_not_empty($result !== false, 'вызов getSubscriptions');
    return $result;
});

if ($subs !== null) {
    $list  = $subs['subscriptions'] ?? (isset($subs[0]) ? $subs : []);
    $count = is_array($list) ? count($list) : 0;
    echo C_DIM . "    Активных подписок: $count\n" . C_RESET;
}

// ── createSubscription ────────────────────────────────────────────────────────

test('createSubscription — создание webhook-подписки', function () {
    skip_test('только для webhook-режима — требует публичный HTTPS URL');
});

// ── deleteSubscription ────────────────────────────────────────────────────────

test('deleteSubscription — удаление webhook-подписки', function () {
    skip_test('только для webhook-режима — требует публичный HTTPS URL');
});

test_summary();
