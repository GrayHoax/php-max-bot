<?php
/**
 * helpers.php — общий bootstrap и утилиты для функциональных тестов PHPMaxBot
 *
 * Подключается в начале каждого тестового файла: require_once __DIR__ . '/helpers.php';
 */

// ── Загрузка конфига ────────────────────────────────────────────────────────

if (!defined('BOT_TOKEN')) {
    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        echo "\033[31mОшибка: файл tests/config.php не найден.\033[0m\n";
        echo "Скопируйте tests/config.example.php в tests/config.php и заполните параметры.\n";
        exit(1);
    }
    require_once $configFile;
}

if (!defined('TEST_CHAT_ID')) {
    define('TEST_CHAT_ID', 0);
}
if (!defined('TEST_USER_ID')) {
    define('TEST_USER_ID', 0);
}

// ── Загрузка библиотеки ─────────────────────────────────────────────────────

$_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_autoload)) {
    require_once $_autoload;
} else {
    require_once __DIR__ . '/../src/PHPMaxBot.php';
    if (!class_exists('PHPMaxBot\\Helpers\\Keyboard')) {
        require_once __DIR__ . '/../src/Helpers/Keyboard.php';
    }
}
unset($_autoload);

// Инициализируем токен
PHPMaxBot::$token = BOT_TOKEN;
PHPMaxBot::$debug = false;

// ── Цвета терминала ─────────────────────────────────────────────────────────

define('C_GREEN',  "\033[32m");
define('C_RED',    "\033[31m");
define('C_YELLOW', "\033[33m");
define('C_CYAN',   "\033[36m");
define('C_RESET',  "\033[0m");
define('C_BOLD',   "\033[1m");
define('C_DIM',    "\033[2m");

// ── Состояние тестов ────────────────────────────────────────────────────────

$GLOBALS['_tests'] = ['passed' => 0, 'failed' => 0, 'skipped' => 0];

// ── Вспомогательные функции ─────────────────────────────────────────────────

/**
 * Вывести заголовок группы тестов
 */
function test_header(string $title): void
{
    echo "\n" . C_BOLD . C_CYAN . "┌─ $title " . C_RESET . "\n\n";
}

/**
 * Выполнить один тест.
 * Callback может вернуть значение — оно передаётся как результат теста.
 *
 * @param string   $name Название теста
 * @param callable $fn   Тело теста
 * @return mixed         Возвращаемое значение callback или null при ошибке/пропуске
 */
function test(string $name, callable $fn)
{
    try {
        $result = $fn();
        echo C_GREEN . "  ✓ " . C_RESET . $name . "\n";
        $GLOBALS['_tests']['passed']++;
        return $result;
    } catch (SkipTestException $e) {
        $reason = $e->getMessage() !== '' ? " ({$e->getMessage()})" : '';
        echo C_YELLOW . "  ~ " . C_RESET . C_DIM . $name . C_RESET
            . C_YELLOW . " [пропущен$reason]" . C_RESET . "\n";
        $GLOBALS['_tests']['skipped']++;
        return null;
    } catch (\Throwable $e) {
        echo C_RED . "  ✗ " . C_RESET . $name . "\n";
        echo C_RED . "    → " . $e->getMessage() . C_RESET . "\n";
        $GLOBALS['_tests']['failed']++;
        return null;
    }
}

/**
 * Пропустить тест (вызывается внутри callback теста)
 */
function skip_test(string $reason = ''): void
{
    throw new SkipTestException($reason);
}

/**
 * Проверить наличие ключа в массиве
 */
function assert_key(array $data, string $key): void
{
    if (!array_key_exists($key, $data)) {
        throw new \RuntimeException(
            "Ответ не содержит ключ '$key'. Получено: "
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}

/**
 * Проверить что значение не пустое
 */
function assert_not_empty($value, string $field = ''): void
{
    if (empty($value) && $value !== 0 && $value !== false) {
        $label = $field !== '' ? " '$field'" : '';
        throw new \RuntimeException("Значение{$label} не должно быть пустым");
    }
}

/**
 * Проверить что значение является массивом
 */
function assert_array($value, string $field = ''): void
{
    if (!is_array($value)) {
        $label = $field !== '' ? " '$field'" : '';
        throw new \RuntimeException(
            "Ожидался массив{$label}, получено: " . gettype($value)
            . ' — ' . json_encode($value, JSON_UNESCAPED_UNICODE)
        );
    }
}

/**
 * Проверить равенство двух значений
 */
function assert_equals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException(
            $message !== ''
                ? $message
                : 'Ожидалось: ' . json_encode($expected) . ', получено: ' . json_encode($actual)
        );
    }
}

/**
 * Вывести итоговую строку и завершить процесс
 */
function test_summary(): void
{
    $t     = $GLOBALS['_tests'];
    $total = $t['passed'] + $t['failed'] + $t['skipped'];

    echo "\n" . C_DIM . str_repeat('─', 50) . C_RESET . "\n";
    echo C_BOLD . "Итого: $total" . C_RESET;
    echo '  ' . C_GREEN  . "✓ {$t['passed']} пройдено" . C_RESET;
    if ($t['failed'] > 0) {
        echo '  ' . C_RED    . "✗ {$t['failed']} провалено" . C_RESET;
    }
    if ($t['skipped'] > 0) {
        echo '  ' . C_YELLOW . "~ {$t['skipped']} пропущено" . C_RESET;
    }
    echo "\n\n";

    exit($t['failed'] > 0 ? 1 : 0);
}

// ── Исключения ──────────────────────────────────────────────────────────────

class SkipTestException extends \RuntimeException {}
