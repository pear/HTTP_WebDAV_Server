<?php

class HTTP_WebDAV_Server_file extends HTTP_WebDAV_Server {
    var $base;

    function HTTP_WebDAV_Server_file ($base)
    {
        $this->base = $base;
        parent::dav_server();
    } 

    function propfind ($options, &$files)
    {
        $fspath = realpath($this->base . $options["path"]);

        if (!file_exists($fspath)) {
            return false;
        } 
        $files["files"] = array();
        $files["files"][] = $this->fileinfo($options["path"], $options);

        if (!empty($options["depth"])) {
            if (substr($options["path"], -1) != "/") {
                $options["path"] .= "/";
            } 
            $handle = opendir($fspath);

            while ($filename = readdir($handle)) {
                if ($filename != "." && $filename != "..") {
                    $files["files"][] = $this->fileinfo ($options["path"] . $filename, $options);
                } 
            } 
        } 

        return true;
    } 

    function fileinfo($uri, $options)
    {
        $fspath = $this->base . $uri;

        $file = array();
        $file["path"] = $uri;

        $file["props"]["displayname"] = strtoupper($uri);

        $file["props"]["creationdate"] = filectime($fspath);
        $file["props"]["getlastmodified"] = filemtime($fspath);

        if (is_dir($fspath)) {
            $file["props"]["getcontentlength"] = 0;
            $file["props"]["resourcetype"] = "collection";
            $file["props"]["getcontenttype"] = "httpd/unix-directory";
        } else {
            $file["props"]["resourcetype"] = "";
            $file["props"]["getcontentlength"] = filesize($fspath);
            if (is_readable($fspath)) {
                $file["props"]["getcontenttype"] = rtrim(preg_replace("/^([^;]*);.*/", "$1",));
            } else {
                $file["props"]["getcontenttype"] = "application/x-non-readable";
            } 
        } 

        return $file;
    } 

    function get($options)
    {
        $fspath = $this->base . $options["path"];

        if (file_exists($fspath)) {
            if (!is_dir($fspath)) {
                header("Content-Type: " .);
            } else {
                header ("Content-Type: httpd/unix-directory");
            } 
            readfile($fspath);
            return true;
        } else {
            return false;
        } 
    } 

    function put($options)
    {
        $fspath = $this->base . $options["path"];

        if (!@is_dir(dirname($fspath))) {
            return "409 Conflict";
        } 

        $new = ! file_exists($fspath);

        $fp = fopen($fspath, "w");
        if ($fp) {
            fwrite($fp, $options["data"]);
            fclose($fp);
        } 

        return $new ? "201 Created" : "204 No Content";
    } 

    function mkcol($options)
    {
        $path = $this->base . $options["path"];
        $parent = dirname($path);
        $name = basename($path);

        if (!file_exists($parent)) {
            return "409 Conflict";
        } 

        if (!is_dir($parent)) {
            return "403 Forbidden";
        } 

        if (file_exists($parent . "/" . $name)) {
            return "405 Method not allowed";
        } 

        if (!empty($GLOBALS["HTTP_RAW_POST_DATA"])) { // no body parsing yet
            return "415 Unsupported media type";
        } 

        mkdir ($parent . "/" . $name, 0777);
        return ("201 Created");
    } 

    function delete($options)
    {
        $path = $this->base . "/" . $options["path"];

        if (!file_exists($path)) return "404 Not found";

        if (is_dir($path)) {
            system("rm -rf $path");
        } else {
            unlink ($path);
        } 

        return "204 No Content";
    } 

    function move($options)
    {
        return $this->copy($options, true);
    } 

    function copy($options, $del = false)
    {
        if (!empty($GLOBALS["HTTP_RAW_POST_DATA"])) { // no body parsing yet
            return "415 Unsupported media type";
        } 

        if (isset($options["dest_url"])) {
            return "502 bad gateway";
        } 

        $source = $this->base . $options["path"];
        if (!file_exists($source)) return "404 Not found";

        $dest = $this->base . $options["dest"];

        $new = !file_exists($dest);
        $existing_col = false;
        // if(!$new) {
        // if(is_dir($dest) && !is_dir($source)) {
        // error_log("xxx $source $dest ".is_dir($dest));
        // $dest .= basename($source);
        // if(file_exists($dest)) {
        // $options["dest"] .= basename($source);
        // } else {
        // $new = true;
        // }
        // }
        // error_log("xxx2 $source $dest $new");
        // }
        if (!$new) {
            if ($del && is_dir($dest)) {
                if (!$options["overwrite"]) {
                    return "412 precondition failed";
                } 
                $dest .= basename($source);
                if (file_exists($dest . basename($source))) {
                    $options["dest"] .= basename($source);
                } else {
                    $new = true;
                    $existing_col = true;
                } 
            } 
        } 

        if (!$new) {
            if ($options["overwrite"]) {
                $stat = $this->delete(array("path" => $options["dest"]));
                if ($stat {
                        0} != "2") return $stat;
            } else {
                return "412 precondition failed";
            } 
        } 

        if (is_dir($source)) {
            if ($options["depth"] == "infinity") {
                system("cp -R $source $dest");
            } else {
                mkdir($dest, 0777);
            } 
            if ($del) system("rm -rf $source");
        } else {
            if ($del) {
                rename($source, $dest);
            } else {
                if (substr($dest, -1) == "/") $dest = substr($dest, 0, -1);
                copy($source, $dest);
            } 
        } 

        return ($new && !$existing_col) ? "201 Created" : "204 No Content";
    } 
} 

?>
