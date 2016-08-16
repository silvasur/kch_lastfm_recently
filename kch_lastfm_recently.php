<?php

/*
 * kch_lastfm_recently.php
 * This file is part of kch_lastfm_recently
 *
 * Copyright (C) 2010 - Laria Carolin Chabowski
 *
 * kch_lastfm_recently is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; GPLv2 only.
 *
 * kch_lastfm_recently is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with kch_lastfm_recently; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, 
 * Boston, MA  02110-1301  USA
 */



// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Uncomment and edit this line to override:
# $plugin['name'] = 'abc_plugin';

$plugin['version'] = '0.5.1';
$plugin['author'] = 'Laria Carolin Chabowski';
$plugin['author_uri'] = 'http://hi-im.laria.me/';
$plugin['description'] = 'Add your recently played tracks from last.fm to your website.';

// Plugin types:
// 0 = regular plugin; loaded on the public web side only
// 1 = admin plugin; loaded on both the public and admin side
// 2 = library; loaded only when include_plugin() or require_plugin() is called
$plugin['type'] = 0; 


@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

h1. kch_lastfm_recently

Returns a unsorted list of your recently listened songs.

h2. Usage

Insert this txp tag to your template:
@<txp:kch_lastfm_recently />@

Here is a table of parameters:

|_. Parameter|_. Needed|_. Default|_. Explanation|
|name|Yes|@ @|Your last.fm account name|
|count|No|3|How many songs should be listed|
|cover|No|1|Whether album covers should be displayed, or not|
|track_format|No|{ar} - {s} ({t})|Format of track information (See beyond)|
|date_format|No|%d %h %Y %H:%M:%S|Time/Date format as used by strftime[1]|
|caching|No|1|Should we enable caching? (See section caching)|

And here is a list of *track_format* tokens (case sensitive):
* @{ar}@ - *Ar*tist
* @{s}@ - *S*ong name
* @{al}@ - *Al*bum name ( will turn to "(?)", if Album is unknown)
* @{t}@ - The *T*ime, you've listened to the song

h2. Output

The output will be a unsorted list (@<ul> ... </ul>@) with the CSS class @kch_lastfm_recently@ .
The single list elements have this format:

bc. <li>
  <img src="http://lastfm-or-amazon-server.foo/path/to/image" alt="Album cover" style="width:60px;height:60px;" />
  <a href="http://last.fm/path/to/song/informations">The parsed track_format</a></li>";
</li>

h2. Hidden errors

If something went wrong, @<txp:kch_lastfm_recently />@ will not displaying anything, but it will write an HTML comment with an errormessage. So if you can not see anything, first check the returned HTML code, if there is a comment with an error message.

h2. Caching

If you have enabled caching, kch_lastfm_recently will save the results to "/my/textpattern/installation/textpattern/cache".
So if you want to use caching, you have to create a directory called "caching" inside the textpattern directory and the HTTP server must have write permission to it.
Results are cached for one minute. If you have multiple @<txp:kch_lastfm_recently />@ tags with different configurations, this is not a problem, because the cache file gets a unique identifier based on the parameters.
If you have the possibility to create the cache directory and set the required permissions, you really should use this feature, because the last.fm API calls can generate a lot of traffic, if your website has much hits.

fn1. Documentation of "strftime":http://php.net/strftime

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

/* For internal use only... */
function kch_INTERNAL_lastfm_api_call($function, $params)
{
	/* Prepare the API call */
	$api_call_url = "http://ws.audioscrobbler.com/2.0/?method=$function";
	$params['api_key'] = "68a0243a9eef21f92766800a084c566c";
	foreach($params as $p_key => $p_value)
		$api_call_url .= "&$p_key=".urlencode($p_value);
	
	$raw_response = @file_get_contents($api_call_url);
	return $raw_response==false ?  false : new SimpleXMLElement($raw_response);
}

function kch_lastfm_recently($atts)
{
	$plugin_params = lAtts(array(
		"name" => "",
		"count" => "3",
		"cover" => "1",
		"track_format" => "{ar} - {s} ({t})",
		"date_format" => "%d %h %Y %H:%M:%S",
		"caching" => "1"
	), $atts);
	
	$img_default = "http://cdn.last.fm/flatness/catalogue/noimage/2/default_artist_mega.png";
	
	/* Check attributes */
	if(empty($plugin_params['name'] ))
		return "<!-- lastfm-plugin: Invalid params -->";
	foreach(array('count','cover','caching') as $testme)
		if(!is_numeric($plugin_params[$testme]))
			return "<!-- lastfm-plugin: Invalid params -->";
	
	/* get cached data (if available and not too old) */
	if($plugin_params['caching'])
	{
		$cache_filename = getcwd() . '/textpattern/cache/kch_lastfm_recently_' . md5(implode(':',$plugin_params));
		if(file_exists($cache_filename) and (filemtime($cache_filename)>(time()-60)))
			return file_get_contents($cache_filename);
	}
	
	$output = '<ul class="kch_lastfm_recently">';
	$count = 0; /* We have to count all entries, because last.fm sometimes send more tracks */
	
	$lastfm_query_result = kch_INTERNAL_lastfm_api_call("user.getRecentTracks", array(
		"user"	=> $plugin_params['name'],
		"limit"	=> $plugin_params['count'])
	);
	if($lastfm_query_result == false)
		return "<!-- lastfm-plugin: failed to call last.fm. correct username? -->";
	
	foreach($lastfm_query_result->recenttracks->track as $track)
	{
		if($count++ == $plugin_params['count'])
			break;
		
		$d_album = empty($track->album) ? '(?)' : $track->album;
		
		/* Get album cover or, if not available, a picture of the artist. */
		if(!empty($track->image))
			$d_image = $track->image[1];
		else
		{
			if(!($artist_image_pre = kch_INTERNAL_lastfm_api_call("artist.getInfo",  array(
				"artist" => $track->artist
			))))
				$d_image = $img_default;
			else
				$d_image = !empty($artist_image_pre->artist->image) ? $artist_image_pre->artist->image[1] : $img_default;
		}
		
		$datetext = $track->date["uts"] > 0 ? @strftime($plugin_params['date_format'],$track->date["uts"]+0) : ' ';
		
		/* WARNING: The following command is *very* ugly! */
		$infotext = str_replace(array(
			"{ar}", "{s}", "{al}", "{t}"), array(
			$track->artist, $track->name, $d_album, $datetext),
			$plugin_params['track_format']);
		$song_url = $track->url;
		$output .= "<li>";
		if($plugin_params['cover'])
			$output .= "<img src=\"$d_image\" alt=\"Album cover\" style=\"width:60px;height:60px;\" />";
		$output .= "<a href=\"$song_url\">$infotext</a></li>";
	}
	$output .= '</ul><a href="http://www.last.fm" class="kch_lastfm_recently_lfm_credits">Powered by Last.fm</a>';
	/* The TOS of last.fm forces us to credit them... */
	
	if($plugin_params['caching'] and ($track->date["uts"]>0))
	{
		$cfh = fopen($cache_filename,"w");
		fwrite($cfh, $output);
		fclose($cfh);
	}
	
	return $output;
}

# --- END PLUGIN CODE ---

?>
