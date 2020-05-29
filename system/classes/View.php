<?php
/**
 * Acts as an object wrapper for HTML pages with embedded PHP, called "views".
 * Variables can be assigned with the view object and referenced locally within
 * the view.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 * @category       Driver
 *
 * @package        Modseven
 */

namespace Modseven;

use Traversable;

class View
{
    // View filename
    protected static array $_global_data = [];

    // Array of local variables
    protected $_file;

    // Array of global variables
    protected array $_data = [];

    /**
     * Sets the initial view filename and local data. Views should almost
     * always only be created using [View::factory].
     *
     * @param string $file view filename
     * @param array $data array of values
     *
     * @throws Exception
     */
    public function __construct(string $file = null, array $data = null)
    {
        if ($file !== null) {
            $this->setFilename($file);
        }

        if ($data !== null) {
            // Add the values to the current data
            $this->_data = $data + $this->_data;
        }
    }

    /**
     * Sets the view filename.
     *
     *     $view->set_filename($file);
     *
     * @param string $file view filename
     *
     * @return  self
     * @throws  View\Exception
     */
    public function setFilename(string $file): self
    {
        if (($path = Core::findFile('views', $file)) === false) {
            throw new View\Exception('The requested view :file could not be found', [
                ':file' => $file,
            ]);
        }

        // Store the file path locally
        $this->_file = $path;

        return $this;
    }

    /**
     * Returns a new View object. If you do not define the "file" parameter,
     * you must call [View::set_filename].
     *
     * @param string $file view filename
     * @param array $data array of values
     *
     * @return  View
     *
     * @throws Exception
     */
    public static function factory(string $file = null, array $data = null): View
    {
        return new self($file, $data);
    }

    /**
     * Sets a global variable, similar to [View::set], except that the
     * variable will be accessible to all views.
     *
     * @param string|array|Traversable $key variable name or an array of variables
     * @param mixed $value value
     */
    public static function setGlobal($key, $value = null): void
    {
        if (is_array($key) || $key instanceof Traversable) {
            foreach ($key as $name => $val) {
                static::$_global_data[$name] = $val;
            }
        } else {
            static::$_global_data[$key] = $value;
        }
    }

    /**
     * Assigns a global variable by reference, similar to [View::bind], except
     * that the variable will be accessible to all views.
     *
     * @param string $key variable name
     * @param mixed $value referenced variable
     */
    public static function bindGlobal(string $key, & $value): void
    {
        static::$_global_data[$key] =& $value;
    }

    /**
     * Magic method, searches for the given variable and returns its value.
     * Local variables will be returned before global variables.
     *
     *     $value = $view->foo;
     *
     * [!!] If the variable has not yet been set, an exception will be thrown.
     *
     * @param string $key variable name
     *
     * @return  mixed
     * @throws  Exception
     */
    public function & __get(string $key)
    {
        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }
        if (array_key_exists($key, static::$_global_data)) {
            return static::$_global_data[$key];
        }

        throw new Exception('View variable is not set: :var', [':var' => $key]);
    }

    /**
     * Magic method, calls [View::set] with the same parameters.
     *
     * @param string $key variable name
     * @param mixed $value value
     */
    public function __set(string $key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Assigns a variable by name. Assigned values will be available as a
     * variable within the view file.
     *
     * @param string|array|Traversable $key variable name or an array of variables
     * @param mixed $value value
     *
     * @return  self
     */
    public function set($key, $value = null): self
    {
        if (is_array($key) || $key instanceof Traversable) {
            foreach ($key as $name => $val) {
                $this->_data[$name] = $val;
            }
        } else {
            $this->_data[$key] = $value;
        }

        return $this;
    }

    /**
     * Magic method, determines if a variable is set.
     *
     * @param string $key variable name
     *
     * @return  boolean
     */
    public function __isset(string $key): bool
    {
        return (isset($this->_data[$key]) || isset(static::$_global_data[$key]));
    }

    /**
     * Magic method, unsets a given variable.
     *
     * @param string $key variable name
     */
    public function __unset(string $key): void
    {
        unset($this->_data[$key], static::$_global_data[$key]);
    }

    /**
     * Magic method, returns the output of [View::render].
     *
     * @return  string
     *
     * @throws Exception
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Exception $e) {
            /**
             * Display the exception message and halt script execution.
             *
             * We use this method here because it's impossible to throw an
             * exception from __toString().
             */
            Exception::handler($e);

            // This line will never ne reached
            return '';
        }
    }

    /**
     * Renders the view object to a string. Global and local data are merged
     * and extracted to create local variables within the view file.
     *
     *     $output = $view->render();
     *
     * [!!] Global variables with the same key name as local variables will be
     * overwritten by the local variable.
     *
     * @param string $file view filename
     *
     * @return  string
     *
     * @throws  View\Exception
     * @throws Exception
     */
    public function render(string $file = null): string
    {
        if ($file !== null) {
            $this->setFilename($file);
        }

        if (empty($this->_file)) {
            throw new View\Exception('You must set the file to use within your view before rendering');
        }

        // Combine local and global data and capture the output
        return self::capture($this->_file, $this->_data);
    }

    /**
     * Captures the output that is generated when a view is included.
     * The view data will be extracted to make local variables. This method
     * is static to prevent object scope resolution.
     *
     * @param string $modseven_view_filename filename
     * @param array $modseven_view_data variables
     *
     * @return  string
     *
     * @throws  Exception
     */
    protected static function capture(string $modseven_view_filename, array $modseven_view_data): string
    {
        // Import the view variables to local namespace
        extract($modseven_view_data, EXTR_SKIP);

        if (static::$_global_data) {
            // Import the global view variables to local namespace
            extract(static::$_global_data, EXTR_SKIP | EXTR_REFS);
        }

        // Capture the view output
        ob_start();

        try {
            // Load the view within the current scope
            include $modseven_view_filename;
        } catch (\Exception $e) {
            // Delete the output buffer
            ob_end_clean();

            // Re-throw the exception
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        // Get the captured output and close the buffer
        return ob_get_clean();
    }

    /**
     * Assigns a value by reference. The benefit of binding is that values can
     * be altered without re-setting them. It is also possible to bind variables
     * before they have values. Assigned values will be available as a
     * variable within the view file.
     *
     * @param string $key variable name
     * @param mixed $value referenced variable
     *
     * @return  self
     */
    public function bind(string $key, & $value): self
    {
        $this->_data[$key] =& $value;

        return $this;
    }

}
