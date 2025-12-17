<?php
/**
 * Bot.php
 *
 * @author GrayHoax <grayhoax@grayhoax.ru>
 * @link https://github.com/grayhoax/phpmaxbot
 * @license GPL-3.0
 */

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
    private static $baseUrl = 'https://platform-api.max.ru';

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
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => $method,
            //CURLOPT_VERBOSE => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . PHPMaxBot::$token,
                'Content-Type: application/json'
            ]
        ];

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

        $body = array_merge(['text' => $text], $extra);
        $response = self::request('POST', 'messages', $body, $query);
        return isset($response['message']) ? $response['message'] : $response;
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

        $body = array_merge(['text' => $text], $extra);
        $response = self::request('POST', 'messages', $body, $query);
        return isset($response['message']) ? $response['message'] : $response;
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
        // Информация описала в методе https://dev.max.ru/docs-api/methods/GET/updates
        if (isset($update['message']['sender']['user_id'])) {
            return self::sendMessageToUser($update['message']['sender']['user_id'], $text, $extra);
        } elseif (isset($update['callback']['sender']['user_id'])) {
            return self::sendMessageToUser($update['callback']['sender']['user_id'], $text, $extra);
        } elseif (isset($update['user']['user_id'])) {
            return self::sendMessageToUser($update['user']['user_id'], $text, $extra);
        } elseif (isset($update['chat']['dialog_with_user']['user_id'])) {
            return self::sendMessageToUser($update['chat']['dialog_with_user']['user_id'], $text, $extra);
        } elseif (isset($update['user_id'])) {
            return self::sendMessageToUser($update['user_id'], $text, $extra);
        } elseif (isset($extra['user_id'])) {
            $user_id = $extra['user_id'];
            unset($extra['user_id']);
            return self::sendMessageToUser($user_id, $text, $extra);
        }

        throw new MaxBotException('Unable to determine recipient for message');
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
        return self::request('PUT', 'messages', $data, ['message_id' => $messageId]);
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
        return self::request('DELETE', 'chats/' . $chatId . '/members', ['user_id' => $userId]);
    }

    /**
     * Get updates (long polling)
     *
     * @param array $types Update types
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
}
