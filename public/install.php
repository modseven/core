<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modseven Installation</title>
    <meta name="title" content="Modseven Installation">
    <meta name="description"
          content="The following tests have been run to determine if Modseven will work in your environment.">
    <style>
        body {
            margin: 0;
            background-color: #efefef;
            font-family: 'Arial', sans-serif;
        }

        .container {
            max-width: 800px;
            margin: 10px auto;
            background: #fff;
            padding: 20px 15px;
            border-radius: 12px;
        }

        table {
            margin: 20px 0;
        }

        h1,
        th {
            margin: 5px 0;
            color: #2f2f2f;
        }

        p,
        td {
            margin: 10px 0;
            color: #383838;
        }

        a,
        a:visited {
            color: #3195f0;
            text-decoration: none;
        }

        th {
            text-align: left;
            width: 30%;
        }

        td,
        th {
            padding: 5px 0;
        }

        .logo {
            background-image: url(data:image/png;base64,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);
            width: 80px;
            height: 40px;
            float: right;
        }

        .pass {
            color: #155724;
        }

        .warning {
            color: #d49907;
        }

        .fail {
            color: #721c24;
        }

        code {
            font-family: monospace;
            font-size: 15px;
            background: #f1f0f0;
        }

        .alert {
            padding: 20px;
            border-radius: 12px;
            border: 1px solid;
            margin-bottom: 20px;
        }

        .alert code {
            background: none;
        }

        .danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            margin-bottom: -6px;
            margin-right: 10px;
        }

        .icon-success {
            background-image: url(data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjMTU1NzI0IiBoZWlnaHQ9IjI0IiB2aWV3Qm94PSIwIDAgMjQgMjQiIHdpZHRoPSIyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4gICAgPHBhdGggZD0iTTAgMGgyNHYyNEgweiIgZmlsbD0ibm9uZSIvPiAgICA8cGF0aCBkPSJNOSAxNi4yTDQuOCAxMmwtMS40IDEuNEw5IDE5IDIxIDdsLTEuNC0xLjRMOSAxNi4yeiIvPjwvc3ZnPg==);
        }

        .icon-danger {
            background-image: url(data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjNzIxYzI0IiBoZWlnaHQ9IjI0IiB2aWV3Qm94PSIwIDAgMjQgMjQiIHdpZHRoPSIyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4gICAgPHBhdGggZD0iTTE5IDYuNDFMMTcuNTkgNSAxMiAxMC41OSA2LjQxIDUgNSA2LjQxIDEwLjU5IDEyIDUgMTcuNTkgNi40MSAxOSAxMiAxMy40MSAxNy41OSAxOSAxOSAxNy41OSAxMy40MSAxMnoiLz4gICAgPHBhdGggZD0iTTAgMGgyNHYyNEgweiIgZmlsbD0ibm9uZSIvPjwvc3ZnPg==);
        }
    </style>
</head>

