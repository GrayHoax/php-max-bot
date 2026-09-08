<?php
/**
 * PHPMaxBot.php
 *
 * @author GrayHoax <grayhoax@grayhoax.ru>
 * @link https://github.com/grayhoax/php-max-bot
 * @license GPL-3.0
 */

/**
 * Class PHPMaxBot
 *
 * Main class for MAX messenger bot framework
 */
class PHPMaxBot
{
    /**
     * Current update data
     *
     * @var array
     */
    public static $currentUpdate = [];

    /**
     * Command handlers
     *
     * @var array
     */
    protected $_command = [];

    /**
     * Event handlers
     *
     * @var array
     */
    protected $_onEvent = [];

    /**
     * Action (callback) handlers
     *
     * @var array
     */
    protected $_onAction = [];

    /**
     * Attachment handlers (indexed by attachment type)
     *
     * @var array
     */
    protected $_onAttachment = [];

    /**
     * Bot token
     *
     * @var string
     */
    public static $token = '';

    /**
     * Debug mode
     *
     * @var bool
     */
    public static $debug = true;

    /**
     * Custom cURL options applied to every request.
     * Use cURL constants as keys (e.g. CURLOPT_TIMEOUT => 30).
     * Protected options (URL, RETURNTRANSFER, CUSTOMREQUEST, HTTPHEADER, POSTFIELDS)
     * cannot be overridden and are always set by the library.
     * Default SSL options (CURLOPT_SSL_VERIFYHOST, CURLOPT_SSL_VERIFYPEER) CAN be
     * overridden here.
     *
     * @var array
     */
    public static $curlOptions = [];

    /**
     * Custom error logger, called as $errorHandler($message, $exception).
     * When null, errors are written with error_log().
     *
     * Handler exceptions are never printed into the webhook HTTP response —
     * MAX discards that body, so an error sent there would be lost silently.
     *
     * @var callable|null
     */
    public static $errorHandler = null;

    /**
     * How many times to re-send a message that the API rejected with
     * "attachment.not.ready".
     *
     * MAX processes an uploaded file asynchronously: a message sent immediately
     * after upload is rejected with HTTP 400 attachment.not.ready and never
     * reaches the chat. Retrying a moment later succeeds. Set to 0 to disable.
     *
     * @var int
     */
    public static $attachmentRetries = 5;

    /**
     * Base delay between attachment retries, in milliseconds. The delay grows
     * linearly (delay, 2*delay, 3*delay, ...) to give bigger files more time.
     *
     * @var int
     */
    public static $attachmentRetryDelay = 500;

    /**
     * PHPMaxBot version
     *
     * @var string
     */
    protected static $version = '1.0';

    /**
     * Last update marker (timestamp)
     *
     * @var int
     */
    private $lastUpdateMarker = 0;

    /**
     * Message format
     *
     * @var string|bool
     * @author Дмитрий А. Морозов <dmitrij.morozov@office.partner-its.ru>
     */
    protected static $format = false;

    /**
     * PHPMaxBot Constructor
     *
     * @param string $token   Bot token
     * @param array  $options Optional configuration:
     *   - 'curlOptions' (array)  Custom cURL options (CURLOPT_* constants as keys)
     *   - 'debug'       (bool)   Override debug mode
     */
    public function __construct($token, array $options = [])
    {
        // Check PHP version
        if (version_compare(phpversion(), '7.4', '<')) {
            die("PHPMaxBot needs to use PHP 7.4 or higher.\n");
        }

        // Check curl
        if (!function_exists('curl_version')) {
            die("cURL is NOT installed on this server.\n");
        }

        // Check bot token
        if (empty($token)) {
            die("Bot token should not be empty!\n");
        }

        self::$token = $token;

        if (isset($options['curlOptions']) && is_array($options['curlOptions'])) {
            self::$curlOptions = $options['curlOptions'];
        }

        if (isset($options['debug'])) {
            self::$debug = (bool) $options['debug'];
        }
    }

