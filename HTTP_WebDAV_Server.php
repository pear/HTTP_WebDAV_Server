<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Hartmut Holzgraefe <hartmut@six.de>                         |
// |          Author: Christian Stocker <chregu@bitflux.ch>               |
// +----------------------------------------------------------------------+
//
// $Id$
//
// WebDAV server base class, needs to be extended to do useful work
//



// helper class for parsing PROPFIND request bodies
class _parse_propinfo {
    // get requested properties as array containing name/namespace pairs
    function _parse_propinfo()
    {
        global $HTTP_RAW_POST_DATA;

        $this->success = true;

        if (trim($HTTP_RAW_POST_DATA) == "") {
            $this->props = "all";
        } else {
            $this->depth = 0;
            $this->props = array();
            $xml_parser = xml_parser_create_ns("UTF-8", " ");
            xml_set_element_handler($xml_parser, array(&$this, "_startElement"), array(&$this, "_endElement"));
            xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
            $this->success &= xml_parse($xml_parser, $HTTP_RAW_POST_DATA, true);
            xml_parser_free($xml_parser);
        } 
    } 

    function _startElement($parser, $name, $attrs)
    {
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "") $this->success = false;
        } else {
            $ns = "";
            $tag = $name;
        } 
        if ($this->depth == 1) {
            if ($tag == "allprop") $this->props = "all";
            if ($tag == "propnaname") $this->props = "names";
        } 
        if ($this->depth == 2) {
            $prop = array("name" => $tag);
            if ($ns && $ns != "DAV:") $prop["xmlns"] = $ns;
            $this->props[] = $prop;
        } 
        $this->depth++;
    } 

    function _endElement($parser, $name)
    {
        $this->depth--;
    } 
} 
// helper class for parsing LOCK request bodies
// TODO: ignores owner for now
class _parse_lockinfo {
    var $what = "";

    function _parse_lockinfo()
    {
        global $HTTP_RAW_POST_DATA;

        if (trim($HTTP_RAW_POST_DATA) == "") {
            $this->lockinfo = false;
        } else {
            $this->lockinfo = array();
            $xml_parser = xml_parser_create_ns("UTF-8", " ");
            xml_set_element_handler($xml_parser, array(&$this, "_startElement"), array(&$this, "_endElement"));
            xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
            $this->success = xml_parse($xml_parser, $HTTP_RAW_POST_DATA, true);
            xml_parser_free($xml_parser);
        } 
    } 

    function _startElement($parser, $name, $attrs)
    {
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
        } else {
            $ns = "";
            $tag = $name;
        } 

        switch ($tag) {
        case "lockscope":
        case "locktype":
            if ($ns == "DAV:" || $ns == "") {
            $this->what = $name;
        } 
        break;
    default:
        if ($this->what) {
            $this->lockinfo[$this->what] = $tag;
        } 
        break;
    } 
} 

function _endElement($parser, $name)
{
    if ($name == $this->what || $name == "DAV: {$this->what}") {
        $this->what = "";
    } 
} 
} 

