#!/bin/bash
(cd ..; sudo pear install -f package.xml)
rm -f *.log
mysql -u root webdav -e "TRUNCATE TABLE locks"
mysql -u root webdav -e "TRUNCATE TABLE properties"
sudo rm -rf /usr/local/apache/htdocs/mod_dav/*
sudo rm -rf /usr/local/apache/htdocs/litmus/*
litmus -k http://localhost/file.php   # add -k to continue on errors
php -q split_log.php



