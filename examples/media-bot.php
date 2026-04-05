<?php
/**
 * Media Bot Example
 *
 * Demonstrates sending images, video, audio and documents with one method call.
 *
 * High-level send methods (sendImageToChat, sendVideoToChat, etc.) handle the
 * full upload cycle internally — they obtain the upload URL, transfer the file
 * and send the resulting message in one call.
 *
 * Token-source differences by type (handled automatically):
 *   image / file  — token is issued after the file is transferred to the
 *                   upload URL.
 *   video / audio — token is pre-assigned by the /uploads endpoint BEFORE
 *                   the file is transferred; the file is still sent to the
 *                   upload URL to finalise the slot.
 */

require_once __DIR__ . '/../src/PHPMaxBot.php';

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("Please set BOT_TOKEN environment variable\n");
}

$bot = new PHPMaxBot($token);

Bot::setMyCommands([
    ['name' => 'photo',    'description' => 'Send a photo'],
    ['name' => 'video',    'description' => 'Send a video'],
    ['name' => 'audio',    'description' => 'Send an audio file'],
    ['name' => 'document', 'description' => 'Send a document'],
]);

// ── /photo ────────────────────────────────────────────────────────────────────

$bot->command('photo', function () {
    $file = __DIR__ . '/../tests/assets/test-image.png';

    // Uploads the file and sends the image to the current chat in one call.
    return Bot::sendImageToChat(
        PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'],
        $file,
        'Here is a photo!'          // caption (optional)
    );
});

// ── /video ────────────────────────────────────────────────────────────────────

$bot->command('video', function () {
    $file = __DIR__ . '/../tests/assets/test-video.mp4';

    return Bot::sendVideoToChat(
        PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'],
        $file,
        'Here is a video!'
    );
});

// ── /audio ────────────────────────────────────────────────────────────────────

$bot->command('audio', function () {
    // Replace with a real audio file path.
    $file = __DIR__ . '/../tests/assets/test-audio.mp3';

    if (!file_exists($file)) {
        return Bot::sendMessage('Audio file not found.');
    }

    return Bot::sendAudioToChat(
        PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'],
        $file,
        'Here is an audio file!'
    );
});

// ── /document ─────────────────────────────────────────────────────────────────

$bot->command('document', function () {
    $file = __DIR__ . '/../tests/assets/test-image.png';

    return Bot::sendFileToChat(
        PHPMaxBot::$currentUpdate['message']['recipient']['chat_id'],
        $file,
        'Here is a document!'
    );
});

// ── Example: send to a specific user ─────────────────────────────────────────
//
// $userId = 12345678;
// Bot::sendImageToUser($userId, '/path/to/photo.jpg', 'Caption');
// Bot::sendVideoToUser($userId, '/path/to/video.mp4', 'Caption');
// Bot::sendAudioToUser($userId, '/path/to/audio.mp3', 'Caption');
// Bot::sendFileToUser($userId,  '/path/to/doc.pdf',   'Caption');

// ── Example: generic sendMediaToChat ─────────────────────────────────────────
//
// $chatId = -73078707407377;
// Bot::sendMediaToChat($chatId, 'image', '/path/to/photo.jpg', 'Caption', 'image/jpeg');

// ── Example: manual two-step upload ──────────────────────────────────────────
//
// If you need the token before sending:
//   $token = Bot::upload('image', '/path/to/photo.jpg');
//   Bot::sendMessageToChat($chatId, 'Caption', [
//       'attachments' => [['type' => 'image', 'payload' => ['token' => $token]]],
//   ]);

echo "Starting Media Bot...\n";
$bot->start();