class HTTP_WebDAV_Server {
var $path; 
// constructor
function HTTP_WebDAV_Server()
{ 
    // identify ourselves
    header ("X-Dav-Powered-By: PHP class: " . get_class($this));

    if (!$this->_check_auth()) {
        header('WWW-Authenticate: Basic realm="WebDAV"'); // todo customizable
        header('HTTP/1.0 401 Unauthorized');
        exit;
    } 
    // set path
    $this->path = isset($_SERVER["PATH_INFO"]) ? $_SERVER["PATH_INFO"] : "/"; 
    // detect requested method names
    $method = strtolower($_SERVER["REQUEST_METHOD"]);
    $wrapper = "http_" . $method;

    if (method_exists($this, $wrapper) && ($method == "options" || method_exists($this, $method))) {
        $this->$wrapper(); // call method by name
    } else {
        header ("HTTP/1.1 405 Method not allowed");
        header("Allow: " . join(", ", $this->_allow())); // tell client what's allowed
    } 
} 
// check for implemented HTTP methods
function _allow()
{ 
    // OPTIONS is always there
    $allow = array("options" => "OPTIONS"); 
    // all other METHODS need both a http_method() wrapper
    // and a method() implementation
    // the base class supplies wrappers only
    foreach(get_class_methods($this) as $method) {
        if (!strncmp("http_", $method, 5)) {
            $method = substr($method, 5);
            if (method_exists($this, $method)) {
                $allow[$method] = strtoupper($method);
            } 
        } 
    } 

    if (!method_exists($this, "checklock")) {
        unset($allow["lock"]);
        unset($allow["unlock"]);
    } 

    return $allow;
} 

function _lock_tokens()
{
    if (!isset($_SERVER["HTTP_IF"])) return false;
    preg_match_all("/\(<opaquelocktoken:([^\>]*)>\)/", $_SERVER["HTTP_IF"], $matches);

    if (!empty($matches[1])) return $matches[1];

    return false;
} 
// OPTIONS method handler
function http_OPTIONS ()
{
    header("HTTP/1.1 200 OK"); 
    // be nice to M$ clients
    header("MS-Author-Via: DAV"); 
    // get allowed methods
    $allow = $this->_allow();
    header("Allow: " . join(", ", $allow));

    $dav = array(1); // assume we are always dav class 1 compliant
    if (isset($allow['lock'])) $dav[] = 2; // dav class 2 requires locking 
    header("DAV: " . join(",", $dav));
} 

function http_PROPFIND ()
{
    $options = Array();
    $options["path"] = $this->path;

    if (isset($_SERVER['HTTP_DEPTH'])) {
        $options["depth"] = $_SERVER["HTTP_DEPTH"];
    } else {
        $options["depth"] = "infinity";
    } 

    $propinfo = new _parse_propinfo();

    if (!$propinfo->success) {
        header("HTTP/1.1 400 Error");
        return;
    } 

    $options['props'] = $propinfo->props;

    if ($this->propfind($options, &$files)) {
        header ("HTTP/1.1 207 Multi-Status");
        header ('Content-Type: text/xml');
        // ob_start();
        print "<?xml version='1.0' encoding='utf-8'?>\n";

        print "<D:multistatus xmlns:D='DAV:'>\n";

        foreach($files["files"] as $file) {
            print " <D:response>\n";
            print "  <D:href>" . str_replace(' ', '%20', $_SERVER["SCRIPT_NAME"] . $file['path']) . "</D:href>\n";
            print "  <D:propstat>\n";
            print "   <D:prop>\n";

            foreach($file["props"] as $name => $prop) {
                if (!is_array($prop)) $prop = array("val" => $prop);
                if (empty($prop["ns"]) || $prop["ns"] == "DAV:") {
                    switch ($name) {
                    case "creationdate":
                        print "    <D:creationdate>" . date("Y-m-d\\TH-i-s\\Z", $prop['val']) . "</D:creationdate>\n";
                        break;
                    case "getlastmodified":
                        print "    <D:getlastmodified>" . date("D, j M Y H:m:s GMT", $prop['val']) . "</D:getlastmodified>\n";
                        break;
                    case "resourcetype":
                        if (empty($prop["val"])) {
                        print "    <D:resourcetype/>\n";
                    } else {
                        print "    <D:resourcetype><D:$prop[val]/></D:resourcetype>\n";
                    } 
                    break;
                default:
                    print "    <D:$name>$prop[val]</D:$name>\n";
                } 
            } else {
                // different namespaces
            } 
        } 
        print "   </D:prop>\n";
        print "   <D:status>HTTP/1.1 200 OK</D:status>\n";
        print "  </D:propstat>\n";
        print " </D:response>\n";
    } 

    print '</D:multistatus>';
    // error_log(ob_get_contents());
    // ob_end_flush();
} else {
    header ("HTTP/1.1 404 File Not Found");
} 
} 

function http_MKCOL()
{
$options = Array();
$options["path"] = $this->path;

$stat = $this->mkcol($options);
header("HTTP/1.1 " . $stat);
} 

function http_GET ()
{
$options = Array();
$options["path"] = $this->path;

if (! $this->get($options)) {
    header ("HTTP/1.1 404 Not Found");
} else {
    header ("HTTP/1.1 200 Success");
} 
} 

function http_PUT()
{
global $HTTP_RAW_POST_DATA;
$options = Array();
$options["path"] = $this->path;
$options["content_length"] = $_SERVER["CONTENT_LENGTH"];
$options["data"] = &$HTTP_RAW_POST_DATA;

$stat = $this->put($options);

header("HTTP/1.1 " . $stat);
} 

function http_DELETE()
{
$options = Array();
$options["path"] = $this->path;

$stat = $this->delete($options);

header("HTTP/1.1 " . $stat);
} 

function http_COPY()
{
$this->_copymove("copy");
} 
function http_MOVE()
{
$this->_copymove("move");
} 

function _copymove($what)
{
$options = Array();
$options["path"] = $this->path;

if (isset($_SERVER['HTTP_DEPTH'])) {
    $options["depth"] = $_SERVER["HTTP_DEPTH"];
} else {
    $options["depth"] = "infinity";
} 

extract(parse_url($_SERVER["HTTP_DESTINATION"]));
$http_host = $host;
if (isset($port)) $http_host .= ":$port";

if ($http_host == $_SERVER["HTTP_HOST"] && !strncmp($_SERVER["SCRIPT_NAME"], $path, strlen($_SERVER["SCRIPT_NAME"]))) {
    $options["dest"] = substr($path, strlen($_SERVER["SCRIPT_NAME"]));
} else {
    $options["dest_url"] = $_SERVER["HTTP_DESTINATION"];
} 

$options["overwrite"] = @$_SERVER["HTTP_OVERWRITE"] == "T";

$stat = $this->$what($options);
header("HTTP/1.1 " . $stat);
} 

function http_LOCK()
{
$options = Array();
$options["path"] = $this->path;

if (isset($_SERVER['HTTP_DEPTH'])) {
    $options["depth"] = $_SERVER["HTTP_DEPTH"];
} else {
    $options["depth"] = "infinity";
} 

$options["tokens"] = $this->_lock_tokens();

$stat = $this->lock($options);

header("HTTP/1.1 " . $stat);
} 

function http_UNLOCK()
{
$options = Array();
$options["path"] = $this->path;

if (isset($_SERVER['HTTP_DEPTH'])) {
    $options["depth"] = $_SERVER["HTTP_DEPTH"];
} else {
    $options["depth"] = "infinity";
} 

$options["tokens"] = $this->_lock_tokens();

$stat = $this->unlock($options);

header("HTTP/1.1 " . $stat);
} 

function _check_auth()
{
if (method_exists($this, "check_auth")) {
    return $this->check_auth(@$_SERVER["AUTH_TYPE"], @$_SERVER["PHP_AUTH_USER"], @$_SERVER["PHP_AUTH_PW"]);
} else {
    return true;
} 
} 
} 

?>
