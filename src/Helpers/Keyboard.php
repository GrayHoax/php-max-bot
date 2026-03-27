<?php
/**
 * Keyboard.php
 *
 * @author GrayHoax <grayhoax@grayhoax.ru>
 * @link https://github.com/grayhoax/php-max-bot
 * @license GPL-3.0
 */

namespace PHPMaxBot\Helpers;

/**
 * Class Keyboard
 *
 * Helper class for creating inline keyboards and buttons for MAX messenger
 */
class Keyboard
{
    /**
     * Create inline keyboard attachment
     *
     * @param array $buttons Array of button rows
     * @return array Inline keyboard attachment
     */
    public static function inlineKeyboard($buttons)
    {
        return [
            'type' => 'inline_keyboard',
            'payload' => [
                'buttons' => $buttons
            ]
        ];
    }

    /**
     * Create callback button
     *
     * @param string $text Button text
     * @param string $payload Callback data
     * @param array $extra Additional parameters (e.g., intent)
     * @return array Button structure
     */
    public static function callbackButton($text, $payload, $extra = [])
    {
        return array_merge([
            'type' => 'callback',
            'text' => $text,
            'payload' => $payload
        ], $extra);
    }

    /**
     * Create open_app button
     *
     * @param string $text Button text
     * @param string $url Url of webapp
     * @param array $extra Additional parameters (e.g., payload or contact_id)
     * @return array Button structure
     */
    public static function open_appButton($text, $url, $extra = [])
    {
        return array_merge([
            'type' => 'open_app',
            'text' => $text,
            'web_app' => $url
        ], $extra);
    }

    /**
     * Create link button
     *
     * @param string $text Button text
     * @param string $url URL to open
     * @return array Button structure
     */
    public static function linkButton($text, $url)
    {
        return [
            'type' => 'link',
            'text' => $text,
            'url' => $url
        ];
    }

    /**
     * Create request contact button
     *
     * @param string $text Button text
     * @return array Button structure
     */
    public static function requestContactButton($text)
    {
        return [
            'type' => 'request_contact',
            'text' => $text
        ];
    }

    /**
     * Create request geo location button
     *
     * @param string $text Button text
     * @param array $extra Additional parameters
     * @return array Button structure
     */
    public static function requestGeoLocationButton($text, $extra = [])
    {
        return array_merge([
            'type' => 'request_geo_location',
            'text' => $text
        ], $extra);
    }

    /**
     * Create chat button
     *
     * @param string $text Button text
     * @param string $chatTitle Title for the chat to be created
     * @param array $extra Additional parameters (e.g., start_payload)
     * @return array Button structure
     */
    public static function chatButton($text, $chatTitle, $extra = [])
    {
        return array_merge([
            'type' => 'chat',
            'text' => $text,
            'chat_title' => $chatTitle
        ], $extra);
    }

    /**
     * Alias for callbackButton (for compatibility)
     *
     * @param string $text Button text
     * @param string $payload Callback data
     * @param array $extra Additional parameters
     * @return array Button structure
     */
    public static function callback($text, $payload, $extra = [])
    {
        return self::callbackButton($text, $payload, $extra);
    }

    /**
     * Alias for linkButton (for compatibility)
     *
     * @param string $text Button text
     * @param string $url URL to open
     * @return array Button structure
     */
    public static function link($text, $url)
    {
        return self::linkButton($text, $url);
    }

    /**
     * Alias for requestContactButton (for compatibility)
     *
     * @param string $text Button text
     * @return array Button structure
     */
    public static function requestContact($text)
    {
        return self::requestContactButton($text);
    }

    /**
     * Alias for requestGeoLocationButton (for compatibility)
     *
     * @param string $text Button text
     * @param array $extra Additional parameters
     * @return array Button structure
     */
    public static function requestGeoLocation($text, $extra = [])
    {
        return self::requestGeoLocationButton($text, $extra);
    }

    /**
     * Alias for chatButton (for compatibility)
     *
     * @param string $text Button text
     * @param string $chatTitle Title for the chat to be created
     * @param array $extra Additional parameters
     * @return array Button structure
     */
    public static function chat($text, $chatTitle, $extra = [])
    {
        return self::chatButton($text, $chatTitle, $extra);
    }

    /**
     * Alias for open_appButton (for compatibility)
     *
     * @param string $text Button text
     * @param string $url Url of webapp
     * @param array $extra Additional parameters (e.g., payload or contact_id)
     * @return array Button structure
     */
    public static function open_app($text, $url, $extra = [])
    {
        return self::open_appButton($text, $url, $extra);
    }
}
