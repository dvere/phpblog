<?php 

# Basic preferences. CHANGE THESE!
$blog_url = 'https:/example.org/';
$blog_owner = 'owner@example.org';
$blog_title = 'Blog Title';
$blog_subtitle = 'Blog Subtitle';
$blog_rights = 'Copyright 2020, Blog Owner';

# General preferences. REVIEW THESE!  
$language = 'en-gb';
$blog_theme = 'basic';
$feed_updates = 'hourly';
$articles_per_page = '10';
$max_tags_in_cloud = 20;

# Comments preferences. REVIEW THESE!
$comments_allowed = 1;
$comment_max_length = '1000';
$captcha_pubkey = '';
$captcha_seckey = '';
$smtp_mailer = 1;
$smtp_server = 'localhost:587';
$smtp_sender = 'www@example.org';

# Google Analytics (currently unused).
$google_analytics_id = '';
$google_webmaster_id = '';

# System defaults. DO NOT CHANGE WITHOUT GOOD REASON
$dsn = 'sqlite:data/site.db';
$uploads_dir = 'user_content/';
$admin_template = 'themes/{$blog_theme}/admin.phtml';
$index_template = 'themes/{$blog_theme}/index.phtml';
$debug = 0;

$conf = array(
    'admin_template' => $admin_template,
    'articles_per_page' => $articles_per_page,
    'blog_owner' => $blog_owner,
    'blog_rights' => $blog_rights,
    'blog_subtitle' => $blog_subtitle,
    'blog_theme' => $blog_theme,
    'blog_title' => $blog_title,
    'blog_url' => $blog_url,
    'captcha_pubkey' => $captcha_pubkey,
    'captcha_seckey' => $captcha_seckey,
    'comment_max_length' => $comment_max_length,
    'comments_allowed' => $comments_allowed,
    'database' => $dsn,
    'debug' => $debug,
    'feed_updates' => $feed_updates,
    'google_analytics_id' => $google_analytics_id,
    'google_webmaster_id' => $google_webmaster_id,
    'index_template' => $index_template,
    'language' => $language,
    'max_tags_in_cloud' => $max_tags_in_cloud,
    'smtp_mailer' => $smtp_mailer,
    'smtp_server' => $smtp_server,
    'smtp_sender' => $smtp_sender,
    'uploads_dir' => $uploads_dir
    );

