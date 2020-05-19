<?php
require_once 'conf/config.php';
require_once 'src/admin_functions.php';

$dbh = new PDO($conf['database']);

$view = $_REQUEST['view'] ?? 'administrate';

switch ($view) {
  case 'moderate':
    manage_comments();
    break;
  case 'edit':
    edit_article();
    break;
  case 'manage':
    manage_files();
    break;
  default:
    manage_articles();
}

include $conf['admin_template'];

