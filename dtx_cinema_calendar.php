<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
# $plugin['name'] = 'abc_plugin';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;

$plugin['version'] = '0.1';
$plugin['author'] = 'Giles Dring';
$plugin['author_uri'] = 'http://dringtech.com/';
$plugin['description'] = 'Manage showing times for cinema';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
# $plugin['order'] = 5;

// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and non-AJAX admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the non-AJAX admin side
// 4 = admin+ajax   : only on admin side
// 5 = public+admin+ajax   : on both the public and admin side
# $plugin['type'] = 0;

// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

# $plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
	@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

h1. Textile-formatted help goes here

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

// Register the new tag with Textpattern.
\Txp::get('\Textpattern\Tag\Registry')
   ->register('dtx_showing_event')
   ->register('dtx_extra_details')
   ->register('dtx_showing_details')
   ->register('dtx_now');

/**
 * Tag to output hello world.
 */
function dtx_showing_event($atts, $thing = null)
{
    extract(lAtts(array(
        'from'       => '',
        'to'         => '',
        'details'    => '',
        'section'    => null,
        'rating'     => null,
        'wraptag'    => '',
        'break'      => '',
        'class'      => '',
    ), $atts));

    $events = dtx_get_events($details, $from, $to, $section, $rating);

    $out = dtx_render_articles($events, $thing);

    return doWrap($out, $wraptag, $break, $class);
}

function dtx_get_events($details, $earliest, $latest, $section = null, $rating = null)
{
    $events = dtx_get_showing_data($details, $section);
    $events = dtx_split_showings($events);
    $events = dtx_filter_showings($events, $earliest, $latest);
    $events = dtx_sort_showings($events);
    $events = dtx_augment_showings($events, $rating);
    return $events;
}

function dtx_get_showing_data($details, $section = null) {
    $columns = array();
    $columns[] = 'ID';
    $columns[] = "${details} AS showings";

    $filter = array();
    $filter[] = "TRIM(IFNULL(${details},'')) <> ''";
    if ($section) {
        $secFilter = 'AND Section IN ('
            . join(',', array_map('doQuote', doSlash(do_list($section))))
            . ')';
        $filter[] = $secFilter;
    }

    return safe_rows(join(',', $columns), 'textpattern', join(' ', $filter));
}

function dtx_split_showings($events) {
    $split_showings = function ($carry, $item) {
        $showings = do_list($item['showings'], ";");
        unset($item['showings']);

        $format_showing = function ($val) use ($item) {
            $rawData = do_list($val, ",");
            if ($rawData[0] == '') return NULL;
            try {
                $date = new DateTime($rawData[0]);
            } catch (Exception $e) {
                return NULL;
            }
            $flags = (sizeof($rawData) > 1) ? preg_split("/\s+/", $rawData[1]) : array();
            return array_merge( array(
                'Posted' => $date->format('Y-m-d H:i:s'),
                'uPosted' => $date->format('U'),
                'Flags' => $flags
            ), $item );
        };

        $output = array_map( $format_showing, $showings );
        $output = array_filter( $output, function ($item) { return $item != NULL; });
        return array_merge($carry, $output);
    };
    
    return array_reduce( $events, $split_showings, array() );
}

function dtx_filter_showings($events, $earliest = null, $latest = null) {
    $earliest = intval(safe_strtotime($earliest));
    $latest = intval(safe_strtotime($latest));

    $dtx_date_filter = function ($event) use ($earliest, $latest) {
        if (!$event['uPosted']) return false;
        $date = intval($event['uPosted']);
        return ($date >= intval($earliest)) && ($date <= intval($latest));
    };

    return array_filter($events, $dtx_date_filter);
}

function dtx_sort_showings($events) {
    array_multisort(array_column($events, 'Posted'), SORT_ASC, $events);
    return $events;
}

function dtx_augment_showings($events, $rating = null) {
    if (sizeof($events) < 1) return array();
    $fields = ['*'];
    $ids = join(',', doSlash(array_map(function ($item) { return $item['ID']; }, $events)));
    $filter = "ID IN (${ids})";
    $baseEvents = safe_rows(join(',',$fields), 'textpattern', $filter);
    $baseEvents = array_combine(array_map(function ($i) { return $i['ID']; }, $baseEvents), $baseEvents);

    return array_map(function ($ev) use ($baseEvents) { return array_merge($baseEvents[$ev['ID']], $ev); }, $events);
}

