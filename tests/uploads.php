<?php
/**
 * Тест: Загрузка файлов и отправка медиа
 *
 * Запуск: php tests/uploads.php
 *
 * Проверяет два сценария загрузки:
 *
 *   image / file — uploadFile() возвращает только url;
 *                  токен выдаётся в ответе на загрузку файла.
 *
 *   video / audio — uploadFile() сразу возвращает url И token;
 *                   файл всё равно загружается по url, но токен берётся
 *                   из первоначального ответа.
 *
 * Для каждого успешно загруженного файла отправляется тестовое сообщение
 * в чат (и пользователю, если задан TEST_USER_ID), затем оно удаляется.
 *
 * Требует: TEST_CHAT_ID в config.php, файлы в tests/assets/
 * Опционально: TEST_USER_ID для тестов личных сообщений
 */

require_once __DIR__ . '/helpers.php';

use PHPMaxBot\Exceptions\ApiException;

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Загрузка файлов');

$assetsDir = __DIR__ . '/assets';
$sentMids  = [];

// ── Сценарий A: image / file ─────────────────────────────────────────────────
// uploadFile() возвращает только url.
// Токен выдаётся сервером после реальной загрузки файла (uploadFileToUrl).

echo C_DIM . "\n  Сценарий A: image/file — токен из ответа на загрузку файла\n\n" . C_RESET;

// ── uploadFile — получение upload URL ───────────────────────────────────────

foreach (['image', 'file'] as $type) {
    test("uploadFile — получение upload URL для типа '$type'", function () use ($type) {
        $result = Bot::uploadFile($type);
        assert_array($result, "ответ uploadFile('$type')");
        assert_key($result, 'url');
        assert_not_empty($result['url'], 'url');
        // Для image/file токен в этом ответе отсутствует
        if (isset($result['token'])) {
            throw new \RuntimeException(
                "Для типа '$type' токен не должен присутствовать в ответе uploadFile(),"
                . ' он выдаётся только после загрузки файла'
            );
        }
    });
}

// ── Загрузка image → отправка в чат ─────────────────────────────────────────

$imageFile = $assetsDir . '/test-image.png';

$mid = test('upload image — загрузка и отправка в чат', function () use ($imageFile) {
    if (!file_exists($imageFile)) {
        skip_test('файл test-image.png не найден в tests/assets');
    }

    $token = Bot::upload('image', $imageFile, 'image/png');
    assert_not_empty($token, 'token');
    echo "\n" . C_DIM . "      token: $token" . C_RESET;

    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] изображение', [
        'attachments' => [['type' => 'image', 'payload' => ['token' => $token]]],
    ]);
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
    echo "\n" . C_DIM . "    mid: $mid\n" . C_RESET;
}

// ── Загрузка file → отправка в чат ──────────────────────────────────────────

$mid = test('upload file — загрузка и отправка в чат', function () use ($imageFile) {
    if (!file_exists($imageFile)) {
        skip_test('файл test-image.png не найден в tests/assets (используется как generic file)');
    }

    $token = Bot::upload('file', $imageFile, 'image/png');
    assert_not_empty($token, 'token');
    echo "\n" . C_DIM . "      token: $token" . C_RESET;

    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] файл', [
        'attachments' => [['type' => 'file', 'payload' => ['token' => $token]]],
    ]);
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
    echo "\n" . C_DIM . "    mid: $mid\n" . C_RESET;
}

// ── Сценарий B: video / audio ────────────────────────────────────────────────
// uploadFile() возвращает url И token одновременно.
// Файл загружается по url, но токен для вложения берётся из первого ответа.

echo C_DIM . "\n  Сценарий B: video/audio — токен из ответа uploadFile(), до загрузки файла\n\n" . C_RESET;

// ── uploadFile — получение upload URL + token ────────────────────────────────

foreach (['video', 'audio'] as $type) {
    test("uploadFile — получение upload URL + token для типа '$type'", function () use ($type) {
        $result = Bot::uploadFile($type);
        assert_array($result, "ответ uploadFile('$type')");
        assert_key($result, 'url');
        assert_not_empty($result['url'], 'url');
        // Для video/audio токен должен присутствовать уже здесь
        assert_key($result, 'token');
        assert_not_empty($result['token'], 'token');
    });
}

// ── Загрузка video → отправка в чат ─────────────────────────────────────────

$videoFile = $assetsDir . '/test-video.mp4';

$mid = test('upload video — загрузка и отправка в чат', function () use ($videoFile) {
    if (!file_exists($videoFile)) {
        skip_test('файл test-video.mp4 не найден в tests/assets');
    }

    $token = Bot::upload('video', $videoFile, 'video/mp4');
    assert_not_empty($token, 'token');
    echo "\n" . C_DIM . "      token: $token" . C_RESET;

    $result = Bot::sendMessageToChat(TEST_CHAT_ID, '[тест] видео', [
        'attachments' => [['type' => 'video', 'payload' => ['token' => $token]]],
    ]);
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
    echo "\n" . C_DIM . "    mid: $mid\n" . C_RESET;
}

// ── Отправка пользователю ────────────────────────────────────────────────────

if (TEST_USER_ID) {
    $mid = test('upload image — загрузка и отправка пользователю', function () use ($imageFile) {
        if (!file_exists($imageFile)) {
            skip_test('файл test-image.png не найден в tests/assets');
        }

        $token = Bot::upload('image', $imageFile, 'image/png');
        assert_not_empty($token, 'token');

        $result = Bot::sendMessageToUser(TEST_USER_ID, '[тест] изображение пользователю', [
            'attachments' => [['type' => 'image', 'payload' => ['token' => $token]]],
        ]);
        assert_key($result['message']['body'], 'mid');
        return $result['message']['body']['mid'];
    });
    if ($mid) {
        $sentMids[] = $mid;
        echo "\n" . C_DIM . "    mid: $mid\n" . C_RESET;
    }
} else {
    test('upload image — загрузка и отправка пользователю', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
}

// ── Очистка отправленных сообщений ────────────────────────────────────────────

if (!empty($sentMids)) {
    echo "\n";
    foreach ($sentMids as $cleanMid) {
        test("deleteMessage — удаление тестового сообщения ($cleanMid)", function () use ($cleanMid) {
            $result = Bot::deleteMessage($cleanMid);
            assert_not_empty($result !== false, 'вызов deleteMessage');
        });
    }
}

test_summary();
