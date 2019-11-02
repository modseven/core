<?php
/**
 * HTML helper class. Provides generic methods for generating various HTML
 * tags and making output HTML safe.
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

class HTML
{
    /**
     * preferred order of attributes
     * @var array
     */
    public static array $attribute_order = [
        'action',
        'method',
        'type',
        'id',
        'name',
        'value',
        'href',
        'src',
        'width',
        'height',
        'cols',
        'rows',
        'size',
        'maxlength',
        'rel',
        'media',
        'accept-charset',
        'accept',
        'tabindex',
        'accesskey',
        'alt',
        'title',
        'class',
        'style',
        'selected',
        'checked',
        'readonly',
        'disabled',
    ];

    /**
     * use strict XHTML mode?
     * @var boolean
     */
    public static bool $strict = true;

    /**
     * automatically target external URLs to a new window?
     * @var boolean
     */
    public static bool $windowed_urls = false;

    /**
     * Convert all applicable characters to HTML entities. All characters
     * that cannot be represented in HTML with the current character set
     * will be converted to entities.
     *
     * @param string $value string to convert
     * @param boolean $double_encode encode existing entities
     * @return  string
     */
    public static function entities(string $value, bool $double_encode = TRUE): string
    {
        return htmlentities($value, ENT_QUOTES, Core::$charset, $double_encode);
    }

    /**
     * Create HTML link anchors. Note that the title is not escaped, to allow
     * HTML elements within links (images, etc).
     *
     *     echo HTML::anchor('/user/profile', 'My Profile');
     *
     * @param string $uri URL or URI string
     * @param string $title link text
     * @param array $attributes HTML anchor attributes
     * @param mixed $protocol protocol to pass to URL::base()
     * @param boolean $index include the index page
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function anchor(string $uri, ?string $title = NULL, ?array $attributes = NULL, $protocol = NULL, bool $index = TRUE): string
    {
        if ($title === NULL) {
            // Use the URI as the title
            $title = $uri;
        }

        if ($uri === '') {
            // Only use the base URL
            try
            {
                $uri = URL::base($protocol, $index);
            }
            catch (\Exception $e)
            {
                throw new Exception($e->getMessage(), null, $e->getCode(), $e);
            }
        } elseif (strpos($uri, '://') !== FALSE || strncmp($uri, '//', 2) === 0) {
            if (static::$windowed_urls === TRUE && empty($attributes['target'])) {
                // Make the link open in a new window
                $attributes['target'] = '_blank';
            }
        } elseif ($uri[0] !== '#' && $uri[0] !== '?') {
            // Make the URI absolute for non-fragment and non-query anchors
            $uri = URL::site($uri, $protocol, $index);
        }

        // Add the sanitized link to the attributes
        $attributes['href'] = $uri;

        return '<a' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Compiles an array of HTML attributes into an attribute string.
     * Attributes will be sorted using HTML::$attribute_order for consistency.
     *
     * @param array $attributes attribute list
     * @return  string
     */
    public static function attributes(?array $attributes = NULL): string
    {
        if (empty($attributes)) {
            return '';
        }

        $sorted = [];
        foreach (static::$attribute_order as $key) {
            if (isset($attributes[$key])) {
                // Add the attribute to the sorted list
                $sorted[$key] = $attributes[$key];
            }
        }

        // Combine the sorted attributes
        $attributes = $sorted + $attributes;

        $compiled = '';
        foreach ($attributes as $key => $value) {
            if ($value === NULL) {
                // Skip attributes that have NULL values
                continue;
            }

            if (is_int($key)) {
                // Assume non-associative keys are mirrored attributes
                $key = $value;

                if (!static::$strict) {
                    // Just use a key
                    $value = FALSE;
                }
            }

            // Add the attribute key
            $compiled .= ' ' . $key;

            if ($value || static::$strict) {
                // Add the attribute value
                $compiled .= '="' . static::chars($value) . '"';
            }
        }

        return $compiled;
    }

    /**
     * Convert special characters to HTML entities. All untrusted content
     * should be passed through this method to prevent XSS injections.
     *
     *     echo HTML::chars($username);
     *
     * @param string $value string to convert
     * @param boolean $double_encode encode existing entities
     * @return  string
     */
    public static function chars(string $value, $double_encode = TRUE): string
    {
        return htmlspecialchars($value, ENT_QUOTES, Core::$charset, $double_encode);
    }

    /**
     * Creates an HTML anchor to a file. Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     * @param string $file name of file to link to
     * @param string $title link text
     * @param array $attributes HTML anchor attributes
     * @param mixed $protocol protocol to pass to URL::base()
     * @param boolean $index include the index page
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function file_anchor(string $file, ?string $title = NULL, ?array $attributes = NULL, $protocol = NULL, bool $index = FALSE): string
    {
        if ($title === NULL) {
            // Use the file name as the title
            $title = basename($file);
        }

        // Add the file link to the attributes
        $attributes['href'] = URL::site($file, $protocol, $index);

        return '<a' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an email (mailto:) anchor. Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     * @param string $email email address to send to
     * @param string $title link text
     * @param array $attributes HTML anchor attributes
     * @return  string
     */
    public static function mailto(string $email, ?string $title = NULL, ?array $attributes = NULL): string
    {
        if ($title === NULL) {
            // Use the email address as the title
            $title = $email;
        }

        return '<a href="&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email . '"' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates a style sheet link element.
     *
     * @param string $file file name
     * @param array $attributes default attributes
     * @param mixed $protocol protocol to pass to URL::base()
     * @param boolean $index include the index page
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function style(string $file, ?array $attributes = NULL, $protocol = NULL, bool $index = FALSE): string
    {
        if (strpos($file, '://') === FALSE && strncmp($file, '//', 2)) {
            // Add the base URL
            $file = URL::site($file, $protocol, $index);
        }

        // Set the stylesheet link
        $attributes['href'] = $file;

        // Set the stylesheet rel
        $attributes['rel'] = empty($attributes['rel']) ? 'stylesheet' : $attributes['rel'];

        // Set the stylesheet type
        $attributes['type'] = 'text/css';

        return '<link' . self::attributes($attributes) . ' />';
    }

    /**
     * Creates a script link.
     *
     * @param string $file file name
     * @param array $attributes default attributes
     * @param mixed $protocol protocol to pass to URL::base()
     * @param boolean $index include the index page
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function script(string $file, ?array $attributes = NULL, $protocol = NULL, bool $index = FALSE): string
    {
        if (strpos($file, '://') === FALSE && strncmp($file, '//', 2)) {
            // Add the base URL
            $file = URL::site($file, $protocol, $index);
        }

        // Set the script link
        $attributes['src'] = $file;

        // Set the script type
        $attributes['type'] = 'text/javascript';

        return '<script' . self::attributes($attributes) . '></script>';
    }

    /**
     * Creates a image link.
     *
     * @param string $file file name
     * @param array $attributes default attributes
     * @param mixed $protocol protocol to pass to URL::base()
     * @param boolean $index include the index page
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function image(string $file, ?array $attributes = NULL, $protocol = NULL, bool $index = FALSE): string
    {
        if (strpos($file, '://') === FALSE && strncmp($file, '//', 2) && strncmp($file, 'data:', 5)) {
            // Add the base URL
            $file = URL::site($file, $protocol, $index);
        }

        // Add the image link
        $attributes['src'] = $file;

        return '<img' . self::attributes($attributes) . ' />';
    }

}
