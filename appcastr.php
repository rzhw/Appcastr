<?php
/**
 * Appcastr
 * Copyright (c) 2010-2011 Richard Z.H. Wang
 * 
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this license.  If not, see <http://www.gnu.org/licenses/>.
 */

// CHANGE THIS FOR YOUR OWN INSTANCE OF APPCASTR!
$salt = 'sdklfjsdkfjsdkljflsdfj';

// We need to write, boy
if (!is_writable('appcastr/'))
{
	appcastr_die('Appcastr doesn\'t have permissions to write to the <code>appcastr</code> directory.');
}

// Data
$data = array();
try
{
	$data = json_decode(file_get_contents('appcastr/data'), true);
}
catch (Exception $e)
{
	appcastr_die('Could not decode `appcastr/data`.<p>'.$e);
}
$olddata = $data;

// Password hash
if (isset($_GET['hash']))
{
	appcastr_page('Your hashed password', '<p>' . appcastr_password($_GET['hash']));
}

// Admin?
if (isset($_GET['admin']))
{
	session_start();
	
	if (appcastr_password($_SESSION['password']) != $data['users'][$_SESSION['username']])
	{
		if (isset($_POST['login']) && appcastr_password($_POST['password']) == $data['users'][$_POST['username']])
		{
			$_SESSION['username'] = $_POST['username'];
			$_SESSION['password'] = $_POST['password'];
			header('Refresh: 0');
			appcastr_page('Admin panel', 'Logging in...');
		}
		else
		{
			appcastr_page('Login to access the admin panel',
			(isset($_POST['login']) ? '<p class="error">Username or password incorrect' : '') . '
			<form action="" method="post">
				<p><label for="username">Username:</label>
				<input type="text" id="username" name="username">
				<p><label for="password">Password:</label>
				<input type="password" id="password" name="password">
				<p><input type="submit" name="login" value="Login">
			</form>');
		}
	}
	else
	{
		if (isset($_GET['logout']))
		{
			session_destroy();
			header('Refresh: 0');
			appcastr_page('Admin panel', 'Logging you out...');
		}
		
		// Appcasts
		$strappcasts = '';
		foreach ($data['appcasts'] as $appcast => $params)
		{
			$strappcasts .= '
			<h3>' . $params['title'] . ' <a href="?admin&action=edit&id='.$appcast.'">(edit)</a> <a href="?id='.$appcast.'">(feed)</a></h3>
			<p>ID: ' . $appcast . '
			<br>Type: ' . $params['type'] . '
			<br>Description: "' . $params['description'] . '"
			
			<h4>Items <a href="?admin&action=add&id='.$appcast.'">(add)</a></h4>
			<ul>';
			
			foreach (get_items($appcast) as $item)
			{
				$strappcasts .= '<li>' . $item['title'];
			}
			
			$strappcasts .= '</ul>';
		}
		
		// Users
		$strusers .= 'Hi';
		
		appcastr_page('Admin panel', '		
		<h2>Appcasts</h2>
		' . $strappcasts . '
		
		<h2>Users</h2>
		' . $strusers. '
		
		<a id="logout" href="?admin&logout">Logout</a>');
	}
}

// Potential errors
if (!isset($_GET['id']))
{
	appcastr_die('Appcastr needs "id" to be passed in the GET parameters.');
}

if ($_GET['id'] == 'data')
{
	appcastr_die('Reserved id');
}

if (strstr($_GET['id'], '/') || strstr($_GET['id'], '\\'))
{
	appcastr_die('Appcastr will not accept an id containing a slash.');
}

if (!file_exists('appcastr/' . $_GET['id']))
{
	appcastr_die('Appcastr couldn\'t find the file associated with the given id.');
}

// A simple echo
if (isset($_GET['echo']))
{
	exit(base64_decode($_GET['echo']));
}

// Our items
$items = get_items($_GET['id']);

foreach ($items as &$item)
{
	// The description
	if (isset($item['description']))
	{
		// SparkleDotNET 0.1 unfortunately will stack overflow without "sparkle:releaseNotesLink".
		// I'm not sure whether Sparkle takes <description>, but ehhh.
		if ((appcast_info('format') == 'sparkledotnet' && appcast_info('formatVersion') == '0.1')
			|| appcast_info('convertDescriptionToLink') === true)
		{
			// Don't override if we already have a link
			if (!isset($item['sparkle:releaseNotesLink']))
			{
				$item['sparkle:releaseNotesLink'] = curPageURL() . '&amp;echo=' . base64_encode($item['description']);
			}
		}
		
		$item['description'] = '<![CDATA[' . $item['description'] . ']]>';
	}
	
	// The publish date
	if (isset($item['pubDate']))
	{
		$date = strtotime($item['pubDate']);
		if ($date === false)
		{
			appcastr_die('Invalid `pubDate` attribute.');
		}
		else
		{
			$item['pubDate'] = date(DATE_ATOM, $date);
		}
	}
	
	// Filesize and content type
	$cacheid = $item['enclosure']['_params']['url'];
	if (isset($item['enclosure']['_params']['type']) && isset($item['enclosure']['_params']['length']))
	{
		//
	}
	elseif (!isset($data['cache'][$cacheid]))
	{
		$headers = get_remote_headers($item['enclosure']['_params']['url']);
		$type = get_content_type($headers);
		$length = get_content_length($headers);
		
		if (!$headers)
		{
			appcastr_die('Appcastr can\'t make a connection to <code>' . $item['enclosure']['url'] . '</code>, or the file doesn\'t exist.');
		}
		else
		{
			$item['enclosure']['_params']['type'] = $type;
			$item['enclosure']['_params']['length'] = $length;
			
			$data['cache'][$cacheid] = array(
				'type' => $type,
				'length' => $length);
		}
	}
	elseif (isset($data['cache'][$cacheid]))
	{
		$item['enclosure']['_params']['type'] = $data['cache'][$cacheid]['type'];
		$item['enclosure']['_params']['length'] = $data['cache'][$cacheid]['length'];
	}
	
	// Still no content type?
	if (!isset($item['enclosure']['_params']['type']))
	{
		$item['enclosure']['_params']['type'] = 'application/octet-stream';
	}
}

// Any data changes?
if ($data != $olddata)
{
	$dataf = fopen('appcastr/data', 'w+');
	fwrite($dataf, json_encode($data));
	fclose($dataf);
}

// We got everything! Now to print out a lovely, formatted feed
echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle"'
. (appcast_info('format') == 'sparkledotnet' ? ' xmlns:sparkleDotNET="http://bitbucket.org/ikenndac/sparkledotnet"' : '') .
' xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>'."\n";

echo '<title>' . (appcast_info('title') ? appcast_info('title') : 'Untitled') . '</title>';
echo appcast_info('description') ? '<description>' . appcast_info('description') . '</description>' : '';
echo appcast_info('language') ? '<language>' . appcast_info('language') . '</language>' : '';

echo "\n";

foreach ($items as &$item)
{
	echo "<item>\n" . array_to_xml($item) . "</item>\n";
}

echo '</channel>
</rss>';

function array_to_xml($arr, $fromrecurse=false)
{
	$ret = '';
	
	foreach ($arr as $key => $value)
	{
		// If we came here from a recurse, then _params would've been dealt with
		if ($fromrecurse && $key == '_params')
			break;
		
		// Array?
		if (is_array($value))
		{
			$ret .= "<$key";
			
			$lencheck = 0;
			
			// Parameters
			if (isset($value['_params']))
			{
				foreach ($value['_params'] as $key2 => $value2)
				{
					$ret .= " $key2=\"$value2\"";
				}
				
				$lencheck = 1;
			}
			
			// Do we have anything else now?
			if (count($value) > $lencheck)
			{
				$ret .= ">\n" . array_to_xml($value, true) . "</$key>";
			}
			else
			{
				$ret .= " />";
			}
		}
		else
		{
			$ret .= "<$key>$value</$key>";
		}
		
		$ret .= "\n";
	}
	return $ret;
}

function appcast_info($key)
{
	global $data;
	return $data['appcasts'][$_GET['id']][$key];
}

function get_items($id)
{
	$items = array();
	try
	{
		$items = json_decode(file_get_contents('appcastr/' . $id), true);
	}
	catch (Exception $e)
	{
		appcastr_die('Could not decode `appcastr/' . $id . '`.<p>'.$e);
	}
	return $items;
}

function appcastr_die($message)
{
	appcastr_page('Error!', '<p>' . $message);
}

function appcastr_page($title, $body)
{
die('<!DOCTYPE html>
<html>
<head>
<title>Appcastr</title>
<style type="text/css">

body { background: #ebedea; color: #000; padding: 30px; font-size: 13px; font-family: "helvetica neue", helvetica, arial, sans-serif; }
a { text-decoration: none; color: #4183C4; } a:hover { text-decoration: underline; }
p, ul { margin: 8px 0; line-height: 20px; }
ul { padding-left: 24px; }

h1 { font-size: 20px; margin: 10px; padding: 0 0 4px 0; text-shadow: 0 1px 0 rgba(255,255,255,0.5); }
h2 { font-size: 19px; margin: 24px 0 8px; color: #333; padding-bottom: 4px; border-bottom: 1px dotted #ccc; }
h3 { font-size: 15px; margin: 8px 0; color: #444; }
h4 { font-size: 14px; margin: 8px 0; color: #555; }

.error { color: #f00; font-weight: bold; }

#logout { position: absolute; right: 10px; top: -32px; }

#wrap { margin: 0 auto; width: 800px; }
#content { position: relative; background: #fff; background: rgba(255,255,255,0.75); padding: 20px; border: 1px solid #dcdcea;
	box-shadow: 0 0 8px rgba(0, 0, 0, 0.05);
	-webkit-box-shadow: 0 0 8px rgba(0, 0, 0, 0.05);
	border-radius: 4px;
	-moz-border-radius: 4px;
	-webkit-border-radius: 4px; }
#content h2:first-child { margin-top: 0; }
footer { float: right; margin-right: 10px; text-shadow: 0 1px 0 rgba(255,255,255,0.5); color: #aaa; font-size: 10px; }
footer a { color: #888; }

#background { position: absolute; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; z-index: -1; }
#background div:before { content: "\2668"; font-size: 1000px; opacity: 0.02;
position: absolute; left: -100px; bottom: -380px; }
#background div:after { content: "Appcastr."; font-size: 100px; opacity: 0.02;
position: absolute; left: 500px; bottom: 40px; }

</style>
</head>
<body>
<div id="wrap">
<h1>' . $title . '</h1>
<div id="content">
' . $body . '
</div>
<footer><p>Appcastr 0.2 &copy; <a href="http://rewrite.name/">Richard Z.H. Wang</a> 2010-2011</footer>
</div>
<div id="background"><div></div></div>
</body>
</html>');
}

function appcastr_password($raw)
{
	global $salt;
	return hash('whirlpool', $salt . $raw);
}

// Function from webcheatsheet.com/PHP/get_current_page_url.php
function curPageURL() {
 $pageURL = 'http';
 if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}

// Based on code from codesnippets.joyent.com/posts/show/1214
function get_remote_headers($url)
{
   $parsed = parse_url($url);
   $host = $parsed["host"];
   $fp = @fsockopen($host, 80, $errno, $errstr, 20);
   if(!$fp) return false;
   else {
       @fputs($fp, "HEAD $url HTTP/1.1\r\n");
       @fputs($fp, "HOST: $host\r\n");
       @fputs($fp, "Connection: close\r\n\r\n");
       $headers = "";
       while(!@feof($fp))$headers .= @fgets ($fp, 128);
   }
   @fclose ($fp);
   $return = false;
   $arr_headers = explode("\n", $headers);
   foreach($arr_headers as $header) {
			// follow redirect
			$s = 'Location: ';
			if(substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
				$url = trim(substr($header, strlen($s)));
				return get_remote_headers($url);
			}
   }
   return $arr_headers;
}
function get_content_length($headers)
{
	foreach($headers as $header) {
       $s = "Content-Length: ";
       if(substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
           $return = trim(substr($header, strlen($s)));
           break;
       }
   }
   return $return;
}
function get_content_type($headers)
{
	foreach($headers as $header) {
       $s = "Content-Type: ";
       if(substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
           $return = trim(substr($header, strlen($s)));
           break;
       }
   }
   return $return;
}
?>