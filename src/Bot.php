<?php
/**
 * Bot.php
 *
 * @author GrayHoax <grayhoax@grayhoax.ru>
 * @link https://github.com/grayhoax/php-max-bot
 * @license GPL-3.0
 */

require_once __DIR__ . '/Exceptions/MaxBotException.php';
require_once __DIR__ . '/Exceptions/ApiException.php';

use PHPMaxBot\Exceptions\ApiException;
use PHPMaxBot\Exceptions\MaxBotException;

/**
 * Class Bot
 *
 * Static wrapper for MAX Bot API methods
 */
class Bot
{
    /**
     * Bot response debug
     *
     * @var string
     */
    public static $debug = '';

    /**
     * Base API URL
     *
     * @var string
     */
    private static $baseUrl = 'https://platform-api2.max.ru';

    /**
     * Send HTTP request to MAX API
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $query Query parameters
     * @return array|bool
     */
    public static function request($method = 'GET', $endpoint = '', $data = [], $query = [])
    {
        $url = self::$baseUrl . '/' . ltrim($endpoint, '/');

        // Add query parameters
        if (!empty($query)) {
            $queryString = http_build_query($query);
            $url .= '?' . $queryString;
        }

        $ch = curl_init();

        // Default options (can be overridden via PHPMaxBot::$curlOptions)
        $defaultOptions = [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        // Required options always override user-supplied ones
        $requiredOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . PHPMaxBot::$token,
                'Content-Type: application/json'
            ]
        ];

