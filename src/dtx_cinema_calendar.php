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

$plugin['version'] = '0.3.1';
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
$plugin['type'] = 1;

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

Documentation to come...

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

if (txpinterface === 'admin') {
    register_callback('dtx_cinema_calendar_install', 'plugin_lifecycle.dtx_cinema_calender', 'installed');
    // TODO: Should not do this every time... 
    dtx_cinema_calendar_install();
    add_privs('dtx_calendar_admin', '1'); // Publishers only
    register_tab('extensions', 'dtx_calendar_admin', 'Showings');
    register_callback('dtx_calendar_admin_gui', 'dtx_calendar_admin');
    register_callback('dtx_calendar_article_showing', 'article_ui', 'body');
}

// Register the new tag with Textpattern.
\Txp::get('\Textpattern\Tag\Registry')
   ->register('dtx_showing_event')
   ->register('dtx_calendar')
   ->register('dtx_extra_details')
   ->register('dtx_showing_details')
   ->register('dtx_now');


/********************************************/
/* Setup Functions                          */
/********************************************/

function dtx_cinema_calendar_install() {
    $showings_table = 'dtx_showings';
    safe_create(
        $showings_table,
        "`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `movie_id` int(11) UNSIGNED NOT NULL,
        `date_time` datetime NOT NULL,
        PRIMARY KEY (id),
        KEY (movie_id)"
    );

    $flag_fields = array(
        'subtitled',
        'audio_description',
        'elevenses',
        'parent_and_baby'
    );
    foreach ($flag_fields as $flag ) {
        if (!getRows("SHOW COLUMNS FROM $showings_table LIKE '$flag'")) {
            safe_alter(
                $showings_table,
                "ADD $flag boolean NOT NULL DEFAULT 0"
            );
        }
    }
}

/********************************************/
/* ADMIN FUNCTIONS                          */
/********************************************/

function dtx_calendar_article_showing($event, $step, $data, $rs) {
    $now = date_create()->format('Y-m-d');
    $screenings = safe_rows('*', 'dtx_showings', "movie_id = '$rs[ID]' AND date_time >= '$now'");
    $screenings = join('', array_map(function ($s) {
        $date_time = strftime("%c", date_create($s['date_time'])->getTimestamp());
        return <<<ENTRY
        <tr>
            <td><a href='?showing_id=$s[id]&event=dtx_calendar_admin&step=dtx_calendar_edit_showing'>$date_time</a></td>
            <td>$s[subtitled]</td>
            <td>$s[audio_description]</td>
            <td>$s[elevenses]</td>
            <td>$s[parent_and_baby]</td>
        </tr>
ENTRY;
    }, $screenings));
    $form = <<<SCREENINGS
    <label>Future Screenings</label>
    <table>
    <thead>
        <th>Date / Time</th>
        <th>S/T</th>
        <th>A/D</th>
        <th>11</th>
        <th>PB</th>
    </thead>
    $screenings
    </table>
    <p><a href="?movie_id=$rs[ID]&event=dtx_calendar_admin&step=dtx_calendar_add_showing">Add screening</a></p>
    </form>
SCREENINGS;
    return $data.$form;
}

function dtx_calendar_admin_gui($evt, $stp)
{
    if (!$stp or !in_array($stp, array(
        'dtx_calendar_list',
        'dtx_calendar_add_showing',
        'dtx_calendar_save_showing',
        'dtx_calendar_edit_showing',
        'dtx_calendar_delete_showing',
        'dtx_calendar_confirm_delete_showing'
    ))) {
        dtx_calendar_list();
    } else {
        $stp();
    }
}

