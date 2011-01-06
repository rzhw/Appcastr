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

// Nope.avi
if (strstr($_GET['id'], '/') || strstr($_GET['id'], '\\'))
{
	appcastr_die('Appcastr will not accept an id containing a slash.');
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
		ob_start();
		
		if (isset($_GET['logout']))
		{
			session_destroy();
			header('Refresh: 0');
			appcastr_page('Admin panel', 'Logging you out...');
		}
		
		switch ($_GET['section'])
		{
			case 'users':
				echo '<h2>Users</h2><ul>';
				foreach ($data['users'] as $username => $passwordhash)
				{
					echo '<li>' . $username . ($username == $_SESSION['username'] ? ' <em>- that\'s you!</em>' : '');
				}
				echo '</ul>';
				break;
				
			case 'appcasts':
			default:				
				switch ($_GET['action'])
				{
					case 'edititem':
						
						if (!isset($data['appcasts'][$_GET['id']]))
						{
							echo '<h2>Error!</h2>Appcast not found.';
							break;
						}
						
						$items = get_items($_GET['id']);
						
						if (isset($_GET['delete']))
						{
							if (!isset($_GET['yes']))
							{
								echo '<p>Are you <strong>absolutely</strong> sure you want to delete the item ' . urldecode($_GET['title']) . '?
								<p>
									<a href="' . curPageURL() . '&yes">Yes</a>';
							}
							else
							{
								$c = count($items);
								for ($i=0; $i<$c; $i++)
								{
									if ($items[$i]['title'] == urldecode($_GET['title']))
									{
										unset($items[$i]);
									}
								}
								save_items($items, $_GET['id']);
							}
							
							break;
						}
						
						if (isset($_POST['submit']))
						{
							if ($_POST['title'] == '' || $_POST['url'] == '' || $_POST['sparkle:version'] = '')
							{
								echo 'Required fields not found.';
								break;
							}
							
							$working_id = -1;
							if (isset($_GET['title']))
							{
								$c = count($items);
								for ($i=0; $i<$c; $i++)
								{
									if ($items[$i]['title'] == urldecode($_GET['title']))
									{
										$working_id = $i;
										break;
									}
								}
								
								if ($working_id == -1)
								{
									echo 'Couldn\'t find the item to save to...';
								}
							}
							else
							{
								$working_id = count($items);
							}
							
							$ignore_if_blank = !isset($_GET['title']);
							
							set_array_value($items[$working_id], $ignore_if_blank, 'title', $_POST['title']);
							set_array_value($items[$working_id], $ignore_if_blank, 'pubDate', $_POST['pubDate']);
							set_array_value($items[$working_id], $ignore_if_blank, 'description', $_POST['description']);
							set_array_value($items[$working_id], $ignore_if_blank, 'sparkle:releaseNotesLink', $_POST['sparkle:releaseNotesLink']);
							set_array_value($items[$working_id]['enclosure']['_params'], $ignore_if_blank, 'url', $_POST['url']);
							set_array_value($items[$working_id]['enclosure']['_params'], $ignore_if_blank, 'sparkle:version', $_POST['sparkle:version']);
							set_array_value($items[$working_id]['enclosure']['_params'], $ignore_if_blank, 'sparkle:shortVersionString', $_POST['sparkle:shortVersionString']);
							set_array_value($items[$working_id]['enclosure']['_params'], $ignore_if_blank, 'sparkle:dsaSignature', $_POST['sparkle:dsaSignature']);
							set_array_value($items[$working_id]['enclosure']['_params'], $ignore_if_blank, 'sparkleDotNET:primaryInstallationFile', $_POST['sparkleDotNET:primaryInstallationFile']);
							set_array_value($items[$working_id]['enclosure']['_params'], $ignore_if_blank, 'sparkleDotNET:executableType', $_POST['sparkleDotNET:executableType']);
							
							save_items($items, $_GET['id']);
							break;
						}
						
						$title = urldecode($_GET['title']);
						
						echo '<h2>Appcasts - ' . ($title ? 'Edit item' : 'Add item') . '</h2>';
						
						$item = array();
						foreach ($items as $it)
						{
							if ($it['title'] == urldecode($_GET['title']))
							{
								$item = $it;
								break;
							}
						}
						
						echo '
						<form action="' . curPageURL() . '" method="post">
							<p><strong>Title (must be unique):</strong>
								<input type="text" style="width: 200px;" name="title" value="' . $item['title'] . '" required>
							<p><strong>Publish date (must be parsable by <a href="http://php.net/manual/en/function.strtotime.php"><code>strtotime</code></a>):</strong>
								<input style="width: 300px;" type="text" name="pubDate" value="' . ($item['pubDate'] ? $item['pubDate'] : date(DATE_ATOM)) . '" required>
							<p><strong>Version:</strong>
								<input type="text" name="sparkle:version" value="' . $item['enclosure']['_params']['sparkle:version'] . '" required>, displayed as (optional):
								<input type="text" name="sparkle:shortVersionString" value="' . $item['enclosure']['_params']['sparkle:shortVersionString'] . '">
							<p><strong>Download link:</strong>
								<input style="width: 100%;" type="url" name="url" value="' . $item['enclosure']['_params']['url'] . '" required>
							<p><strong>DSA signature (optional):</strong>
								<input style="width: 300px;" type="text" name="sparkle:dsaSignature" value="' . $item['enclosure']['_params']['sparkle:dsaSignature'] . '">
							<p><strong>Release notes:</strong>
								<table><tr>
									<td>Enter a URL:<br>
										<input type="url" name="sparkle:releaseNotesLink" value="' . $item['sparkle:releaseNotesLink'] . '"></td>
									<td style="width: 5%; text-align: center;">-or-</td>
									<td style="width: 75%;">
										<textarea name="description" style="width: 100%; height: 128px;">' . $item['description'] . '</textarea></td>
								</tr></table>';
						
						if ($data['appcasts'][$_GET['id']]['format'] == 'sparkledotnet')
						{
							echo '
							<p><strong>Primary installation file (optional)</strong>
								<input style="width: 200px;" type="text" name="sparkleDotNET:primaryInstallationFile"
									value="' . $item['enclosure']['_params']['sparkleDotNET:primaryInstallationFile'] . '">
							<p><strong>Installation file type</strong>
								<select name="sparkleDotNET:executableType">
									<option value="">Don\'t specify</option>
									<option value="InstallShieldSetup"'
										. ($item['enclosure']['_params']['sparkleDotNET:executableType'] == 'InstallShieldSetup' ? 'selected' : '')
										. '>InstallShield (or compatible)</option>
									<option value="InnoSetup"'
										. ($item['enclosure']['_params']['sparkleDotNET:executableType'] == 'InnoSetup' ? 'selected' : '')
										. '>Inno Setup (or compatible)</option>
								</select>';
						}
						
						echo '
							<p style="text-align: right;">
								<a href="' . curPageURL() . '&delete">Delete</a>
								<input type="submit" name="submit" value="Save">
						</form>';
						break;
					default:
						echo '<h2>Appcasts</h2>';
						foreach ($data['appcasts'] as $appcast => $params)
						{
							echo '
							<h3>' . $params['title'] . '
								<a class="button" href="' . curPageURL() . '&action=edit&id='.$appcast.'">Edit</a>
								<a class="button" href="?id='.$appcast.'">Feed</a></h3>
							<p>ID: ' . $appcast . '
							<br>Format: ' . $params['format'] . ($params['formatVersion'] ? ' ('.$params['formatVersion'].')' : '') . '
							<br>Description: ' . ($params['description'] ? $params['description'] : '<em>No description</em>' ). '
							
							<h4>Items
								<a class="button" href="' . curPageURL() . '&action=edititem&id='.$appcast.'">Add</a></h4>
							<ul>';
							
							foreach (get_items($appcast) as $item)
							{
								printf('<li>%s <small>(%s/%s)</small></small>
									<a class="button" href="%s">Edit</a><a class="button" href="%s">Download (%sK)</a>
									<span class="viewnotes"><a class="button" href="#">View Release Notes</a><br>%s</span>',
									$item['title'],
									$item['enclosure']['_params']['sparkle:version'],
									$item['enclosure']['_params']['sparkle:shortVersionString'] ?
										$item['enclosure']['_params']['sparkle:shortVersionString'] : '-',
									curPageURL() . '&action=edititem&id=' . $appcast . '&title=' . urlencode($item['title']),
									$item['enclosure']['_params']['url'],
									round(($item['enclosure']['_params']['length'] ?
										$item['enclosure']['_params']['length']
										: $data['cache'][$item['enclosure']['_params']['url']]['length'])
										/ 1024),
									$item['sparkle:releaseNotesLink'] ?
										'<iframe class="notes" src="' . $item['sparkle:releaseNotesLink'] . '"></iframe>'
										: '<blockquote class="notes">'
											. ($item['description'] ? $item['description'] : '<em>No description</em>')
											. '</blockquote>');
							}
							
							echo '</ul>';
						}
						break;
				}
				break;
		}
		
		appcastr_page('Admin panel', ob_get_clean() .'		
		<div id="menu">
			<a href="?admin&section=appcasts">Appcasts</a>
			&middot; <a href="?admin&section=users">Users</a>
			&middot; <a href="?admin&logout">Logout</a>
		</div>');
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

if (!file_exists('appcastr/' . $_GET['id']))
{
	appcastr_die('Appcastr couldn\'t find the file associated with the given id.');
}

// A simple echo
if (isset($_GET['echo']))
{
	exit(urldecode($_GET['echo']));
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
				$item['sparkle:releaseNotesLink'] = curPageURL() . '&amp;echo=' . urlencode($item['description']);
			}
		}
		
		$item['description'] = '<![CDATA[' . $item['description'] . ']]>';
	}
	
	// The publish date
	if (isset($item['pubDate']))
	{
		$item['pubDate'] = parse_date($item['pubDate']);
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

function parse_date($str)
{
	$date = strtotime($str);
	if ($date === false)
	{
		appcastr_die('Invalid `pubDate` attribute.');
	}
	else
	{
		return date(DATE_ATOM, $date);
	}
}

function set_array_value(&$array, $ignore_if_blank, $key, $value)
{
	// If value is not blank, OR if the value is blank and the key is set to something
	if ($value || (!$ignore_if_blank && isset($array[$key])))
		$array[$key] = $value;
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

function save_items($newitems, $id)
{
	$f = fopen('appcastr/' . $id, 'w+');
	fwrite($f, json_encode($newitems));
	fclose($f);
	
	echo '<p>Success!';
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
p, ul { margin: 8px 0; line-height: 20px; }
ul { padding-left: 24px; }

h1 { font-size: 20px; margin: 10px; padding: 0 0 4px 0; text-shadow: 0 1px 0 rgba(255,255,255,0.5); }
h2 { font-size: 19px; margin: 24px 0 8px; color: #333; padding-bottom: 4px; border-bottom: 1px dotted #ccc; }
h3 { font-size: 15px; margin: 8px 0; color: #333; }
h4 { font-size: 14px; margin: 8px 0; color: #333; }

a { text-decoration: none; color: #4183C4; } a:hover { text-decoration: underline; }
.button, .button.disabled:hover { margin-left: 2px; padding: 2px 7px; background: #eee; border: 1px solid #ddd; color: #444;
	font-size: 10px; font-weight: bold;
	border-radius: 3px;
	-moz-border-radius: 3px;
	-webkit-border-radius: 3px; }
.button:hover { text-decoration: none; background: #eaeaea; border: 1px solid #ccc; }
.button.disabled, .button.disabled:hover { color: #aaa; }

.error { color: #f00; font-weight: bold; }

.viewnotes .notes { display: none; }
.viewnotes:active .notes { display: block; }
blockquote.notes { margin: 8px 0; padding: 12px; border: 1px dashed #bbb; }
iframe.notes { margin: 8px 0; width: 100%; height: 300px; border: 1px dashed #bbb; }

#menu { position: absolute; right: 10px; top: -32px; }

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
#background div:before { content: "\2668"; font-size: 1000px; opacity: 0.03;
position: absolute; left: -100px; bottom: -380px; }
#background div:after { content: "Appcastr."; font-size: 100px; opacity: 0.03;
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