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

$plugin['version'] = '@@GIT_VERSION@@';
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
include_once 'modules/globals.php';

if (txpinterface === 'admin') {
    register_callback('dtx_cinema_calendar_install', 'plugin_lifecycle.dtx_cinema_calender', 'installed');
    // TODO: Should not do this every time... 
    dtx_cinema_calendar_install();
    add_privs('dtx_calendar_admin', '1'); // Publishers only
    register_tab('extensions', 'dtx_calendar_admin', 'Showings');
    register_callback('dtx_calendar_admin_gui', 'dtx_calendar_admin');
    register_callback('dtx_calendar_article_showing', 'article_ui', 'body');
}

/********************************************/
/* Setup Functions                          */
/********************************************/

function dtx_cinema_calendar_install() {
    global $dtx_screening_flags;
    $showings_table = 'dtx_showings';
    safe_create(
        $showings_table,
        "`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `movie_id` int(11) UNSIGNED NOT NULL,
        `date_time` datetime NOT NULL,
        PRIMARY KEY (id),
        KEY (movie_id)"
    );

    $flag_fields = array_keys($dtx_screening_flags);

    foreach ($flag_fields as $flag ) {
        if (!getRows("SHOW COLUMNS FROM $showings_table LIKE '$flag'")) {
            safe_alter(
                $showings_table,
                "ADD $flag boolean NOT NULL DEFAULT 0"
            );
        }
    }
}
include 'modules/admin.php';
include 'modules/database.php';
include 'modules/flag.php';
include 'modules/tags.php';

function dtx_icons_for_screening($screening) {
    global $dtx_screening_flags;
    $flags = array_keys($dtx_screening_flags);

    $makeIcon = function ($f) use ($dtx_screening_flags) {
        return '<span class="icon"><i class="fas fa-'.$dtx_screening_flags[$f]['icon'].'"></i></span>';
    };

    $icons = [];

    foreach ($flags as $f) {
        if ($screening[$f] == 1) $icons[] = $makeIcon($f);
    }

    return $icons;
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

    public function __construct($year, $month, $events, $cal_link, $debug = 0)
    {
        $this->debug = $debug;
        $this->events = $events;
        $this->cal_link = $cal_link;
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

            $link = $this->cal_link . "?" . join(a, $flt);
            $content = '<a href="' . $link . '">' . $content . '</a>';
        }

        if ($this->year == date('Y',$now) and $this->month == date('n',$now) and $theday == date('j',$now) ) {
            $cellclass[] = $this->cls_pfx.'today';
            if ( gps('d') == NULL ) {
                $cellclass[] = $this->cls_pfx.'selected';    
            }
        }
        if ($theday == gps('d')) {
            $cellclass[] = $this->cls_pfx.'selected';
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
        // global $pretext, $smd_calinfo,
        global $permlink_mode;

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