function dtx_calendar_list() {
    pagetop('Showings');

    $screenings = dtx_get_screenings();

    foreach( $screenings as $a ) {
        $date_time = strftime("%c", date_create($a['date_time'])->getTimestamp());
        $showings[] = <<<SHOWING
        <tr>
            <td><a href="?event=article&step=edit&ID=$a[ID]">$a[Title]</a></td>
            <td>$date_time</td>
            <td>$a[subtitled]</td>
            <td>$a[audio_description]</td>
            <td>$a[elevenses]</td>
            <td>$a[parent_and_baby]</td>
            <td><a href="?event=dtx_calendar_admin&step=dtx_calendar_edit_showing&showing_id=$a[showing_id]">Edit</a></td>
        </tr>
SHOWING;
    }

    $showings = join("", $showings);

    $page = <<<PAGEEND
    <nav>
        <form method="GET">
            <input type="hidden" name="event" value="dtx_calendar_admin" />
            <button type="submit" name="step" value="dtx_calendar_add_showing">Add showing</button>
        </form>
    </nav>

    <section>
    <h1>Movie Showings</h1>
    <table>
        <thead>
            <th>Movie</th>
            <th>Date and Time</th>
            <th>S/T</th>
            <th>A/D</th>
            <th>11</th>
            <th>PB</th>
            <th>Actions</td>
        </thead>
        <tbody>
        $showings
        </tbody>
    </table>
    </section>

PAGEEND;

    echo $page;
}

function get_one_showing($id) {
    return safe_row('*', 'dtx_showings', "id = $id");
}

function list_showing($id) {
    $showing = get_one_showing($id);
}

function dtx_calendar_add_showing() {
    $movie_id = $_GET[movie_id];

    if (!$movie_id) {
        pagetop('Showings');
        echo '<h1>Select movie</h1>';
        $movies_data = safe_rows("ID,Title", "textpattern", "Section IN ('movies')");
        $movies = array_combine(
            array_column($movies_data, 'Title'),
            array_column($movies_data, 'ID')
        );
        $movies_json = json_encode($movies);

        $datalist = join("", array_map(function ($key) {
            return '<option value="' . htmlentities($key, ENT_QUOTES | ENT_HTML5) . '">';
        }, array_keys($movies) ));
        $searchbox = <<<SEARCHBOX
            <datalist id="movies">$datalist</datalist>
            <script type="text/javascript">
                var options = $movies_json;
                function populateMovie() {
                    var selectedMovie = document.querySelector('#movie_name').value;
                    var movieId = options[selectedMovie];
                    document.querySelector('#movie_id').value = movieId;
                }
            </script>
            <label for="movie_name">Search for movie</label>
            <input id="movie_name" name="movie_name" list="movies" oninput="populateMovie()"></input>
            <section id="selected_movie">
                <form method='get'>
                    <input type='hidden' name='event' value='dtx_calendar_admin'>
                    <label for='movie_id'>Movie ID</label><input readonly name='movie_id' id='movie_id'>
                    <button type='hidden' name='step' value='dtx_calendar_add_showing'>Add showing</button>
                </form>
            </section>
SEARCHBOX;
        echo $searchbox;
    } else {
        echo '<h1>Add showing</h1>';
        dtx_calendar_showing_form();
    }
}

function dtx_calendar_edit_showing() {
    echo '<h1>Edit showing</h1>';
    dtx_calendar_showing_form();
}

function dtx_calendar_showing_form() {
    pagetop('Showings');

    $id = $_GET['showing_id'];
    $movie_id = $_GET['movie_id'];

    if ($id) {
        $showing_data = get_one_showing($id);
        $date_time = date_create($showing_data[date_time]);
        $date = $date_time->format('Y-m-d');
        $time = $date_time->format('H:i');
        $movie_id = $showing_data[movie_id];
        $delete = "<input type='submit' formaction='?event=dtx_calendar_admin&step=dtx_calendar_delete_showing&showing_id=$id' value='Delete showing'/>";
    }
    $movie_details = safe_row('title', 'textpattern', "id = '$movie_id'");

    $flagfield = function ($name, $label) use ($showing_data) {
        $checked = ($showing_data[$name] == 1) ? 'checked' : '';
        $input = <<<ENDINPUT
            <label for="$name">$label</label>
            <input type="checkbox" id="$name" name="$name" $checked>
ENDINPUT;
        return $input;
    };

    $flags[] = $flagfield('subtitled', 'Subtitled');
    $flags[] = $flagfield('audio_description', 'Audio Description');
    $flags[] = $flagfield('elevenses', 'Elevenses');
    $flags[] = $flagfield('parent_and_baby', 'Parent & Baby');
    $flags = join('', $flags);

    $page = <<<PAGEEND
        $searchbox
    
        <h2>$movie_details[title]</h2>
        <form method="post" action="?event=dtx_calendar_admin&step=dtx_calendar_save_showing">
            <input id="id" type="hidden" name="id" value="$id">
            <input id="movie_id" type="hidden" name="movie_id" value="$movie_id">
            <fieldset>
                <label for="date">Date</label>
                <input type="date" name="date" value="$date">
                <label for="time">Time</label>
                <input type="time" name="time" value="$time">
            </fieldset>
            <fieldset>
                <legend>Showing attributes:</legend>
                $flags
            </fieldset>
            <button action="submit">Save showing</button>
            $delete
        </form>

PAGEEND;

    echo $page;
}

