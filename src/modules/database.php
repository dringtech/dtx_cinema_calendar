<?php
/**
 * dtx_get_screenings
 */
function dtx_get_screenings(
    $details = null,
    $earliest = null,
    $latest = null,
    $section = null,
    $sort = 'ASC',
    $limit = '500',
    $deduplicate = false
) {
    if ($limit == NULL) $limit = 50;
    if ($sort == NULL) $sort = 'ASC';
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
  $dedup = 'dtx_showings.id';
  if ($deduplicate) $dedup = 'textpattern.ID';

  $query = <<<QUERY
SELECT dtx_showings.*, $details, MIN(dtx_showings.date_time) screening_time
    FROM dtx_showings LEFT JOIN (textpattern)
    ON (dtx_showings.movie_id = textpattern.id)
    $filter
    GROUP BY $dedup
    ORDER BY screening_time $sort LIMIT $limit
QUERY;
  
  $join = safe_query($query);
  $showings = [];
  global $dtx_screening_flags;
  $flags = array_keys($dtx_screening_flags);
  while ($a = nextRow($join)) {
      $date = date_create($a['date_time']);
      $a['showing_id'] = $a['id'];
      $a['Posted'] = $date->format('Y-m-d H:i:s');
      $a['Expires'] = null;
      $a['uPosted'] = $date->format('U');
      $a['Flags'] = array_filter( $flags, function ($f) use ($a) {
          return $a[$f] == 1;
      });
      $showings[] = $a;
  }
  return $showings;
}

function dtx_get_future_screenings_for_movie($id) {
  return safe_rows(
    '*',
    'dtx_showings',
    "movie_id = '$id' AND DATE(date_time) >= CURDATE() ORDER BY date_time"
  );
}

?>