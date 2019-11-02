<?php
/**
 * Modseven exception class. Translates exceptions using the [I18n] class.
 *
 * @package    Modseven
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use Throwable;
use ErrorException;

class Exception extends \Exception
{
    /**
     *  PHP error code => human readable name
     * @var array
     */
    public static array $php_errors = [
        E_ERROR             => 'Fatal Error',
        E_USER_ERROR        => 'User Error',
        E_PARSE             => 'Parse Error',
        E_WARNING           => 'Warning',
        E_USER_WARNING      => 'User Warning',
        E_STRICT            => 'Strict',
        E_NOTICE            => 'Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
    ];

    /**
     * Error rendering view
     * @var string
     */
    public static string $error_view = 'modseven/error';

    /**
     * error view content type
     * @var string
     */
    public static string $error_view_content_type = 'text/html';

    /**
     * Creates a new translated exception.
     *
     *     throw new Modseven_Exception('Something went terrible wrong, :user',
     *         array(':user' => $user));
     *
     * @param string $message error message
     * @param array $variables translation variables
     * @param integer|string $code the exception code
     * @param Throwable $previous Previous throwable
     */
    public function __construct(string $message = '', ?array $variables = NULL, int $code = 0, Throwable $previous = NULL)
    {
        // Set the message
        $message = I18n::get([$message, $variables]);

        // Pass the message and integer code to the parent
        parent::__construct($message, (int)$code, $previous);

        // Save the unmodified code
        // @link http://bugs.php.net/39615
        $this->code = $code;
    }

    /**
     * Inline exception handler, displays the error message, source of the
     * exception, and the stack trace of the error.
     *
     * @param Throwable $t
     *
     * @return  void
     *
     * @throws Exception
     */
    public static function handler(Throwable $t): void
    {
        // Send the response to the browser
        echo self::_handler($t)->send_headers()->body();

        exit(1);
    }

    /**
     * Exception handler, logs the exception and generates a Response object
     * for display.
     *
     * @param Throwable $t
     * @return  Response
     */
    public static function _handler(Throwable $t): Response
    {
        try {
            // Log the exception
            self::log($t);

            // Generate the response
            return self::response($t);
        } catch (\Exception $e) {
            /**
             * Things are going *really* badly for us, We now have no choice
             * but to bail. Hard.
             */
            // Clean the output buffer if one exists
            ob_get_level() AND ob_clean();

            // Set the Status code to 500, and Content-Type to text/plain.
            header('Content-Type: text/plain; charset=' . Core::$charset, TRUE, 500);

            echo self::text($e);

            exit(1);
        }
    }

    /**
     * Logs an exception.
     *
     * @param Throwable $t
     * @param string $level
     * @return  void
     */
    public static function log(Throwable $t, string $level = Log::EMERGENCY): void
    {
        if (is_object(Core::$log)) {
            // Create a text version of the exception
            $error = self::text($t);

            // Add this exception to the log
            Core::$log->log($level, $error, ['exception' => $t]);
        }
    }

    /**
     * Get a Response object representing the exception
     *
     * @param Throwable $t
     * @return  Response
     */
    public static function response(Throwable $t): Response
    {
        try {
            // Get the exception information
            $class = get_class($t);
            $code = $t->getCode();
            $message = $t->getMessage();
            $file = $t->getFile();
            $line = $t->getLine();
            $trace = $t->getTrace();

            /**
             * HTTP_Exceptions are constructed in the HTTP_Exception::factory()
             * method. We need to remove that entry from the trace and overwrite
             * the variables from above.
             */
            if ($t instanceof HTTP\Exception && $trace[0]['function'] === 'factory') {
                extract(array_shift($trace), null);
            }


            if ($t instanceof ErrorException) {
                /**
                 * If XDebug is installed, and this is a fatal error,
                 * use XDebug to generate the stack trace
                 */
                if (function_exists('xdebug_get_function_stack') && $code === E_ERROR) {
                    $trace = array_slice(array_reverse(xdebug_get_function_stack()), 4);

                    foreach ($trace as & $frame) {
                        /**
                         * XDebug pre 2.1.1 doesn't currently set the call type key
                         * http://bugs.xdebug.org/view.php?id=695
                         */
                        if (!isset($frame['type'])) {
                            $frame['type'] = '??';
                        }

                        // Xdebug returns the words 'dynamic' and 'static' instead of using '->' and '::' symbols
                        if ('dynamic' === $frame['type']) {
                            $frame['type'] = '->';
                        } elseif ('static' === $frame['type']) {
                            $frame['type'] = '::';
                        }

                        // XDebug also has a different name for the parameters array
                        if (isset($frame['params']) && !isset($frame['args'])) {
                            $frame['args'] = $frame['params'];
                        }
                    }
                    unset($frame);
                }

                if (isset(static::$php_errors[$code])) {
                    // Use the human-readable error name
                    $code = static::$php_errors[$code];
                }
            }

            /**
             * The stack trace becomes unmanageable inside PHPUnit.
             *
             * The error view ends up several GB in size, taking
             * serveral minutes to render.
             */
            if (
                defined('PHPUnit_MAIN_METHOD')
                ||
                defined('PHPUNIT_COMPOSER_INSTALL')
                ||
                defined('__PHPUNIT_PHAR__')
            ) {
                $trace = array_slice($trace, 0, 2);
            }

            // Instantiate the error view.
            $view = View::factory(static::$error_view, get_defined_vars());

            // Prepare the response object.
            $response = Response::factory();

            // Set the response status
            $response->status(($t instanceof HTTP\Exception) ? $t->getCode() : 500);

            // Set the response headers
            $response->headers('Content-Type', static::$error_view_content_type . '; charset=' . Core::$charset);

            // Set the response body
            $response->body($view->render());
        } catch (\Exception $e) {
            /**
             * Things are going badly for us, Lets try to keep things under control by
             * generating a simpler response object.
             */
            $response = Response::factory();
            $response->status(500);
            $response->headers('Content-Type', 'text/plain');
            $response->body(self::text($e));
        }

        return $response;
    }

    /**
     * Magic object-to-string method.
     *
     * @return  string
     */
    public function __toString(): string
    {
        return self::text($this);
    }

    /**
     * Get a single line of text representing the exception:
     *
     * Error [ Code ]: Message ~ File [ Line ]
     *
     * @param Throwable $t
     * @return  string
     */
    public static function text(Throwable $t): string
    {
        return sprintf('%s [ %s ]: %s ~ %s [ %d ]',
            get_class($t), $t->getCode(), strip_tags($t->getMessage()), Debug::path($t->getFile()), $t->getLine());
    }

}
