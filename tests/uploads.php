<?php
/**
 * Тест: Загрузка файлов и отправка медиа
 *
 * Запуск: php tests/uploads.php
 *
 * Два сценария получения токена:
 *
 *   image / file — uploadFile() возвращает только url;
 *                  токен выдаётся сервером после загрузки файла.
 *
 *   video / audio — uploadFile() сразу возвращает url И token;
 *                   файл всё равно загружается по url, но токен берётся
 *                   из первоначального ответа.
 *
 * Высокоуровневые методы (sendImageToChat, sendVideoToChat и др.) объединяют
 * оба шага в один вызов. Тест проверяет именно их.
 *
 * Требует: TEST_CHAT_ID в config.php, файлы в tests/assets/
 * Опционально: TEST_USER_ID для тестов личных сообщений
 */

require_once __DIR__ . '/helpers.php';

if (!TEST_CHAT_ID) {
    echo C_RED . "Ошибка: TEST_CHAT_ID не задан в config.php\n" . C_RESET;
    exit(1);
}

test_header('Загрузка файлов');

$assetsDir = __DIR__ . '/assets';
$sentMids  = [];

// ── Сценарий A: image / file ─────────────────────────────────────────────────
// uploadFile() возвращает только url. Токен выдаётся после загрузки файла.

echo C_DIM . "\n  Сценарий A: image/file — токен из ответа на загрузку файла\n\n" . C_RESET;

foreach (['image', 'file'] as $type) {
    test("uploadFile — получение upload URL для типа '$type' (без токена)", function () use ($type) {
        $result = Bot::uploadFile($type);
        assert_array($result, "ответ uploadFile('$type')");
        assert_key($result, 'url');
        assert_not_empty($result['url'], 'url');
        if (isset($result['token'])) {
            throw new \RuntimeException(
                "Для типа '$type' токен не должен присутствовать в ответе uploadFile() — "
                . 'он выдаётся только после загрузки файла'
            );
        }
    });
}

$imageFile = $assetsDir . '/test-image.png';

$mid = test('sendImageToChat — загрузка image и отправка в чат', function () use ($imageFile) {
    if (!file_exists($imageFile)) {
        skip_test('файл test-image.png не найден в tests/assets');
    }
    $result = Bot::sendImageToChat(TEST_CHAT_ID, $imageFile, '[тест] изображение в чат', 'image/png');
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
    echo C_DIM . "    mid: $mid\n" . C_RESET;
}

$mid = test('sendFileToChat — загрузка file и отправка в чат', function () use ($imageFile) {
    if (!file_exists($imageFile)) {
        skip_test('файл test-image.png не найден в tests/assets');
    }
    $result = Bot::sendFileToChat(TEST_CHAT_ID, $imageFile, '[тест] файл в чат', 'image/png');
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
    echo C_DIM . "    mid: $mid\n" . C_RESET;
}

// ── Сценарий B: video / audio ────────────────────────────────────────────────
// uploadFile() сразу возвращает url И token.

echo C_DIM . "\n  Сценарий B: video/audio — токен из ответа uploadFile(), до загрузки файла\n\n" . C_RESET;

foreach (['video', 'audio'] as $type) {
    test("uploadFile — получение upload URL + token для типа '$type'", function () use ($type) {
        $result = Bot::uploadFile($type);
        assert_array($result, "ответ uploadFile('$type')");
        assert_key($result, 'url');
        assert_not_empty($result['url'], 'url');
        assert_key($result, 'token');
        assert_not_empty($result['token'], 'token');
    });
}

$videoFile = $assetsDir . '/test-video.mp4';

$mid = test('sendVideoToChat — загрузка video и отправка в чат', function () use ($videoFile) {
    if (!file_exists($videoFile)) {
        skip_test('файл test-video.mp4 не найден в tests/assets');
    }
    $result = Bot::sendVideoToChat(TEST_CHAT_ID, $videoFile, '[тест] видео в чат', 'video/mp4');
    assert_key($result['message']['body'], 'mid');
    return $result['message']['body']['mid'];
});
if ($mid) {
    $sentMids[] = $mid;
    echo C_DIM . "    mid: $mid\n" . C_RESET;
}

// ── Отправка пользователю ────────────────────────────────────────────────────

if (TEST_USER_ID) {
    $mid = test('sendImageToUser — загрузка image и отправка пользователю', function () use ($imageFile) {
        if (!file_exists($imageFile)) {
            skip_test('файл test-image.png не найден в tests/assets');
        }
        $result = Bot::sendImageToUser(TEST_USER_ID, $imageFile, '[тест] изображение пользователю', 'image/png');
        assert_key($result['message']['body'], 'mid');
        return $result['message']['body']['mid'];
    });
    if ($mid) {
        $sentMids[] = $mid;
        echo C_DIM . "    mid: $mid\n" . C_RESET;
    }

    $mid = test('sendVideoToUser — загрузка video и отправка пользователю', function () use ($videoFile) {
        if (!file_exists($videoFile)) {
            skip_test('файл test-video.mp4 не найден в tests/assets');
        }
        $result = Bot::sendVideoToUser(TEST_USER_ID, $videoFile, '[тест] видео пользователю', 'video/mp4');
        assert_key($result['message']['body'], 'mid');
        return $result['message']['body']['mid'];
    });
    if ($mid) {
        $sentMids[] = $mid;
        echo C_DIM . "    mid: $mid\n" . C_RESET;
    }
} else {
    test('sendImageToUser — загрузка image и отправка пользователю', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
    test('sendVideoToUser — загрузка video и отправка пользователю', function () {
        skip_test('TEST_USER_ID не задан в config.php');
    });
}

// ── Очистка ──────────────────────────────────────────────────────────────────

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
