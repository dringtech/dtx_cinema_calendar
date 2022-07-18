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
    $deduplicate = false,
    $flags = null,
    $not_flags = null
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
  $date_time_filter = [];

  if ($earliest) {
      $start = date_create($earliest)->format('Y-m-d H:i:s');
      $filter[] = "dtx_showings.date_time >= '$start'";
      $date_time_filter[] = "i.date_time >= '$start'";
  }
  if ($latest) {
      $end = date_create($latest)->format('Y-m-d H:i:s');
      $filter[] = "dtx_showings.date_time < '$end'";
      $date_time_filter[] = "i.date_time < '$end'";
  }
  if ($section) {
      $secFilter = 'textpattern.section IN ('
          . join(',', array_map('doQuote', doSlash(do_list($section))))
          . ')';
      $filter[] = $secFilter;
  }
  if ($flags) {
      $flags = explode(',', $flags);
      $flagsFilter = array_map(function ($f) { return "${f}=1"; }, $flags);
      $filter = array_merge($filter, $flagsFilter);
  }
  if ($not_flags) {
      $not_flags = explode(',', $not_flags);
      $notFlagsFilter = array_map(function ($f) { return "${f}=0"; }, $not_flags);
      $filter = array_merge($filter, $notFlagsFilter);
  }

  $filter = join(' AND ', $filter);
  if ($filter) $filter = 'WHERE ' . $filter;
  $date_time_filter = join(' AND ', $date_time_filter);
  if ($date_time_filter) $date_time_filter = 'AND ' . $date_time_filter;
  $dedup = 'dtx_showings';
  if ($deduplicate) $dedup = <<<DEDUP
  (
    SELECT
    (
      SELECT id
      FROM dtx_showings AS i WHERE i.movie_id = m.movie_id $date_time_filter
      ORDER BY i.date_time asc
      LIMIT 1
    ) AS id
    FROM (
      SELECT DISTINCT movie_id
      FROM dtx_showings AS _m
    ) AS m
  ) AS r
  INNER JOIN dtx_showings ON (r.id = dtx_showings.id)
DEDUP;

  $query = <<<QUERY
    SELECT dtx_showings.*, $details
    FROM $dedup
    INNER JOIN textpattern ON (dtx_showings.movie_id = textpattern.id)
    $filter
    ORDER BY dtx_showings.date_time $sort LIMIT $limit
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
      // TODO Add extra fields to this? Needed?
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