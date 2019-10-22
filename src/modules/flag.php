<?php

function flag_conditional_render($flag, $template) {
    global $thisarticle, $dtx_screening_flags;

    $output = null;

    if (in_array($flag, $thisarticle['dtx']['flags'])) {
        $flag_label = $dtx_screening_flags[$flag]['label'];
        if ($template == null) {
            $template = $flag_label;
        }
        $output = parse($template);
    }
    return $output;

}
?>