function dtx_render_articles($events, $thing = null) {

    if ($thing) {
        $render = function ($event) use ($thing) {
            global $thisarticle;
            populateArticleData($event);
            dtx_add_showing_extensions($event);
            return parse($thing);
        };    
    } else {
        $render = function ($event) {
            return href( $event['Posted'], permlinkurl($event), ' title="'.$event['Title'].'"' );
        };    
    }

    return array_map($render, $events);
}

function dtx_add_showing_extensions($event) {
    global $thisarticle;
    $thisarticle['dtx']['flags'] = $event['Flags'];
}

function dtx_showing_details($atts, $thing = null) {
    global $thisarticle;

    $flags = $thisarticle['dtx']['flags'];

    $makeIcon = function ($icon, $label) {
        return '<div class="icon"><i class="fas '.$icon.'"></i></div><div class="label">'.$label.'</div>';
    };

    $details = array(
        'A' => $makeIcon('fa-audio-description', 'Audio Description'),
        'S' => $makeIcon('fa-closed-captioning', 'Subtitled'),
        'PB' => $makeIcon('fa-baby-carriage', 'Parent & Baby'),
        '11' => $makeIcon('fa-mug-hot', 'Elevenses'),
    );

    $out = array_map(function ($flag) use ($details) {
        return $details[$flag];
    }, $flags);

    return doWrap($out, 'ul', 'li', 'showing-details');
}

function dtx_extra_details($atts, $thing = null) {
    global $thisarticle;

    extract(lAtts(array(
        'field' => null,
    ), $atts));

    if (!$field) return;
    return $field;
}

function dtx_now($atts)
{
    global $dateformat;

    extract(lAtts(array(
        'format' => $dateformat,
        'now'    => '',
        'offset' => '',
        'gmt'    => '',
        'lang'   => '',
        'fixed'  => 0,
    ), $atts));

    $theDay = (gps('d') && is_numeric(gps('d')) && !$fixed) ? (int)gps('d') : safe_strftime('%d');
    $theMonth = (gps('m') && is_numeric(gps('m')) && !$fixed) ? (int)gps('m') : safe_strftime('%m');
    $theYear = (gps('y') && is_numeric(gps('y')) && !$fixed) ? (int)gps('y') : safe_strftime('%Y');
    if ($now) {
        $now = str_replace("?month", date('F', mktime(12,0,0,$theMonth,$theDay,$theYear)), $now);
        $now = str_replace("?year", $theYear, $now);
        $now = str_replace("?day", $theDay, $now);
        $now = is_numeric($now) ? $now : strtotime($now);
    } else {
        $now = time();
    }

    if ($offset) {
        $now = strtotime($offset, $now);
    }

    $format = smd_cal_reformat_win($format, $now);
    return safe_strftime($format, $now, $gmt, $lang);
}

// Adapted from: http://php.net/manual/en/function.strftime.php
function dtx_cal_reformat_win($format, $ts = null)
{
    // Only Win platforms need apply
    if (!IS_WIN) {
        return $format;
    }

    if (!$ts) $ts = time();

    $mapping = array(
        '%C' => sprintf("%02d", date("Y", $ts) / 100),
        '%D' => '%m/%d/%y',
        '%e' => sprintf("%' 2d", date("j", $ts)),
        '%F' => '%Y-%m-%d',
        '%g' => smd_cal_iso_week('%g', $ts),
        '%G' => smd_cal_iso_week('%G', $ts),
        '%h' => '%b',
        '%l' => sprintf("%' 2d", date("g", $ts)),
        '%n' => "\n",
        '%P' => date('a', $ts),
        '%r' => date("h:i:s", $ts) . " %p",
        '%R' => date("H:i", $ts),
        '%s' => date('U', $ts),
        '%t' => "\t",
        '%T' => '%H:%M:%S',
        '%u' => ($w = date("w", $ts)) ? $w : 7,
        '%V' => smd_cal_iso_week('%V', $ts),
    );
    $format = str_replace(
        array_keys($mapping),
        array_values($mapping),
        $format
    );

    return $format;
}

# --- END PLUGIN CODE ---

?>