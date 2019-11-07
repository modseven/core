<?php
// Unique error identifier
$error_id = uniqid('error', false);

use Modseven\Core;
use Modseven\Debug;
use Modseven\I18n;

?>
<style type="text/css">
    #modseven_error {
        background: #ddd;
        font-size: 1em;
        font-family: sans-serif;
        text-align: left;
        color: #111;
    }

    #modseven_error h1,
    #modseven_error h2 {
        margin: 0;
        padding: 1em;
        font-size: 1em;
        font-weight: normal;
        background: #911;
        color: #fff;
    }

    #modseven_error h1 a,
    #modseven_error h2 a {
        color: #fff;
    }

    #modseven_error h2 {
        background: #222;
    }

    #modseven_error h3 {
        margin: 0;
        padding: 0.4em 0 0;
        font-size: 1em;
        font-weight: normal;
    }

    #modseven_error p {
        margin: 0;
        padding: 0.2em 0;
    }

    #modseven_error a {
        color: #1b323b;
    }

    #modseven_error pre {
        overflow: auto;
        white-space: pre-wrap;
    }

    #modseven_error table {
        width: 100%;
        display: block;
        margin: 0 0 0.4em;
        padding: 0;
        border-collapse: collapse;
        background: #fff;
    }

    #modseven_error table td {
        border: solid 1px #ddd;
        text-align: left;
        vertical-align: top;
        padding: 0.4em;
    }

    #modseven_error div.content {
        padding: 0.4em 1em 1em;
        overflow: hidden;
    }

    #modseven_error pre.source {
        margin: 0 0 1em;
        padding: 0.4em;
        background: #fff;
        border: dotted 1px #b7c680;
        line-height: 1.2em;
    }

    #modseven_error pre.source span.line {
        display: block;
    }

    #modseven_error pre.source span.highlight {
        background: #f0eb96;
    }

    #modseven_error pre.source span.line span.number {
        color: #666;
    }

    #modseven_error ol.trace {
        display: block;
        margin: 0 0 0 2em;
        padding: 0;
        list-style: decimal;
    }

    #modseven_error ol.trace li {
        margin: 0;
        padding: 0;
    }

    .js .collapsed {
        display: none;
    }
</style>
<script type="text/javascript">
    document.documentElement.className = document.documentElement.className + ' js';

    function toggle(elem) {
        let display;
        elem = document.getElementById(elem);

        if (elem.style && elem.style['display'])
        // Only works with the "style" attr
            display = elem.style['display'];
        else if (elem.currentStyle)
        // For MSIE, naturally
            display = elem.currentStyle['display'];
        else if (window.getComputedStyle)
        // For most other browsers
            display = document.defaultView.getComputedStyle(elem, null).getPropertyValue('display');

        // Toggle the state of the "display" style
        elem.style.display = display === 'block' ? 'none' : 'block';
        return false;
    }
</script>
<html lang="en-US">
<body>
<div id="modseven_error">
    <h1>
                <span class="type">
                    <?php echo $class ?> [ <?php echo $code ?> ]:
                </span>
        <span class="message">
                    <?php echo htmlspecialchars((string)$message, ENT_QUOTES | ENT_IGNORE, Core::$charset, TRUE); ?>
                </span>
    </h1>
    <div id="<?php echo $error_id ?>" class="content">
        <p>
                    <span class="file">
                        <?php echo Debug::path($file) ?> [ <?php echo $line ?> ]
                    </span>
        </p>
        <?php echo Debug::source($file, $line) ?>
        <ol class="trace">
            <?php foreach (Debug::trace($trace) as $i => $step): ?>
                <li>
                    <p>
                            <span class="file">
                                <?php if ($step['file']): $source_id = $error_id . 'source' . $i; ?>
                                    <a href="#<?php echo $source_id ?>"
                                       onclick="return toggle('<?php echo $source_id ?>')">
                                        <?php echo Debug::path($step['file']) ?> [ <?php echo $step['line'] ?> ]
                                    </a>
                                <?php else: ?>
                                    {<?php echo I18n::get('PHP internal call') ?>}
                                <?php endif ?>
                            </span>
                        &raquo;
                        <?php echo $step['function'] ?>(
                        <?php if ($step['args']): $args_id = $error_id . 'args' . $i; ?>
                            <a href="#<?php echo $args_id ?>" onclick="return toggle('<?php echo $args_id ?>')">
                                <?php echo I18n::get('arguments') ?>
                            </a>
                        <?php endif ?>
                        )
                    </p>
                    <?php if (isset($args_id)): ?>
                        <div id="<?php echo $args_id ?>" class="collapsed">
                            <table>
                                <?php foreach ($step['args'] as $name => $arg): ?>
                                    <tr>
                                        <td><code><?php echo $name ?></code></td>
                                        <td>
                                            <pre><?php echo Debug::dump($arg) ?></pre>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </table>
                        </div>
                    <?php endif ?>
                    <?php if (isset($source_id)): ?>
                        <pre id="<?php echo $source_id ?>" class="source collapsed">
                                <code><?php echo $step['source'] ?></code>
                            </pre>
                    <?php endif ?>
                </li>
                <?php unset($args_id, $source_id); ?>
            <?php endforeach ?>
        </ol>
    </div>
    <h2>
        <a href="#<?php echo $env_id = $error_id . 'environment' ?>" onclick="return toggle('<?php echo $env_id ?>')">
            <?php echo I18n::get('Environment') ?>
        </a>
    </h2>
    <div id="<?php echo $env_id ?>" class="content collapsed">
        <?php $included = get_included_files() ?>
        <h3>
            <a href="#<?php echo $env_id = $error_id . 'environment_included' ?>"
               onclick="return toggle('<?php echo $env_id ?>')"><?php echo I18n::get('Included files') ?></a>
            (<?php echo count($included) ?>)
        </h3>
        <div id="<?php echo $env_id ?>" class="collapsed">
            <table>
                <?php foreach ($included as $file): ?>
                    <tr>
                        <td><code><?php echo Debug::path($file) ?></code></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
        <?php $included = get_loaded_extensions() ?>
        <h3>
            <a href="#<?php echo $env_id = $error_id . 'environment_loaded' ?>"
               onclick="return toggle('<?php echo $env_id ?>')"><?php echo I18n::get('Loaded extensions') ?></a>
            (<?php echo count($included) ?>)
        </h3>
        <div id="<?php echo $env_id ?>" class="collapsed">
            <table>
                <?php foreach ($included as $file): ?>
                    <tr>
                        <td><code><?php echo $file ?></code></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
        <?php foreach (['_SESSION', '_GET', '_POST', '_FILES', '_COOKIE', '_SERVER'] as $var): ?>
            <?php if (empty($GLOBALS[$var]) || !is_array($GLOBALS[$var])) continue ?>
            <h3>
                <a href="#<?php echo $env_id = $error_id . 'environment' . strtolower($var) ?>"
                   onclick="return toggle('<?php echo $env_id ?>')">$<?php echo $var ?></a>
            </h3>
            <div id="<?php echo $env_id ?>" class="collapsed">
                <table>
                    <?php foreach ($GLOBALS[$var] as $key => $value): ?>
                        <tr>
                            <td>
                                <code><?php echo htmlspecialchars((string)$key, ENT_QUOTES, Core::$charset, TRUE); ?></code>
                            </td>
                            <td>
                                <pre><?php echo Debug::dump($value) ?></pre>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
        <?php endforeach ?>
    </div>
</div>
</body>
</html>
