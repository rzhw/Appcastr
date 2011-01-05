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
		
		$strappcasts = '';
		foreach ($data['appcasts'] as $appcast => $params)
		{
			$strappcasts .= '
			<h3>' . $params['title'] . ' <a href="?admin&edit='.$appcast.'">(edit)</a></h3>
			<p>ID: ' . $appcast . '
			<br>Type: ' . $params['type'] . '
			<br>Description: "' . $params['description'] . '"';
		}
		
		appcastr_page('Admin panel', '
		<a id="logout" href="?admin&logout">Logout</a>
		
		<h2>Appcasts</h2>
		' . $strappcasts . '');
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

// Item ids
$iteminfos = file('appcastr/' . $_GET['id']);

// Now let's get the ids
$title = '';
$items = array();
$i = 0;

foreach ($iteminfos as $iteminfo)
{
	$iteminfo = trim($iteminfo);
	
	// Did we just hit a separator?
	if ($iteminfo == '---')
	{
		$str = implode("\n", $itemarr[$i - 2]);
		$json = array();
		$description = '';
		$fail = false;
		
		if (preg_match('/(\{(.*)\n\})(.*)/sm', $str, $regs))
		{
			$result = $regs[1];
			$description = $regs[3];
			
			try
			{
				$json = json_decode($result, true);
			}
			catch (Exception $e)
			{
				$fail = true;
			}
		}
		else
		{
			$fail = true;
		}
		
		if ($fail)
		{
			appcastr_die('Separator found even though a valid JSON formatted string wasn\'t found.');
		}
		else
		{
			// Description
			$json['description'] = '<![CDATA[' . str_replace("\n", '<br>', trim($description)) . ']]>';
			
			// Publish date
			$date = strtotime($json['date']);
			if ($date === false)
			{
				appcastr_die_invalidformat('Invalid date.');
			}
			else
			{
				unset($json['date']);
				$json['pubDate'] = date(DATE_ATOM, $date);
			}
			
			// Filesize and content type
			$cacheid = $json['enclosure']['url'];
			if (!isset($data['cache'][$cacheid]))
			{
				$headers = get_remote_headers($json['enclosure']['url']);
				$type = get_content_type($headers);
				$length = get_content_length($headers);
				
				if (!$headers)
				{
					appcastr_die('Appcastr can\'t make a connection to <code>' . $json['enclosure']['url'] . '</code>, or the file doesn\'t exist.');
				}
				else
				{
					$json['enclosure']['type'] = $type;
					$json['enclosure']['length'] = $length;
					
					$data['cache'][$cacheid] = array(
						'type' => $type,
						'length' => $length);
				}
			}
			else
			{
				$json['enclosure']['type'] = $data['cache'][$cacheid]['type'];
				$json['enclosure']['length'] = $data['cache'][$cacheid]['length'];
			}
			
			// Still no content type?
			if (!isset($json['enclosure']['type']))
			{
				$json['enclosure']['type'] = 'application/octet-stream';
			}
			
			$items[] = $json;
		}
	}
	else
	{
		$itemarr[$i - 2][] = $iteminfo;
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
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle"'
. (appcast_info('type') == 'sparkledotnet' ? ' xmlns:sparkleDotNET="http://bitbucket.org/ikenndac/sparkledotnet"' : '') .
' xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>';

echo '<title>' . (appcast_info('title') ? appcast_info('title') : 'Untitled') . '</title>';
echo appcast_info('description') ? '<description>' . appcast_info('description') . '</description>' : '';
echo appcast_info('language') ? '<language>' . appcast_info('language') . '</language>' : '';

foreach ($items as $item)
{
	echo '<item>' . array_to_xml($item) . '</item>';
}

echo '</channel>
</rss>';

function array_to_xml($arr)
{
	$ret = '';
	foreach ($arr as $key => $value)
	{		
		$ret .= "<$key>";
		
		if (is_array($value))
		{
			$ret .= "\n" . array_to_xml($value);
		}
		else
		{
			$ret .= $value;
		}
		
		$ret .= "</$key>\n";
	}
	return $ret;
}

function appcast_info($key)
{
	global $data;
	return $data['appcasts'][$_GET['id']][$key];
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
p { margin: 8px 0; }
p, ul { line-height: 20px; }

h1 { font-size: 20px; margin: 10px; padding: 0 0 4px 0; text-shadow: 0 1px 0 rgba(255,255,255,0.5); }
h2 { font-size: 17px; margin: 4px 0; color: #333; }
h3 { font-size: 14px; margin: 8px 0; color: #444; }

.error { color: #f00; font-weight: bold; }

#logout { float: right; position: relative; top: -56px; }

#wrap { margin: 0 auto; width: 800px; }
#content { background: #fff; background: rgba(255,255,255,0.5); padding: 20px; border: 1px solid #dcdcea;
	box-shadow: 0 0 8px rgba(0, 0, 0, 0.05);
	border-radius: 4px;
	-moz-border-radius: 4px;
	-webkit-border-radius: 4px; }
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

// Based on code from http://codesnippets.joyent.com/posts/show/1214
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