<?php

function render_rss()
{
  global $req, $conf;
  $url = $conf['blog_url'];
  $version = ($req['rss'] == 2) ? '2.0' : '1.0';

  $channel = [
    'title' => $conf['blog_title'],
    'link' => $conf['blog_url'],
    'description' => $conf['blog_subtitle']
    ];

  if ($version == "2.0") {
    $channel['language'] = $conf['language'];
    $channel['copyright'] = $conf['blog_rights'];
    $channel['managingEditor'] = $conf['blog_owner'];
    $channel['webMaster'] = $conf['blog_owner'];
  }
  else {
    $channel['dc'] = [
      'subject' => $conf['blog_title'],
      'creator' => $conf['blog_owner'],
      'publisher' => $conf['blog_owner'],
      'rights' => $conf['blog_rights'],
      'language' => $conf['language']
      ];
    $channel['syn'] = [
      'updatePeriod' => $conf['feed_updates'],
      'updateFrequency' => 1,
      'updateBase' => '1901-01-01T00:00+00:00'
      ];
  }

  $articles = get_articles();
  foreach($articles as $item) {
    preg_match("/(\d{4})\-(\d{2})\-\d{2} /", $item['date'], $i);
    $year = $i[1];
    $month = $i[2];
    $link = sprintf("%s%s/%s/%s", $url, $year, $month, $item['uri']);

    if ($version == '2.0') {
      $channel['items'][] = [
        'title' => $item['title'],
        'link' => $link,
        'description' => substr($item['body'], 0, 200) . "...",
        'author' => $item['author'],
        'category' => preg_split("/, */", $item['tags']),
        'comments' => $link . '#comments',
        'pubDate' => gmstrftime("%a, %d %b %Y %H:%M:%S %Z", $item['epoch']),
        ];
    }
    else {
      $channel['items'][] = [
        'title' => $item['title'],
        'link' => $link,
        'description' => substr($item['body'], 0, 200) . "...",
        'dc' => [
          'subject' => $conf['blog_title'],
          'creator' => $item['author'],
          'date' => gmstrftime("%a, %d %b %Y %H:%M:%S %Z", $item['epoch']),
          ]
        ];
    }
  }
  header('Content-Type: application/rss+xml');
  include "themes/common/rss-{$version}.xmlt";
}

function render_page()
{
  global $conf, $comment_form, $error, $message,
    $page, $page_prev, $page_next, $req;

  if ($conf['comments_allowed'] == 1) {
    read_comment();
  }
  $articles = get_articles();
  $archives = get_archives();
  $tagcloud = get_tag_cloud();
  $theme = $conf['blog_theme'];
  $title = $conf['blog_title'];
  $subtitle = $conf['blog_subtitle'];
  $copyright = $conf['blog_rights'];
  $google_analytics_id = $conf['google_analytics_id'];
  $google_webmaster_id = $conf['google_webmaster_id'];
    
  if (isset($articles)) { 
    if (isset( $req['uri'] ) && $conf['comments_allowed'] === 1) {
      $comment_form = 1;
      $id = $articles[0]['id'];
    }
  }
  else {
    $error = '404 post not found';
  }
  $dump = get_defined_vars();
  include $conf['index_template'];
}

function get_articles()
{
  global $conf, $dbh, $page, $page_next, $page_prev, $req;

  $app = $conf['articles_per_page'];
  $app = ($app > 0) ? $app : -1;
  $show_comments = 0;
  $where_clause = 'WHERE enabled = 1 ';
  $limit_clause = '';
  $params = [];
  $j = 0;
    
  if (isset( $req['page'])) {
    $page = (int) $req['page'];
    $offset = ($page - 1) * $app;
  }
  else {
    $page = 1;
    $offset = 0;
  }

  if (isset($req['year'])) {
    $year = $req['year'];
    if (preg_match("/\d{4}/", $year) && 1900 < $year && $year < 2036) {
      $where_clause .= "AND date LIKE '%{$year}";
      if (isset($req['month'])) {
	$month = $req['month'];
	if (preg_match("/\d{2}/", $month) && 0 < $month && $month < 13) {
	  $where_clause .= "-{$month}%' ";
	  if (isset($req['uri']) && preg_match("/\w+/", $_REQUEST['uri'])) {
	    $where_clause .= "AND uri=:uri ";
	    $params['uri'] = $req['uri']; 
	    $show_comments = 1;
	  }
	}
      }
      else { 
	$where_clause .= "%' ";
      }
    } 
  }
  elseif (isset($req['search'])) {
    $search_term = sprintf("%%%s%%", $req['search']);
    $where_clause .= "AND (tags LIKE :tag OR author LIKE :author) ";
    $params['tag'] = $search_term;
    $params['author'] = $search_term;
  }
  elseif (isset($req['id']) ) {
    $where_clause .= "AND id=:id ";
    $params['id'] = $req['id'];
    $show_comments = 1;
  }
  else {
    $limit_clause = " LIMIT $app OFFSET $offset";
  }

  $query = "SELECT *, strftime('%s', date) AS epoch FROM articles ";
  $query .= "{$where_clause}ORDER BY date DESC{$limit_clause}";
  $sth = $dbh->prepare($query);

  if (empty($params)) {
    $sth->execute() or error_log($dbh->lastErrorMsg());
  }
  else {
    $sth->execute($params) or error_log($dbh->lastErrorMsg());
  }

  $articles = [];
  while ($row = $sth->fetch()) {
    preg_match('/(\d{4})\-(\d{2})\-\d{2} /', $row['date'], $d);
    $row['year'] = $d[1];
    $row['month'] = $d[2];
    $row['body'] = nl2br($row['body']);

    # cut off readmore if we are on the front page
    $readmore = (preg_match("/<!--readmore-->/", $row['body']));
    if ($readmore && $j < 3 && !isset($req['rss'])) {
      $row['readmore'] = preg_split("/<!--readmore-->/", $row['body']);
      $row['body'] = $row['readmore'][0];
    }
    $row['tag_loop'] = format_tags($row['tags']);
    $comments = get_comments($row['id'], 1);
    $row['comments_count'] = count($comments);
    if ($show_comments == 1) {
      $row['comments'] = $comments;
    }
    array_push($articles, $row);
  }

  $query2 = 'SELECT count(*) as total FROM articles WHERE enabled=1';
  $sth2 = $dbh->prepare($query2);
  $sth2->execute() or error_log($dbh->lastErrorMsg());
  $row2 = $sth2->fetch();
  $ac = ( int ) $row2['total'];
  if ($j == 0) {
    if (($ac > ($offset - $app)) && $page > 1) {
      $page_prev = ($page - 1);
    }
    if ($ac > ($offset + $app)) {
      $page_next = ($page + 1);
    }
  }
  return $articles;
}

