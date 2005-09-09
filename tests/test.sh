#!/bin/bash
(cd ..; pear install -f package.xml)
pathfront /usr/local/litmus-0.9.3/bin
rm -f *.log
mysql -u root webdav -e "TRUNCATE TABLE locks"
mysql -u root webdav -e "TRUNCATE TABLE properties"
sudo rm -rf /usr/local/apache/htdocs/mod_dav/*
litmus -k http://localhost/file.php 
php -q split_log.php



