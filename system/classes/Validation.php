<?php
/**
 * Array and variable validation.
 *
 * @package    Modseven
 * @category   Security
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use ArrayAccess;
use ReflectionException;
use ReflectionMethod;
use ReflectionFunction;
use Modseven\Valid;

class Validation implements ArrayAccess
{
    protected array $_bound = [];

    // Bound values
    protected array $_rules = [];

    // Field rules
    protected array $_labels = [];

    // Field labels
    protected array $_empty_rules = ['notEmpty', 'matches'];

    // Rules that are executed even when the value is empty
    protected array $_errors = [];

    // Error list, field => rule
    protected array $_data = [];

    /**
     * Sets the unique "any field" key and creates an ArrayObject from the
     * passed array.
     *
     * @param array $array array to validate
     */
    public function __construct(array $array)
    {
        $this->_data = $array;
    }

    /**
     * Creates a new Validation instance.
     *
     * @param array $array array to use for validation
     * @return  self
     */
    public static function factory(array $array): self
    {
        return new self($array);
    }

    /**
     * Throws an exception because Validation is read-only.
     * Implements ArrayAccess method.
     *
     * @param string $offset key to set
     * @param mixed $value value to set
     * @throws  Exception
     */
    public function offsetSet($offset, $value): void
    {
        throw new Exception('Validation objects are read-only.');
    }

    /**
     * Checks if key is set in array data.
     * Implements ArrayAccess method.
     *
     * @param string $offset key to check
     * @return  bool    whether the key is set
     */
    public function offsetExists($offset): bool
    {
        return isset($this->_data[$offset]);
    }

    /**
     * Throws an exception because Validation is read-only.
     * Implements ArrayAccess method.
     *
     * @param string $offset key to unset
     * @throws  Exception
     */
    public function offsetUnset($offset): void
    {
        throw new Exception('Validation objects are read-only.');
    }

    /**
     * Gets a value from the array data.
     * Implements ArrayAccess method.
     *
     * @param string $offset key to return
     * @return  mixed   value from array
     */
    public function offsetGet($offset)
    {
        return $this->_data[$offset];
    }

    /**
     * Copies the current rules to a new array.
     *
     * @param array $array new data set
     * @return  self
     */
    public function copy(array $array): self
    {
        // Create a copy of the current validation set
        $copy = clone $this;

        // Replace the data set
        $copy->_data = $array;

        return $copy;
    }

    /**
     * Returns the array of data to be validated.
     *
     * @return  array
     */
    public function data(): array
    {
        return $this->_data;
    }

    /**
     * Sets or overwrites the label name for a field.
     *
     * @param string $field field name
     * @param string $label label
     * @return  self
     */
    public function label(string $field, string $label): self
    {
        // Set the label for this field
        $this->_labels[$field] = $label;

        return $this;
    }

    /**
     * Sets labels using an array.
     *
     * @param array $labels list of field => label names
     * @return  self
     */
    public function labels(array $labels): self
    {
        $this->_labels = $labels + $this->_labels;

        return $this;
    }

    /**
     * Add rules using an array.
     *
     * @param string $field field name
     * @param array $rules list of callbacks
     * @return  self
     */
    public function rules(string $field, array $rules): self
    {
        foreach ($rules as $rule) {
            $this->rule($field, $rule[0], Arr::get($rule, 1));
        }

        return $this;
    }

    /**
     * Overwrites or appends rules to a field. Each rule will be executed once.
     * All rules must be string names of functions method names. Parameters must
     * match the parameters of the callback function exactly
     *
     * [!!] Errors must be added manually when using closures!
     *
     * @param string $field field name
     * @param callback $rule valid PHP callback or closure
     * @param array $params extra parameters for the rule
     * @return  self
     */
    public function rule(string $field, $rule, array $params = NULL): self
    {
        if ($params === NULL) {
            // Default to array(':value')
            $params = [':value'];
        }

        if (!isset($this->_labels[$field])) {
            // Set the field label to the field name
            $this->_labels[$field] = $field;
        }

        // Store the rule and params for this rule
        $this->_rules[$field][] = [$rule, $params];

        return $this;
    }

    /**
     * Executes all validation rules. This should
     * typically be called within an if/else block.
     *
     * @return  boolean
     *
     * @throws Exception
     */
    public function check(): bool
    {
        if (Core::$profiling === TRUE) {
            // Start a new benchmark
            $benchmark = Profiler::start('Validation', __FUNCTION__);
        }

        // New data set
        $data = $this->_errors = [];

        // Store the original data because this class should not modify it post-validation
        $original = $this->_data;

        // Get a list of the expected fields
        $expected = Arr::merge(array_keys($original), array_keys($this->_labels));

        // Import the rules locally
        $rules = $this->_rules;

        foreach ($expected as $field) {
            // Use the submitted value or NULL if no data exists
            $data[$field] = Arr::get($this, $field);

            if (isset($rules[TRUE])) {
                if (!isset($rules[$field])) {
                    // Initialize the rules for this field
                    $rules[$field] = [];
                }

                // Append the rules
                $rules[$field] = array_merge($rules[$field], $rules[TRUE]);
            }
        }

        // Overload the current array with the new one
        $this->_data = $data;

        // Remove the rules that apply to every field
        unset($rules[TRUE]);

        // Bind the validation object to :validation
        $this->bind(':validation', $this);
        // Bind the data to :data
        $this->bind(':data', $this->_data);

        // Execute the rules
        foreach ($rules as $field => $set) {
            // Get the field value
            $value = $this[$field];

            // Bind the field name and value to :field and :value respectively
            $this->bind([
                ':field' => $field,
                ':value' => $value,
            ]);

            foreach ($set as $array) {
                // Rules are defined as array($rule, $params)
                [$rule, $params] = $array;

                foreach ($params as $key => $param) {
                    if (is_string($param) && array_key_exists($param, $this->_bound)) {
                        // Replace with bound value
                        $params[$key] = $this->_bound[$param];
                    }
                }

                // Default the error name to be the rule (except array and lambda rules)
                $error_name = $rule;

                if (is_array($rule)) {
                    // Allows rule('field', array(':model', 'some_rule'));
                    if (is_string($rule[0]) && array_key_exists($rule[0], $this->_bound)) {
                        // Replace with bound value
                        $rule[0] = $this->_bound[$rule[0]];
                    }

                    // This is an array callback, the method name is the error name
                    $error_name = $rule[1];
                    $passed = call_user_func_array($rule, $params);
                } elseif (!is_string($rule)) {
                    // This is a lambda function, there is no error name (errors must be added manually)
                    $error_name = FALSE;
                    $passed = call_user_func_array($rule, $params);
                } elseif (method_exists(Valid::class, $rule)) {
                    // Use a method in this object
                    try
                    {
                        $method = new ReflectionMethod(Valid::class, $rule);
                    }
                    catch (ReflectionException $e)
                    {
                        throw new Exception($e->getMessage(), null, $e->getCode(), $e);
                    }
                    // Call static::$rule($this[$field], $param, ...) with Reflection
                    $passed = $method->invokeArgs(NULL, $params);
                } elseif (strpos($rule, '::') === FALSE) {
                    // Use a function call
                    try
                    {
                        $function = new ReflectionFunction($rule);
                    }
                    catch (ReflectionException $e)
                    {
                        throw new Exception($e->getMessage(), null, $e->getCode(), $e);
                    }

                    // Call $function($this[$field], $param, ...) with Reflection
                    $passed = $function->invokeArgs($params);
                } else {
                    // Split the class and method of the rule
                    [$class, $method] = explode('::', $rule, 2);

                    // Use a static method call
                    try
                    {
                        $method = new ReflectionMethod($class, $method);
                    }
                    catch (ReflectionException $e)
                    {
                        throw new Exception($e->getMessage(), null, $e->getCode(), $e);
                    }

                    // Call $Class::$method($this[$field], $param, ...) with Reflection
                    $passed = $method->invokeArgs(NULL, $params);
                }

                // Ignore return values from rules when the field is empty
                if (!in_array($rule, $this->_empty_rules, true) && !Valid::notEmpty($value)) {
                    continue;
                }

                if ($passed === FALSE && $error_name !== FALSE) {
                    // Add the rule to the errors
                    $this->error($field, $error_name, $params);

                    // This field has an error, stop executing rules
                    break;
                }
                if (isset($this->_errors[$field])) {
                    // The callback added the error manually, stop checking rules
                    break;
                }
            }
        }

        // Unbind all the automatic bindings to avoid memory leaks.
        unset($this->_bound[':validation'], $this->_bound[':data'], $this->_bound[':field'], $this->_bound[':value']);

        // Restore the data to its original form
        $this->_data = $original;

        if (isset($benchmark)) {
            // Stop benchmarking
            Profiler::stop($benchmark);
        }

        return empty($this->_errors);
    }

    /**
     * Bind a value to a parameter definition.
     *
     * @param mixed $key variable name or an array of variables
     * @param mixed $value value
     * @return  self
     */
    public function bind($key, $value = NULL): self
    {
        if (is_array($key)) {
            foreach ($key as $name => $val) {
                $this->_bound[$name] = $val;
            }
        } else {
            $this->_bound[$key] = $value;
        }

        return $this;
    }

    /**
     * Add an error to a field.
     *
     * @param string $field field name
     * @param string $error error message
     * @param array $params
     * @return  self
     */
    public function error(string $field, string $error, array $params = NULL): self
    {
        $this->_errors[$field] = [$error, $params];

        return $this;
    }

    /**
     * Returns the error messages. If no file is specified, the error message
     * will be the name of the rule that failed. When a file is specified, the
     * message will be loaded from "field/rule", or if no rule-specific message
     * exists, "field/default" will be used. If neither is set, the returned
     * message will be "file/field/rule".
     *
     * By default all messages are translated using the default language.
     * A string can be used as the second parameter to specified the language
     * that the message was written in.
     *
     * @param string $file file to load error messages from
     * @param mixed $translate translate the message
     * @return  array
     */
    public function errors(string $file = NULL, $translate = TRUE): array
    {
        if ($file === NULL) {
            // Return the error list
            return $this->_errors;
        }

        // Create a new message list
        $messages = [];

        foreach ($this->_errors as $field => $set) {
            [$error, $params] = $set;

            // Get the label for this field
            $label = $this->_labels[$field];

            if ($translate) {
                if (is_string($translate)) {
                    // Translate the label using the specified language
                    $label = I18n::get($label, NULL, $translate);
                } else {
                    // Translate the label
                    $label = I18n::get($label);
                }
            }

            // Start the translation values list
            $values = [
                ':field' => $label,
                ':value' => Arr::get($this, $field),
            ];

            if (is_array($values[':value'])) {
                // All values must be strings
                $values[':value'] = implode(', ', Arr::flatten($values[':value']));
            }

            if ($params) {
                foreach ($params as $key => $value) {
                    if (is_array($value)) {
                        // All values must be strings
                        $value = implode(', ', Arr::flatten($value));
                    } elseif (is_object($value)) {
                        // Objects cannot be used in message files
                        continue;
                    }

                    // Check if a label for this parameter exists
                    if (isset($this->_labels[$value])) {
                        // Use the label as the value, eg: related field name for "matches"
                        $value = $this->_labels[$value];

                        if ($translate) {
                            if (is_string($translate)) {
                                // Translate the value using the specified language
                                $value = I18n::get($value, NULL, $translate);
                            } else {
                                // Translate the value
                                $value = I18n::get($value);
                            }
                        }
                    }

                    // Add each parameter as a numbered value, starting from 1
                    $values[':param' . ($key + 1)] = $value;
                }
            }

            if ($message = Core::message($file, "{$field}.{$error}") AND is_string($message)) {
                // Found a message for this field and error
            } elseif ($message = Core::message($file, "{$field}.default") AND is_string($message)) {
                // Found a default message for this field
            } elseif ($message = Core::message($file, $error) AND is_string($message)) {
                // Found a default message for this error
            } elseif ($message = Core::message('validation', $error) AND is_string($message)) {
                // Found a default message for this error
            } else {
                // No message exists, display the path expected
                $message = "{$file}.{$field}.{$error}";
            }

            if ($translate) {
                if (is_string($translate)) {
                    // Translate the message using specified language
                    $message = I18n::get([$message, $values], NULL, $translate);
                } else {
                    // Translate the message using the default language
                    $message = I18n::get([$message, $values]);
                }
            } else {
                // Do not translate, just replace the values
                $message = strtr($message, $values);
            }

            // Set the message for this field
            $messages[$field] = $message;
        }

        return $messages;
    }

}
