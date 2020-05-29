<?php
/**
 * Request Client for Internal Execution
 *
 * @package        Modseven\Driver
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 *
 */

namespace Modseven\Request\Client;

use ReflectionClass;
use Modseven\Core;
use Modseven\Profiler;
use Modseven\Request;
use Modseven\Request\Client;
use Modseven\Request\Exception;
use Modseven\Response;

class Internal extends Client
{
    /**
     * Processes the request, executing the controller action that handles this
     * request, determined by the [Route].
     *
     * @param Request $request Request Object
     * @param Response $response Response Object
     *
     * @return  Response
     */
    public function executeRequest(Request $request, Response $response): Response
    {
        // Controller
        $controller = $request->controller();

        // Namespace
        $namespace = $request->namesp();

        // Directory
        $directory = $request->directory();

        if (Core::$profiling) {
            // Set the benchmark name
            $benchmark = '"' . $request->uri() . '"';

            if ($request !== Request::$initial && Request::$current) {
                // Add the parent request uri
                $benchmark .= ' « "' . Request::$current->uri() . '"'; // @codeCoverageIgnore
            }

            // Start benchmarking
            $benchmark = Profiler::start('Requests', $benchmark);
        }

        // Store the currently active request
        $previous = Request::$current;

        // Change the current request to this request
        Request::$current = $request;

        // Transform the controller name according to PSR-4
        $controller = ucfirst(str_replace('_', '\\', ltrim($controller, '\\')));

        // Convert Controller to full PSR-4 namespaced class
        $fqns = $namespace . '\\Controller\\';

        // Support directories
        if ($directory)
        {
            $fqns .= $directory . '\\';
        }

        // Full Controller Class with correct namespace
        $controller = $fqns . $controller;

        try {
            if (!class_exists($controller)) {
                throw \Modseven\HTTP\Exception::factory(404, 'The requested URL :uri was not found on this server.', [
                    ':uri' => $request->uri()
                ])->request($request);
            }

            // Load the controller using reflection
            $class = new ReflectionClass($controller);

            if ($class->isAbstract()) {
                throw new Exception('Cannot create instances of abstract :controller', [
                    ':controller' => $controller
                ]);
            }

            // Create a new instance of the controller
            $controller = $class->newInstance($request, $response);

            // Run the controller's execute() method
            $response = $class->getMethod('execute')->invoke($controller);

            if (!$response instanceof Response) {
                // Controller failed to return a Response.
                throw new Exception('Controller failed to return a Response');
            }
        } catch (\Modseven\HTTP\Exception $e) {
            // Store the request context in the Exception
            if ($e->request() === NULL) {
                $e->request($request); // @codeCoverageIgnore
            }

            // Get the response via the Exception
            $response = $e->getResponse();
        } catch (\Exception $e) {
            // Generate an appropriate Response object
            $response = Exception::_handler($e);
        }

        // Restore the previous request
        Request::$current = $previous;

        if (isset($benchmark)) {
            // Stop the benchmark
            Profiler::stop($benchmark);
        }

        // Return the response
        return $response;
    }
}
