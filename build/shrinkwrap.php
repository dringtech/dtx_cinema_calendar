<?php
$PLUGIN_FILE = $argv[1];
function flatten($file) {
    $dir = dirname($file);
    ob_start();
    readfile(realpath($file));
    $source_code = ob_get_contents();
    ob_end_clean();
    $flattened_file = preg_replace_callback(
        '/(?:include|require)(?:_once){0,1}\s+[\'"](.*?)[\'"]\s*;/',
        function ($m) use ($dir) {
            $include_file = join('/', [$dir, $m[1]]);
            $included_code = flatten($include_file);
            return <<<INCLUDE
// START $m[0]
?>$included_code<?php
// END $m[0]
INCLUDE;
        },
        $source_code
    );
    return preg_replace('/\?>\s*<\?php/', '', $flattened_file);
}

echo flatten($PLUGIN_FILE);
?>