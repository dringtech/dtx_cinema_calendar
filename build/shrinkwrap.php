<?php
$PLUGIN_FILE = $argv[1];
$GLOBALS['GIT_VERSION'] = preg_replace('/^v/', '', trim(shell_exec('git describe --always  --dirty')));
function flatten($file) {
    global $GIT_VERSION;
    $original_include_path = set_include_path(dirname($file));
    ob_start();
    readfile(realpath($file));
    $source_code = ob_get_contents();
    ob_end_clean();
    $flattened_file = preg_replace_callback(
        '/((?:include|require)(?:_once){0,1})\s+[\'"](.*?)[\'"]\s*;/',
        function ($m) {
            $action = $m[1];
            $include_file = stream_resolve_include_path($m[2]);
            $included_code = flatten($include_file);
            return <<<INCLUDE
/**
 * START $m[0]
 * $action -> $include_file
 */
?>$included_code<?php
/**
 * END $m[0]
 */
INCLUDE;
        },
        $source_code
    );
    set_include_path($original_include_path);   
    return preg_replace(
        [ '/\?>\s*<\?php/', '/@@GIT_VERSION@@/' ],
        [ '', $GIT_VERSION ],
        $flattened_file
    );
}

echo flatten($PLUGIN_FILE);
?>