function dtx_calendar_save_showing() {
    $id = $_POST['id'];
    $movie_id = $_POST['movie_id'];
    $date_time = $_POST['date'] . ' ' . $_POST['time'];

    $update = [ "movie_id=$movie_id", "date_time='$date_time'" ];
    $flag_keys = array('subtitled', 'audio_description', 'elevenses', 'parent_and_baby');
    foreach ( $flag_keys as $key ) {
        $value = $_POST[$key] == 'on' ? 1 : 0;
        $update[] = "$key='$value'";
    }
    $update = join(',', $update);

    if ($id) {
        safe_update('dtx_showings', $update, "id = '$id'");
    } else {
        safe_insert('dtx_showings', $update);    
    }

    // PRG redirect on completion
    // dtx_calendar_list();
    header('Location: ?event=dtx_calendar_admin&step=dtx_calendar_list', true, 303);
}

function dtx_calendar_delete_showing () {
    pagetop('Showing');
    $id = $_GET[showing_id];
    echo "<h1>Delete a showing</h1>";
    list_showing($id);
    $page = <<<PAGE
        <p>Are you sure you want to delete this showing?</p>
        <form method="post" action="?event=dtx_calendar_admin&step=dtx_calendar_confirm_delete_showing">
            <input type="hidden" name="showing_id" value="$id">
            <input type="submit" name="delete" value="Confirm" />
            <input type="submit" name="cancel" value="Cancel" />
        </form>
PAGE;

    echo $page;
}

function dtx_calendar_confirm_delete_showing () {
    $id = $_POST[showing_id];
    if ($_POST['delete'] == 'Confirm' && $id) {
        safe_delete('dtx_showings', "id = '$id'");
    }
    header('Location: ?event=dtx_calendar_admin&step=dtx_calendar_list', true, 303);
}

/**
 * database query functions
 */
/**
 * dtx_get_screenings
 */
function dtx_get_screenings($details, $earliest, $latest, $section = null) {
    if ($details) {
        $details = '*,'.$details;
    } else {
        $details = '*';
    }

    $details = join(',', preg_filter('/^/', 'textpattern.', do_list($details)));
    // Compute filter
    $filter = [];
    if ($earliest) {
        $start = date_create($earliest)->format('Y-m-d H:i:s');
        $filter[] = "dtx_showings.date_time >= '$start'";
    }
    if ($latest) {
        $end = date_create($latest)->format('Y-m-d H:i:s');
        $filter[] = "dtx_showings.date_time < '$end'";
    }
    if ($section) {
        $secFilter = 'textpattern.Section IN ('
            . join(',', array_map('doQuote', doSlash(do_list($section))))
            . ')';
        $filter[] = $secFilter;
    }
    
    $filter = join(' AND ', $filter);
    if ($filter) $filter = 'WHERE ' . $filter;
    
    $join = safe_query( "SELECT
        dtx_showings.*, $details
        from dtx_showings LEFT JOIN (textpattern)
        ON (dtx_showings.movie_id = textpattern.id)
        $filter
        ORDER BY dtx_showings.date_time" );
    $showings = [];
    $flags = array(
        [ 'field' => 'audio_description', 'value' => 'A' ],
        [ 'field' => 'subtitled', 'value' => 'S' ],
        [ 'field' => 'parent_and_baby', 'value' => 'PB' ],
        [ 'field' => 'elevenses', 'value' => '11' ]
    );
    while ($a = nextRow($join)) {
        $date = date_create($a['date_time']);
        $a['showing_id'] = $a['id'];
        $a['Posted'] = $date->format('Y-m-d H:i:s');
        $a['Expires'] = null;
        $a['uPosted'] = $date->format('U');
        $a['Flags'] = array_map(function ($f) use ($a) {
            return ($a[$f['field']] == 1) ? $f['value'] : NULL;
        }, $flags);
        $showings[] = $a;
    }
    return $showings;
}

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
        'wraptag'    => '',
        'break'      => '',
        'class'      => '',
        'form'       => '',
    ), $atts));

    $events = dtx_get_screenings($details, $from, $to, $section);

    $out = dtx_render_articles($events, $thing);

    return doWrap($out, $wraptag, $break, $class);
}

