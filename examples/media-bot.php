<?php
/**
 * Media Bot Example
 *
 * Demonstrates how to upload and send files (images, video, audio, documents).
 *
 * Upload token flow:
 *   image / file  — token is returned in the upload-URL response body AFTER
 *                   the file has been transferred.
 *   video / audio — token is pre-assigned by the /uploads endpoint BEFORE the
 *                   file is transferred; the file is still sent to the upload URL.
 *
 * Use Bot::upload($type, $path) — it handles both flows automatically.
 */

require_once __DIR__ . '/../src/PHPMaxBot.php';

use PHPMaxBot\Helpers\Keyboard;

$token = getenv('BOT_TOKEN');
if (!$token) {
    die("Please set BOT_TOKEN environment variable\n");
}

$bot = new PHPMaxBot($token);

Bot::setMyCommands([
    ['name' => 'photo',    'description' => 'Send a photo'],
    ['name' => 'video',    'description' => 'Send a video'],
    ['name' => 'document', 'description' => 'Send a document'],
]);

// ── /photo ────────────────────────────────────────────────────────────────────
// image type: token is issued after the file is uploaded.

$bot->command('photo', function () {
    $filePath = __DIR__ . '/../tests/assets/test-image.png';

    if (!file_exists($filePath)) {
        return Bot::sendMessage('File not found: ' . $filePath);
    }

    // Bot::upload() fetches the upload URL, transfers the file and returns the token.
    $token = Bot::upload('image', $filePath, 'image/png');

    return Bot::sendMessage('Here is your photo:', [
        'attachments' => [
            ['type' => 'image', 'payload' => ['token' => $token]],
        ],
    ]);
});

// ── /video ────────────────────────────────────────────────────────────────────
// video type: token is pre-assigned by uploadFile(); the file is still transferred.

$bot->command('video', function () {
    $filePath = __DIR__ . '/../tests/assets/test-video.mp4';

    if (!file_exists($filePath)) {
        return Bot::sendMessage('File not found: ' . $filePath);
    }

    // For video/audio Bot::upload() uses the token from the uploadFile() response,
    // while still sending the file to the upload URL to finalise the slot.
    $token = Bot::upload('video', $filePath, 'video/mp4');

    return Bot::sendMessage('Here is your video:', [
        'attachments' => [
            ['type' => 'video', 'payload' => ['token' => $token]],
        ],
    ]);
});

// ── /document ─────────────────────────────────────────────────────────────────
// file type: same flow as image — token is issued after upload.

$bot->command('document', function () {
    $filePath = __DIR__ . '/../tests/assets/test-image.png';

    if (!file_exists($filePath)) {
        return Bot::sendMessage('File not found: ' . $filePath);
    }

    $token = Bot::upload('file', $filePath, 'image/png');

    return Bot::sendMessage('Here is your document:', [
        'attachments' => [
            ['type' => 'file', 'payload' => ['token' => $token]],
        ],
    ]);
});

// ── Advanced: manual two-step upload ─────────────────────────────────────────
// If you need access to the raw upload responses, use the lower-level API:
//
//   $uploadInfo = Bot::uploadFile('image');      // step 1: get URL
//   $uploaded   = Bot::uploadFileToUrl(          // step 2: transfer file
//       $uploadInfo['url'],
//       '/path/to/file.jpg',
//       'image/jpeg'
//   );
//   $token = $uploaded['token'];                 // token from step 2 response
//
// For video/audio:
//   $uploadInfo = Bot::uploadFile('video');      // step 1: get URL + token
//   Bot::uploadFileToUrl($uploadInfo['url'], '/path/to/video.mp4', 'video/mp4');
//   $token = $uploadInfo['token'];               // token from step 1 response

echo "Starting Media Bot...\n";
$bot->start();
