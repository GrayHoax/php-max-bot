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
     * Cache of instances created from [ClassName::class, 'method'] handlers
     * when the method is non-static. Keyed by fully-qualified class name.
     *
     * @var array<string, object>
     */
    private $_handlerInstances = [];

    /**
     * Factories for creating handler instances on demand.
     * Keyed by fully-qualified class name; each value is a callable returning an object.
     *
     * @var array<string, callable>
     */
    private $_handlerFactories = [];

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
     * Register a ready-made instance of a class for use in array-style handlers
     * like [ClassName::class, 'method'].
     *
     * Allows pre-instantiating classes that require constructor arguments
     * (e.g. dependency injection) before registering their methods as handlers.
     *
     * Example:
     *   $bot->setInstance(UserHandler::class, new UserHandler($db));
     *   $bot->command('profile', [UserHandler::class, 'show']);
     *
     * @param string $className Fully-qualified class name
     * @param object $instance  Ready-to-use instance of that class
     * @return self
     * @throws InvalidArgumentException If $instance is not an instance of $className
     */
    public function setInstance($className, $instance)
    {
        if (!is_object($instance)) {
            throw new InvalidArgumentException('Instance must be an object.');
        }
        if (!($instance instanceof $className)) {
            throw new InvalidArgumentException(sprintf(
                'Instance must be of class %s, got %s.',
                $className,
                get_class($instance)
            ));
        }
        $this->_handlerInstances[$className] = $instance;
        return $this;
    }

    /**
     * Register a factory callback that lazily creates an instance of a class
     * on the first time it is needed by a handler.
     *
     * The factory receives no arguments and must return an instance of $className.
     * The created instance is cached for subsequent calls.
     *
     * Example:
     *   $bot->setFactory(UserHandler::class, fn() => new UserHandler($container->get('db')));
     *
     * @param string   $className Fully-qualified class name
     * @param callable $factory   Callable returning an instance of $className
     * @return self
     */
    public function setFactory($className, callable $factory)
    {
        $this->_handlerFactories[$className] = $factory;
        return $this;
    }

    /**
     * Get a registered/cached handler instance, or null if none exists yet
     * and no factory is registered for the given class.
     *
     * @param string $className Fully-qualified class name
     * @return object|null
     */
    public function getInstance($className)
    {
        if (isset($this->_handlerInstances[$className])) {
            return $this->_handlerInstances[$className];
        }
        if (isset($this->_handlerFactories[$className])) {
            $instance = call_user_func($this->_handlerFactories[$className]);
            if (!is_object($instance) || !($instance instanceof $className)) {
                throw new RuntimeException(sprintf(
                    'Factory for %s must return an instance of that class.',
                    $className
                ));
            }
            $this->_handlerInstances[$className] = $instance;
            return $instance;
        }
        return null;
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
            echo $e->getMessage() . "\n";
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

            echo $this->process();
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
                        $process = $this->process();

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
                echo "Error in long poll loop: " . $e->getMessage() . "\n";
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
            $callable = $this->resolveHandler($handler);
            if ($callable !== null) {
                $result = call_user_func_array($callable, [$param]);
            } else {
                // Plain string response
                $result = Bot::sendMessage($handler);
            }
            return is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string)($result ?? '');
        }

        return null;
    }

    /**
     * Resolve a registered handler into something callable.
     *
     * Accepts every standard PHP callable form and additionally supports the
     * shorthand [ClassName::class, 'method'] for non-static methods —
     * the class is instantiated once (cached per bot instance) and the call
     * is dispatched on the instance. Returns null when the handler is not a
     * callable (e.g. a plain string used as a canned text response).
     *
     * Supported forms:
     *   - Closure / first-class callable
     *   - [$instance, 'method']
     *   - [ClassName::class, 'staticMethod']               (called statically)
     *   - [ClassName::class, 'instanceMethod']             (auto-instantiated)
     *   - 'ClassName::staticMethod'                        (PHP string form)
     *   - 'function_name'
     *
     * @param mixed $handler
     * @return callable|null
     */
    private function resolveHandler($handler)
    {
        // [ClassName, 'method'] — shorthand form. Resolve non-static methods
        // by instantiating the class so call_user_func_array can dispatch
        // on an instance (otherwise PHP 8+ fatals on a non-static call).
        if (is_array($handler)
            && count($handler) === 2
            && array_keys($handler) === [0, 1]
            && is_string($handler[0])
            && is_string($handler[1])
            && class_exists($handler[0])
            && method_exists($handler[0], $handler[1])
        ) {
            $class  = $handler[0];
            $method = $handler[1];

            try {
                $ref = new ReflectionMethod($class, $method);
            } catch (\ReflectionException $e) {
                return null;
            }

            if (!$ref->isPublic()) {
                return null;
            }

            if ($ref->isStatic()) {
                return [$class, $method];
            }

            // Prefer pre-registered instance or factory; fall back to
            // parameterless instantiation only if neither is available.
            if (!isset($this->_handlerInstances[$class])) {
                if (isset($this->_handlerFactories[$class])) {
                    $this->getInstance($class);
                } else {
                    $this->_handlerInstances[$class] = new $class();
                }
            }
            return [$this->_handlerInstances[$class], $method];
        }

        return is_callable($handler) ? $handler : null;
    }
}

require_once __DIR__ . '/Bot.php';
