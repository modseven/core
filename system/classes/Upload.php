<?php
/**
 * Upload helper class for working with uploaded files and [Validation].
 *
 *     $array = Validation::factory($_FILES);
 *
 * [!!] Remember to define your form with "enctype=multipart/form-data" or file
 * uploading will not work!
 *
 * The following configuration properties can be set:
 *
 * - [Upload::$remove_spaces]
 * - [Upload::$default_directory]
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

class Upload
{
    /**
     * remove spaces in uploaded files
     * @var boolean
     */
    public static bool $remove_spaces = true;

    /**
     * default upload directory
     * @var string
     */
    public static string $default_directory = 'upload';

    /**
     * Save an uploaded file to a new location. If no filename is provided,
     * the original filename will be used, with a unique prefix added.
     *
     * This method should be used after validating the $_FILES array:
     *
     * @param array $file uploaded file data
     * @param string $filename new filename
     * @param string $directory new directory
     * @param integer $chmod chmod mask
     *
     * @return  string|false  on success, full path to new file, on failure
     *
     * @throws Exception
     */
    public static function save(array $file, ?string $filename = NULL, ?string $directory = NULL, int $chmod = 0644)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            // Ignore corrupted uploads
            return FALSE;
        }

        if ($filename === NULL) {
            // Use the default filename, with a timestamp pre-pended
            $filename = uniqid('', false) . $file['name'];
        }

        if (static::$remove_spaces === TRUE) {
            // Remove spaces from the filename
            $filename = preg_replace('/\s+/u', '_', $filename);
        }

        if ($directory === NULL) {
            // Use the pre-configured upload directory
            $directory = static::$default_directory;
        }

        if (!is_dir($directory) || !is_writable(realpath($directory))) {
            throw new Exception('Directory :dir must be writable',
                [':dir' => Debug::path($directory)]);
        }

        // Make the filename into a complete path
        $filename = realpath($directory) . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($file['tmp_name'], $filename)) {
            // Set permissions on filename
            chmod($filename, $chmod);

            // Return new file path
            return $filename;
        }

        return FALSE;
    }

    /**
     * Tests if upload data is valid, even if no file was uploaded. If you
     * _do_ require a file to be uploaded, add the [Upload::not_empty] rule
     * before this rule.
     *
     * @param array $file $_FILES item
     * @return  bool
     */
    public static function valid(array $file): bool
    {
        return isset($file['error'], $file['name'], $file['type'], $file['tmp_name'], $file['size']);
    }

    /**
     * Test if an uploaded file is an allowed file type, by extension.
     *
     * @param array $file $_FILES item
     * @param array $allowed allowed file extensions
     * @return  bool
     */
    public static function type(array $file, array $allowed): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return true;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        return in_array($ext, $allowed, true);
    }

    /**
     * Validation rule to test if an uploaded file is allowed by file size.
     * File sizes are defined as: SB, where S is the size (1, 8.5, 300, etc.)
     * and B is the byte unit (K, MiB, GB, etc.). All valid byte units are
     * defined in Num::$byte_units
     *
     * @param array $file $_FILES item
     * @param string $size maximum file size allowed
     *
     * @return  bool
     *
     * @throws Exception
     */
    public static function size(array $file, string $size): bool
    {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE) {
            // Upload is larger than PHP allowed size (upload_max_filesize)
            return FALSE;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            // The upload failed, no size to check
            return TRUE;
        }

        // Convert the provided size to bytes for comparison
        $size = Num::bytes($size);

        // Test that the file is under or equal to the max size
        return ($file['size'] <= $size);
    }

    /**
     * Validation rule to test if an upload is an image and, optionally, is the correct size.
     *
     * @param array $file $_FILES item
     * @param integer $max_width maximum width of image
     * @param integer $max_height maximum height of image
     * @param boolean $exact match width and height exactly?
     * @return  boolean
     */
    public static function image(array $file, ?int $max_width = NULL, ?int $max_height = NULL, bool $exact = FALSE): bool
    {
        if (self::not_empty($file)) {

            // Get the width and height from the uploaded image
            [$width, $height] = getimagesize($file['tmp_name']);

            if (empty($width) || empty($height)) {
                // Cannot get image size, cannot validate
                return FALSE;
            }

            if (!$max_width) {
                // No limit, use the image width
                $max_width = $width;
            }

            if (!$max_height) {
                // No limit, use the image height
                $max_height = $height;
            }

            if ($exact) {
                // Check if dimensions match exactly
                return ($width === $max_width && $height === $max_height);
            }

            return ($width <= $max_width && $height <= $max_height);
        }

        return FALSE;
    }

    /**
     * Tests if a successful upload has been made.
     *
     * @param array $file $_FILES item
     * @return  bool
     */
    public static function not_empty(array $file): bool
    {
        return (isset($file['error'], $file['tmp_name'])
            && $file['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($file['tmp_name']));
    }

}
