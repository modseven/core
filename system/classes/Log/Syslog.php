<?php
/**
 * Syslog log writer.
 *
 * @author         Jeremy Bush
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Log;

class Syslog extends Writer {

    /**
     * The syslog identifier
     * @var string
     */
    protected string $_ident;

    /**
     * Holds the original message array
     * @var array
     */
    protected array $_original;

    /**
     * Creates a new syslog logger.
     *
     * @param string $ident    Syslog identifier
     * @param int    $facility Facility to log to
     */
    public function __construct(string $ident = 'ModsevenPHP', int $facility = LOG_USER)
    {
        $this->_ident = $ident;

        // Open the connection to syslog
        openlog($this->_ident, LOG_CONS, $facility);
    }

    /**
     * Writes the message into the syslog.
     *
     * @param string $message
     */
    public function write(string $message) : void
    {
        $original = $this->_original;
        syslog($original['level'], $message);

        if (isset($original['context']['exception']))
        {
            syslog(static::$strace_level, $original['context']['exception']->getTraceAsString());
        }
    }

    /**
     * Extend the format message method, as we do not need to format it for syslog
     *
     * @param array  $message
     * @param string $format
     *
     * @return string
     */
    public function format_message(array $message, string $format = 'time --- level: body in file:line') : string
    {
        $this->_original = $message;
        return $message['body'];
    }

    /**
     * Closes the syslog connection
     */
    public function __destruct()
    {
        // Close connection to syslog
        closelog();
    }
}
