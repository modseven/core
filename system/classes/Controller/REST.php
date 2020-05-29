<?php
/**
 * Abstract Controller class for REST controller mapping.
 * Supports GET, PUT, POST, and DELETE.
 *
 * @copyright  (c) 2016 - 2020 Koseven Team
 * @copyright  (c) since  2020 Modseven Team
 *
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Controller;

use \Modseven\View;
use \Modseven\Config;
use \Modseven\Controller;
use \Modseven\HTTP\Request;
use \Modseven\REST\Exception;
use \Modseven\REST\Formatter;

abstract class REST extends Controller
{
    /**
     * IF the formatter is html and a template is set, this will be the title sent to the template
     */
    protected string $_title = '';

    /**
     * Supported REST types and their corresponding actions
     * @var array
     */
    protected array $_action_map = [
        Request::GET => 'get',
        Request::PUT => 'put',
        Request::POST => 'post',
        Request::DELETE => 'delete'
    ];

    /**
     * Automatically executed before the controller action.
     * Evaluate Request (method, action, parameter, format)
     */
    public function before() : void
    {
        // Parent call
        parent::before();

        // Determine the request action from the request method, if the action/method is not allowed throw error
        // We need to do this because this module does not support all HTTP_Requests
        if ( ! isset($this->_action_map[$this->request->method()]))
        {
            $this->response
                ->status(405)
                ->headers('Allow', implode(', ', array_keys($this->_action_map)));
        }
        else
        {
            $this->request->action($this->_action_map[$this->request->method()]);
        }
    }

    /**
     * Automatically executed after the controller action.
     *
     * - Adds cache and content header(s).
     * - Formats body with given formatting method
     * - Adds attachment header if necessary
     *
     * @throws Exception
     * @throws \Modseven\HTTP\Exception
     */
    public function after() : void
    {
        // Parent call
        parent::after();

        // Parse Parameter
        $params = $this->_parse_params();

        // Some clients cannot handle HTTP responses different than 200
        if (isset($params['suppressResponseCodes']) && (bool)$params['suppressResponseCodes'] === true)
        {
            $body = $this->response->body();
            $body['responseCode'] = $this->response->status();
            $this->response->body($body);
            $this->response->status(200);
        }

        // Try initializing the formatter
        try
        {
            $formatter = Formatter::factory($this->request, $this->response);
        }
        catch (Exception $e)
        {
            throw \Modseven\HTTP\Exception::factory(500, $e->getMessage(), NULL, $e);
        }

        // No cache / must-revalidate cache if method is not GET
        if ($this->request->method() !== Request::GET)
        {
            $this->response->headers('cache-control', 'no-cache, no-store, max-age=0, must-revalidate');
        }

        // Format body
        $body = $formatter->format();

        // If the formatter is HTML and we have a template, render that
        $viewTemplate = Config::instance()->load('app')->get('view_template', '');
        if ($viewTemplate !== '' && $formatter->getExtensionType() === 'html')
        {
            $mainTemplate = View::factory($viewTemplate);
            $mainTemplate->set('title', $this->_title);
            $mainTemplate->set('body', $body);
            $body = $mainTemplate->render();
        }

        $this->response->body($body);

        // Support attachment header
        if (isset($params['attachment']))
        {
            try
            {
                $this->response->send_file(TRUE, $params['attachment'].'.'.($this->request->param('format') ?: $formatter->getExtensionType()));
            }
            catch (\Modseven\Exception $e)
            {
                throw new Exception($e->getMessage(), NULL, $e->getCode(), $e);
            }
        }
    }

    /**
     * Initializes the request params array based on the current request.
     *
     * @throws Exception
     *
     * @return array
     */
    protected function _parse_params() : array
    {
        // If method is GET, fetch params from query
        if ($this->request->method() === Request::GET)
        {
            return $this->request->query();
        }

        // Otherwise we have a PUT, POST, DELETE Method
        // If content_type is JSON we need to decode the body first
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== FALSE)
        {
            try
            {
                $parsed_body = json_decode($this->request->body(), true, 512, JSON_THROW_ON_ERROR);
            }
            catch (\JsonException $e)
            {
                throw new Exception($e->getMessage(). NULL, $e->getCode(), $e);
            }
        }
        else
        {
            parse_str($this->request->body(), $parsed_body);
        }

        return array_merge((array)$parsed_body, (array)$this->request->post());
    }

}