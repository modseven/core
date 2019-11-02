<?php
/**
 * RSS and Atom feed helper.
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

use function simplexml_load_string;

class Feed
{
    /**
     * Parses a remote feed into an array.
     *
     * @param string $feed remote feed URL
     * @param integer $limit item limit to fetch
     *
     * @return  array
     *
     * @throws Exception
     */
    public static function parse(string $feed, int $limit = 0): array
    {
        // Check if SimpleXML is installed
        if (!function_exists('simplexml_load_file')) {
            throw new Exception('SimpleXML must be installed!');
        }

        // Disable error reporting while opening the feed
        $error_level = error_reporting(0);

        // Allow loading by filename or raw XML string
        if (Valid::url($feed)) {
            // Use native Request client to get remote contents
            $feed = Request::factory($feed)->execute()->body();
        } elseif (is_file($feed)) {
            // Get file contents
            $feed = file_get_contents($feed);
        }

        // Load the feed
        $feed = simplexml_load_string($feed, 'SimpleXMLElement', LIBXML_NOCDATA);

        // Restore error reporting
        error_reporting($error_level);

        // Feed could not be loaded
        if ($feed === FALSE) {
            return [];
        }

        $namespaces = $feed->getNamespaces(TRUE);

        // Detect the feed type. RSS 1.0/2.0 and Atom 1.0 are supported.
        $feed = isset($feed->channel) ? $feed->xpath('//item') : $feed->entry;

        $i = 0;
        $items = [];

        foreach ($feed as $item) {
            if ($limit > 0 && $i++ === $limit) {
                break;
            }
            $item_fields = (array)$item;

            // get namespaced tags
            foreach ($namespaces as $ns) {
                $item_fields += (array)$item->children($ns);
            }
            $items[] = $item_fields;
        }

        return $items;
    }

    /**
     * Creates a feed from the given parameters.
     *
     * @param array $info feed information
     * @param array $items items to add to the feed
     * @param string $encoding define which encoding to use
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function create(array $info, array $items, string $encoding = 'UTF-8'): string
    {
        $info += ['title' => 'Generated Feed', 'link' => '', 'generator' => 'ModsevenPHP'];

        $feed = simplexml_load_string('<?xml version="1.0" encoding="' . $encoding . '"?><rss version="2.0"><channel></channel></rss>');

        foreach ($info as $name => $value) {
            if ($name === 'image') {
                // Create an image element
                $image = $feed->channel->addChild('image');

                if (!isset($value['link'], $value['url'], $value['title'])) {
                    throw new Exception('Feed images require a link, url, and title');
                }

                if (strpos($value['link'], '://') === FALSE) {
                    // Convert URIs to URLs
                    $value['link'] = URL::site($value['link'], 'http');
                }

                if (strpos($value['url'], '://') === FALSE) {
                    // Convert URIs to URLs
                    $value['url'] = URL::site($value['url'], 'http');
                }

                // Create the image elements
                $image->addChild('link', $value['link']);
                $image->addChild('url', $value['url']);
                $image->addChild('title', $value['title']);
            } else {
                if (($name === 'pubDate' || $name === 'lastBuildDate') && (is_int($value) || ctype_digit($value))) {
                    // Convert timestamps to RFC 822 formatted dates
                    $value = date('r', $value);
                } elseif (($name === 'link' || $name === 'docs') && strpos($value, '://') === FALSE) {
                    // Convert URIs to URLs
                    $value = URL::site($value, 'http');
                }

                // Add the info to the channel
                $feed->channel->addChild($name, $value);
            }
        }

        foreach ($items as $item) {
            // Add the item to the channel
            $row = $feed->channel->addChild('item');

            foreach ($item as $name => $value) {
                if ($name === 'pubDate' && (is_int($value) || ctype_digit($value))) {
                    // Convert timestamps to RFC 822 formatted dates
                    $value = date('r', $value);
                } elseif (($name === 'link' || $name === 'guid') && strpos($value, '://') === FALSE) {
                    // Convert URIs to URLs
                    $value = URL::site($value, 'http');
                }

                // Add the info to the row
                $row->addChild($name, $value);
            }
        }

        if (function_exists('dom_import_simplexml')) {
            // Convert the feed object to a DOM object
            $feed = dom_import_simplexml($feed)->ownerDocument;

            // DOM generates more readable XML
            $feed->formatOutput = TRUE;

            // Export the document as XML
            $feed = $feed->saveXML();
        } else {
            // Export the document as XML
            $feed = $feed->asXML();
        }

        return $feed;
    }

}
