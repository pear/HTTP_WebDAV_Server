#!/bin/bash

cp -R ../* /usr/local/apache/htdocs/HTTP/WebDAV
clear 
rm -f *.log
mysql -u root webdav -e "TRUNCATE TABLE locks"
mysql -u root webdav -e "TRUNCATE TABLE properties"
litmus http://localhost:8080/HTTP/WebDAV/file.php
php -q split_log.php



