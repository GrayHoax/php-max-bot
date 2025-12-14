<?php
/**
 * Keyboard Bot Example
 *
 * Advanced example demonstrating keyboard and button usage
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMaxBot\Helpers\Keyboard;

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("Please set BOT_TOKEN environment variable\n");
}

$bot = new PHPMaxBot($token);

// Set bot commands
Bot::setMyCommands([
    ['name' => 'callback', 'description' => 'Send callback keyboard'],
    ['name' => 'geoLocation', 'description' => 'Send geoLocation request'],
    ['name' => 'contact', 'description' => 'Send contact request'],
    ['name' => 'createChat', 'description' => 'Create chat']
]);

// Default keyboard for all examples
$defaultKeyboard = [
    [Keyboard::link('❤️', 'https://dev.max.ru/')],
    [Keyboard::callback('Remove message', 'remove_message', ['intent' => 'negative'])]
];

// Remove message action
$bot->action('remove_message', function() {
    $update = PHPMaxBot::$currentUpdate;
    $callbackId = $update['callback']['callback_id'];
    $messageId = $update['message']['body']['mid'] ?? null;

    if ($messageId) {
        $result = Bot::deleteMessage($messageId);
        $success = $result['success'] ?? false;

        Bot::answerOnCallback($callbackId, [
            'notification' => $success
                ? 'Successfully removed message'
                : 'Failed to remove message'
        ]);
    }
});

/* Callback keyboard */

$bot->command('callback', function() use ($defaultKeyboard) {
    $callbackKeyboard = Keyboard::inlineKeyboard([
        [
            Keyboard::callback('default', 'color:default'),
            Keyboard::callback('positive', 'color:positive', ['intent' => 'positive']),
            Keyboard::callback('negative', 'color:negative', ['intent' => 'negative'])
        ],
        ...$defaultKeyboard
    ]);

    return Bot::sendMessage('Callback keyboard', [
        'attachments' => [$callbackKeyboard]
    ]);
});

$bot->action('color:(.+)', function($matches) {
    $update = PHPMaxBot::$currentUpdate;
    $callbackId = $update['callback']['callback_id'];
    $color = $matches[1];

    return Bot::answerOnCallback($callbackId, [
        'message' => [
            'text' => "Your choice: $color color",
            'attachments' => []
        ]
    ]);
});

/* GeoLocation keyboard */

$bot->command('geoLocation', function() use ($defaultKeyboard) {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestGeoLocation('Send geoLocation')],
        ...$defaultKeyboard
    ]);

    return Bot::sendMessage('GeoLocation keyboard', [
        'attachments' => [$keyboard]
    ]);
});

$bot->on('message_created', function() {
    $update = PHPMaxBot::$currentUpdate;

    if (isset($update['message']['location'])) {
        $location = $update['message']['location'];
        $lat = $location['latitude'];
        $lon = $location['longitude'];
        return Bot::sendMessage("Your location: $lat, $lon");
    } else {
        return false;
    }
});

/* Contact keyboard */

$bot->command('contact', function() use ($defaultKeyboard) {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestContact('Send my contact')],
        ...$defaultKeyboard
    ]);

    return Bot::sendMessage('Contact keyboard', [
        'attachments' => [$keyboard]
    ]);
});

$bot->on('message_created', function() {
    $update = PHPMaxBot::$currentUpdate;

    if (isset($update['message']['contact_info'])) {
        $contact = $update['message']['contact_info'];
        $name = $contact['full_name'] ?? 'Unknown';
        $phone = $contact['tel'] ?? 'Unknown';
        return Bot::sendMessage("Your name: $name\nYour phone: $phone");
    } else {
        return false;
    }
});

/* CreateChat keyboard */

$bot->command('createChat', function($param) {
    if (empty(trim($param))) {
        return Bot::sendMessage('Enter chat title after command');
    }

    $chatTitle = trim($param);
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::chat("Create chat \"$chatTitle\"", $chatTitle)]
    ]);

    return Bot::sendMessage('Create chat keyboard', [
        'attachments' => [$keyboard]
    ]);
});

// Start the bot
echo "Starting Keyboard Bot...\n";
$bot->start();
