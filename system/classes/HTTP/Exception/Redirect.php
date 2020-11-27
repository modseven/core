<?php


namespace Modseven\HTTP\Exception;


use Modseven\Core;
use Modseven\HTTP\Exception;
use Modseven\Response;
use Modseven\URL;
use Throwable;

class Redirect extends Exception
{

    protected ?string $_uri = null;

    protected ?Response $_response = null;


    /**
     * Exception constructor.
     *
     * @param string $uri
     * @param array|null $variables translation variables
     * @param integer $code the http status code
     * @param Throwable|null $previous
     * @throws Exception
     */
    public function __construct(string $uri = '', ?array $variables = NULL, int $code = 303, Throwable $previous = NULL)
    {
        if ($code < 300 || $code > 308) {
            throw Exception::factory(500, 'Invalid redirect code \':code\'', [':code' => $code]);
        }
        if( $uri == '') {
            throw Exception::factory(500, 'Empty redirect URI');
        }
        // i don't care, important is code and uri
        parent::__construct($uri, $variables, $code, $previous);

        $this->_code = $code;
        $this->_uri = $uri;
    }

    /**
     * Creates an HTTP_Exception of the specified type.
     *
     * @param integer $code the http status code
     * @param string|null $uri
     * @param array|null $variables translation variables
     * @param null|\Exception $previous Previous Exception
     *
     * @return  Exception
     * @throws Exception
     */
    public static function factory(int $code, ?string $uri = null, ?array $variables = NULL, ?\Exception $previous = NULL): Exception
    {
        return new self($uri, $variables, $code);
    }

    /**
     * Generate a Response for the current Exception
     *
     * @return \Modseven\Response
     * @throws \Modseven\Exception
     */
    public function getResponse(): \Modseven\Response
    {
        if( $this->_response === null) {
            $response = Response::factory();
            $response->status($this->code);

            $url = strpos($this->_uri, '://') === false ? URL::site($this->_uri, true, ! empty(Core::$index_file))  : $this->_uri;
            $response->headers('Location', $url);
            $response->body('');
            $this->_response = $response;
        }
        return $this->_response;
    }

}