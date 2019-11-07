<?php
/**
 * Response wrapper. Created as the result of any [Request] execution
 * or utility method (i.e. Redirect). Implements standard HTTP
 * response format.
 *
 * @package    Modseven
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class Response implements HTTP\Response
{
    /**
     * Response Messages
     * @var array
     */
    public static array $messages = [
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        429 => 'Too Many Requests',

        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded'
    ];

    /**
     * The response http status
     * @var  integer
     */
    protected int $_status = 200;

    /**
     * Headers returned in the response
     * @var null|HTTP\Header
     */
    protected ?HTTP\Header $_header = null;

    /**
     * The response body
     * @var string
     */
    protected string $_body = '';

    /**
     * Cookies to be returned in the response
     * @var array
     */
    protected array $_cookies = [];

    /**
     * The response protocol
     * @var null|string
     */
    protected ?string $_protocol = null;

    /**
     * Sets up the response object
     *
     * @param array $config Setup the response object
     */
    public function __construct(array $config = [])
    {
        $this->_header = new HTTP\Header;

        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key === '_header') {
                    $this->headers($value);
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Gets and sets headers to the [Response], allowing chaining
     * of response methods. If chaining isn't required, direct
     * access to the property should be used instead.
     *
     * @param mixed $key
     * @param string $value
     * @return mixed
     */
    public function headers($key = NULL, ?string $value = NULL)
    {
        if ($key === NULL) {
            return $this->_header;
        }
        if (is_array($key)) {
            $this->_header->exchangeArray($key);
            return $this;
        }
        if ($value === NULL) {
            return Arr::get($this->_header, $key);
        }
        $this->_header[$key] = $value;
        return $this;
    }

    /**
     * Factory method to create a new [Response]. Pass properties
     * in using an associative array.
     *
     * @param array $config Setup the response object
     * @return  Response
     */
    public static function factory(array $config = []): Response
    {
        return new self($config);
    }

    /**
     * Outputs the body when cast to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->_body;
    }

    /**
     * Gets or sets the HTTP protocol. The standard protocol to use
     * is `HTTP/1.1`.
     *
     * @param string $protocol Protocol to set to the request/response
     * @return  mixed
     */
    public function protocol(?string $protocol = NULL)
    {
        if ($protocol) {
            $this->_protocol = strtoupper($protocol);
            return $this;
        }

        if ($this->_protocol === NULL) {
            $this->_protocol = HTTP::$protocol;
        }

        return $this->_protocol;
    }

    /**
     * Sets or gets the HTTP status from this response.
     *
     * @param integer $status Status to set to this response
     *
     * @return  self|int|boolean    acting as setter \ acting as getter \ false on invalid status code
     */
    public function status(int $status = NULL)
    {
        if ($status === NULL) {
            return $this->_status;
        }
        if (array_key_exists($status, static::$messages)) {
            $this->_status = $status;
            return $this;
        }
        return false;
    }

    /**
     * Set and get cookies values for this response.
     *
     *
     * @param mixed $key cookie name, or array of cookie values
     * @param mixed $value value to set to cookie
     * @return  string|void|self|array
     */
    public function cookie($key = NULL, $value = NULL)
    {
        // Handle the get cookie calls
        if ($key === NULL) {
            return $this->_cookies;
        }
        if (!is_array($key) && !$value) {
            return Arr::get($this->_cookies, $key);
        }

        // Handle the set cookie calls
        if (is_array($key)) {
            reset($key);
            foreach ($key as $_key => $_value) {
                $this->cookie($_key, $_value);
            }
        } else {
            if (!is_array($value)) {
                $value = [
                    'value' => $value,
                    'expiration' => Cookie::$expiration
                ];
            } elseif (!isset($value['expiration'])) {
                $value['expiration'] = Cookie::$expiration;
            }

            $this->_cookies[$key] = $value;
        }

        return $this;
    }

    /**
     * Deletes a cookie set to the response
     *
     * @param string $name
     * @return  self
     */
    public function deleteCookie(string $name): self
    {
        unset($this->_cookies[$name]);
        return $this;
    }

    /**
     * Deletes all cookies from this response
     *
     * @return  self
     */
    public function deleteCookies(): self
    {
        $this->_cookies = [];
        return $this;
    }

    /**
     * Send file download as the response. All execution will be halted when
     * this method is called! Use TRUE for the filename to send the current
     * response as the file content. The third parameter allows the following
     * options to be set:
     *
     * Type      | Option    | Description                        | Default Value
     * ----------|-----------|------------------------------------|--------------
     * `boolean` | inline    | Display inline instead of download | `FALSE`
     * `string`  | mime_type | Manual mime type                   | Automatic
     * `boolean` | delete    | Delete the file after sending      | `FALSE`
     *
     * Download a file that already exists:
     *
     *     $request->send_file('media/packages/modseven.zip');
     *
     * Download a generated file:
     *
     *     $csv = tmpfile();
     *     fputcsv($csv, ['label1', 'label2']);
     *     $request->send_file($csv, $filename);
     *
     * Download generated content as a file:
     *
     *     $request->response($content);
     *     $request->send_file(TRUE, $filename);
     *
     * [!!] No further processing can be done after this method is called!
     *
     * @param string|resource|bool $filename filename with path, file stream, or TRUE for the current response
     * @param string $download downloaded file name
     * @param array $options additional options
     *
     * @return  void
     * @throws  Exception
     */
    public function sendFile($filename, ?string $download = NULL, ?array $options = NULL): void
    {
        if (!empty($options['mime_type'])) {
            // The mime-type has been manually set
            $mime = $options['mime_type'];
        }

        if ($filename === TRUE) {
            if (empty($download)) {
                throw new Exception('Download name must be provided for streaming files');
            }

            // Temporary files will automatically be deleted
            $options['delete'] = FALSE;

            if (!isset($mime)) {
                // Guess the mime using the file extension
                $mime = File::mimeByExt(strtolower(pathinfo($download, PATHINFO_EXTENSION)));
            }

            // Force the data to be rendered if
            $file_data = (string)$this->_body;

            // Get the content size
            $size = strlen($file_data);

            // Create a temporary file to hold the current response
            $file = tmpfile();

            // Write the current response into the file
            fwrite($file, $file_data);

            // File data is no longer needed
            unset($file_data);
        } else if (is_resource($filename) && get_resource_type($filename) === 'stream') {
            if (empty($download)) {
                throw new Exception('Download name must be provided for streaming files');
            }

            // Make sure this is a file handle
            $file_meta = stream_get_meta_data($filename);
            if ($file_meta['seekable'] === FALSE) {
                throw new Exception('Resource must be a file handle');
            }

            // Handle file streams passed in as resources
            $file = $filename;
            $size = fstat($file)['size'];
        } else {
            // Get the complete file path
            $filename = realpath($filename);

            if (empty($download)) {
                // Use the file name as the download file name
                $download = pathinfo($filename, PATHINFO_BASENAME);
            }

            // Get the file size
            $size = filesize($filename);

            if (!isset($mime)) {
                // Get the mime type from the extension of the download file
                $mime = File::mimeByExt(pathinfo($download, PATHINFO_EXTENSION));
            }

            // Open the file for reading
            $file = fopen($filename, 'rb');
        }

        if (!is_resource($file)) {
            throw new Exception('Could not read file to send: :file', [
                ':file' => $download,
            ]);
        }

        // Inline or download?
        $disposition = empty($options['inline']) ? 'attachment' : 'inline';

        // Calculate byte range to download.
        [$start, $end] = $this->_calculateByteRange($size);

        if (!empty($options['resumable'])) {
            if ($start > 0 || $end < ($size - 1)) {
                // Partial Content
                $this->_status = 206;
            }

            // Range of bytes being sent
            $this->_header['content-range'] = 'bytes ' . $start . '-' . $end . '/' . $size;
            $this->_header['accept-ranges'] = 'bytes';
        }

        // Set the headers for a download
        $this->_header['content-disposition'] = $disposition . '; filename="' . $download . '"';
        $this->_header['content-type'] = $mime;
        $this->_header['content-length'] = (string)(($end - $start) + 1);

        if (Request::userAgent('browser') === 'Internet Explorer') {
            // Naturally, IE does not act like a real browser...
            if (Request::$initial->secure()) {
                // http://support.microsoft.com/kb/316431
                $this->_header['pragma'] = $this->_header['cache-control'] = 'public';
            }

            if (version_compare(Request::userAgent('version'), '8.0', '>=')) {
                // http://ajaxian.com/archives/ie-8-security
                $this->_header['x-content-type-options'] = 'nosniff';
            }
        }

        // Send all headers now
        $this->sendHeaders();

        while (ob_get_level()) {
            // Flush all output buffers
            ob_end_flush();
        }

        // Manually stop execution
        ignore_user_abort(TRUE);

        // Send data in 16kb blocks
        $block = 1024 * 16;

        fseek($file, $start);

        while (!feof($file) && ($pos = ftell($file)) <= $end) {
            if (connection_aborted()) {
                break;
            }

            if ($pos + $block > $end) {
                // Don't read past the buffer.
                $block = $end - $pos + 1;
            }

            // Output a block of the file
            echo fread($file, $block);

            // Send the data now
            flush();
        }

        // Close the file
        fclose($file);

        if (!empty($options['delete'])) {
            try {
                // Attempt to remove the file
                unlink($filename);
            } catch (\Exception $e) {
                // Create a text version of the exception
                $error = Exception::text($e);

                if (is_object(Core::$log)) {
                    // Add this exception to the log
                    Core::$log->error($error);
                }

                // Do NOT display the exception, it will corrupt the output!
            }
        }

        // Stop execution
        exit;
    }

    /**
     * Calculates the byte range to use with send_file. If HTTP_RANGE doesn't
     * exist then the complete byte range is returned
     *
     * @param integer $size
     * @return array
     */
    protected function _calculateByteRange(int $size): array
    {
        // Defaults to start with when the HTTP_RANGE header doesn't exist.
        $start = 0;
        $end = $size - 1;

        if ($range = $this->_parseByteRange()) {
            // We have a byte range from HTTP_RANGE
            $start = $range[1];

            if ($start[0] === '-') {
                // A negative value means we start from the end, so -500 would be the
                // last 500 bytes.
                $start = $size - abs($start);
            }

            if (isset($range[2])) {
                // Set the end range
                $end = $range[2];
            }
        }

        // Normalize values.
        $start = abs((int)$start);

        // Keep the the end value in bounds and normalize it.
        $end = min(abs((int)$end), $size - 1);

        // Keep the start in bounds.
        $start = ($end < $start) ? 0 : max($start, 0);

        return [$start, $end];
    }

    /**
     * Parse the byte ranges from the HTTP_RANGE header used for
     * resumable downloads.
     *
     * @link   http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
     * @return array|FALSE
     */
    protected function _parseByteRange()
    {
        if (!isset($_SERVER['HTTP_RANGE'])) {
            return FALSE;
        }

        // TODO, speed this up with the use of string functions.
        preg_match_all('/(-?[0-9]++(?:-(?![0-9]++))?)(?:-?([0-9]++))?/', $_SERVER['HTTP_RANGE'], $matches, PREG_SET_ORDER);

        return $matches[0];
    }

    /**
     * Sends the response status and all set headers.
     *
     * @param boolean $replace replace existing headers
     * @param callback $callback function to handle header output
     *
     * @return  mixed
     *
     * @throws Exception
     */
    public function sendHeaders(bool $replace = FALSE, $callback = NULL)
    {
        return $this->_header->sendHeaders($this, $replace, $callback);
    }

    /**
     * Generate ETag
     * Generates an ETag from the response ready to be returned
     *
     * @return string Generated ETag
     * @throws Request\Exception
     */
    public function generateEtag(): string
    {
        if ($this->_body === '') {
            throw new Request\Exception('No response yet associated with request - cannot auto generate resource ETag');
        }

        // Generate a unique hash for the response
        return '"' . sha1($this->render()) . '"';
    }

    /**
     * Renders the HTTP_Interaction to a string, producing
     *
     *  - Protocol
     *  - Headers
     *  - Body
     *
     * @return  string
     */
    public function render(): string
    {
        if (!$this->_header->offsetExists('content-type')) {
            // Add the default Content-Type header if required
            $this->_header['content-type'] = Core::$content_type . '; charset=' . Core::$charset;
        }

        // Set the content length
        $this->headers('content-length', (string)$this->contentLength());

        // If Modseven expose, set the user-agent
        if (Core::$expose) {
            $this->headers('user-agent', Core::version());
        }

        // Prepare cookies
        if ($this->_cookies) {
            if (extension_loaded('http')) {
                $cookies = version_compare(phpversion('http'), '2.0.0', '>=') ?
                    (string)new \http\Cookie($this->_cookies) :
                    http_build_cookie($this->_cookies);
                $this->_header['set-cookie'] = $cookies;
            } else {
                $cookies = [];

                // Parse each
                foreach ($this->_cookies as $key => $value) {
                    $string = $key . '=' . $value['value'] . '; expires=' . date('l, d M Y H:i:s T', $value['expiration']);
                    $cookies[] = $string;
                }

                // Create the cookie string
                $this->_header['set-cookie'] = $cookies;
            }
        }

        $output = $this->_protocol . ' ' . $this->_status . ' ' . static::$messages[$this->_status] . "\r\n";
        $output .= $this->_header;
        $output .= $this->_body;

        return $output;
    }

    /**
     * Returns the length of the body for use with
     * content header
     *
     * @return  integer
     */
    public function contentLength(): int
    {
        return strlen($this->body());
    }

    /**
     * Gets or sets the body of the response
     *
     * @param null|string $content Content to put into the body
     *
     * @return  mixed
     */
    public function body(?string $content = NULL)
    {
        if ($content === NULL) {
            return $this->_body;
        }

        $this->_body = $content;
        return $this;
    }

}
