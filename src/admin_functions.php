<?php

function manage_articles()
{
  global $article_id, $articles, $comments, $dbh;
  $status = 2;
  $params = [];

  if (isset($_REQUEST['delete']) && is_numeric($_REQUEST['delete'])) {
    $article_id = $_REQUEST['delete'];
    $status = -1;
  }
  if (isset($_REQUEST['draft']) && is_numeric($_REQUEST['draft'])) {
    $article_id = $_REQUEST['draft'];
    $status = 0;
  }
  if (isset($_REQUEST['publish']) && is_numeric($_REQUEST['publish'])) {
    $article_id = $_REQUEST['publish'];
    $status = 1;
  }
  if ($status < 2) {
    $query = "UPDATE articles SET enabled = :status";
    $params['status'] = $status;
  }
  if ($status == 1) {
    $query .= ", date = datetime('now', 'localtime')";
  }
  if(!empty($article_id)) {
    $query .= " WHERE id = :id";
    $sth = $dbh->prepare($query);
    $params['id'] = $article_id;
    $sth->execute($params);
  }

  $comments = get_comments();

  $articles = get_articles();
}

function manage_comments()
{
  global $dbh, $comment_id, $comments;
  $status = 2;

  if (isset($_REQUEST['delete']) && is_numeric($_REQUEST['delete'])) {
    $params['id'] = $_REQUEST['delete'];
    $params['status'] = -1;
  }
  if (isset($_REQUEST['publish']) && is_numeric($_REQUEST['publish'])) {
    $params['id'] = $_REQUEST['publish'];
    $params['status'] = 1;
  }
  if ($status < 2) {
    $stmt = "UPDATE comments SET enabled = :status WHERE id = :id";
    $sth = $dbh->prepare($stmt);
    $sth->execute($params);
  }
  $comments = get_comments();
}

function manage_files()
{
  global $conf, $error, $message, $files;
  $fpath = $conf['uploads_dir'];
  $uploaderr = [
    0 => 'File upoaded successfully',
    1 => 'The file exceeds upload_max_filesize in php.ini',
    2 => 'The file exceeds MAX_FILE_SIZE in the HTML form',
    3 => 'The file was only partially uploaded',
    4 => 'No file was uploaded',
    6 => 'Missing temporary folder',
    7 => 'Failed to write file to disk.',
    8 => 'A PHP extension stopped the file upload.',
    ];

  if (isset($_REQUEST['unlink']) && is_file($fpath . $_REQUEST['unlink'])) {
    if (unlink($fpath . $_REQUEST['unlink'])) {
      $message = "{$_REQUEST['unlink']} has been deleted.";
    }
    else {
      $error = "Failed to unlink() {$_REQUEST['unlink']}.";
    }
  }
  if (isset($_FILES['userfile'])) {
    $uploadfile = $fpath . basename($_FILES['userfile']['name']);
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
      $message = $uploaderr[0];
    }
    else {
      $errx = $_FILES['userfile']['error']; 
      $error = $uploaderr[$errx];
    }
  }
  $files = get_files($fpath);
}

function get_files($dpath)
{
  $files = scandir($dpath);
  $f_inf = [];
  foreach ($files as $file) {
    if (substr($file, 0, 1) != '.') {
      $f_inf[$file] = stat($dpath . $file);
      $f_inf[$file]['name'] = $file;
      $f_inf[$file]['location'] = $dpath . $file;
      $f_inf[$file]['date'] = strftime("%c", $f_inf[$file]['mtime']);
      $f_inf[$file]['sz'] = formatBytes($f_inf[$file]['size']);
      $size = getimagesize($dpath . $file);
      $f_inf[$file]['html_size'] = $size[3];
    }
  }
  return $f_inf;
}