        // Merge order: defaults → user options → required (protected)
        $options = array_replace($defaultOptions, PHPMaxBot::$curlOptions, $requiredOptions);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno) {
            throw new MaxBotException(
                'cURL Error: ' . $curlError,
                $curlErrno,
                ['endpoint' => $endpoint, 'curl_error' => $curlError]
            );
        }

        if (PHPMaxBot::$debug && $endpoint != 'subscriptions') {
            self::$debug .= 'Method: ' . $method . ' ' . $endpoint . "\n";
            self::$debug .= 'HTTP Code: ' . $httpcode . "\n";
            self::$debug .= 'Response: ' . substr($result, 0, 500) . "\n";
        }

        // Parse JSON response
        $response = json_decode($result, true);
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new MaxBotException(
                'Error while parsing JSON response from MAX API',
                json_last_error(),
                [
                    'endpoint' => $endpoint,
                    'json_error' => json_last_error_msg(),
                    'response' => substr($result, 0, 200)
                ]
            );
        }

        // Handle platform's "empty response body" signal — treat as successful call with no data
        if (isset($response['code']) && $response['code'] === 'empty.response.body') {
            return [];
        }

        // Handle HTTP errors
        if ($httpcode == 401) {
            throw new ApiException(
                'Unauthorized: Invalid or missing token',
                401,
                'verify.token',
                'Invalid access_token',
                ['endpoint' => $endpoint]
            );
        }

        // Handle API errors
        if (isset($response['code']) && $httpcode >= 400) {
            $message = isset($response['message']) ? $response['message'] : 'Unknown error';
            $errorCode = isset($response['code']) ? $response['code'] : '';

            throw new ApiException(
                'MAX API Error: ' . $message,
                $httpcode,
                $errorCode,
                $message,
                ['endpoint' => $endpoint, 'data' => $data]
            );
        }

        return $response;
    }

    /**
     * Get bot information
     *
     * @return array
     */
    public static function getMyInfo()
    {
        return self::request('GET', 'me');
    }

    /**
     * Edit bot information
     *
     * @param array $data
     * @return array
     */
    public static function editMyInfo($data = [])
    {
        return self::request('PATCH', 'me', $data);
    }

    /**
     * Set bot commands
     *
     * @param array $commands
     * @return array
     */
    public static function setMyCommands($commands = [])
    {
        return self::editMyInfo(['commands' => $commands]);
    }

    /**
     * Delete bot commands
     *
     * @return array
     */
    public static function deleteMyCommands()
    {
        return self::editMyInfo(['commands' => []]);
    }

    /**
     * Get all chats
     *
     * @param array $params
     * @return array
     */
    public static function getAllChats($params = [])
    {
        return self::request('GET', 'chats', [], $params);
    }

    /**
     * Get chat by ID
     *
     * @param int $chatId
     * @return array
     */
    public static function getChat($chatId)
    {
        return self::request('GET', 'chats/' . $chatId);
    }

    /**
     * Get chat by link
     *
     * @param string $link
     * @return array
     */
    public static function getChatByLink($link)
    {
        return self::request('GET', 'chats/' . $link);
    }

    /**
     * Edit chat information
     *
     * @param int $chatId
     * @param array $data
     * @return array
     */
    public static function editChatInfo($chatId, $data = [])
    {
        return self::request('PATCH', 'chats/' . $chatId, $data);
    }

    /**
     * Delete chat
     *
     * @param int $chatId
     * @return array
     */
    public static function deleteChat($chatId)
    {
        return self::request('DELETE', 'chats/' . $chatId);
    }

    /**
     * Perform a message request, retrying while the API reports that an
     * attachment is still being processed.
     *
     * MAX accepts an upload before it has finished processing the file. A message
     * sent right after Bot::upload() is therefore rejected with HTTP 400
     * {"code":"attachment.not.ready"} and never appears in the chat — which looks
     * exactly like "the bot sent nothing". Retrying a moment later succeeds.
     *
     * Tuned via PHPMaxBot::$attachmentRetries and PHPMaxBot::$attachmentRetryDelay.
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data     Request body
     * @param array  $query    Query parameters
     * @return array|bool
     * @throws ApiException When the API keeps rejecting the attachment
     */
    private static function requestWithAttachmentRetry($method, $endpoint, $data = [], $query = [])
    {
        $maxAttempts = max(1, (int)PHPMaxBot::$attachmentRetries + 1);
        $baseDelay   = max(0, (int)PHPMaxBot::$attachmentRetryDelay);

        for ($attempt = 1; ; $attempt++) {
            try {
                return self::request($method, $endpoint, $data, $query);
            } catch (ApiException $e) {
                if ($e->getApiErrorCode() !== 'attachment.not.ready' || $attempt >= $maxAttempts) {
                    throw $e;
                }
                // Linear backoff: bigger files need more processing time.
                usleep($baseDelay * 1000 * $attempt);
            }
        }
    }

    /**
     * Attachment types that may accompany a 'file' attachment in one message.
     *
     * MAX rejects any message that carries a 'file' attachment together with
     * another media attachment, or with a second 'file'. Only an inline
     * keyboard is allowed alongside.
     *
     * @var array
     */
    private static $fileCompatibleTypes = ['inline_keyboard'];

    /**
     * Validate the attachment set against MAX's "one file per message" rule.
     *
     * MAX answers a violating message with HTTP 400
     * {"code":"proto.payload","message":"Must be only one file attachment in message"}.
     * Catching it here turns a silent delivery failure into a clear exception.
     *
     * Allowed:   1 file, or 1 file + inline_keyboard, or any mix of non-file media.
     * Rejected:  2+ files, or a file next to image/video/audio/location/share/contact.
     *
     * @param mixed $attachments Value of the 'attachments' message field
     * @return void
     * @throws MaxBotException When the combination would be rejected by the API
     */
    private static function assertAttachmentsCompatible($attachments)
    {
        if (!is_array($attachments) || empty($attachments)) {
            return;
        }

        $fileCount = 0;
        $conflicting = [];

        foreach ($attachments as $attachment) {
            $type = is_array($attachment) && isset($attachment['type']) ? $attachment['type'] : null;
            if ($type === null) {
                continue;
            }
            if ($type === 'file') {
                $fileCount++;
            } elseif (!in_array($type, self::$fileCompatibleTypes, true)) {
                $conflicting[] = $type;
            }
        }

        if ($fileCount === 0) {
            return;
        }

        if ($fileCount > 1) {
            throw new MaxBotException(
                'MAX allows only one attachment of type "file" per message, ' . $fileCount . ' given. '
                . 'Send each file as a separate message — see Bot::sendAttachmentsToChat().',
                0,
                ['file_count' => $fileCount]
            );
        }

        if (!empty($conflicting)) {
            throw new MaxBotException(
                'A message with a "file" attachment cannot carry other attachments ('
                . implode(', ', array_unique($conflicting)) . '). '
                . 'Only "inline_keyboard" is allowed alongside a file; send the rest as a separate '
                . 'message — see Bot::sendAttachmentsToChat().',
                0,
                ['conflicting_types' => array_values(array_unique($conflicting))]
            );
        }
    }

    /**
     * Send message to chat
     *
     * @param int $chatId
     * @param string $text
     * @param array $extra
     * @return array
     */
    public static function sendMessageToChat($chatId, $text, $extra = [])
    {
        $query = ['chat_id' => $chatId];
        if (isset($extra['disable_link_preview'])) {
            $query['disable_link_preview'] = $extra['disable_link_preview'];
            unset($extra['disable_link_preview']);
        }

        $format = PHPMaxBot::getFormat();
        if ($format !== false) {
            $extra['format'] = $format;
        }

        if (isset($extra['attachments'])) {
            self::assertAttachmentsCompatible($extra['attachments']);
        }

        $body = array_merge(['text' => $text], $extra);
        return self::requestWithAttachmentRetry('POST', 'messages', $body, $query);
    }

    /**
     * Send message to user
     *
     * @param int $userId
     * @param string $text
     * @param array $extra
     * @return array
     */
    public static function sendMessageToUser($userId, $text, $extra = [])
    {
        $query = ['user_id' => $userId];
        if (isset($extra['disable_link_preview'])) {
            $query['disable_link_preview'] = $extra['disable_link_preview'];
            unset($extra['disable_link_preview']);
        }

        $format = PHPMaxBot::getFormat();
        if ($format !== false) {
            $extra['format'] = $format;
        }

        if (isset($extra['attachments'])) {
            self::assertAttachmentsCompatible($extra['attachments']);
        }

        $body = array_merge(['text' => $text], $extra);
        return self::requestWithAttachmentRetry('POST', 'messages', $body, $query);
    }

    /**
     * Send message (auto-detect chat_id from update)
     *
     * @param string $text
     * @param array $extra
     * @return array
     */
    public static function sendMessage($text, $extra = [])
    {
        $update = PHPMaxBot::$currentUpdate;

        // Явно указан получатель через extra
        if (isset($extra['user_id'])) {
            $user_id = $extra['user_id'];
            unset($extra['user_id']);
            return self::sendMessageToUser($user_id, $text, $extra);
        }
        if (isset($extra['chat_id'])) {
            $chat_id = $extra['chat_id'];
            unset($extra['chat_id']);
            return self::sendMessageToChat($chat_id, $text, $extra);
        }

        // Информация описала в методе https://dev.max.ru/docs-api/methods/GET/updates
        // Групповой чат — отправляем в чат
        if (isset($update['message']['recipient']['chat_id'])) {
            return self::sendMessageToChat($update['message']['recipient']['chat_id'], $text, $extra);
        }
        if (isset($update['callback']['message']['recipient']['chat_id'])) {
            return self::sendMessageToChat($update['callback']['message']['recipient']['chat_id'], $text, $extra);
        }

        // Личный диалог — отправляем пользователю
        if (isset($update['message']['sender']['user_id'])) {
            return self::sendMessageToUser($update['message']['sender']['user_id'], $text, $extra);
        }
        if (isset($update['callback']['sender']['user_id'])) {
            return self::sendMessageToUser($update['callback']['sender']['user_id'], $text, $extra);
        }
        if (isset($update['user']['user_id'])) {
            return self::sendMessageToUser($update['user']['user_id'], $text, $extra);
        }
        if (isset($update['chat']['dialog_with_user']['user_id'])) {
            return self::sendMessageToUser($update['chat']['dialog_with_user']['user_id'], $text, $extra);
        }
        if (isset($update['user_id'])) {
            return self::sendMessageToUser($update['user_id'], $text, $extra);
        }

        throw new MaxBotException('Unable to determine recipient for message');
    }

    // ── Media send helpers ───────────────────────────────────────────────────

    /**
     * Upload a media file and send it to a chat in one call.
     *
     * Internally calls Bot::upload() then Bot::sendMessageToChat().
     * The token-source difference between image/file and video/audio is
     * handled automatically by Bot::upload().
     *
     * @param int         $chatId   Target chat ID
     * @param string      $type     Upload type: 'image', 'video', 'audio', 'file'
     * @param string      $filePath Local path to the file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters passed to sendMessageToChat()
     * @return array Sent message response
     */
    public static function sendMediaToChat($chatId, $type, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        $token = self::upload($type, $filePath, $mimeType);
        $extra['attachments'] = array_merge(
            [['type' => $type, 'payload' => ['token' => $token]]],
            $extra['attachments'] ?? []
        );
        return self::sendMessageToChat($chatId, $caption, $extra);
    }

    /**
     * Upload a media file and send it to a user in one call.
     *
     * @param int         $userId   Target user ID
     * @param string      $type     Upload type: 'image', 'video', 'audio', 'file'
     * @param string      $filePath Local path to the file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters passed to sendMessageToUser()
     * @return array Sent message response
     */
    public static function sendMediaToUser($userId, $type, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        $token = self::upload($type, $filePath, $mimeType);
        $extra['attachments'] = array_merge(
            [['type' => $type, 'payload' => ['token' => $token]]],
            $extra['attachments'] ?? []
        );
        return self::sendMessageToUser($userId, $caption, $extra);
    }

    /**
     * Upload an image and send it to a chat.
     *
     * @param int         $chatId   Target chat ID
     * @param string      $filePath Local path to the image file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendImageToChat($chatId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToChat($chatId, 'image', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload an image and send it to a user.
     *
     * @param int         $userId   Target user ID
     * @param string      $filePath Local path to the image file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendImageToUser($userId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToUser($userId, 'image', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload a video and send it to a chat.
     *
     * @param int         $chatId   Target chat ID
     * @param string      $filePath Local path to the video file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendVideoToChat($chatId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToChat($chatId, 'video', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload a video and send it to a user.
     *
     * @param int         $userId   Target user ID
     * @param string      $filePath Local path to the video file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendVideoToUser($userId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToUser($userId, 'video', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload an audio file and send it to a chat.
     *
     * @param int         $chatId   Target chat ID
     * @param string      $filePath Local path to the audio file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendAudioToChat($chatId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToChat($chatId, 'audio', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload an audio file and send it to a user.
     *
     * @param int         $userId   Target user ID
     * @param string      $filePath Local path to the audio file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendAudioToUser($userId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToUser($userId, 'audio', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload a document/file and send it to a chat.
     *
     * @param int         $chatId   Target chat ID
     * @param string      $filePath Local path to the file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendFileToChat($chatId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToChat($chatId, 'file', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload a document/file and send it to a user.
     *
     * @param int         $userId   Target user ID
     * @param string      $filePath Local path to the file
     * @param string      $caption  Optional message caption
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @param array       $extra    Additional parameters
     * @return array Sent message response
     */
    public static function sendFileToUser($userId, $filePath, $caption = '', $mimeType = null, $extra = [])
    {
        return self::sendMediaToUser($userId, 'file', $filePath, $caption, $mimeType, $extra);
    }

    /**
     * Upload several attachments and deliver them as a valid sequence of messages.
     *
     * MAX allows only one "file" attachment per message and forbids mixing it with
     * any other media, so a mixed batch cannot travel in a single message. This
     * helper splits the batch the way the API expects:
     *   - every non-file attachment (image/video/audio) goes into ONE message;
     *   - every file goes into a message of its own, in the given order.
     *
     * The caption is placed on the first message; $extra["attachments"] (typically
     * an inline keyboard) is placed on the last one. The remaining $extra keys are
     * applied to every message.
     *
     * Usage:
     *   Bot::sendAttachmentsToChat($chatId, [
     *       ["type" => "image", "path" => "/path/to/photo.jpg"],
     *       ["type" => "file",  "path" => "/path/to/doc.pdf"],
     *       ["type" => "file",  "token" => $alreadyUploadedToken],
     *   ], "Caption");
     *
     * @param int    $chatId  Target chat ID
     * @param array  $items   List of ["type" => ..., "path" => ...] or ["type" => ..., "token" => ...]
     *                        items; "mime" is optional and only used with "path"
     * @param string $caption Caption for the first message
     * @param array  $extra   Additional parameters passed to sendMessageToChat()
     * @return array List of API responses, one per sent message
     */
    public static function sendAttachmentsToChat($chatId, $items, $caption = '', $extra = [])
    {
        return self::sendAttachments(['chat_id' => $chatId], $items, $caption, $extra);
    }

    /**
     * Upload several attachments and deliver them to a user as a valid sequence
     * of messages. See sendAttachmentsToChat() for the splitting rules.
     *
     * @param int    $userId  Target user ID
     * @param array  $items   List of attachment descriptors
     * @param string $caption Caption for the first message
     * @param array  $extra   Additional parameters passed to sendMessageToUser()
     * @return array List of API responses, one per sent message
     */
    public static function sendAttachmentsToUser($userId, $items, $caption = '', $extra = [])
    {
        return self::sendAttachments(['user_id' => $userId], $items, $caption, $extra);
    }

    /**
     * Shared implementation for sendAttachmentsToChat() / sendAttachmentsToUser().
     *
     * @param array  $recipient Either ["chat_id" => int] or ["user_id" => int]
     * @param array  $items     List of attachment descriptors
     * @param string $caption   Caption for the first message
     * @param array  $extra     Additional message parameters
     * @return array List of API responses
     * @throws MaxBotException On a malformed descriptor or an empty batch
     */
    private static function sendAttachments($recipient, $items, $caption, $extra)
    {
        $group = [];
        $files = [];

        foreach ((array)$items as $index => $item) {
            if (!is_array($item) || empty($item['type'])) {
                throw new MaxBotException("Attachment item #$index must be an array with a 'type' key");
            }

            if (isset($item['token'])) {
                $token = $item['token'];
            } elseif (isset($item['path'])) {
                $token = self::upload($item['type'], $item['path'], $item['mime'] ?? null);
            } else {
                throw new MaxBotException("Attachment item #$index must define either 'path' or 'token'");
            }

            $attachment = ['type' => $item['type'], 'payload' => ['token' => $token]];

            if ($item['type'] === 'file') {
                $files[] = $attachment;
            } else {
                $group[] = $attachment;
            }
        }

        // One message for all non-file media, then one message per file.
        $queue = [];
        if (!empty($group)) {
            $queue[] = $group;
        }
        foreach ($files as $file) {
            $queue[] = [$file];
        }

        if (empty($queue)) {
            throw new MaxBotException('No attachments to send');
        }

        $trailing = $extra['attachments'] ?? [];
        unset($extra['attachments']);

        $responses = [];
        $lastIndex = count($queue) - 1;

        foreach ($queue as $index => $attachments) {
            $messageExtra = $extra;
            $messageExtra['attachments'] = $index === $lastIndex
                ? array_merge($attachments, $trailing)
                : $attachments;

            $text = $index === 0 ? $caption : '';

            $responses[] = isset($recipient['chat_id'])
                ? self::sendMessageToChat($recipient['chat_id'], $text, $messageExtra)
                : self::sendMessageToUser($recipient['user_id'], $text, $messageExtra);
        }

        return $responses;
    }

    /**
     * Get messages
     *
     * @param int $chatId
     * @param array $params
     * @return array
     */
    public static function getMessages($chatId, $params = [])
    {
        $query = array_merge(['chat_id' => $chatId], $params);
        if (isset($params['message_ids']) && is_array($params['message_ids'])) {
            $query['message_ids'] = implode(',', $params['message_ids']);
        }
        return self::request('GET', 'messages', [], $query);
    }

    /**
     * Get message by ID
     *
     * @param string $messageId
     * @return array
     */
    public static function getMessage($messageId)
    {
        return self::request('GET', 'messages/' . $messageId);
    }

    /**
     * Edit message
     *
     * @param string $messageId
     * @param array $data
     * @return array
     */
    public static function editMessage($messageId, $data = [])
    {
        if (isset($data['attachments'])) {
            self::assertAttachmentsCompatible($data['attachments']);
        }

        return self::requestWithAttachmentRetry('PUT', 'messages', $data, ['message_id' => $messageId]);
    }

    /**
     * Delete message
     *
     * @param string $messageId
     * @return array
     */
    public static function deleteMessage($messageId)
    {
        return self::request('DELETE', 'messages', [], ['message_id' => $messageId]);
    }

    /**
     * Answer on callback
     *
     * @param string $callbackId
     * @param array $data
     * @return array
     */
    public static function answerOnCallback($callbackId, $data = [])
    {
        return self::request('POST', 'answers', $data, ['callback_id' => $callbackId]);
    }

    /**
     * Get chat membership
     *
     * @param int $chatId
     * @return array
     */
    public static function getChatMembership($chatId)
    {
        return self::request('GET', 'chats/' . $chatId . '/members/me');
    }

    /**
     * Get chat admins
     *
     * @param int $chatId
     * @return array
     */
    public static function getChatAdmins($chatId)
    {
        return self::request('GET', 'chats/' . $chatId . '/members/admins');
    }

    /**
     * Add chat admin
     *
     * @param int $chatId
     * @param int $userId
     * @return array
     */
    public static function addChatAdmin($chatId, $userId, $permissions = [])
    {
        return self::request('POST', 'chats/' . $chatId . '/members/admins', [
            'admins' => [
                ['user_id' => $userId, 'permissions' => $permissions],
            ],
        ]);
    }

    /**
     * Remove chat admin
     *
     * @param int $chatId
     * @param int $userId
     * @return array
     */
    public static function removeChatAdmin($chatId, $userId)
    {
        return self::request('DELETE', 'chats/' . $chatId . '/members/admins/' . $userId);
    }

    /**
     * Add chat members
     *
     * @param int $chatId
     * @param array $userIds
     * @return array
     */
    public static function addChatMembers($chatId, $userIds)
    {
        return self::request('POST', 'chats/' . $chatId . '/members', ['user_ids' => $userIds]);
    }

    /**
     * Get chat members
     *
     * @param int $chatId
     * @param array $params
     * @return array
     */
    public static function getChatMembers($chatId, $params = [])
    {
        $query = $params;
        if (isset($params['user_ids']) && is_array($params['user_ids'])) {
            $query['user_ids'] = implode(',', $params['user_ids']);
        }
        return self::request('GET', 'chats/' . $chatId . '/members', [], $query);
    }

    /**
     * Remove chat member
     *
     * @param int $chatId
     * @param int $userId
     * @return array
     */
    public static function removeChatMember($chatId, $userId)
    {
        return self::request('DELETE', 'chats/' . $chatId . '/members', [], ['user_id' => $userId]);
    }

    /**
     * Get video information
     *
     * @param string $videoToken
     * @return array
     */
    public static function getVideo($videoToken)
    {
        return self::request('GET', 'videos/' . $videoToken);
    }

    /**
     * Get the list of active webhook subscriptions.
     *
     * @return array
     */
    public static function getSubscriptions()
    {
        return self::request('GET', 'subscriptions');
    }

    /**
     * Create or update a webhook subscription.
     *
     * Calling this method with a URL that is already subscribed replaces its
     * settings (event list) — use it both to create a new subscription and to
     * update an existing one.
     *
     * Requirements (effective 2026-05-25):
     *   - URL MUST use HTTPS. HTTP webhooks are no longer accepted.
     *   - The TLS certificate MUST be issued by a trusted CA. Self-signed
     *     certificates are no longer accepted.
     *
     * @param string $url   Webhook URL — HTTPS with a trusted-CA certificate.
     * @param array  $types Update types to subscribe to. Empty = all types.
     * @return array
     */
    public static function createSubscription($url, $types = [])
    {
        $data = ['url' => $url];
        if (!empty($types)) {
            $data['update_types'] = $types;
        }
        return self::request('POST', 'subscriptions', $data);
    }

    /**
     * Delete a webhook subscription.
     *
     * @param string $url Webhook URL to remove.
     * @return array
     */
    public static function deleteSubscription($url)
    {
        return self::request('DELETE', 'subscriptions', [], ['url' => $url]);
    }

    /**
     * Request an upload slot for a given file type.
     *
     * Behaviour differs by type:
     *   - image / file  → response contains only 'url'; token is issued after the
     *                     file is actually uploaded via uploadFileToUrl().
     *   - video / audio → response contains both 'url' and 'token'; the token is
     *                     pre-assigned before upload and must be used in the
     *                     message attachment.
     *
     * @param string $type File type: 'image', 'video', 'audio', 'file'
     * @return array  Keys: 'url' (always), 'token' (video/audio only)
     */
    public static function uploadFile($type)
    {
        return self::request('POST', 'uploads', [], ['type' => $type]);
    }

    /**
     * Upload a local file in one step and return the attachment token.
     *
     * Token source differs by type:
     *   - image / file  → token is returned inside the upload-URL response body.
     *   - video / audio → token is pre-assigned by uploadFile(); the file is still
     *                     transferred to the upload URL to finalise the slot.
     *
     * Usage:
     *   $token = Bot::upload('image', '/path/to/photo.jpg');
     *   Bot::sendMessageToChat($chatId, 'Photo', [
     *       'attachments' => [['type' => 'image', 'payload' => ['token' => $token]]]
     *   ]);
     *
     * @param string      $type     Upload type: 'image', 'video', 'audio', 'file'
     * @param string      $filePath Local path to the file
     * @param string|null $mimeType MIME type (auto-detected when null)
     * @return string Attachment token
     */
    public static function upload($type, $filePath, $mimeType = null)
    {
        $uploadInfo = self::uploadFile($type);

        if (empty($uploadInfo['url'])) {
            throw new MaxBotException("Upload URL not found in uploadFile() response for type '$type'");
        }

        if (in_array($type, ['video', 'audio'], true)) {
            // Token is pre-assigned; upload the file to finalise the slot.
            if (empty($uploadInfo['token'])) {
                throw new MaxBotException("Token not found in uploadFile() response for type '$type'");
            }
            self::uploadFileToUrl($uploadInfo['url'], $filePath, $mimeType);
            return $uploadInfo['token'];
        }

        // image / file: token is issued after the actual upload.
        $uploaded = self::uploadFileToUrl($uploadInfo['url'], $filePath, $mimeType);

        if (empty($uploaded['token'])) {
            throw new MaxBotException(
                "Token not found in upload response for type '$type'. "
                . 'Response: ' . json_encode($uploaded, JSON_UNESCAPED_UNICODE)
            );
        }

        return $uploaded['token'];
    }

    /**
     * Upload file content to the URL obtained from uploadFile().
     *
     * For image/file types this method returns the response that contains the
     * attachment token.  For video/audio the token was already returned by
     * uploadFile(), so the return value of this method is not needed in normal
     * usage (use Bot::upload() instead).
     *
     * @param string      $uploadUrl Upload URL from uploadFile()
     * @param string      $filePath  Local path to the file
     * @param string|null $mimeType  MIME type (auto-detected when null)
     * @return array Raw upload response; guaranteed to have key 'token' if found
     */
    public static function uploadFileToUrl($uploadUrl, $filePath, $mimeType = null)
    {
        if (!file_exists($filePath)) {
            throw new MaxBotException("File not found: $filePath");
        }

        if ($mimeType === null) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeMap = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif',  'webp' => 'image/webp', 'bmp' => 'image/bmp',
                'mp4' => 'video/mp4',  'avi' => 'video/avi',   'mov' => 'video/quicktime',
                'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg',   'wav' => 'audio/wav',
                'aac' => 'audio/aac',  'pdf' => 'application/pdf',
            ];
            $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';
        }
        $data_payload = ['data' => new CURLFile($filePath, $mimeType, basename($filePath)) ];

        $ch = curl_init();
        $options = array_replace([
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ], PHPMaxBot::$curlOptions, [
            CURLOPT_URL            => $uploadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $data_payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: multipart/form-data', 'Authorization: ' . PHPMaxBot::$token],
        ]);
        curl_setopt_array($ch, $options);

        $result    = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno) {
            throw new MaxBotException('cURL Error: ' . $curlError, $curlErrno);
        }

        // For video/audio the upload server returns a non-JSON success marker
        // (e.g. "<retval>1</retval>"). That is expected: the token for those
        // types was already provided by uploadFile(), not by this response.
        // For image/file the server returns JSON containing the token.
        $response = json_decode($result, true);
        if ($response === null) {
            return [];
        }

        $token = null;

        array_walk_recursive($response, function ($value, $key) use (&$token) {
            if ($key === 'token' && empty($token)) {
                $token = (string)$value;
            }
        });

        return array_merge($response, ['token' => $token]);
    }

    /**
     * Get updates (long polling).
     *
     * Long polling is rate-limited and events have a limited server-side
     * lifetime — it is intended for local development only. For staging and
     * production, use webhook subscriptions via createSubscription().
     *
     * @param array $types  Update types
     * @param array $params Additional parameters
     * @return array
     */
    public static function getUpdates($types = [], $params = [])
    {
        $query = $params;
        if (!empty($types)) {
            $query['types'] = is_array($types) ? implode(',', $types) : $types;
        }
        return self::request('GET', 'updates', [], $query);
    }

    /**
     * Get pinned message
     *
     * @param int $chatId
     * @return array
     */
    public static function getPinnedMessage($chatId)
    {
        return self::request('GET', 'chats/' . $chatId . '/pin');
    }

    /**
     * Pin message
     *
     * @param int $chatId
     * @param string $messageId
     * @param array $data
     * @return array
     */
    public static function pinMessage($chatId, $messageId, $data = [])
    {
        $body = array_merge(['message_id' => $messageId], $data);
        return self::request('PUT', 'chats/' . $chatId . '/pin', $body);
    }

    /**
     * Unpin message
     *
     * @param int $chatId
     * @return array
     */
    public static function unpinMessage($chatId)
    {
        return self::request('DELETE', 'chats/' . $chatId . '/pin');
    }

    /**
     * Send action (typing, etc.)
     *
     * @param int $chatId
     * @param string $action
     * @return array
     */
    public static function sendAction($chatId, $action)
    {
        return self::request('POST', 'chats/' . $chatId . '/actions', ['action' => $action]);
    }

    /**
     * Leave chat
     *
     * @param int $chatId
     * @return array
     */
    public static function leaveChat($chatId)
    {
        return self::request('DELETE', 'chats/' . $chatId . '/members/me');
    }

    /**
     * Get update type
     *
     * @return string|null
     */
    public static function type()
    {
        $update = PHPMaxBot::$currentUpdate;
        if (isset($update['update_type'])) {
            return $update['update_type'];
        }
        return null;
    }

    /**
     * Get message text
     *
     * @return string|null
     */
    public static function getText()
    {
        $update = PHPMaxBot::$currentUpdate;
        if (isset($update['message']['text'])) {
            return $update['message']['text'];
        }
        return null;
    }

    /**
     * Get callback data
     *
     * @return string|null
     */
    public static function getCallbackData()
    {
        $update = PHPMaxBot::$currentUpdate;
        if (isset($update['callback']['payload'])) {
            return $update['callback']['payload'];
        }
        return null;
    }

    /**
     * Get user contact data data
     *
     * @return array
     * @author Дмитрий А. Морозов <dmitrij.morozov@office.partner-its.ru>
     */
    public static function getContact()
    {
        $update = PHPMaxBot::$currentUpdate;
        if (isset($update['message']['body']['attachments'])) {
            foreach ($update['message']['body']['attachments'] as $attachment) {
                if ($attachment['type'] == 'contact') {
                    return [
                        'vcard' => $attachment['payload']['vcf_info'],
                        'user_id' => $attachment['payload']['max_info']['user_id'],
                        'first_name' => $attachment['payload']['max_info']['first_name'],
                        'last_name' => $attachment['payload']['max_info']['last_name']
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Get sender data
     *
     * @return array|null
     * @author Дмитрий А. Морозов <dmitrij.morozov@office.partner-its.ru>
     */
    public static function getSender()
    {
        $update = PHPMaxBot::$currentUpdate;
        if (isset($update['message']['sender'])) {
            return $update['message']['sender'];
        } elseif ($update['user']) {
            return $update['user'];
        }
        return null;
    }
}
