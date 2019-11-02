<?php

// Get the latest logo contents
$data = base64_encode(file_get_contents('http://koseven.ga/media/img/ko7.png'));

// Create the logo file
file_put_contents('logo.php', "<?php
/**
 * KO7 Logo, base64_encoded PNG
 * 
 * @copyright  (c) Modseven Team
 * @license    https://koseven.ga/LICENSE
 */
return array('mime' => 'image/png', 'data' => '{$data}'); ?>", LOCK_EX);
