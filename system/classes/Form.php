<?php
/**
 * Form helper class. Unless otherwise noted, all generated HTML will be made
 * safe using the [HTML::chars] method. This prevents against simple XSS
 * attacks that could otherwise be triggered by inserting HTML characters into
 * form fields.
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class Form
{
    /**
     * Generates an opening HTML form tag.
     *
     *     // Form will submit back to the current page using POST
     *     echo Form::open();
     *
     *     // Form will submit to 'search' using GET
     *     echo Form::open('search', array('method' => 'get'));
     *
     *     // When "file" inputs are present, you must include the "enctype"
     *     echo Form::open(NULL, array('enctype' => 'multipart/form-data'));
     *
     * @param mixed $action form action, defaults to the current request URI, or [Request] class to use
     * @param array $attributes html attributes
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function open($action = NULL, ?array $attributes = NULL): string
    {
        if ($action instanceof Request) {
            // Use the current URI
            $action = $action->uri();
        }

        if (!$action) {
            // Allow empty form actions (submits back to the current url).
            $action = '';
        } elseif (strpos($action, '://') === FALSE && strncmp($action, '//', 2)) {
            // Make the URI absolute
            $action = URL::site($action);
        }

        // Add the form action to the attributes
        $attributes['action'] = $action;

        // Only accept the default character set
        $attributes['accept-charset'] = Core::$charset;

        if (!isset($attributes['method'])) {
            // Use POST method
            $attributes['method'] = 'post';
        }

        return '<form' . HTML::attributes($attributes) . '>';
    }

    /**
     * Creates the closing form tag.
     *
     * @return  string
     */
    public static function close(): string
    {
        return '</form>';
    }

    /**
     * Creates a hidden form input.
     *
     * @param string $name input name
     * @param string $value input value
     * @param array $attributes html attributes
     * @return  string
     */
    public static function hidden(string $name, ?string $value = NULL, ?array $attributes = NULL): string
    {
        $attributes['type'] = 'hidden';

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a form input. If no type is specified, a "text" type input will
     * be returned.
     *
     * @param string $name input name
     * @param string $value input value
     * @param array $attributes html attributes
     * @return  string
     */
    public static function input(string $name, ?string $value = NULL, ?array $attributes = NULL): string
    {
        // Set the input name
        $attributes['name'] = $name;

        // Set the input value
        $attributes['value'] = $value;

        if (!isset($attributes['type'])) {
            // Default type is text
            $attributes['type'] = 'text';
        }

        return '<input' . HTML::attributes($attributes) . ' />';
    }

    /**
     * Creates a password form input.
     *
     * @param string $name input name
     * @param string $value input value
     * @param array $attributes html attributes
     * @return  string
     */
    public static function password(string $name, ?string $value = NULL, ?array $attributes = NULL): string
    {
        $attributes['type'] = 'password';

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a file upload form input. No input value can be specified.
     *
     * @param string $name input name
     * @param array $attributes html attributes
     * @return  string
     */
    public static function file(string $name, ?array $attributes = NULL): string
    {
        $attributes['type'] = 'file';

        return self::input($name, NULL, $attributes);
    }

    /**
     * Creates a checkbox form input.
     *
     * @param string $name input name
     * @param string $value input value
     * @param boolean $checked checked status
     * @param array $attributes html attributes
     * @return  string
     */
    public static function checkbox(string $name, ?string $value = NULL, bool $checked = FALSE, ?array $attributes = NULL): string
    {
        $attributes['type'] = 'checkbox';

        if ($checked === TRUE) {
            // Make the checkbox active
            $attributes[] = 'checked';
        }

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a radio form input.
     *
     * @param string $name input name
     * @param string $value input value
     * @param boolean $checked checked status
     * @param array $attributes html attributes
     * @return  string
     */
    public static function radio(string $name, ?string $value = NULL, bool $checked = FALSE, ?array $attributes = NULL): string
    {
        $attributes['type'] = 'radio';

        if ($checked === TRUE) {
            // Make the radio active
            $attributes[] = 'checked';
        }

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a textarea form input.
     *
     * @param string $name textarea name
     * @param string $body textarea body
     * @param array $attributes html attributes
     * @param boolean $double_encode encode existing HTML characters
     * @return  string
     */
    public static function textarea(string $name, string $body = '', ?array $attributes = NULL, bool $double_encode = TRUE): string
    {
        // Set the input name
        $attributes['name'] = $name;

        // Add default rows and cols attributes (required)
        $attributes += ['rows' => 10, 'cols' => 50];

        return '<textarea' . HTML::attributes($attributes) . '>' . HTML::chars($body, $double_encode) . '</textarea>';
    }

    /**
     * Creates a select form input.
     *
     * [!!] Support for multiple selected options was added in v3.0.7.
     *
     * @param string $name input name
     * @param array $options available options
     * @param mixed $selected selected option string, or an array of selected options
     * @param array $attributes html attributes
     * @return  string
     */
    public static function select(string $name, ?array $options = NULL, $selected = NULL, ?array $attributes = NULL): string
    {
        // Set the input name
        $attributes['name'] = $name;

        if (is_array($selected)) {
            // This is a multi-select, god save us!
            $attributes[] = 'multiple';
        }

        if (!is_array($selected)) {
            if ($selected === NULL) {
                // Use an empty array
                $selected = [];
            } else {
                // Convert the selected options to an array
                $selected = [(string)$selected];
            }
        }

        if (empty($options)) {
            // There are no options
            $options = '';
        } else {
            foreach ($options as $value => $nm) {
                if (is_array($nm)) {
                    // Create a new optgroup
                    $group = ['label' => $value];

                    // Create a new list of options
                    $_options = [];

                    foreach ($nm as $_value => $_name) {
                        // Force value to be string
                        $_value = (string)$_value;

                        // Create a new attribute set for this option
                        $option = ['value' => $_value];

                        if (in_array($_value, $selected, true)) {
                            // This option is selected
                            $option[] = 'selected';
                        }

                        // Change the option to the HTML string
                        $_options[] = '<option' . HTML::attributes($option) . '>' . HTML::chars($_name, FALSE) . '</option>';
                    }

                    // Compile the options into a string
                    $_options = "\n" . implode("\n", $_options) . "\n";

                    $options[$value] = '<optgroup' . HTML::attributes($group) . '>' . $_options . '</optgroup>';
                } else {
                    // Force value to be string
                    $value = (string)$value;

                    // Create a new attribute set for this option
                    $option = ['value' => $value];

                    if (in_array($value, $selected, true)) {
                        // This option is selected
                        $option[] = 'selected';
                    }

                    // Change the option to the HTML string
                    $options[$value] = '<option' . HTML::attributes($option) . '>' . HTML::chars($nm, FALSE) . '</option>';
                }
            }

            // Compile the options into a single string
            $options = "\n" . implode("\n", $options) . "\n";
        }

        return '<select' . HTML::attributes($attributes) . '>' . $options . '</select>';
    }

    /**
     * Creates a submit form input.
     *
     * @param string $name input name
     * @param string $value input value
     * @param array $attributes html attributes
     * @return  string
     */
    public static function submit(string $name, string $value, ?array $attributes = NULL): string
    {
        $attributes['type'] = 'submit';

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a image form input.
     *
     * @param string $name input name
     * @param string $value input value
     * @param array $attributes html attributes
     * @param boolean $index add index file to URL?
     *
     * @return  string
     * @throws \Exception
     *
     */
    public static function image(string $name, string $value, ?array $attributes = NULL, bool $index = FALSE): string
    {
        if (!empty($attributes['src']) && strpos($attributes['src'], '://') === false && strncmp($attributes['src'],
                '//', 2)) {
            // Add the base URL
            $attributes['src'] = URL::base($index) . $attributes['src'];
        }

        $attributes['type'] = 'image';

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a button form input. Note that the body of a button is NOT escaped,
     * to allow images and other HTML to be used.
     *
     * @param string $name input name
     * @param string $body input value
     * @param array $attributes html attributes
     * @return  string
     */
    public static function button(string $name, string $body, ?array $attributes = NULL): string
    {
        // Set the input name
        $attributes['name'] = $name;

        return '<button' . HTML::attributes($attributes) . '>' . $body . '</button>';
    }

    /**
     * Creates a form label. Label text is not automatically translated.
     *
     * @param string $input target input
     * @param string $text label text
     * @param array $attributes html attributes
     * @return  string
     */
    public static function label(string $input, string $text = NULL, ?array $attributes = NULL): string
    {
        if ($text === NULL) {
            // Use the input name as the text
            $text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
        }

        // Set the label target
        $attributes['for'] = $input;

        return '<label' . HTML::attributes($attributes) . '>' . $text . '</label>';
    }

}