function dtx_calendar($atts, $thing = null) {

    extract(lAtts(array(
        'details'    => null,
        'section'    => '',
        'navarrow'   => '&#60;, &#62;',
        'remap'      => '',
        'weekstart'  => 1,
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
    $calendar = new DTX_Calendar($year, $month, $events, $debug);
    $calendar->setFirstDayOfWeek($weekstart);
    $calendar->setNavInfo($navpclass,$navnclass,$navparr,$navnarr,$navid);
    $calendar->setRemap($dmap);
    $calendar->setEmptyClass('empty');
  
    return $calendar->display();
  }

class DTX_Calendar extends DTX_Raw_Calendar
{
    // Override Constructor
    // Permits multiple events to show per day
    var $section = '';
    var $category = '';
    var $size = '';
    var $debug = 0;
    var $events = array();

    public function __construct($year, $month, $events, $debug = 0)
    {
        $this->debug = $debug;
        $this->events = $events;
        parent::__construct($year,$month,$debug);
    }

    // Override dspDayCell to display stuff right
    function dspDayCell($theday)
    {
        $thedate = mktime(0, 0, 0, $this->month, $theday, $this->year);
        $todaysEvents = array_filter($this->events, function ($ev) use ($thedate) {
            return ($ev['uPosted'] >= $thedate) && ($ev['uPosted'] < ($thedate + 86400));
        });
        $eventCount = sizeof($todaysEvents);
        $now = time();

        $cellclass = array();
        $link = null;

        $content = $theday;

        if ($eventCount > 0) {
            $request = serverSet('REQUEST_URI');
            $redirect = serverSet('REDIRECT_URL');
            if (!empty($redirect) && ($request != $redirect) && is_callable('_l10n_set_browse_language')) {
                // MLP in da house: use the redirect URL instead
                $request = $redirect;
            }
            $urlp = parse_url($request);

            $cellclass[] = $this->cls_pfx.'event';
            $year = $this->year;
            $month = $this->month;

            $flt[] = "d=$theday";
            $flt[] = "m=$month";
            $flt[] = "y=$year";
            $flt[] = $this->remap['m']."=$month";
            $flt[] = $this->remap['y']."=$year";
    
            $link = $url . "?" . join(a, $flt);
            $content = '<a href="' . $link . '">' . $content . '</a>';
        }

        if ($this->year == date('Y',$now) and $this->month == date('n',$now) and $theday == date('j',$now) ) {
            $cellclass[] = $this->cls_pfx.'today';
        }

        $out = array();

        $out[] = $content;

        // }

        // // Amalgamate the event-level classes and cell-level classes if required
        // $runningclass = array_unique($runningclass);
        // if (in_array("cellplus", $this->cls_lev)) {
        //     $smd_cal_flag = array_merge($smd_cal_flag, $flags);
        // }

        // if ($this->cellform) {
        //     $thistime = mktime(0, 0, 0, $this->month, $theday, $this->year);
        //     $smd_calinfo['id'] = $this->tableID;
        //     $smd_date['y'] = $this->year;
        //     $smd_date['m'] = $this->month;
        //     $smd_date['w'] = strftime(smd_cal_reformat_win('%V', $thistime), $thistime);
        //     $smd_date['iy'] = strftime(smd_cal_reformat_win('%G', $thistime), $thistime);
        //     $smd_date['d'] = $theday;
        //     $reps = array(
        //         '{evid}' => join($this->event_delim, $evid),
        //         '{standard}' => join('',$fout['standard']),
        //         '{recur}' => join('',$fout['recur']),
        //         '{recurfirst}' => join('',$fout['recurfirst']),
        //         '{allrecur}' => join('',array_merge($fout['recur'], $fout['recurfirst'])),
        //         '{multifirst}' => join('',$fout['multifirst']),
        //         '{multiprev}' => join('',$fout['multiprev']),
        //         '{multi}' => join('',$fout['multilast']),
        //         '{multilast}' => join('',$fout['multilast']),
        //         '{allmulti}' => join('',array_merge($fout['multifirst'],$fout['multi'],$fout['multiprev'],$fout['multilast'])),
        //         '{cancel}' => join('',$fout['cancel']),
        //         '{extra}' => join('',$fout['extra']),
        //         '{events}' => join('',$out),
        //         '{numevents}' => $evcnt,
        //         '{day}' => $theday,
        //         '{dayzeros}' => str_pad($theday, 2, '0', STR_PAD_LEFT),
        //         '{weekday}' => ((is_array($this->dayNameFmt)) ? $this->dayNames[date('w',$thistime)] : strftime($this->dayNameFmt, $thistime)),
        //         '{weekdayabbr}' => strftime('%a', $thistime),
        //         '{weekdayfull}' => strftime('%A', $thistime),
        //         '{week}' => $smd_date['w'],
        //         '{month}' => $this->month,
        //         '{monthzeros}' => str_pad($this->month, 2, '0', STR_PAD_LEFT),
        //         '{monthname}' => ((is_array($this->mthNameFmt)) ? $this->mthNames[date('n',$thistime)] : strftime($this->mthNameFmt, $thistime)),
        //         '{monthnameabbr}' => strftime('%b', $thistime),
        //         '{monthnamefull}' => strftime('%B', $thistime),
        //         '{year}' => $this->year,
        //         '{shortyear}' => strftime('%y', $thistime),
        //         '{isoyear}' => $smd_date['iy'],
        //         '{shortisoyear}' => strftime(smd_cal_reformat_win('%g', $thistime), $thistime),
        //     );
        //     $cellout = parse(strtr($this->cellform, $reps));
        //     $carray = array_merge($runningclass, $smd_cal_ucls);
        //     $smd_cal_ucls = array();

        //     return doTag($cellout,'td',join(' ',$carray));
        // } else {
        return doTag(join('',$out),'td',join(' ',$cellclass));
        // }
    }

    function display()
    {
        $sum = ($this->tblSummary) ? ' summary="'.$this->tblSummary.'"' : '';
        $id = ($this->tableID) ? ' id="'.$this->tableID.'"' : '';
        $c[] = ($this->tblCaption) ? '<caption>'.$this->tblCaption.'</caption>' : '';
        $c[] = '<thead>';
        $c[] = $this->dspHeader();
        $c[] = $this->dspDayNames();
        $c[] = '</thead>';
        $c[] = $this->dspDayCells();

        return doTag(join('',$c),'table',$this->tableclass,$sum.$id);
    }

    function dspHeader()
    {
        // global $pretext, $smd_calinfo, $permlink_mode;

        $currmo = $this->month;
        $curryr = $this->year;
        $navpclass = $this->getNavInfo("pc");
        $navnclass = $this->getNavInfo("nc");
        $navparrow = $this->getNavInfo("pa");
        $navnarrow = $this->getNavInfo("na");
        $navid = $this->getNavInfo("id");
        $navpclass = ($navpclass) ? ' class="'.$navpclass.'"' : '';
        $navnclass = ($navnclass) ? ' class="'.$navnclass.'"' : '';

        $fopts = array();

        // $sec = (isset($smd_calinfo['s']) && !empty($smd_calinfo['s'])) ? $smd_calinfo['s'] : '';

        $filters = array();
        $filterHid = array();
        foreach($fopts as $key => $val) {
            $filters[] = $key.'='.$val;
            $filterHid[] = hInput($key, $val);
        }

        $selector[] = doTag($this->getMonthName(), 'span', (($this->mywraptag) ? '' : $this->myclass));
        $selector[] = doTag($curryr, 'span', (($this->mywraptag) ? '' : $this->myclass));

        $request = serverSet('REQUEST_URI');
        $redirect = serverSet('REDIRECT_URL');
        if (!empty($redirect) && ($request != $redirect) && is_callable('_l10n_set_browse_language')) {
            // MLP in da house: use the redirect URL instead
            $request = $redirect;
        }
        $urlp = parse_url($request);
        $action = $urlp['path'];

        if ($permlink_mode == 'messy') {
            $out = makeOut('id','s','c','q','pg','p','month');
            foreach($out as $key => $val) {
                if ($val) {
                    $filters[] = $key.'='.$val;
                    $filterHid[] = hInput($key, $val);
                }
            }
        }
        $filterHid = array_unique($filterHid);
        $filters = array_unique($filters);

        $extras = '';
        $selector = '<form action="'.$action.'" method="get"'.(($navid) ? ' id="'.$navid.'"' : '').'>'.doTag(join(sp, $selector).$extras, $this->mywraptag, $this->myclass).'</form>';
        $nav_back_link = $this->navigation($curryr, $currmo, '-', $filters, $urlp['path']);
        $nav_fwd_link  = $this->navigation($curryr, $currmo, '+', $filters, $urlp['path']);

        $nav_back = '<a href="'.$nav_back_link.'"'.$navpclass.'>'.$navparrow.'</a>';
        $nav_fwd  = '<a href="'.$nav_fwd_link.'"'.$navnclass.'>'.$navnarrow.'</a>';

        $c[] = doTag($nav_back,'th');
        $c[] = '<th colspan="5">'.$selector.'</th>';
        $c[] = doTag($nav_fwd,'th');

        return doTag(join('',$c),'tr', $this->cls_pfx.'navrow');
    }

    function navigation($year, $month, $direction, $flt, $url = '')
    {
        global $permlink_mode;

        if($direction == '-') {
            if($month - 1 < 1) {
                $month = 12;
                $year -= 1;
            } else {
                $month -= 1;
            }
        } else {
            if($month + 1 > 12) {
                $month = 1;
                $year += 1;
            } else {
                $month += 1;
            }
        }

        $flt[] = $this->remap['m']."=$month";
        $flt[] = $this->remap['y']."=$year";

        return $url . "?" . join(a, $flt);
    }
}

/**
 * Basic Calendar data and display
 * http://www.oscarm.org/static/pg/calendarClass/
 * @author Oscar Merida
 * @created Jan 18 2004
 */
class DTX_Raw_Calendar
{
    var $gmt = 1, $lang, $debug = 0;
    var $year, $eyr, $lyr, $month, $week;
    var $dayNameFmt, $mthNameFmt, $dayNames, $mthNames, $startDay, $endDay, $firstDayOfWeek = 0, $startOffset = 0;
    var $selectors, $selbtn, $selpfx, $selsfx;
    var $ISOWeekHead, $ISOWeekCell;
    var $cls_lev, $cls_pfx;
    var $evwraptag, $mywraptag;
    var $rowclass, $cellclass, $emptyclass, $isoclass, $myclass, $tableID, $tblSummary, $tblCaption;
    var $navpclass, $navnclass, $navparrow, $navnarrow, $navid;
    var $holidays, $cellform, $hdrform, $maintain, $remap;
    var $event_delim;

    /**
     * Constructor
     *
     * @param integer, year
     * @param integer, month
     * @return object
     */
    public function __construct($yr, $mo, $debug = 0)
    {
        $this->setDebug($debug);
        $this->setYear($yr);
        $this->setMonth($mo);
        $this->setClassPrefix('dtx_cal_');

        $this->startTime = strtotime( "$yr-$mo-01 00:00" );
        $this->startDay = date( 'D', $this->startTime );
        $this->endDay = date( 't', $this->startTime );
        $this->endTime = strtotime( "$yr-$mo-".$this->endDay." 23:59:59" );
        if ($this->debug) {
            echo "++ THIS MONTH'S RENDERED CALENDAR [ start stamp // end date // start day // end stamp // end date // end day number ] ++";
            dmp($this->startTime, date('Y-m-d H:i:s', $this->startTime), $this->startDay, $this->endTime, date('Y-m-d H:i:s', $this->endTime), $this->endDay);
        }
        $this->setNameFormat('%a', 'd');
        $this->setNameFormat('%B', 'm');
        $this->setFirstDayOfWeek(0);
        $this->setTableID('');
        $this->setTableClass('');
    }

    function useSelector($val)
    {
        return in_array($val, $this->selectors);
    }

    function getDayName($day)
    {
        return ($this->dayNames[$day%7]);
    }

    function getMonthName()
    {
        if (is_array($this->mthNameFmt)) {
            return $this->mthNames[date('n',$this->startTime)];
        } else {
            return strftime($this->mthNameFmt, $this->startTime);
        }
    }

    function getNavInfo($type)
    {
        $r = '';
        switch ($type) {
            case "id": $r = $this->navid; break;
            case "pc": $r = $this->navpclass; break;
            case "nc": $r = $this->navnclass; break;
            case "pa": $r = $this->navparrow; break;
            case "na": $r = $this->navnarrow; break;
        }
        return $r;
    }

    function setDebug($d)
    {
        $this->debug = $d;
    }

    function setGMT($b)
    {
        $this->gmt = $b;
    }

    function setLang($code)
    {
        $this->lang = $code;
    }

    function setSummary($txt)
    {
        $this->tblSummary = $txt;
    }

    function setCaption($txt)
    {
        $this->tblCaption = $txt;
    }

    function setCellForm($frm)
    {
        $this->cellform = $frm;
    }

    function setHdrForm($frm)
    {
        $this->hdrform = $frm;
    }

    function setTableID($id)
    {
        $this->tableID = $id;
    }

    function setYear($yr)
    {
        $this->year = $yr;
    }

    function setEYear($yr)
    {
        $this->eyr = $yr;
    }

    function setLYear($yr)
    {
        $this->lyr = $yr;
    }

    function setMonth($mth)
    {
        $this->month = (int)$mth;
    }

    function setWeek($wk)
    {
        if ($wk) {
            $wk = str_pad($wk, 2, '0', STR_PAD_LEFT);
            $this->week = $wk;
            $this->month = safe_strftime("%m", strtotime($this->year."W".$wk));
        }
    }

    function setNavKeep($ar)
    {
        $this->maintain = $ar;
    }

    function setRemap($map)
    {
        $this->remap = $map;
    }

    function setClassLevels($cls)
    {
        $this->cls_lev = $cls;
    }

    function setClassPrefix($cls)
    {
        $this->cls_pfx = $cls;
    }

    function setEventWraptag($wrap)
    {
        $this->evwraptag = $wrap;
    }

    function setMYWraptag($wrap)
    {
        $this->mywraptag = $wrap;
    }

    function setTableClass($cls)
    {
        $this->tableclass = ($cls) ? $this->cls_pfx.$cls : '';
    }

    function setRowClass($cls)
    {
        $this->rowclass = ($cls) ? $this->cls_pfx.$cls : '';
    }

    function setCellClass($cls)
    {
        $this->cellclass = ($cls) ? $this->cls_pfx.$cls : '';
    }

    function setEmptyClass($cls)
    {
        $this->emptyclass = ($cls) ? $this->cls_pfx.$cls : '';
    }

    function setISOWeekClass($cls)
    {
        $this->isoclass = ($cls) ? $this->cls_pfx.$cls : '';
    }

    function setDelim($dlm)
    {
        $this->event_delim = $dlm;
    }

    function setNavInfo($clsp, $clsn, $arrp, $arrn, $nid)
    {
        $this->navpclass = ($clsp) ? $this->cls_pfx.$clsp : '';
        $this->navnclass = ($clsn) ? $this->cls_pfx.$clsn : '';
        $this->navparrow = ($arrp) ? $arrp : '';
        $this->navnarrow = ($arrn) ? $arrn : '';
        $this->navid = ($nid) ? $this->cls_pfx.$nid : '';
    }

    function setMYClass($cls)
    {
        $this->myclass = ($cls) ? $this->cls_pfx.$cls : '';
    }

    function setHolidays($hols)
    {
        $this->holidays = $hols;
    }

    function setSelectors($sel, $btn)
    {
        foreach ($sel as $idx => $item) {
            $selparts = explode(":", $item);
            $sel[$idx] = $selparts[0];
            $this->selpfx[$selparts[0]] = (isset($selparts[1])) ? $selparts[1] : '';
            $this->selsfx[$selparts[0]] = (isset($selparts[2])) ? $selparts[2] : '';
        }
        $this->selectors = $sel;
        $this->selbtn = $btn;
    }

    function setFirstDayOfWeek($d)
    {
        $this->firstDayOfWeek = ((int)$d <= 6 and (int)$d >= 0) ? (int)$d : 0;
        $this->startOffset = date('w', $this->startTime) - $this->firstDayOfWeek;
        if ( $this->startOffset < 0 ) {
            $this->startOffset = 7 - abs($this->startOffset);
        }
    }

    /**
     *
     * frm: any valid PHP strftime() string or ABBR/FULL
     * typ: d to set day, m to set month format
     */
    function setNameFormat($frm, $typ = "d")
    {
        switch ($frm) {
            case "full":
            case "FULL":
                $fmt = ($typ == 'd') ? "%A" : "%B";
                break;
            case "abbr":
            case "ABBR":
                $fmt = ($typ == 'd') ? "%a" : "%b";
                break;
            default:
                if (strpos($frm, '%') === 0) {
                    $fmt = $frm;
                } else {
                    $frm = trim($frm, '{}');
                    $frm = do_list($frm);
                    $fmt = $frm;
                }
                break;
        }

        if ($typ == "d") {
            $this->dayNameFmt = $fmt;
            $this->dayNames = array();

            // This is done to make sure Sunday is always the first day of our array
            $start = 0;
            $end = $start + 7;
            $sunday = strtotime('1970-Jan-04 12:00:00');

            for($i=$start; $i<$end; $i++) {
                if (is_array($fmt)) {
                    $this->dayNames[] = $fmt[$i-$start];
                } else {
                    $this->dayNames[] = ucfirst(strftime($fmt, ($sunday + (86400*$i))));
                }
            }
        } else {
            $this->mthNameFmt = $fmt;
            $this->mthNames = array();
            for ($i=0; $i<12; $i++) {
                if (is_array($fmt)) {
                    $this->mthNames[$i+1] = $fmt[$i];
                }
            }
        }
    }

    /**
     * Displays the row of day names.
     *
     * @return string
     * @private
     */
    function dspDayNames()
    {
        $c[] = '<tr class="' . $this->cls_pfx . 'daynames">';

        $i = $this->firstDayOfWeek;
        $j = 0; // count number of days displayed
        $end = false;

        if ($this->showISOWeek) {
            $c[] = "<th>".$this->ISOWeekHead."</th>";
        }
        for($j = 0; $j<=6; $j++, $i++) {
            if($i == 7) { $i = 0; }
            $c[] = '<th>'.$this->getDayName($i)."</th>";
        }

        $c[] = '</tr>';
        return join('',$c);
    }

    /**
     * Displays all day cells for the month.
     *
     * @return string
     * @private
     */
    function dspDayCells()
    {
        $i = 0; // cell counter
        $emptyClass = $this->emptyclass;
        $isoClass = $this->isoclass;
        $rowClass = $this->rowclass;
        $rowClass = ($rowClass) ? ' class="'.$rowClass.'"' : '';

        $c[] = '<tr'.$rowClass.'>';

        // first display empty cells based on what weekday the month starts in
        for( $j=0; $j<$this->startOffset; $j++ )    {
            $i++;
            $c[] = '<td class="'.$emptyClass.'">&nbsp;</td>';
        } // end offset cells

        // write out the rest of the days, at each sunday, start a new row.
        for( $d=1; $d<=$this->endDay; $d++ ) {
            $i++;
            $c[] = $this->dspDayCell( $d );

            if ( $i%7 == 0 ) { $c[] = '</tr>'; }
            if ( $d<$this->endDay && $i%7 == 0 ) {
                $c[] = '<tr'.$rowClass.'>';
                if ($this->showISOWeek) {
                    // **Not** using safe_strtotime() here to cater for an operating timezone that differs from the server timezone.
                    // Probably should do this in other places too but no bugs have been filed yet so it can be done on a
                    // case-by-case basis
                    $theTime = strtotime($this->year."-".$this->month."-".(int)($d + 1) ." 00:00");
                    $reps = array(
                        '{week}' => date('W', $theTime),
                        '{month}' => date('n', $theTime),
                        '{year}' => date('Y', $theTime),
                        '{isoyear}' => date('o', $theTime),
                    );
                    $wkcell = strtr($this->ISOWeekCell, $reps);
                    $c[] = '<td class="'.$isoClass.'">'.$wkcell.'</td>';
                }
            }
        }
        // fill in the final row
        $left = 7 - ( $i%7 );
        if ( $left < 7) {
            for ( $j=0; $j<$left; $j++ )    {
              $c[] = '<td class="'.$emptyClass.'">&nbsp;</td>';
            }
            $c[] = "\n\t</tr>";
        }
        return '<tbody>' . join('',$c) . '</tbody>';
    }
}

function dtx_render_articles($events, $thing = null) {

    if ($thing) {
        $render = function ($event) use ($thing) {
            // global $thisarticle;
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

    $format = dtx_cal_reformat_win($format, $now);
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
