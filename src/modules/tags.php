<?php
// Register the new tag with Textpattern.
\Txp::get('\Textpattern\Tag\Registry')
    ->register('dtx_calendar')
    ->register('dtx_now')
    ->register('dtx_showing_details')
    ->register('dtx_showing_event')
    ->register('dtx_showings_for_movie')
    ;

function dtx_showing_event($atts, $thing = null)
{
    extract(lAtts(array(
        'from'       => '',
        'to'         => '',
        'details'    => '',
        'section'    => null,
        'wraptag'    => '',
        'break'      => '',
        'class'      => '',
        'form'       => '',
        'sort'       => 'ASC',
        'limit'      => 50,
        'deduplicate' => '0',
    ), $atts));

    global $thisarticle;
    $entryArticle = $thisarticle;
    $events = dtx_get_screenings($details, $from, $to, $section, $sort, $limit, $deduplicate == 1 ? true : false );

    $out = dtx_render_articles($events, $thing);

    $rendered = doWrap($out, $wraptag, $break, $class);
    $thisarticle = $entryArticle;
    return $rendered;
}

function dtx_showings_for_movie($atts, $thing = null) {
    global $thisarticle;
    $movie_id = $thisarticle['thisid'];
    $screenings = dtx_get_future_screenings_for_movie($movie_id);

    if (count($screenings) == 0) return;

    $out[] = doWrap([
        doWrap([ 'Date', 'Time' ], 'tr', 'th')],
        'thead', ''
    );

    foreach ( $screenings as $s) {
        $date = date_create($s['date_time']);
        $body[] = dowrap([
            $date->format('l d F'),
            $date->format('g:ia') . doWrap(dtx_icons_for_screening($s), '', '')
        ], 'tr', 'td');
    }
    $out[] = doWrap($body, 'tbody', '');

    return doWrap( $out, 'table', '');
}

function dtx_calendar($atts, $thing = null) {
    extract(lAtts(array(
        'details'    => null,
        'section'    => '',
        'navarrow'   => '&#60;, &#62;',
        'remap'      => '',
        'weekstart'  => 1,
        'calendar_page' => null,
    ), $atts));
  
    // Handle nvaigation overrides
    $navarrow = do_list($navarrow);
    $navparr = $navarrow[0];
    $navnarr = (count($navarrow) > 1) ? $navarrow[1] : $navarrow[0];
    $navclass = do_list('navprev, navnext');
    $navpclass = $navclass[0];
    $navnclass = (count($navclass) > 1) ? $navclass[1] : $navclass[0];
    $navid = '';
  
    // Remap w/m/y to other vars if required
    $remap = do_list($remap);
    $dmap = array("y" => "y", "m" => "m", "w" => "w");
    foreach ($remap as $dpair) {
      $dpair = do_list($dpair, ':');
      $dmap[$dpair[0]] = (isset($dpair[1])) ? $dpair[1] : $dpair[0];
    }
  
    // Set date range
    $month = (gps($dmap['y']) && is_numeric(gps($dmap['m']))) ? (int)gps($dmap['m']) : safe_strftime('%m');
    $year = (gps($dmap['y']) && is_numeric(gps($dmap['y']))) ? (int)gps($dmap['y']) : safe_strftime('%Y');
  
    $firstDay = "${year}-${month}-1 00:00:00";
    $lastDay = "${year}-${month}-" . date('t', safe_strtotime($firstDay)) . ' 23:59:59';
    
    // Grab events in date range
    $events = dtx_get_screenings($details, $firstDay, $lastDay, $section);
  
    // Construct new calendar
    $calendar = new DTX_Calendar($year, $month, $events, $calendar_page, $debug);
    $calendar->setFirstDayOfWeek($weekstart);
    $calendar->setNavInfo($navpclass,$navnclass,$navparr,$navnarr,$navid);
    $calendar->setRemap($dmap);
    $calendar->setEmptyClass('empty');
  
    return $calendar->display();
}

function dtx_showing_details($atts, $thing = null){
    global $thisarticle, $dtx_screening_flags;

    extract(lAtts(array(
        'wraptag'    => 'ul',
        'break'      => 'li',
        'class'      => '',
        'breakclass' => '',
    ), $atts));

    $details = $dtx_screening_flags;

    $flags = array_map(function ($f) use ($details) {
        return $details[$f];
    }, array_filter($thisarticle['dtx']['flags']));

    $render = function ($flag) {
        return doTag($flag[icon], 'i', 'fas fa-' . $flag[icon]) . doTag($flag[label], 'p');
    };

    $out = array_map($render, $flags);
    return doWrap($out, $wraptag, $break, $class, $breakclass);
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

    $format = dtx_cal_reformat_win($format, $now);
    return safe_strftime($format, $now, $gmt, $lang);
}
?>