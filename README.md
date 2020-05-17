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

In the temporary directory create the database and run mtree to update ownership and permissions and remove setup files.  

```
$ cd ~/blog && sqlite3 data/site.db < data/schema.sql 

$ doas mtree -rUf conf/perms.mtree
```

Copy the temporary directory into the web server chroot and grant an initial user access ensuring the password file is readable by the webserver.

```
$ doas cp -rp ~/blog /var/www/htdocs/example.org

$ doas htpasswd /var/www/blog.htpasswd admin
Password:
Retype Password:

$ doas chown root:daemon /var/www/blog.htpasswd

$ doas chmod 640 /var/www/blog.htpasswd
```

## httpd(8) Configuration

Example [httpd.conf(5)](http://man.openbsd.org/httpd.conf) server directives  

```
server "example.org" {
    listen on * port 80

    root "htdocs/example.org"

    log {
        access "example.org-access.log"
        error "example.org-error.log"
    }

    location "/.well-known/acme-challenge/*" {
        root "/acme"
        request strip 2
    }
    location '*' {
        block return 301 "https://example.org$REQUEST_URI"
    }
}

server "example.org" {
    listen on * tls port 443

    root "htdocs/example.org"

    tls certificate "/etc/ssl/acme/example.org/fullchain.pem"
    tls key "/etc/ssl/acme/private/example.org/privkey.pem"

    directory index index.php
    connection max request body 2097152

    log {
        access "example.org-access.log"
        error "example.org-error.log"
    }

    location "/.well-known/acme-challenge/*" {
        root "/acme"
        request strip 2
    }

    location "/data*"           { block }
    location "/conf*"           { block }
    location "/src*"            { block }

    location "/admin.php" {
        authenticate with "/blog.htpasswd"
        fastcgi socket "/run/php-fpm.sock"
    }

    location "*.php" {
        fastcgi socket "/run/php-fpm.sock"
    }

    location match '^/rss.xml$' {
        block return 302 "/?rss=1"
    }

    location match '^/rss2.xml$' {
        block return 302 "/?rss=2"
    }

    location match '^/Page/([^/]+)$' {
        block return 302 "/?page=%1"
    }

    location match '^/Tags/([^/]+)$' {
        block return 302 "/?search=%1"
    }

    location match '^/(%d%d%d%d)/(%d%d)/([^/]+)$' {
        block return 302 "/?year=%1&month=%2&uri=%3"
    }

    location match '^/(%d%d%d%d)/(%d%d)/?$' {
        block return 302 "/?year=%1&month=%2"
    }

    location match '^/(%d%d%d%d)/?$' {
        block return 302 "/?year=%1"
    }
}
```
 
Reload httpd

```
$ doas rcctl reload httpd
```

