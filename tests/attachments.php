<?php
/**
 * Тест: Входящие вложения (onAttachment)
 *
 * Запуск: php tests/attachments.php
 *
 * Интерактивный тест: отправляет в чат инструкцию что прислать боту,
 * при получении каждого типа вложения обрабатывает его, выводит результат
 * в консоль и дублирует ответом в чат.
 *
 * Ожидаемые типы: location, contact, image, video, audio, file, sticker, share
 * (inline_keyboard пользователь отправить не может — не тестируется)
 *
 * Структура вложений по типам (из официальной схемы API):
 *   location         → $a['latitude'], $a['longitude']  (без payload)
 *   contact          → $a['payload']['vcf_info'], $a['payload']['max_info'][...]
 *   image            → $a['payload']['photo_id'], $a['payload']['token'], $a['payload']['url']
 *   video            → $a['payload']['url'], $a['payload']['token']
 *   audio            → $a['payload']['url'], $a['payload']['token']
 *   file             → $a['payload']['url|token'] + $a['filename'], $a['size']
 *   sticker          → $a['payload']['url|code']  + $a['width'],    $a['height']
 *   share            → $a['payload']['url']
 *
 * Требует: TEST_CHAT_ID в config.php
 * Таймаут ожидания: 120 секунд
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

define('ATTACH_TIMEOUT', 120);

// ── Типы вложений: метка, инструкция и обработчик ────────────────────────────

$defs = [
    'location' => [
        'label'   => 'location  — нажмите кнопку «Геолокация»',
        'handler' => function (array $a): string {
            return sprintf(
                "latitude=%s, longitude=%s",
                $a['latitude']  ?? '?',
                $a['longitude'] ?? '?'
            );
        },
    ],
    'contact' => [
        'label'   => 'contact   — нажмите кнопку «Контакт»',
        'handler' => function (array $a): string {
            $info = $a['payload']['max_info'] ?? [];
            $name = trim(($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''));
            return sprintf(
                "name=%s, user_id=%s",
                $name ?: '—',
                $info['user_id'] ?? '—'
            );
        },
    ],
    'image' => [
        'label'   => 'image     — пришлите фото (не файлом)',
        'handler' => function (array $a): string {
            $p = $a['payload'] ?? [];
            return sprintf(
                "photo_id=%s\ntoken=%s\nurl=%s",
                $p['photo_id'] ?? '—',
                mb_substr($p['token'] ?? '—', 0, 32),
                mb_substr($p['url']   ?? '—', 0, 64)
            );
        },
    ],
    'video' => [
        'label'   => 'video     — пришлите видеозапись',
        'handler' => function (array $a): string {
            $p = $a['payload'] ?? [];
            return sprintf(
                "token=%s\nurl=%s",
                mb_substr($p['token'] ?? '—', 0, 32),
                mb_substr($p['url']   ?? '—', 0, 64)
            );
        },
    ],
    'audio' => [
        'label'   => 'audio     — запишите голосовое сообщение',
        'handler' => function (array $a): string {
            $p = $a['payload'] ?? [];
            return sprintf(
                "token=%s\nurl=%s",
                mb_substr($p['token'] ?? '—', 0, 32),
                mb_substr($p['url']   ?? '—', 0, 64)
            );
        },
    ],
    'file' => [
        'label'   => 'file      — пришлите любой файл',
        'handler' => function (array $a): string {
            $p = $a['payload'] ?? [];
            return sprintf(
                "filename=%s, size=%d байт\ntoken=%s\nurl=%s",
                $a['filename'] ?? '—',
                (int) ($a['size'] ?? 0),
                mb_substr($p['token'] ?? '—', 0, 32),
                mb_substr($p['url']   ?? '—', 0, 64)
            );
        },
    ],
    'sticker' => [
        'label'   => 'sticker   — пришлите любой стикер',
        'handler' => function (array $a): string {
            $p = $a['payload'] ?? [];
            return sprintf(
                "code=%s, %s×%s\nurl=%s",
                $p['code']   ?? '—',
                $a['width']  ?? '—',
                $a['height'] ?? '—',
                mb_substr($p['url'] ?? '—', 0, 64)
            );
        },
    ],
    'share' => [
        'label'   => 'share     — поделитесь ссылкой или постом',
        'handler' => function (array $a): string {
            return "url=" . mb_substr($a['payload']['url'] ?? '—', 0, 80);
        },
    ],
];

// ── Отправка инструкций в чат ─────────────────────────────────────────────────

test_header('Входящие вложения (onAttachment)');

$keyboard = Keyboard::inlineKeyboard([
    [Keyboard::requestGeoLocation('Геолокация')],
    [Keyboard::requestContact('Контакт')],
]);

$lines = ["[тест] onAttachment — пришлите боту вложения каждого типа:\n"];
foreach ($defs as $def) {
    $lines[] = '• ' . $def['label'];
}
$lines[] = "\nТаймаут: " . ATTACH_TIMEOUT . " сек.";

$instructionMid = test('Отправка инструкций в чат', function () use ($lines, $keyboard) {
    $result = Bot::sendMessageToChat(TEST_CHAT_ID, implode("\n", $lines), [
        'attachments' => [$keyboard],
    ]);
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});

if (!$instructionMid) {
    echo C_RED . "  Не удалось отправить инструкции — тест прерван.\n" . C_RESET;
    test_summary();
}

// ── Список ожидаемых типов ────────────────────────────────────────────────────

echo "\n";
foreach ($defs as $def) {
    echo C_DIM . "  · " . $def['label'] . C_RESET . "\n";
}
echo "\n" . C_YELLOW . C_BOLD
    . "  ! Пришлите каждое вложение в чат. Таймаут: " . ATTACH_TIMEOUT . " сек.\n"
    . C_RESET . "\n";

// ── Инициализация маркера (не берём обновления до запуска теста) ──────────────

$marker = 0;
try {
    $init = Bot::getUpdates([]);
    if (isset($init['marker'])) {
        $marker = $init['marker'];
    }
    foreach ($init['updates'] ?? [] as $upd) {
        if (isset($upd['timestamp']) && $upd['timestamp'] > $marker) {
            $marker = $upd['timestamp'];
        }
    }
} catch (\Exception $e) {
    // Начнём с нуля — возможна обработка старых обновлений
}

// ── Цикл ожидания вложений ────────────────────────────────────────────────────

$received = [];
$deadline = time() + ATTACH_TIMEOUT;

while (count($received) < count($defs) && time() < $deadline) {
    try {
        $params   = $marker > 0 ? ['marker' => $marker] : [];
        $response = Bot::getUpdates([], $params);
    } catch (\Exception $e) {
        sleep(1);
        continue;
    }

    // Продвигаем маркер по ответу API
    if (isset($response['marker'])) {
        $marker = $response['marker'];
    }

    foreach ($response['updates'] ?? [] as $update) {
        // Обновляем маркер по timestamp каждого обновления
        if (isset($update['timestamp']) && $update['timestamp'] > $marker) {
            $marker = $update['timestamp'];
        }

        if (($update['update_type'] ?? '') !== 'message_created') {
            continue;
        }

        foreach ($update['message']['body']['attachments'] ?? [] as $attachment) {
            $type = $attachment['type'] ?? null;

            if (!$type || !isset($defs[$type]) || isset($received[$type])) {
                continue;
            }

            $result          = ($defs[$type]['handler'])($attachment);
            $received[$type] = $result;

            // ── Вывод в консоль ───────────────────────────────────────────────
            echo C_GREEN . "  ✓ " . C_RESET . $defs[$type]['label'] . "\n";
            echo C_DIM   . "    " . str_replace("\n", "\n    ", $result) . "\n" . C_RESET;
            $GLOBALS['_tests']['passed']++;

            // ── Ответ в чат ───────────────────────────────────────────────────
            Bot::sendMessageToChat(TEST_CHAT_ID, "[✓ {$type}]\n{$result}");
        }
    }

    if (empty($response['updates'])) {
        sleep(1);
    }
}

// ── Пропущенные типы (таймаут) ────────────────────────────────────────────────

foreach ($defs as $type => $def) {
    if (!isset($received[$type])) {
        echo C_YELLOW . "  ~ " . C_RESET . C_DIM . $def['label'] . C_RESET
            . C_YELLOW . " [таймаут]" . C_RESET . "\n";
        $GLOBALS['_tests']['skipped']++;
    }
}

// ── Итоговое сообщение в чат ──────────────────────────────────────────────────

$done  = count($received);
$total = count($defs);
Bot::sendMessageToChat(
    TEST_CHAT_ID,
    "[тест onAttachment завершён] получено {$done}/{$total} типов вложений."
);

test_summary();
