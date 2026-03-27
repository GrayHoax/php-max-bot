<?php
/**
 * ApiException.php
 *
 * @author GrayHoax <grayhoax@grayhoax.ru>
 * @link https://github.com/grayhoax/php-max-bot
 * @license GPL-3.0
 */

namespace PHPMaxBot\Exceptions;

/**
 * Exception class for MAX API errors
 */
class ApiException extends MaxBotException
{
    /**
     * API error code
     *
     * @var string
     */
    protected $apiErrorCode;

    /**
     * API error description
     *
     * @var string
     */
    protected $description;

    /**
     * ApiException constructor
     *
     * @param string $message
     * @param int $httpCode
     * @param string $apiErrorCode
     * @param string $description
     * @param array $context
     */
    public function __construct($message = "", $httpCode = 0, $apiErrorCode = "", $description = "", $context = [])
    {
        parent::__construct($message, $httpCode, $context);
        $this->apiErrorCode = $apiErrorCode;
        $this->description = $description;
    }

    /**
     * Get API error code
     *
     * @return string
     */
    public function getApiErrorCode()
    {
        return $this->apiErrorCode;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
