<?php
// Potential errors
if (!isset($_GET['id']))
{
	cac_die('CloudAppCast needs "id" to be passed in the GET parameters.');
}

if ($_GET['id'] == 'sizecache')
{
	cac_die('Reserved id');
}

if (strstr($_GET['id'], '/') || strstr($_GET['id'], '\\'))
{
	cac_die('CloudAppCast will not accept an id containing a slash.');
}

if (!file_exists('cloudappcast-' . $_GET['id'] . '.txt'))
{
	cac_die('CloudAppCast couldn\'t find the file associated with the given id.');
}

if (!is_writable('.'))
{
	cac_die('CloudAppCast doesn\'t have permissions to write inside its folder.');
}

// Size cache
// Format: id, next line is size, etc
if (!file_exists('cloudappcast-sizecache.txt'))
{
	$sizecache = array();
}
else
{
	$sizecache = file('cloudappcast-sizecache.txt', FILE_IGNORE_NEW_LINES);
}
$sc = fopen('cloudappcast-sizecache.txt', 'a');

// Item ids
$iteminfos = file('cloudappcast-' . $_GET['id'] . '.txt');

// Now let's get the ids
$title = '';
$items = array();
$i = 0;
$i_gotid = false;
$i_gottitle = false;
$i_gotversion = false;
$i_gotdate = false;
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
			cac_die_invalidformat('Initial separator not found or invalid.');
	}
	// And now for the rest
	else
	{
		// The id to use for inserting into $items
		$ii = $i - 2;
		
		// If we have a --- then the next line is a new thingy
		if ($iteminfo == '---')
		{
			if ($i_gotdate)
			{
				$i_gotid = false;
				$i_gottitle = false;
				$i_gotversion = false;
				$i_gotdate = false;
				$i++;
			}
			else
			{
				cac_die_invalidformat('Separator found between items even though the previous one didn\'t have one of the following; an id, title or date.');
			}
		}
		// Let's do some parsing!
		else
		{			
			// The first line should have the id
			if (!$i_gotid)
			{
				$i_gotid = true;
				
				// Store the id
				$items[$ii]['id'] = $iteminfo;
				
				// Let's get the size while we're at it
				if (!in_array($iteminfo, $sizecache))
				{
					$size = get_remote_file_size('http://cl.ly/' . $iteminfo . '/content');
					if (!$size)
					{
						cac_die('CloudAppCast can\'t get a connection to <code>http://cl.ly/</code>, or the file doesn\'t exist.');
					}
					else
					{
						$sizecache[] = $iteminfo;
						$sizecache[] = $size;
						$items[$ii]['size'] = $size;
						fwrite($sc, "$iteminfo\n$size\n");
					}
				}
				else
				{
					// The size is the line after the id
					$key = array_search($iteminfo, $sizecache);
					$items[$ii]['size'] = $sizecache[$key + 1];
				}
			}
			// Next is the title
			elseif (!$i_gottitle)
			{
				$i_gottitle = true;
				$items[$ii]['title'] = $iteminfo;
			}
			// Next is the version
			elseif (!$i_gotversion)
			{
				$i_gotversion = true;
				$items[$ii]['version'] = $iteminfo;
			}
			// Next is the date
			elseif (!$i_gotdate)
			{
				$i_gotdate = true;
				$date = strtotime($iteminfo);
				if ($date === false)
				{
					cac_die_invalidformat('Invalid date.');
				}
				else
				{
					$items[$ii]['date'] = date(DATE_ATOM, $date);
				}
			}
			// The rest is the description
			else
			{
				$items[$ii]['description'] .= $iteminfo . "\n";
			}
		}
	}
}

// We got everything! Now to print out a lovely, formatted feed
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
<title>' . $title . '</title>
<description>Most recent changes with links to updates.</description>
<language>en</language>';

foreach ($items as $item)
{
echo '<item>
<title>' . $item['title'] . '</title>
<pubDate>' . $item['date'] . '</pubDate>
<description><![CDATA[' . str_replace("\n", '<br />', $item['description']) . ']]></description>
<enclosure length="' . $item['size'] . '" sparkle:version="' . $item['version'] . '" type="application/octet-stream" url="http://cl.ly/' . $item['id'] . '/content" />
</item>
';
}

echo '</channel>
</rss>';

// Error message handling
function cac_die_invalidformat($extra = '')
{
	cac_die('Improperly formatted item information file; please consult the docs.' . (!empty($extra) ? ' <b>Details:</b> ' . $extra : ''));
}

function cac_die($message)
{
die('<!DOCTYPE html>
<html>
<head>
<title>CloudAppCast Error</title>
<style type="text/css">
body { background: #eef; color: #000; padding: 16px; font-size: 14px; font-family: "helvetica neue", helvetica, arial, sans-serif; }
h1 { font-size: 20px; margin: 0; padding: 0; }
footer, footer a { color: #aaa; font-size: 10px; }
</style>
</head>
<body>
<h1>CloudAppCast Error!</h1>
<p>' . $message . '</p>
<footer><p>CloudAppCast 0.1 is a project from the weird and wonderful mind of <a href="http://a2h.uni.cc/">a2h</a></footer>
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