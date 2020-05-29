<?php

namespace Modseven\HTTP;

use Throwable;

class Exception extends \Modseven\Exception
{
    /**
     * http status code
     * @var int
     */
    protected int $_code = 0;

    /**
     * Request instance that triggered this exception.
     *
     * @var Request
     */
    protected ?Request $_request = null;

    /**
     * Exception constructor.
     *
     * @param integer $code the http status code
     * @param string $message status message, custom content to display with error
     * @param array $variables translation variables
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', ?array $variables = NULL, int $code = 0, Throwable $previous = NULL)
    {
        $this->_code = $code;
        parent::__construct($message, $variables, $code, $previous);
    }

    /**
     * Creates an HTTP_Exception of the specified type.
     *
     * @param integer         $code      the http status code
     * @param string          $message   status message, custom content to display with error
     * @param array           $variables translation variables
     * @param null|\Exception $previous  Previous Exception
     *
     * @return  Exception
     */
    public static function factory(int $code, ?string $message = NULL, ?array $variables = NULL, ?\Exception $previous = NULL): Exception
    {
        return new self($message, $variables, $code, $previous);
    }

    /**
     * Store the Request that triggered this exception.
     *
     * @param Request $request Request object that triggered this exception.
     *
     * @return  self|Request
     */
    public function request(Request $request = NULL)
    {
        if ($request === NULL) {
            return $this->_request;
        }

        $this->_request = $request;

        return $this;
    }

    /**
     * Generate a Response for the current Exception
     *
     * @return \Modseven\Response
     */
    public function getResponse(): \Modseven\Response
    {
        return \Modseven\Exception::response($this);
    }

}
