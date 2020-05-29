<?php
/**
 * Class for formatting REST-Response bodies as HTML
 *
 * @copyright  (c) 2016 - 2020 Koseven Team
 * @copyright  (c) since  2020 Modseven Team
 *
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\REST\Formatter;

use Modseven\View;
use Modseven\REST\Exception;
use Modseven\REST\Formatter;

class HTML extends Formatter
{
    /**
     * Format function
     *
     * @throws Exception
     *
     * @return string
     */
    public function format() : string
    {
        // Filter the path parts, remove empty ones
        $path = array_filter([
             $this->_request->directory(),
             $this->_request->controller()
         ]);

        $path = strtolower(implode(DIRECTORY_SEPARATOR, $path));

        // Try to initialize View
        try
        {
            return View::factory($path,  $this->_body)->render();
        }
        catch (\Modseven\Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }

}