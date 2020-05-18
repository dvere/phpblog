# phpblog

Simple blogging app inspired by [blogsum](https://github.com/obfuscurity/blogsum) reworked in php and intended for hosting on [OpenBSD httpd(8)](http://man.openbsd.org/httpd).

## Prerequsites

### Required

OpenBSD new httpd configured and accessible. The php package installed with php-fpm running and pdo-sqlite module enabled.

### Highly Desireable 

Valid SSL Certificate to enable https (e.g. from letsencrypt using acme-client)

## Quick Install

Clone this repo somewhere convenient and checkout a bare version to a temporary directory. 
```
$ git clone git@github.com:dvere/phpblog.git

$ cd phpblog && git checkout-index --prefix=~/blog/ -a  
```

Change to the temporary directory and create the database.

```
$ cd ~/blog && sqlite3 data/site.db < data/schema.sql 

```

Review and edit `conf/config.php` to suit. Then run mtree to update ownership and permissions and remove setup files.  

```
$ doas mtree -rUf conf/perms.mtree
```

Copy the temporary directory into the web server chroot and grant an initial user access ensuring the password file is readable by the webserver.

```
$ doas cp -rp ~/blog /var/www/htdocs/

$ doas htpasswd /var/www/blog.htpasswd admin
Password:
Retype Password:

$ doas chown root:daemon /var/www/blog.htpasswd

$ doas chmod 640 /var/www/blog.htpasswd
```

## httpd(8) Configuration

Example [httpd.conf(5)](http://man.openbsd.org/httpd.conf) server directives. 

```
server "example.org" {
    listen on * port 80
    alias "www.example.org"

    root "/htdocs/blog"
    log {
        access "example.org-access.log"
        error "example.org-error.log"
    }

    location "/.well-known/acme-challenge/*" {
        root "/acme"
        request strip 2
    }
    location "*" {
        block return 301 "https://$SERVER_NAME$REQUEST_URI"
    }
}

server "example.org" {
    listen on * tls port 443
    alias "www.example.org"

    tls certificate "/etc/ssl/acme/example.org/fullchain.pem"
    tls key "/etc/ssl/acme/private/example.org/privkey.pem"

    connection max request body 2097152
    root "/htdocs/blog"
    log {
        access "example.org-access.log"
        error "example.org-error.log"
    }

    location "/.well-known/acme-challenge/*" {
        root "/acme"
        request strip 2
    }
    location "/data*"	    { block }
    location "/conf*"	    { block }
    location "/src*"        { block }
    location "*.php" {
		fastcgi socket "/run/php-fpm.sock"
    }
    location "/admin.php" {
		authenticate with "/blog.htpasswd"
    }
    location match '^/rss.xml$' {
		request rewrite "/?rss=1"
    }
    location match '^/rss2.xml$' {
		request rewrite "/?rss=2"
    }
    location match '^/Page/([^/]+)$' {
		request rewrite "/?page=%1"
    }
    location match '^/Tags/([^/]+)$' {
		request rewrite "/?search=%1"
    }
    location match '^/(%d%d%d%d)/(%d%d)/([^/]+)$' {
		request rewrite "/?year=%1&month=%2&uri=%3"
    }
    location match '^/(%d%d%d%d)/(%d%d)/?$' {
		request rewrite "/?year=%1&month=%2"
    }
    location match '^/(%d%d%d%d)/?$' {
		request rewrite "/?year=%1"
    }
}
```
 
Reload httpd

```
$ doas rcctl reload httpd
```

