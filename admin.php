<?php
require_once 'conf/config.php';
require_once 'src/admin_functions.php';

$dbh = new PDO($conf['database']);

if (isset($_REQUEST['view'])) {
  if ($_REQUEST['view'] == 'moderate') {
    $view = 'moderate';
    manage_comments();
  }
  elseif ($_REQUEST['view'] == 'edit') {
    $view = 'create';
    edit_article();
  }
  elseif ($_REQUEST['view'] == 'manage') {
    $view = 'manage';
    manage_files();
  }
  else {
    $view = 'administrate';
    manage_articles();
  }
}
else {
  $view = 'administrate';
  manage_articles();
}
include $conf['admin_template'];

