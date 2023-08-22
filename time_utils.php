<?php

function dayNameToChar($day) {
  switch($day) {
  case 'Sunday': return 'U';
  case 'Monday': return 'M';
  case 'Tuesday': return 'T';
  case 'Wednesday': return 'W';
  case 'Thursday': return 'R';
  case 'Friday': return 'F';
  case 'Saturday': return 'S';
  }
  return '';
}

function getDayChar($date_str) {
  return dayNameToChar(date("l",strtotime($date_str)));
}

function getNextMonth($date_str) {
  # warning: this function may skip over a month when the input date is towards the end of a month
  $dt = new DateTime($date_str);
  $dt->add(new DateInterval('P1M'));
  return $dt->format('Y-m-d');
}

function getPrevMonth($date_str) {
  # warning: this function may skip over a month when the input date is towards the end of a month
  $dt = new DateTime($date_str);
  $dt->sub(new DateInterval('P1M'));
  return $dt->format('Y-m-d');
}

function getNextDay($date_str) {
  $dt = new DateTime($date_str);
  $dt->add(new DateInterval('P1D'));
  return $dt->format('Y-m-d');
}

function getPrevDay($date_str) {
  $dt = new DateTime($date_str);
  $dt->sub(new DateInterval('P1D'));
  return $dt->format('Y-m-d');
}

function getWeekStart($day) {
  $orig_day = $day;
  for($i=0; $i<7; $i++) {
    if( date("l",strtotime($day)) == "Sunday" ) return $day;
    $day = getPrevDay($day);
  }
  return $orig_day;
}

function getNextWeek($day) {
  $orig_day = $day;
  for($i=0; $i<7; $i++) {
    $day = getNextDay($day);
    if( date("l",strtotime($day)) == "Sunday" ) return $day;
  }
  return $orig_day;
}

function getPrevWeek($day) {
  return getWeekStart(getPrevDay(getWeekStart($day)));
}

class TimePager {
  public $show;
  public $by_year;
  public $by_week;
  public $by_month;
  public $default_range;
  public $offer_weekly;
  public $start;
  public $end;
  public $next;
  public $prev;
  public $pre_title;
  public $time_title;

  function __construct($show,$default_range="monthly",$recent=null,$most_recent_bunch_stmt=null,$max_date=null) {
    $this->show = $show;

    $this->pre_title = "";

    $this->by_year = isset($_REQUEST["yearly"]) ? true : false;
    $this->by_week = !$this->by_year && isset($_REQUEST["weekly"]) ? true : false;
    $this->by_month = !$this->by_year && !$this->by_week && isset($_REQUEST["monthly"]) ? true : false;

    $this->default_range = $default_range;
    if( !$this->by_year && !$this->by_week && !$this->by_month ) {
      if( $default_range == "yearly" ) $this->by_year = true;
      else if( $default_range == "weekly" ) $this->by_week = true;
      else $this->by_month = true;
    }

    if( $recent ) {
      $this->start = date("Y-m-d");
    } else if( isset($_REQUEST["start"]) ) {
      $this->start = $_REQUEST["start"];
    } else if( $this->by_year ) {
      $this->start = date("Y-01-01");
    } else if( $this->by_week ) {
      $this->start = getWeekStart(date("Y-m-d"));
    } else {
      $this->start = date("Y-m-01");
    }

    if( $recent ) {
      $this->end = date("Y-m-d",strtotime($this->start . " +1 day"));
    } else if( isset($_REQUEST["end"]) ) {
      $this->end = $_REQUEST["end"];
    } else if( $this->by_year ) {
      $this->end = date("Y-01-01",strtotime($this->start . " +1 year"));
    } else if( $this->by_week ) {
      $this->end = getNextWeek($this->start);
    } else {
      $this->end = getNextMonth($this->start);
    }

    # adjust if necessary
    $this->by_year = ( $this->start == date("Y-01-01",strtotime($this->start)) && $this->end == date("Y-01-01",strtotime($this->start . " +1 year")) );
    $this->by_month = ( $this->start == date("Y-m-01",strtotime($this->start)) && $this->end == getNextMonth($this->start) );
    $this->by_week = ( $this->start == getWeekStart($this->start) && $this->end == getNextWeek($this->start) );

    if( $this->by_week ) {
      $this->offer_weekly = true;
    }

    $recent_bunch = false;
    if( $most_recent_bunch_stmt && !isset($_REQUEST["yearly"]) && !isset($_REQUEST["start"]) && !isset($_REQUEST["end"]) ) {
      $most_recent_bunch_stmt->execute();
      while( ($row=$most_recent_bunch_stmt->fetch()) ) {
        $this->start = $row["DATE"];
        $this->pre_title = "Most Recent";
        $recent_bunch = true;
      }
    }

    $this->next = $this->end;

    $now = date("Y-m-d");
    if( !$max_date ) {
      $max_date = $now;
    }
    if( $this->next > $max_date ) $this->next = "";

    if( $recent ) {
      $this->prev = date("Y-01-01");
    } else if( $this->by_year ) {
      $this->prev = date("Y-01-01",strtotime($this->start . " -1 year"));

      # if start was not at the beginning of the year, make prev the beginning of the same year rather than the beginning of the previous year
      $prev_next = date("Y-01-01",strtotime($this->prev . " +1 year"));
      if( $this->start != $prev_next ) {
        $this->prev = $prev_next;
      }
    } else if( $this->by_week ) {
      $this->prev = getPrevWeek($this->start);

      # if start was not at the beginning of the week, make prev the beginning of the same week rather than the beginning of the previous week
      if( $this->start != getWeekStart($this->start) ) {
        $this->prev = getWeekStart($this->start);
      }
    } else {
      $this->prev = date("Y-m-d",strtotime($this->start . " -1 month"));
    }

    if( $recent_bunch ) {
      $this->time_title = "";
    }
    else if( $this->by_year ) {
      $this->time_title = "in " . date("Y",strtotime($this->start));
    } else if( $this->by_week ) {
      $this->time_title = "in the week of " . date("F j, Y",strtotime($this->start));
    } else if( isset($_REQUEST["end"]) ) {
      $this->time_title = "in {$this->start} to {$this->end}";
    } else {
      $this->time_title = "in " . date("F, Y",strtotime($this->start));
    }
  }

