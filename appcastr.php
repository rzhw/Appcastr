<?php
// Potential errors
if (!isset($_GET['id']))
{
	appcastr_die('AppCastr needs "id" to be passed in the GET parameters.');
}

if ($_GET['id'] == 'sizecache')
{
	appcastr_die('Reserved id');
}

if (strstr($_GET['id'], '/') || strstr($_GET['id'], '\\'))
{
	appcastr_die('AppCastr will not accept an id containing a slash.');
}

if (!file_exists('appcastr-' . $_GET['id'] . '.txt'))
{
	appcastr_die('AppCastr couldn\'t find the file associated with the given id.');
}

if (!is_writable('appcastr-sizecache.txt'))
{
	appcastr_die('AppCastr doesn\'t have permissions to write to <code>appcastr-sizecache.txt</code>.');
}

if (isset($_GET['sparkledotnet']))
{
	$sparkledotnet = true;
}

// Size cache
// Format: id, next line is size, etc
if (!file_exists('appcastr-sizecache.txt'))
{
	$sizecache = array();
}
else
{
	$sizecache = file('appcastr-sizecache.txt', FILE_IGNORE_NEW_LINES);
}
$sc = fopen('appcastr-sizecache.txt', 'a');

// Item ids
$iteminfos = file('appcastr-' . $_GET['id'] . '.txt');

// Now let's get the ids
$title = '';
$items = array();
$i = 0;

foreach ($iteminfos as $iteminfo)
{
	$iteminfo = trim($iteminfo);
	
	// First line is title
	if ($i == 0)
	{
		$title = $iteminfo;
		$i++;
	}
	// Second line must have ---
	elseif ($i == 1)
	{
		if ($iteminfo == '---')
			$i++;
		else
			appcastr_die_invalidformat('Initial separator not found or invalid.');
	}
	// And now for the rest
	else
	{
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
				
				// Filesize
				if (!in_array($json['enclosure']['url'], $sizecache))
				{
					$size = get_remote_file_size($json['enclosure']['url']);
					
					if (!$size)
					{
						appcastr_die('AppCastr can\'t make a connection to <code>' . $json['enclosure']['url'] . '</code>, or the file doesn\'t exist.');
					}
					else
					{
						$json['enclosure']['length'] = $size;
						fwrite($sc, "{$json['enclosure']['url']}\n$size\n");
					}
				}
				else
				{
					// The size is the line after the id
					$key = array_search($iteminfo, $sizecache);
					$json['enclosure']['length'] = $sizecache[$key + 1];
				}
				
				// Filetype
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
}

// We got everything! Now to print out a lovely, formatted feed
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle"'
. ($sparkledotnet ? ' xmlns:sparkleDotNET="http://bitbucket.org/ikenndac/sparkledotnet"' : '') . ' xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
<title>' . $title . '</title>
<description>Most recent changes with links to updates.</description>
<language>en</language>';

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

function appcastr_die($message)
{
die('<!DOCTYPE html>
<html>
<head>
<title>AppCastr Error</title>
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
<footer><p>AppCastr 0.2 &copy; <a href="http://rewrite.name/">Richard Z.H. Wang</a> 2010-2011</footer>
</body>
</html>');
}

// From http://codesnippets.joyent.com/posts/show/1214
function get_remote_file_size($url, $readable = true){
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
				return get_remote_file_size($url, $readable);
			}
			
			// parse for content length
       $s = "Content-Length: ";
       if(substr(strtolower ($header), 0, strlen($s)) == strtolower($s)) {
           $return = trim(substr($header, strlen($s)));
           break;
       }
   }
   /*if($return && $readable) {
			$size = round($return / 1024, 2);
			$sz = "KB"; // Size In KB
			if ($size > 1024) {
				$size = round($size / 1024, 2);
				$sz = "MB"; // Size in MB
			}
			$return = "$size $sz";
   }*/
   return $return;
}

?>