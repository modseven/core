<?php
/**
 * File log writer. Writes out messages and stores them in a YYYY/MM directory.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Log;

use Modseven\Debug;
use Modseven\Exception;

class File extends Writer {

    /**
     * Directory to place log files in
     * @var string
     */
    protected string $_directory;

    /**
     * Creates a new file logger.
     * Checks that the directory exists and is writable.
     *
     * @param string $directory log directory
     *
     * @throws Exception
     */
    public function __construct(string $directory)
    {
        if ( ! is_dir($directory) || ! is_writable($directory))
        {
            throw new Exception('Directory :dir must be writable', [':dir' => Debug::path($directory)]);
        }

        // Determine the directory path
        $this->_directory = realpath($directory).DIRECTORY_SEPARATOR;
    }

    /**
     * Writes the message into the log file. The log file will be
     * appended to the `YYYY/MM/DD.log` file, where YYYY is the current
     * year, MM is the current month, and DD is the current day.
     *
     * @param string $message
     *
     * @throws Exception
     */
    public function write(string $message) : void
    {
        // Set the yearly directory name
        $directory = $this->_directory.date('Y');

        if ( ! is_dir($directory))
        {
            // Create the yearly directory
            if ( ! mkdir($directory, 02777) && ! is_dir($directory))
            {
                throw new Exception('Directory ":dir" was not created', [
                    ':dir' => $directory
                ]);
            }

            // Set permissions (must be manually set to fix umask issues)
            chmod($directory, 02777);
        }

        // Add the month to the directory
        $directory .= DIRECTORY_SEPARATOR.date('m');

        if ( ! is_dir($directory))
        {
            // Create the monthly directory
            if ( ! mkdir($directory, 02777) && ! is_dir($directory))
            {
                throw new Exception('Directory ":dir" was not created', [
                    ':dir' => $directory
                ]);
            }

            // Set permissions (must be manually set to fix umask issues)
            chmod($directory, 02777);
        }

        // Set the name of the log file
        $filename = $directory.DIRECTORY_SEPARATOR.date('d').'.log';

        if ( ! file_exists($filename))
        {
            // Create the log file
            file_put_contents($filename, null, LOCK_EX);

            // Allow anyone to write to log files
            chmod($filename, 0666);
        }

        file_put_contents($filename, $message . PHP_EOL, FILE_APPEND);
    }
}
