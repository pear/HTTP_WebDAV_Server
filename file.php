<?php

	require_once "HTTP_WebDAV_Server.php";
	require_once "HTTP_WebDAV_Server_file.php";

	new dav_fileserver("/usr/local/httpd/htdocs/tmp");

?>
