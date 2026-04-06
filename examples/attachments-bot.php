<?php
/**
 * Attachments Bot Example
 *
 * Demonstrates handling all incoming attachment types via onAttachment().
 *
 * Supported types and their data structure:
 *
 *   location         — $a['latitude'], $a['longitude']           (no payload)
 *   contact          — $a['payload']['vcf_info']
 *                      $a['payload']['max_info']['first_name|last_name|user_id']
 *   image            — $a['payload']['photo_id|token|url']
 *   video            — $a['payload']['url|token']
 *   audio            — $a['payload']['url|token']
 *   file             — $a['payload']['url|token'] + $a['filename'], $a['size']
 *   sticker          — $a['payload']['url|code']  + $a['width'],    $a['height']
 *   share            — $a['payload']['url']
 *
 * Commands:
 *   /request  — send a keyboard with requestGeoLocation and requestContact buttons
 *   /info     — show this help
 *
 * Run:
 *   export BOT_TOKEN=your_token
 *   php examples/attachments-bot.php
 */

require_once __DIR__ . '/../src/PHPMaxBot.php';

use PHPMaxBot\Helpers\Keyboard;

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("Please set BOT_TOKEN environment variable\n");
}

$bot = new PHPMaxBot($token);

Bot::setMyCommands([
    ['name' => 'request', 'description' => 'Request geo location and contact'],
    ['name' => 'info',    'description' => 'Show attachment handling info'],
]);

// ── /request — keyboard that asks user to share location and contact ──────────

$bot->command('request', function () {
    $keyboard = Keyboard::inlineKeyboard([
        [Keyboard::requestGeoLocation('Share location')],
        [Keyboard::requestContact('Share contact')],
    ]);

    return Bot::sendMessage(
        "Send me any attachment to see how it's handled:\n"
        . "• Press a button below for location or contact\n"
        . "• Send a photo, video, voice message, file, or sticker directly\n"
        . "• Share a link or post",
        ['attachments' => [$keyboard]]
    );
});

// ── /info ─────────────────────────────────────────────────────────────────────

$bot->command('info', function () {
    return Bot::sendMessage(
        "Handled attachment types:\n"
        . "location, contact, image, video, audio, file, sticker, share\n\n"
        . "Use /request to get a keyboard for location and contact."
    );
});

// ── location — direct fields, no payload sub-key ──────────────────────────────

$bot->onAttachment('location', function (array $attachment) {
    $lat = $attachment['latitude'];
    $lon = $attachment['longitude'];

    return Bot::sendMessage("📍 Location received\nlatitude: $lat\nlongitude: $lon");
});

// ── contact — data in payload.max_info and payload.vcf_info ──────────────────

$bot->onAttachment('contact', function (array $attachment) {
    $info      = $attachment['payload']['max_info'] ?? [];
    $firstName = $info['first_name'] ?? 'Unknown';
    $lastName  = $info['last_name']  ?? '';
    $userId    = $info['user_id']    ?? null;
    $name      = trim("$firstName $lastName");

    $text = "👤 Contact received\nname: $name";
    if ($userId) {
        $text .= "\nuser_id: $userId";
    }
    if (!empty($attachment['payload']['vcf_info'])) {
        $text .= "\nvCard: available";
    }

    return Bot::sendMessage($text);
});

// ── image — payload contains photo_id, token, url ────────────────────────────

$bot->onAttachment('image', function (array $attachment) {
    $payload  = $attachment['payload'] ?? [];
    $photoId  = $payload['photo_id'] ?? null;
    $url      = $payload['url']      ?? null;

    $text = "🖼 Image received";
    if ($photoId) {
        $text .= "\nphoto_id: $photoId";
    }
    if ($url) {
        $text .= "\nurl: $url";
    }

    return Bot::sendMessage($text);
});

// ── video — payload contains url and token ────────────────────────────────────

$bot->onAttachment('video', function (array $attachment) {
    $payload = $attachment['payload'] ?? [];
    $url     = $payload['url'] ?? null;

    $text = "🎬 Video received";
    if ($url) {
        $text .= "\nurl: $url";
    }

    return Bot::sendMessage($text);
});

// ── audio — payload contains url and token ────────────────────────────────────

$bot->onAttachment('audio', function (array $attachment) {
    $payload = $attachment['payload'] ?? [];
    $url     = $payload['url'] ?? null;

    $text = "🎙 Audio received";
    if ($url) {
        $text .= "\nurl: $url";
    }

    return Bot::sendMessage($text);
});

// ── file — payload has url/token; filename and size are direct fields ─────────

$bot->onAttachment('file', function (array $attachment) {
    $payload  = $attachment['payload'] ?? [];
    $filename = $attachment['filename'] ?? 'unknown';
    $size     = (int) ($attachment['size'] ?? 0);
    $url      = $payload['url'] ?? null;

    $text = "📄 File received\nfilename: $filename\nsize: $size bytes";
    if ($url) {
        $text .= "\nurl: $url";
    }

    return Bot::sendMessage($text);
});

// ── sticker — payload has url/code; width and height are direct fields ────────

$bot->onAttachment('sticker', function (array $attachment) {
    $payload = $attachment['payload'] ?? [];
    $code    = $payload['code']   ?? null;
    $url     = $payload['url']    ?? null;
    $width   = $attachment['width']  ?? null;
    $height  = $attachment['height'] ?? null;

    $text = "🎭 Sticker received";
    if ($code) {
        $text .= "\ncode: $code";
    }
    if ($width && $height) {
        $text .= "\nsize: {$width}×{$height}";
    }
    if ($url) {
        $text .= "\nurl: $url";
    }

    return Bot::sendMessage($text);
});

// ── share — payload contains url ─────────────────────────────────────────────

$bot->onAttachment('share', function (array $attachment) {
    $url = $attachment['payload']['url'] ?? null;

    $text = "🔗 Share received";
    if ($url) {
        $text .= "\nurl: $url";
    }

    return Bot::sendMessage($text);
});

// ── Start ─────────────────────────────────────────────────────────────────────

echo "Starting Attachments Bot...\n";
$bot->start();