  function offerWeeklyPaging($val) {
    $this->offer_weekly = $val;
  }

  function pageButtons($base_url) {
    if( strpos($base_url,'?') === false ) $base_url .= '?';
    $orig_base_url = $base_url;
    if( $this->by_year && $this->default_range != "yearly" ) {
      $base_url .= "&yearly";
    }
    else if( $this->by_week && $this->default_range != "weekly" ) {
      $base_url .= "&weekly";
    }
    else if( $this->by_month && $this->default_range != "monthly" ) {
      $base_url .= "&monthly";
    }

    $week_btn = "";
    if( $this->offer_weekly ) {
      $url = $this->by_week ? $base_url : ($this->default_range == "weekly" ? $orig_base_url : "{$orig_base_url}&weekly");
      $disabled = $this->start == getWeekStart(date("Y-m-d")) ? "disabled" : "";
      $week_btn = "<a href='" . htmlescape($url) . "' class='btn btn-primary noprint $disabled'>This Week</a>\n";
    }

    $month_btn = "";
    $url = $this->by_month ? $base_url : ($this->default_range == "monthly" ? $orig_base_url : "{$orig_base_url}&monthly");
    $disabled = $this->start == date("Y-m-01") ? "disabled" : "";
    $month_btn = "<a href='" . htmlescape($url) . "' class='btn btn-primary noprint $disabled'>This Month</a>\n";

    $url = $this->by_year ? $base_url : ($this->default_range == "yearly" ? $orig_base_url : "{$orig_base_url}&yearly");
    $year = date('Y');
    $url .= "&start=$year-01-01";
    $disabled = $this->start == date("Y-01-01") ? "disabled" : "";
    $year_btn = "<a href='" . htmlescape($url) . "' class='btn btn-primary noprint $disabled'>$year</a>\n";

    $url = "{$base_url}&start={$this->prev}";
    echo "<a href='",htmlescape($url),"' class='btn btn-primary noprint'><i class='fas fa-arrow-left'></i></a>\n";

    if( $this->by_year ) {
      echo $year_btn;
    }
    else if( $this->by_week ) {
      echo $week_btn;
    }
    else {
      echo $month_btn;
    }

    $url = "{$base_url}&start={$this->next}";
    $disabled = $this->next ? "" : "disabled";
    echo "<a href='",htmlescape($url),"' class='btn btn-primary noprint $disabled'><i class='fas fa-arrow-right'></i></a>\n";

    if( !$this->by_week ) {
      echo $week_btn;
    }

    if( $this->by_week || $this->by_year ) {
      echo $month_btn;
    }

    if( !$this->by_year ) {
      echo $year_btn;
    }

  }
  function moreOptionsButton() {
    echo "<button class='btn btn-primary noprint' onclick='showMorePagerOptions_{$this->show}()'>...</button>\n";
  }
  function moreOptions($hidden_vars=null) {
    echo " <form id='more_pager_options_{$this->show}' class='form-inline noprint'>\n";
    echo "<input type='hidden' name='s' value='{$this->show}'/>\n";
    if( $hidden_vars ) foreach( $hidden_vars as $key => $value ) {
      echo "<input type='hidden' name='",htmlescape($key),"' value='",htmlescape($value),"'/>\n";
    }
    echo " <input type='date' name='start' placeholder='start date' size='9' value='",htmlescape($this->start),"'/>";
    echo " <input type='date' name='end' placeholder='end date' size='9' value='",htmlescape($this->end),"'/>";
    echo " <input type='submit' value='Go'/>\n";
    echo "</form>\n";

    ?><script>
    function showMorePagerOptions_<?php echo $this->show ?>() {
      $('#more_pager_options_<?php echo $this->show ?>').show();
    }
    $('#more_pager_options_<?php echo $this->show ?>').hide();
    </script><?php
  }
}
