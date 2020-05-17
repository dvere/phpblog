<?php
require_once 'conf/config.php';
require_once 'src/functions.php';

$req = $_REQUEST;
$dbh = new PDO($conf['database']) or error_log($dbh->lastErrorMsg());

if ( isset($req['rss']) ) {
    render_rss();
} else {
    render_page();
}

