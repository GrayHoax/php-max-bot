<?php
/**
 * run_all.php — последовательный запуск всех тестов
 *
 * Запуск: php tests/run_all.php
 *
 * Каждый тест запускается как отдельный процесс.
 * Итог: количество файлов с ошибками.
 */

$files = [
    'updates.php',       // getUpdates — удобно запускать первым для диагностики
    'bot_info.php',      // getMyInfo, editMyInfo, setMyCommands
    'messages.php',      // sendMessage*, getMessage*, editMessage, deleteMessage
    'chats.php',         // getAllChats, getChat, editChatInfo, sendAction
    'members.php',       // getChatMembers, getChatAdmins, addChatAdmin, removeChatAdmin
    'keyboard.php',      // все типы кнопок
    'pins.php',          // pinMessage, getPinnedMessage, unpinMessage
    'subscriptions.php', // getSubscriptions
    'uploads.php',       // uploadFile
];

$failed  = 0;
$passed  = 0;
$php     = PHP_BINARY;
$testsDir = __DIR__;

foreach ($files as $file) {
    $path = $testsDir . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($path)) {
        echo "\033[33m  ~ $file — файл не найден, пропущен\033[0m\n";
        continue;
    }

    passthru("\"$php\" \"$path\"", $code);

    if ($code !== 0) {
        $failed++;
    } else {
        $passed++;
    }
}

$total = $passed + $failed;
echo "\n\033[1m══ Итог run_all: $total файлов\033[0m";
echo "  \033[32m✓ $passed\033[0m";
if ($failed > 0) {
    echo "  \033[31m✗ $failed\033[0m";
}
echo "\n\n";

exit($failed > 0 ? 1 : 0);