function get_archives()
{
  global $dbh, $req;
  $h = [];
  $cur_month = (isset($req['month'])) ?: sprintf("%02d", localtime()[4] + 1);
  $cur_year = (isset($req['year'])) ?: localtime()[5] + 1900;
  $months = [
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December',
    ];

  $query = 'SELECT * FROM articles WHERE enabled=1 ORDER BY date DESC';
  $sth = $dbh->prepare($query);
  $sth->execute();

  while ($row = $sth->fetch()) {
    preg_match('/(\d{4})\-(\d{2})\-\d{2} /', $row['date'], $d);
    $y = $d[1];
    $m = $d[2];
    if(!isset($h[$y])) {
      $h[$y] = [];
    }
    if(!isset($h[$y]['count'])) {
      $h[$y]['count'] = 0;
    }
    if(!isset($h[$y][$m])) {
      $h[$y][$m] = [];
    }
    $title = $full_title = $row['title'];
    if (strlen($title) > 28) {
      $title = substr($title, 0, 25) . '...';
    }
    if (($y == $cur_year) && ($m == $cur_month) && $row['uri']) {
      if(!isset($h[$y][$m]['uri_loop'])) {
        $h[$y][$m]['uri_loop'] = [];
      }
      $uri_arr = [
        'year' => $y,
        'month' => $m,
        'month_name' => $months[$m],
        'title' => $title,
        'full_title' => $full_title,
        'uri' => $row['uri'],
        ];
      array_push($h[$y][$m]['uri_loop'], $uri_arr);
    }
    else {
      if (!isset($h[$y][$m]['count'])) {
        $h[$y][$m]['count'] = 0;
      }
      $h[$y][$m]['count']++;
    }
    $h[$y]['count']++;
  }
  krsort($h);
  foreach(array_keys($h) as $year) {
    foreach(array_keys($h[$year]) as $month) {
      if ($month != 'count') {
        $month_array = [
          'year' => $year,
          'month' => $month,
          'month_name' => $months[$month],
          ];
      }
      # check to see if uri_loop exists first
      if (isset($h[$year][$month]['uri_loop'])) {
        $month_array['uri_loop'] = $h[$year][$month]['uri_loop'];
      }
      if (isset($h[$year][$month]['count'])) {
        $month_array['count'] = "({$h[$year][$month]['count']})";
      }
      else {
        $month_array['count'] = '';
      }
      if ($month != 'count') {
        if(!isset($h[$year]['month_loop'])) {
          $h[$year]['month_loop'] = [];
        }
        array_push($h[$year]['month_loop'], $month_array);
      }
    }
    $y = ['year' => $year, 'count' => $h[$year]['count']];

    # check to see if we're showing this year, and that month_loop exists
    if (($year == $cur_year) && isset($h[$year]['month_loop'])) {
      $y['month_loop'] = $h[$year]['month_loop'];
    }
    if ($year != 'count') {
      if(!isset($h['year_loop'])) {
        $h['year_loop'] = [];
      }
      array_push($h['year_loop'], $y);
    }
  }
  return $h['year_loop'];
}

function format_tags($tags_raw)
{
  $tags = explode(',', $tags_raw);
  return $tags;
}