    /**
     * Set message text format
     *
     * @param boolean|string $format
     * @return void
     * @author Дмитрий А. Морозов <dmitrij.morozov@office.partner-its.ru>
     */
    public function setFormat($format = false): void
    {
        switch (strtolower($format)) {
            case 'markdown':
            case 'md':
                self::$format = 'markdown';
                break;
            case 'html':
                self::$format = 'html';
                break;
            default:
                self::$format = false;
        }
    }

    /**
     * Get current message format. False if not set
     *
     * @return string|bool
     * @author Дмитрий А. Морозов <dmitrij.morozov@office.partner-its.ru>
     */
    public static function getFormat()
    {
        return self::$format;
    }

    /**
     * Register command handler
     *
     * @param string $command Command name (e.g., "start", "help")
     * @param callable|string $handler Handler function or string response
     * @return self
     */
    public function command($command, $handler)
    {
        $this->_command[$command] = $handler;
        return $this;
    }

    /**
     * Register event handler
     *
     * @param string $event Event type (e.g., "message_created", "bot_started")
     * @param callable|string $handler Handler function or string response
     * @return self
     */
    public function on($event, $handler)
    {
        $events = explode('|', $event);
        foreach ($events as $evt) {
            $this->_onEvent[$evt] = $handler;
        }
        return $this;
    }

    /**
     * Register action (callback) handler
     *
     * @param string $action Action pattern (can be regex)
     * @param callable|string $handler Handler function or string response
     * @return self
     */
    public function action($action, $handler)
    {
        $this->_onAction[$action] = $handler;
        return $this;
    }

    /**
     * Register attachment handler
     *
     * Called when a message_created update contains an attachment of the given type.
     * The handler receives the full attachment array as its first argument.
     *
     * Data location differs by type — some types use a 'payload' sub-key, others have
     * fields directly on the attachment object:
     *
     *   Types with payload sub-object:
     *     'image'          → $a['payload']['photo_id'], $a['payload']['token'], $a['payload']['url']
     *     'video'          → $a['payload']['url'], $a['payload']['token']
     *     'audio'          → $a['payload']['url'], $a['payload']['token']
     *     'contact'        → $a['payload']['vcf_info'], $a['payload']['max_info']['first_name|last_name|user_id']
     *     'inline_keyboard'→ $a['payload']['buttons']
     *     'share'          → $a['payload']['url']
     *
     *   Types with mixed payload + direct fields:
     *     'file'    → $a['payload']['url|token'] + $a['filename'], $a['size']
     *     'sticker' → $a['payload']['url|code']  + $a['width'],    $a['height']
     *
     *   Types with only direct fields (no payload):
     *     'location' → $a['latitude'], $a['longitude']
     *
     * @param string   $type    Attachment type: 'image', 'video', 'audio', 'file',
     *                          'sticker', 'contact', 'inline_keyboard', 'share', 'location'
     * @param callable $handler Handler receiving the full attachment array
     * @return self
     */
    public function onAttachment($type, $handler)
    {
        $this->_onAttachment[$type] = $handler;
        return $this;
    }

    /**
     * Custom regex handler
     *
     * @param string $regex Regular expression pattern
     * @param callable|string $handler Handler function or string response
     * @return self
     */
    public function regex($regex, $handler)
    {
        $this->_command['customRegex:' . $regex] = $handler;
        return $this;
    }

