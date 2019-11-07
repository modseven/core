<?php
/**
 * File helper class.
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

use finfo;

class File
{
    /**
     * Attempt to get the mime type from a file. This method is horribly
     * unreliable, due to PHP being horribly unreliable when it comes to
     * determining the mime type of a file.
     *
     * @param string $filename file name or path
     *
     * @return  string|FALSE  mime type on success
     *
     * @throws Exception
     */
    public static function mime(string $filename)
    {
        // Get the complete path to the file
        $filename = realpath($filename);

        // Get the extension from the filename
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (preg_match('/^(?:jpe?g|png|[gt]if|bmp|swf)$/', $extension)) {
            // Use getimagesize() to find the mime type on images
            $file = getimagesize($filename);

            if (isset($file['mime'])) {
                return $file['mime'];
            }
        }

        if (class_exists('finfo', false) && $info =
                new finfo(defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME)) {
            return $info->file($filename);
        }

        if (function_exists('mime_content_type') && ini_get('mime_magic.magicfile')) {
            // The mime_content_type function is only useful with a magic file
            return mime_content_type($filename);
        }

        if (!empty($extension)) {
            return self::mimeByExt($extension);
        }

        // Unable to find the mime-type
        return FALSE;
    }

    /**
     * Return the mime type of an extension.
     *
     * @param string $extension php, pdf, txt, etc
     *
     * @return  string|FALSE  mime type on success
     *
     * @throws Exception
     */
    public static function mimeByExt(string $extension)
    {
        // Load all of the mime types
        $mimes = Core::$config->load('mimes');

        return isset($mimes[$extension]) ? $mimes[$extension][0] : FALSE;
    }

    /**
     * Lookup MIME types for a file
     *
     * @param string $extension Extension to lookup
     *
     * @return array Array of MIMEs associated with the specified extension
     *
     * @throws Exception
     */
    public static function mimesByExt(string $extension): array
    {
        // Load all of the mime types
        $mimes = Core::$config->load('mimes');

        return isset($mimes[$extension]) ? ((array)$mimes[$extension]) : [];
    }

    /**
     * Lookup a single file extension by MIME type.
     *
     * @param string $type MIME type to lookup
     *
     * @return  string|false   First file extension matching or false
     *
     * @throws Exception
     */
    public static function extByMime(string $type)
    {
        $exts = self::extsByMime($type);

        if ($exts === FALSE) {
            return FALSE;
        }

        return current($exts);
    }

    /**
     * Lookup file extensions by MIME type
     *
     * @param string $type File MIME type
     *
     * @return  array|false   File extensions matching MIME type or false if none
     *
     * @throws Exception
     */
    public static function extsByMime(string $type)
    {
        static $types = [];

        // Fill the static array
        if (empty($types)) {
            foreach (Core::$config->load('mimes') as $ext => $mimes) {
                foreach ($mimes as $mime) {
                    if ($mime === 'application/octet-stream') {
                        // octet-stream is a generic binary
                        continue;
                    }

                    if (!isset($types[$mime])) {
                        $types[$mime] = [(string)$ext];
                    } elseif (!in_array($ext, $types[$mime], true)) {
                        $types[$mime][] = (string)$ext;
                    }
                }
            }
        }

        return $types[$type] ?? false;
    }

    /**
     * Split a file into pieces matching a specific size. Used when you need to
     * split large files into smaller pieces for easy transmission.
     *
     * @param string $filename file to be split
     * @param integer $piece_size size, in MB, for each piece to be
     * @return  integer The number of pieces that were created
     */
    public static function split(string $filename, int $piece_size = 10): int
    {
        // Open the input file
        $file = fopen($filename, 'rb');

        // Change the piece size to bytes
        $piece_size = floor($piece_size * 1024 * 1024);

        // Write files in 8k blocks
        $block_size = 1024 * 8;

        // Total number of pieces
        $pieces = 0;

        while (!feof($file)) {
            // Create another piece
            ++$pieces;

            // Create a new file piece
            $piece = str_pad($pieces, 3, '0', STR_PAD_LEFT);
            $piece = fopen($filename . '.' . $piece, 'wb+');

            // Number of bytes read
            $read = 0;

            do {
                // Transfer the data in blocks
                fwrite($piece, fread($file, $block_size));

                // Another block has been read
                $read += $block_size;
            } while ($read < $piece_size);

            // Close the piece
            fclose($piece);
        }

        // Close the file
        fclose($file);

        return $pieces;
    }

    /**
     * Join a split file into a whole file. Does the reverse of [File::split].
     *
     * @param string $filename split filename, without .000 extension
     * @return  integer The number of pieces that were joined.
     */
    public static function join(string $filename): int
    {
        // Open the file
        $file = fopen($filename, 'wb+');

        // Read files in 8k blocks
        $block_size = 1024 * 8;

        // Total number of pieces
        $pieces = 0;

        while (is_file($piece = $filename . '.' . str_pad($pieces + 1, 3, '0', STR_PAD_LEFT))) {
            // Read another piece
            ++$pieces;

            // Open the piece for reading
            $piece = fopen($piece, 'rb');

            while (!feof($piece)) {
                // Transfer the data in blocks
                fwrite($file, fread($piece, $block_size));
            }

            // Close the piece
            fclose($piece);
        }

        return $pieces;
    }

}