function read_comment()
{
  global $conf, $dbh, $message, $error, $comment_form;

  if ( isset($_POST['g-recaptcha-response'])
    && isset($_POST['comment'])
    && isset($_POST['id'])) {

    # test our captcha
    $seckey = $conf['captcha_seckey'];
    $gresponse = $_POST['g-recaptcha-response'];
    $remote = $_SERVER['REMOTE_ADDR'];
    $result = verify_captcha($seckey, $gresponse, $remote);
    $cmax = $conf['comment_max_length'];

    if ($result['success']) {
      $name = ($_POST['name']) ?: 'anonymous';
      $email = ($_POST['email']) ?: NULL;
      $url = $_POST['url'] ?: NULL;
      $id = $_POST['id'];

      # save comment
      if (empty($_POST['comment'])) {
        $error = "Comment is required.";
        $comment_form = 1;
      }
      else {
        $name = substr($name, 0, 100);
        $email = substr($email, 0, 100);
        $url = substr($url, 0, 100);
        $comment = substr(htmlentities($_POST['comment']), 0, $cmax);

        $query = "INSERT INTO comments VALUES (NULL, :id,
                 datetime('now', 'localtime'), :name, :email, :url, :comment, 0)";
        $sth = $dbh->prepare($query);
	$params = ['id' => $id,
		'name' => $name,
		'email' => $email,
		'url' => $url,
		'comment' => $comment];
        $sth->execute($params) or error_log($dbh->lastErrorMsg());
        $message = 'comment awaiting moderation, thank you';

        # send email notification
        $subj = $conf['blog_title'] . " comment submission";

        $mail_body = "You have received a new comment submission.\n\n";
        $mail_body .= sprintf("Commenteer: %s\n", $name);
        $mail_body .= sprintf("Date: %s\n", strftime("%c"));
        $mail_body .= sprintf("Comment:\n\"%s\"\n\n", wordwrap($comment));
        $mail_body .= "Moderate comments at ";
        $mail_body .= $conf['blog_url'] . "admin.php?view=moderate\n";

	if (!empty($conf['smtp_mailer'])) {
	    include "src/mailer.php";
	    smtp_mail($subj, $mail_body);
	}
	else {
	    $from = $conf['smtp_sender'];
	    $to = $conf['blog_owner'];
	    mail($to, $subj, $mail_body, "From: $from\r\n");
	}

      }
    }
    else {
      $error = 'Error: ' . $result['error'];
      $error .= ', please report to site admin';
      $comment_form = 1;
    }
  }

  # present the challenge
  $captcha_pubkey;
}

function verify_captcha($privkey, $response, $remoteip)
{
  $postdata = [
    'secret' => $privkey,
    'response' => $response,
    'remoteip' => $remoteip
  ];

  $opts = [
    'http' => [
      'method'  => 'POST',
      'content' => http_build_query($postdata),
      'header' => 'Content-type: application/x-www-form-urlencoded',
      'curl_verify_ssl_host' => true
    ]
  ];

  $target = 'https://www.google.com/recaptcha/api/siteverify';
  $context = stream_context_create($opts);
  $result = file_get_contents($target, false, $context);
  $response = json_decode($result, true);

  if (json_last_error() === JSON_ERROR_NONE) {
    if ($response['success'] == 'true' ) {
      return ['success' => 1];
    }
    else {
      return ['success' => 0, 'error' => $response['error']];
    }
  }
  else {
    return ['success' => 0, 'error' => json_last_error()];
  }
}

function get_comments($article_id, $enabled)
{
  global $dbh;
  $query = 'SELECT * FROM comments WHERE article_id=:id ';
  $query .= 'AND enabled=:enabled ORDER BY date ASC';
  $sth = $dbh->prepare($query);
  $params = ['id' => $article_id,
	  'enabled' => $enabled];
  $sth->execute($params) or error_log($dbh->lastErrorMsg());
  $comments = [];
  while ($result = $sth->fetch()) {
    array_push($comments, $result);
  }
  return $comments;
}

function get_tag_cloud()
{
  global $dbh, $conf;
  $query = 'SELECT tags FROM articles WHERE enabled=1';
  $sth = $dbh->prepare($query);
  $sth->execute() or error_log("$dbh->lastErrorMsg()");

  # create a frequency table keyed by tag
  $freq = [];
  while( $row = $sth->fetch() ) {
    foreach( preg_split( "/, */", $row['tags'] ) as $k=>$v ) {
      (array_key_exists($v, $freq)) ? $freq[$v]++ : $freq[$v] = 1;
    }
  }

  # calculate the scaling denominator
  $tags = array_slice($freq, 0, $conf['max_tags_in_cloud']);
  $tag_max = max($tags);
  $tag_min = min($tags);
  $denominator = ($tag_max == $tag_min) ? (1 / 5) : ($tag_max - $tag_min) / 5;

  # build data structure
  $tag_cloud_data = [];
  foreach($tags as $tag => $count) {
    $tag_cloud_data[$tag] = (int) (($count - $tag_min) / $denominator);
  }
  ksort($tag_cloud_data, SORT_NATURAL | SORT_FLAG_CASE);
  return $tag_cloud_data;
}