    /**
     * Report an exception through $errorHandler, or error_log() by default.
     *
     * API failures carry the MAX error code and description, which are the only
     * way to tell "Must be only one file attachment in message" apart from a
     * network problem — so they are unpacked into the log line.
     *
     * @param Exception $e
     * @param string    $context Short label describing where the error happened
     * @return string The formatted message that was logged
     */
    public static function logError($e, $context = '')
    {
        $parts = ['PHPMaxBot error'];
        if ($context !== '') {
            $parts[] = '[' . $context . ']';
        }
        $parts[] = get_class($e) . ': ' . $e->getMessage();

        if ($e instanceof \PHPMaxBot\Exceptions\ApiException) {
            $parts[] = '(api_code=' . $e->getApiErrorCode() . ', http_code=' . $e->getCode() . ')';
        }

        if ($e instanceof \PHPMaxBot\Exceptions\MaxBotException) {
            $context_data = $e->getContext();
            if (!empty($context_data)) {
                $parts[] = 'context=' . json_encode($context_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $message = implode(' ', $parts);

        if (is_callable(self::$errorHandler)) {
            call_user_func(self::$errorHandler, $message, $e);
        } else {
            error_log($message);
        }

        return $message;
    }

    /**
     * Start the bot (webhook or long polling based on environment)
     *
     * @param array $allowedUpdates Array of allowed update types
     * @return bool
     */
    public function start($allowedUpdates = [])
    {
        try {
            if (php_sapi_name() == 'cli') {
                echo 'PHPMaxBot version ' . self::$version;
                echo "\nMode\t: Long Polling\n";
                $options = getopt('q', ['quiet']);
                if (isset($options['q']) || isset($options['quiet'])) {
                    self::$debug = false;
                }
                echo "Debug\t: " . (self::$debug ? 'ON' : 'OFF') . "\n";
                $this->longPoll($allowedUpdates);
            } else {
                $this->webhook();
            }

            return true;
        } catch (Exception $e) {
            $message = self::logError($e, 'start');
            if (php_sapi_name() == 'cli') {
                echo $message . "\n";
            }
            return false;
        }
    }

    /**
     * Webhook mode
     */
    private function webhook()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = file_get_contents('php://input');
            self::$currentUpdate = json_decode($input, true);

            if (self::$currentUpdate === null) {
                http_response_code(400);
                throw new Exception('Invalid JSON in webhook request');
            }

            try {
                echo $this->process();
            } catch (Exception $e) {
                // MAX discards the webhook response body, so an error echoed here
                // would disappear without a trace — the handler would simply look
                // as if it had sent nothing. Log it instead, and acknowledge the
                // update with 200 so the platform does not retry a call that is
                // going to fail the same way again.
                self::logError($e, 'webhook');
                http_response_code(200);
            }
        } else {
            http_response_code(400);
            throw new Exception('Access not allowed!');
        }
    }

    /**
     * Long polling mode
     *
     * @param array $allowedUpdates
     * @throws Exception
     */
    private function longPoll($allowedUpdates = [])
    {
        while (true) {
            try {
                $params = [];
                if ($this->lastUpdateMarker > 0) {
                    $params['marker'] = $this->lastUpdateMarker;
                }

                $response = Bot::getUpdates($allowedUpdates, $params);

                if (isset($response['updates']) && !empty($response['updates'])) {
                    foreach ($response['updates'] as $update) {
                        self::$currentUpdate = $update;

                        // Isolate handler failures: one broken update must not
                        // stop the remaining ones from being processed.
                        try {
                            $process = $this->process();
                        } catch (Exception $e) {
                            $updateType = isset($update['update_type']) ? $update['update_type'] : 'unknown';
                            $process = self::logError($e, 'update:' . $updateType);
                            if (!self::$debug) {
                                echo $process . "\n";
                            }
                        }

                        if (self::$debug) {
                            $line = "\n--------------------\n";
                            $updateType = isset($update['update_type']) ? $update['update_type'] : 'unknown';
                            $timestamp = isset($update['timestamp']) ? $update['timestamp'] : time();
                            $outputFormat = "$line %s %s:%d $line%s";
                            echo sprintf($outputFormat, 'Update:', $updateType, $timestamp, json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                            echo sprintf($outputFormat, 'Response:', $updateType, $timestamp, Bot::$debug ?: $process ?: '--NO RESPONSE--');
                            // Reset debug
                            Bot::$debug = '';
                        }

                        // Update marker to the latest timestamp
                        if (isset($update['timestamp'])) {
                            $this->lastUpdateMarker = $update['timestamp'];
                        }
                    }
                }

                // Delay 1 second
                sleep(1);
            } catch (Exception $e) {
                echo self::logError($e, 'long_poll') . "\n";
                sleep(5); // Wait before retrying
            }
        }
    }

    /**
     * Process the update
     *
     * @return string|null
     */
    private function process()
    {
        $update = self::$currentUpdate;
        $run = false;
        $handler = null;
        $param = '';

        // Skip old messages
        if (isset($update['timestamp']) && $update['timestamp'] < (time() - 120)) {
            return '-- Pass (old update) --';
        }

        $updateType = isset($update['update_type']) ? $update['update_type'] : null;

        // Handle message_callback (button callbacks)
        if ($updateType === 'message_callback' && isset($update['callback'])) {
            $callbackData = isset($update['callback']['payload']) ? $update['callback']['payload'] : '';

            foreach ($this->_onAction as $pattern => $call) {
                // Try regex match
                if (preg_match('/' . str_replace('/', '\/', $pattern) . '/', $callbackData, $matches)) {
                    $run = true;
                    $handler = $call;
                    $param = $matches;
                    break;
                }
                // Try exact match
                if ($pattern === $callbackData) {
                    $run = true;
                    $handler = $call;
                    $param = $callbackData;
                    break;
                }
            }
        }

        // Handle message_created with commands
        if ($updateType === 'message_created' && isset($update['message']['body']) && isset($update['message']['body']['text'])) {
            $text = $update['message']['body']['text'];

            // Check if it's a command (starts with /)
            if (strpos($text, '/') === 0) {
                foreach ($this->_command as $cmd => $call) {
                    if (substr($cmd, 0, 12) == 'customRegex:') {
                        $regex = substr($cmd, 12);
                        if (preg_match($regex, $text, $matches)) {
                            $run = true;
                            $handler = $call;
                            $param = $matches;
                            break;
                        }
                    } else {
                        // Standard command matching
                        $regex = '/^\/' . preg_quote($cmd, '/') . '(?:\s(.*))?$/';
                        if (preg_match($regex, $text, $matches)) {
                            $run = true;
                            $handler = $call;
                            $param = isset($matches[1]) ? $matches[1] : '';
                            break;
                        }
                    }
                }
            }
        }

        // Handle message_created with attachment handlers
        if (!$run && $updateType === 'message_created' && !empty($this->_onAttachment)) {
            $attachments = $update['message']['body']['attachments'] ?? [];
            foreach ($attachments as $attachment) {
                $attachType = $attachment['type'] ?? null;
                if ($attachType && isset($this->_onAttachment[$attachType])) {
                    $run     = true;
                    $handler = $this->_onAttachment[$attachType];
                    $param   = $attachment;
                    break;
                }
            }
        }

        // Handle events
        if (!$run && $updateType) {
            if (isset($this->_onEvent[$updateType])) {
                $run = true;
                $handler = $this->_onEvent[$updateType];

                switch ($updateType) {
                    case 'message_created':
                        $param = isset($update['message']['body']['text']) ? $update['message']['body']['text'] : '';
                        break;
                    case 'message_callback':
                        $param = isset($update['callback']['payload']) ? $update['callback']['payload'] : '';
                        break;
                    case 'bot_started':
                        $param = isset($update['payload']) ? $update['payload'] : '';
                        break;
                    default:
                        $param = '';
                        break;
                }
            } elseif (isset($this->_onEvent['*'])) {
                $run = true;
                $handler = $this->_onEvent['*'];
                $param = '';
            }
        }

        // Execute handler
        if ($run && $handler) {
            if (is_callable($handler)) {
                $result = call_user_func_array($handler, [$param]);
            } else {
                // String response
                $result = Bot::sendMessage($handler);
            }
            return is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string)($result ?? '');
        }

        return null;
    }
}

require_once __DIR__ . '/Bot.php';
