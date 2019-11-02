<?php
/**
 * Message Logging according to PSR-3 and RFC 5424
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven;

use Psr\Log\LogLevel;
use Psr\Log\LoggerTrait;
use Psr\Log\InvalidArgumentException;

class Log extends LogLevel {

    use LoggerTrait;

    /**
     * Singleton instance container
     * @var Log
     */
    protected static ?Log $_instance = null;

    /**
     * List of Log Writer
     * @var array
     */
    protected array $_writers = [];

    /**
     * Get the singleton instance of this class and enable writing at shutdown.
     *
     * @return  Log
     */
    public static function instance() : Log
    {
        if (static::$_instance === null)
        {
            // Create a new instance
            static::$_instance = new self;
        }

        return static::$_instance;
    }

    /**
     * Attaches a log writer, and optionally limits the levels of messages that
     * will be written by the writer.
     *
     * @param Log\Writer $writer Instance of the Writer
     * @param mixed      $levels Array of messages levels to write (empty for all)
     *
     * @return  self
     */
    public function attach(Log\Writer $writer, array $levels = []) : self
    {
        $this->_writers[(string)$writer] = [
            'object' => $writer,
            'levels' => $levels
        ];

        return $this;
    }

    /**
     * Detaches a log writer. The same writer object must be used.
     *
     * @param Log\Writer $writer Instance of Writer
     *
     * @return  self
     */
    public function detach(Log\Writer $writer) : self
    {
        unset($this->_writers[(string)$writer]);
        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level     Log Level (must be valid RFC 5424)
     * @param mixed  $message   Message to log, either string or object with __toString
     * @param array  $context   Contextual Array
     */
    public function log(string $level, $message, array $context = []) : void
    {
        // Check if log level is in RFC 5424, if not throw an Exception (PSR-3)
        if ( ! defined('static::'.strtoupper($level)))
        {
            throw new InvalidArgumentException('Invalid Logging Level supplied.');
        }

        // Replace placeholder inside message
        $message = $this->interpolate($message, $context);

        // Get stack trace
        if (isset($context['exception']) && ($ex = $context['exception']) instanceof Exception)
        {
            $trace = $ex->getTrace();
        }
        else
        {
            $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1);
        }

        // Create Logging array
        $obj = [
            'time' => time(),
            'level' => $level,
            'body' => $message,
            'trace' => $trace,
            'file' => $trace[0]['file'] ?? null,
            'line' => $trace[0]['line'] ?? null,
            'class' => $trace[0]['class'] ?? null,
            'function' => $trace[0]['function'] ?? null,
            'context' => $context,
        ];

        // Loop through the writers and write the message
        foreach ($this->_writers as $writer)
        {
            if (empty($writer['levels']) || in_array($level, $writer['levels'], true))
            {
                $writer['object']->write($writer['object']->formatMessage($obj));
            }
        }
    }

    /**
     * Interpolate Context into the message according to PSR-3
     *
     * @param mixed $message    Message as string or Object with __toString method
     * @param array $context    Context array
     *
     * @return string
     */
    protected function interpolate($message, array $context) : string
    {
        // build a replacement array with braces around the context keys
        $replace = [];

        foreach ($context as $key => $val)
        {
            // check that the value can be casted to string
            if ( ! is_array($val) && ( ! is_object($val) || method_exists($val, '__toString')))
            {
                $replace['{'.$key.'}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