function formatBytes($size, $precision = 2)
{
  $base = log($size, 1024);
  $suffixes = array('', 'K', 'M', 'G', 'T');   

  return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

function edit_article()
{
  global $body,$comments, $conf, $dbh, $date, $edit, $error, $id,
	$message, $preview, $title, $tags, $uri, $view;

  $comments = get_comments();

  # preview, pass through all input
  if (isset($_REQUEST['preview'])) {
    $uri = $_REQUEST['uri'] ?: $_REQUEST['title'];
    $id = $_REQUEST['id'];
    $view = 'edit';
    $title = $_REQUEST['title'];
    $preview = nl2br($_REQUEST['body']);
    $body = htmlentities($_REQUEST['body']);
    $tags = $_REQUEST['tags'];
    $edit = 1;
  }
  # save edits, with id (update)
  elseif (!empty($_REQUEST['save']) && $_REQUEST['save'] == 'save' && !empty($_REQUEST['id'])) {
    if (!empty($_REQUEST['title']) && !empty($_REQUEST['uri']) && !empty($_REQUEST['body'])) {
      $uri = str_replace(' ', '-', $_REQUEST['uri']);
      $stmt = "UPDATE articles SET title = ?, uri = ?, body = ?, tags = ? WHERE id = ?";
      $sth = $dbh->prepare($stmt);
      $params = [ $_REQUEST['title'],
	      $uri,
	      $_REQUEST['body'],
	      $_REQUEST['tags'],
	      $_REQUEST['id']];
      
      $sth->execute($params);
      manage_articles();
    }
    # if missing data, push back to preview
    else {
      $error = 'required fields: title, uri, body';
      $id = $_REQUEST['id'];
      $title = $_REQUEST['title'];
      $uri = $_REQUEST['uri'];
      $preview = $_REQUEST['body'];
      $body = htmlentities($_REQUEST['body']);
      $tags = $_REQUEST['tags'];
      $edit = 1;
    }
  }
  # save new, no id (insert)
  elseif (isset($_REQUEST['save'])) {
    if (!empty($_REQUEST['body']) && !empty($_REQUEST['title'])) {
      $uri = $_REQUEST['uri'] ?: $_REQUEST['title'];
      $uri = str_replace(' ', '-', $uri);
      $author = $_SERVER['REMOTE_USER'];
      $stmt = " INSERT INTO articles VALUES
	  (NULL, datetime('now', 'localtime'), ?, ?, ?, ?, 0, ?)";
      $sth = $dbh->prepare($stmt);
      $params = [ $_REQUEST['title'],
	      $uri,
	      $_REQUEST['body'],
	      $_REQUEST['tags'],
	      $author];

      $sth->execute();
      manage_articles();

    }
    # if missing data, push back to preview
    else {
      $error = 'required fields: title, body';
      $edit = 1;
      $id = $_REQUEST['id'];
      $title = $_REQUEST['title'];
      $uri = $_REQUEST['uri'];
      $preview = $_REQUEST['body'];
      $body = htmlentities($_REQUEST['body']);
      $tags = $_REQUEST['tags'];
    }
  }
  # edit an existing
  elseif (isset($_REQUEST['id'])) {
    $query = "SELECT * FROM articles WHERE id = ?";
    $sth = $dbh->prepare($query);
    $sth->bindValue(1, $_REQUEST['id'], PDO::PARAM_INT);
    $sth->execute();
    $result = $sth->fetch();

    if (!empty($result)) {
      $id = $result['id'];
      $preview = nl2br($result['body']);
      $title = $result['title'];
      $uri = $result['uri'];
      $tags = $result['tags'];
      $body = htmlentities($result['body']);
      $date = substr($result['date'],0,10);
      $author = $result['author'];
      $edit = 1;

    }
    else {
      $error = 'no results found';
      manage_articles();
    }
  }
  # create new, show form
  else {
    $edit = 1;
  }
}

function get_articles()
{
  global $conf, $dbh, $error;
  $query = 'SELECT * FROM articles WHERE enabled != -1 ORDER BY date DESC';
  $sth = $dbh->prepare($query);
  $sth->execute() or error_log("$dbh->lastErrorMsg()");
  $articles = [];

  while ($row = $sth->fetch()) {
    preg_match("/(\d{4})\-(\d{2})\-\d{2} \d{2}\:\d{2}\:\d{2}/",
	$row['date'], $matches);
    $row['year'] = $matches[1];
    $row['month'] = $matches[2];
    $row['date'] = substr($row['date'], 0, 10);
    $row['theme'] = $conf['blog_theme'];
    array_push($articles, $row);
  }
  return $articles;
}

function get_comments()
{
  global $conf, $dbh, $error;
  $query = "SELECT a.title AS article_title, a.uri AS article_uri, a.date AS article_date, c.* FROM articles a, comments c WHERE a.id=c.article_id AND c.enabled=0 ORDER BY c.date DESC";
  $sth = $dbh->prepare($query);
  $sth->execute();
  $comments = [];

  while ($row = $sth->fetch()) {
    preg_match( "/(\d{4})\-(\d{2})\-\d{2} \d{2}\:\d{2}\:\d{2}/",
	$row['article_date'], $matches);
    $row['article_year'] = $matches[1];
    $row['article_month'] = $matches[2];
    $row['theme'] = $conf['blog_theme'];
    $row['uri'] = "{$conf['blog_url']}{$matches[1]}/{$matches[2]}/{$row['article_uri']}";
    array_push($comments, $row);
  }
  return $comments;
}

