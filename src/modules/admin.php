<?php
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
            <td>$s[autistm_friendly]</td>
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
        <th>AF</th>
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
    global $dtx_screening_flags;
    pagetop('Showings');

    $screenings = dtx_get_screenings();

    foreach( $screenings as $a ) {
        $date_time = strftime("%c", date_create($a['date_time'])->getTimestamp());
        $flags = doWrap(array_map(function ($f) use ($a) {
            return $a[$f];
        }, array_keys($dtx_screening_flags)), '', 'td');
        $showings[] = <<<SHOWING
        <tr>
            <td><a href="?event=article&step=edit&ID=$a[ID]">$a[Title]</a></td>
            <td>$date_time</td>
            $flags
            <td><a href="?event=dtx_calendar_admin&step=dtx_calendar_edit_showing&showing_id=$a[showing_id]">Edit</a></td>
        </tr>
SHOWING;
    }

    $showings = join("", $showings);
    $flag_titles = doWrap(array_map(function ($f) {
        return $f[label];
    }, array_values($dtx_screening_flags)), '', 'th');

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
            $flag_titles
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
        $movies_data = safe_rows(
            "id,title,url_title,section,posted",
            "textpattern",
            "section IN ('movies', 'coming-soon', 'events')");
        $label = array_map(
            function ($a) {
                return strtr('id: title section/url_title (posted)', $a);
            },
            $movies_data
        );
        $movies = array_combine(
            $label,
            array_column($movies_data, 'id')
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
            <form class="txp-tabs-vertical-group ui-tabs-panel">
                <div class="txp-form-field">
                    <label class="txp-form-field-label" for="movie_name">Search for movie</label>
                    <input class="txp-form-field-value" id="movie_name" name="movie_name" list="movies" oninput="populateMovie()"></input>
                </div>
            </form>
            <form method='get'>
                <input type='hidden' name='event' value='dtx_calendar_admin'>
                <input type='hidden' readonly name='movie_id' id='movie_id'>
                <button name='step' value='dtx_calendar_add_showing'>Add showing</button>
            </form>
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
    global $dtx_screening_flags;

    pagetop('Showings');

    $id = $_GET['showing_id'];
    $movie_id = $_GET['movie_id'];

    if ($id) {
        $showing_data = get_one_showing($id);
        $date_time = date_create($showing_data[date_time]);
        $date = $date_time->format('Y-m-d');
        $time = $date_time->format('H:i');
        $movie_id = $showing_data[movie_id];
        $delete = "<input type='submit' class='txp-button' formaction='?event=dtx_calendar_admin&step=dtx_calendar_delete_showing&showing_id=$id' value='Delete showing'/>";
    }
    $movie_details = safe_row(
        'title,section,excerpt,url_title,posted',
        'textpattern', "id = '$movie_id'");

    $flagfield = function ($name) use ($showing_data, $dtx_screening_flags) {
        $checked = ($showing_data[$name] == 1) ? 'checked' : '';
        $label = $dtx_screening_flags[$name][label];
        $input = <<<ENDINPUT
            <div class="txp-layout-4col">
                <div class="txp-form-field">
                    <label class="txp-form-field-label" for="$name">$label &rarr;</label>
                    <input class="txp-form-field-checkbox" type="checkbox" id="$name" name="$name" $checked>
                </div>
            </div>
ENDINPUT;
        return $input;
    };

    $flags = array_map($flagfield, array_keys($dtx_screening_flags));
    $flags = join('', $flags);

    $page = <<<PAGEEND
        $searchbox
    
        <h2>$movie_details[title] - $movie_details[posted]</h2>
        <p>$movie_details[section]/$movie_details[url_title]</p>
        <div>$movie_details[excerpt]</div>
        <form method="post" action="?event=dtx_calendar_admin&step=dtx_calendar_save_showing"
            class="txp-tabs-vertical-group ui-tabs-panel">
            <input id="id" type="hidden" name="id" value="$id">
            <input id="movie_id" type="hidden" name="movie_id" value="$movie_id">
            <div class="txp-layout">
                <div class="txp-layout-2col">
                    <div class="txp-form-field">
                        <label class="txp-form-field-label" for="date">Date</label>
                        <input class="txp-form-field-value" type="date" name="date" value="$date">
                    </div>
                </div>
                <div class="txp-layout-2col">
                    <div class="txp-form-field">
                        <label class="txp-form-field-label" for="time">Time</label>
                        <input class="txp-form-field-value" type="time" name="time" value="$time">
                    </div>
                </div>
                <fieldset class="txp-layout-1col">
                    <legend>Showing attributes:</legend>
                    <div class="txp-layout">$flags</div>
                </fieldset>
                <button action="submit" class="txp-button">Save showing</button>
                $delete
            </div>
        </form>

PAGEEND;

    echo $page;
}

function dtx_calendar_save_showing() {
    global $dtx_screening_flags;
    $id = $_POST['id'];
    $movie_id = $_POST['movie_id'];
    $date_time = $_POST['date'] . ' ' . $_POST['time'];

    $update = [ "movie_id=$movie_id", "date_time='$date_time'" ];
    $flag_keys = array_keys($dtx_screening_flags);
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
?>
