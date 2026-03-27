<?php
/**
 * Тест: Загрузка файлов
 *
 * Запуск: php tests/uploads.php
 *
 * Проверяет: uploadFile — получение URL для загрузки файла каждого типа.
 * Примечание: проверяется только получение upload URL.
 *             Сама загрузка файла по полученному URL не выполняется.
 */

require_once __DIR__ . '/helpers.php';

test_header('Загрузка файлов');

foreach (['image', 'video', 'audio', 'file'] as $type) {
    test("uploadFile — получение upload URL для типа '$type'", function () use ($type) {
        $result = Bot::uploadFile($type);
        assert_array($result, "ответ uploadFile('$type')");
        assert_key($result, 'url');
        assert_not_empty($result['url'], 'url');
    });
}

test_summary();
