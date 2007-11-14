<?php // $Id$

	ini_set("include_path", ini_get("include_path").":/usr/local/apache/htdocs");
  require_once "HTTP/WebDAV/Server/Filesystem.php";
	$server = new HTTP_WebDAV_Server_Filesystem();
	$server->ServeRequest($_SERVER["DOCUMENT_ROOT"]);
?>