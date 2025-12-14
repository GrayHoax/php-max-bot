<?php
/**
 * Simple Bot Example
 *
 * Basic example showing command and event handling
 */

require_once __DIR__ . '/vendor/autoload.php';

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("Please set BOT_TOKEN environment variable\n");
}

$bot = new PHPMaxBot($token);

// Set commands
Bot::setMyCommands([
    ['name' => 'start', 'description' => 'Start the bot'],
    ['name' => 'help', 'description' => 'Show help'],
    ['name' => 'echo', 'description' => 'Echo your message']
]);

$bot->on('bot_started', function($text) {
    return Bot::sendMessage("Welcome my friend, your track parameter: {$text}\nLets start with command /start");
});

// Handle /start
$bot->command('start', function() {
    return Bot::sendMessage("Welcome! I'm a simple MAX bot.\nUse /help to see available commands.");
});

// Handle /help
$bot->command('help', function() {
    $helpText = "Available commands:\n";
    $helpText .= "/start - Start the bot\n";
    $helpText .= "/help - Show this help\n";
    $helpText .= "/echo <text> - Echo your text\n";

    return Bot::sendMessage($helpText);
});

// Handle /echo with parameter
$bot->command('echo', function($text) {
    if (empty($text)) {
        return Bot::sendMessage("Usage: /echo <your text>");
    }
    return Bot::sendMessage("You said: $text");
});

// Handle bot_started event
$bot->on('bot_started', function() {
    $update = PHPMaxBot::$currentUpdate;
    $userName = $update['user']['name'] ?? 'User';

    return Bot::sendMessage("Hello, $userName! Thanks for starting the bot. Use /help to see what I can do.");
});

// Handle regular messages
$bot->on('message_created', function() {
    $text = Bot::getText();

    // Only respond to non-command messages
    if ($text && strpos($text, '/') !== 0) {
        return Bot::sendMessage("I received your message. Use /help to see available commands.");
    } else {
        return false;
    }
});

// Start the bot
echo "Starting Simple Bot...\n";
$bot->start();
