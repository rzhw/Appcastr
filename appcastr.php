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

if (!is_writable('appcastr/'))
{
	appcastr_die('Appcastr doesn\'t have permissions to write to the <code>appcastr</code> directory.');
}

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
die('<!DOCTYPE html>
<html>
<head>
<title>Appcastr Error</title>
<style type="text/css">
body { background: #eef; color: #000; padding: 40px; font-size: 14px; font-family: "helvetica neue", helvetica, arial, sans-serif; }
h1 { font-size: 20px; margin: 0; padding: 0; }
#content { background: #fff; padding: 20px; border: 1px solid #dcdcea;
border-radius: 4px;
-moz-border-radius: 4px;
-webkit-border-radius: 4px; }
footer { float: right; margin-right: 10px; }
footer, footer a { color: #aaa; font-size: 10px; }
</style>
</head>
<body>
<div id="content">
<h1>Error!</h1>
<p>' . $message . '</p>
</div>
<footer><p>Appcastr 0.2 &copy; <a href="http://rewrite.name/">Richard Z.H. Wang</a> 2010-2011</footer>
</body>
</html>');
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