<body>
<div class="container">
    <div class="logo"></div>

    <h1>Environment Tests</h1>
    <p>
        The following tests have been run to determine if Modseven will work in your environment. If any of the tests
        have failed, consult the <a href="https://docs.koseven.ga/guide/ko7/install" target="_blank">Koseven documentation</a>
        for more information on how to correct the problem.
    </p>

    <?php $failed = FALSE ?>

    <table>
        <tbody>
        <tr>
            <th>PHP Version</th>
            <?php if (PHP_VERSION_ID >= 80000): ?>
                <td class="pass">
                    <?php echo PHP_VERSION ?>
                </td>
            <?php else: $failed = TRUE ?>
                <td class="fail">Koseven requires PHP 8.0 or newer, this version is
                    <?php echo PHP_VERSION ?>.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Composer initialized</th>
            <?php if (is_dir(DOCROOT . 'vendor') && is_file(DOCROOT . 'vendor/autoload.php')): ?>
                <td class="pass">Pass</td>
            <?php else: $failed = TRUE ?>
                <td class="fail">The configured <code>vendor</code> directory does not exist or does not contain
                    required files. Please run <code>composer install</code> and refresh the page.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>System Directory</th>
            <?php if (is_dir(SYSPATH) && is_file(SYSPATH . 'classes/Core.php')): ?>
                <td class="pass">
                    <?php echo SYSPATH ?>
                </td>
            <?php else: $failed = TRUE ?>
                <td class="fail">The configured <code>system</code> directory does not exist or does not contain
                    required files.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Application Directory</th>
            <?php if (is_dir(APPPATH) && is_file(APPPATH . 'routes.php')): ?>
                <td class="pass">
                    <?php echo APPPATH ?>
                </td>
            <?php else: $failed = TRUE ?>
                <td class="fail">The configured <code>application</code> directory does not exist or does not contain
                    required files.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Cache Directory</th>
            <?php if (is_dir(APPPATH) && is_dir(APPPATH . 'cache') && is_writable(APPPATH . 'cache')): ?>
                <td class="pass">
                    <?php echo APPPATH . 'cache/' ?>
                </td>
            <?php else: $failed = TRUE ?>
                <td class="fail">The <code><?php echo APPPATH . 'cache/' ?></code> directory is not writable.</td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Logs Directory</th>
            <?php if (is_dir(APPPATH) && is_dir(APPPATH . 'logs') && is_writable(APPPATH . 'logs')): ?>
                <td class="pass">
                    <?php echo APPPATH . 'logs/' ?>
                </td>
            <?php else: $failed = TRUE ?>
                <td class="fail">The <code><?php echo APPPATH . 'logs/' ?></code> directory is not writable.</td>
            <?php endif ?>
        </tr>
        <tr>
            <th>PCRE UTF-8</th>
            <?php if (!@preg_match('/^.$/u', 'ñ')): $failed = TRUE ?>
                <td class="fail"><a href="http://php.net/pcre">PCRE</a> has not been compiled with UTF-8 support.</td>
            <?php elseif (!@preg_match('/^\pL$/u', 'ñ')): $failed = TRUE ?>
                <td class="fail"><a href="http://php.net/pcre">PCRE</a> has not been compiled with Unicode property
                    support.
                </td>
            <?php else: ?>
                <td class="pass">Pass</td>
            <?php endif ?>
        </tr>
        <tr>
            <th>SPL Enabled</th>
            <?php if (function_exists('spl_autoload_register')): ?>
                <td class="pass">Pass</td>
            <?php else: $failed = TRUE ?>
                <td class="fail">PHP <a href="http://www.php.net/spl">SPL</a> is either not loaded or not compiled in.</td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Reflection Enabled</th>
            <?php if (class_exists('ReflectionClass')): ?>
                <td class="pass">Pass</td>
            <?php else: $failed = TRUE ?>
                <td class="fail">PHP <a href="http://www.php.net/reflection">reflection</a> is either not loaded or not
                    compiled in.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Filters Enabled</th>
            <?php if (function_exists('filter_list')): ?>
                <td class="pass">Pass</td>
            <?php else: $failed = TRUE ?>
                <td class="fail">The <a href="http://www.php.net/filter">filter</a> extension is either not loaded or
                    not compiled in.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>URI Determination</th>
            <?php if (isset($_SERVER['REQUEST_URI']) || isset($_SERVER['PHP_SELF']) || isset($_SERVER['PATH_INFO'])): ?>
                <td class="pass">Pass</td>
            <?php else: $failed = TRUE ?>
                <td class="fail">Neither <code>$_SERVER['REQUEST_URI']</code>, <code>$_SERVER['PHP_SELF']</code>, or
                    <code>$_SERVER['PATH_INFO']</code> is available.
                </td>
            <?php endif ?>
        </tr>
        </tbody>
    </table>

    <?php if ($failed === TRUE): ?>
        <div id="results" class="alert danger">
            <span class="icon icon-danger"></span> Koseven may not work correctly with your environment.
        </div>
    <?php else: ?>
        <div id="results" class="alert success">
            <span class="icon icon-success"></span> Your environment passed all requirements. Remove or rename the
            <code>install.php</code> file now.
        </div>
    <?php endif ?>

    <h1>Optional Tests</h1>

    <p>
        The following extensions are not required to run the Modseven core, but if enabled can provide access to
        additional classes.
    </p>

    <table>
        <tbody>
        <tr>
            <th>cURL Enabled</th>
            <?php if (extension_loaded('curl')): ?>
                <td class="pass">Pass</td>
            <?php else: ?>
                <td class="warning">Koseven can use the <a href="http://php.net/curl">cURL</a> extension for external
                    requests.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>GD Enabled</th>
            <?php
            if (extension_loaded('gd') && version_compare(GD_VERSION, '2.0', '>=')):
                if (gd_info()['WebP Support'] && gd_info()['BMP Support']):
                    ?>
                    <td class="pass">Pass</td>
                <?php else: ?>
                    <td class="warning">For full support we recommend installing GD with 'webp' and 'bmp' support.</td>
                <?php endif; ?>
            <?php else: ?>
                <td class="warning">Koseven can use <a href="http://php.net/gd">GD</a> >= v2 for Image manipulation</td>
            <?php endif ?>
        </tr>
        <tr>
            <th>Imagick Enabled</th>
            <?php
            if (
                extension_loaded('imagick') &&
                preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', Imagick::getVersion()['versionString'], $api_version) === 1 &&
                version_compare($api_version[1], '6.9', '>=')
            ):
                if (Imagick::queryFormats('WEBP') && Imagick::queryFormats('BMP')):
                    ?>
                    <td class="pass">Pass</td>
                <?php else: ?>
                    <td class="warning">For full support we recommend installing imagick with 'webp' and 'bmp'
                        support.
                    </td>
                <?php endif; ?>
            <?php else: ?>
                <td class="warning">Koseven can use <a href="http://php.net/imagick">Imagick</a> >= v6.9 for Image
                    manipulation.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>MySQLi Enabled</th>
            <?php if (function_exists('mysqli_connect')): ?>
                <td class="pass">Pass</td>
            <?php else: ?>
                <td class="warning">Koseven can use the <a href="http://php.net/mysqli">MySQLi</a> extension to support
                    MySQL databases.
                </td>
            <?php endif ?>
        </tr>
        <tr>
            <th>PDO Enabled</th>
            <?php if (class_exists('PDO')): ?>
                <td class="pass">Pass</td>
            <?php else: ?>
                <td class="warning">Koseven can use <a href="http://php.net/pdo">PDO</a> to support additional databases.
                </td>
            <?php endif ?>
        </tr>
        <tr>
          <th>Redis Enabled</th>
            <?php if (extension_loaded('redis')): ?>
              <td class="pass">Pass</td>
            <?php else: ?>
              <td class="warning">Koseven can use <a href="https://redislabs.com/lp/php-redis/">Redis</a> can be used for Caching using Redis Driver.</td>
            <?php endif ?>
        </tr>
        </tbody>
    </table>

</div>
</body>

</html>
