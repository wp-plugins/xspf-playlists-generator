=== XSPF Playlists Generator ===
Contributors: grosbouff
Tags: Tomahawk, toma.hk, xspf, playlists, parser, music, Spotify, Grooveshark, Soundcloud, Ex.fm
Requires at least: 3.5
Tested up to: 3.5.1
Stable tag: trunk

Parse tracklists from websites and generate a dynamic XSPF file out of it; with its a Toma.hk playlist URL.

== Description ==

This plugin allows you to parse any tracklist found on internet and generate a dynamic XSPF file out of it; and a Toma.hk playlist URL.
The idea behind this plugin is that you can parse tracklists from radio stations websites (for example) and listen to them directly on your website (with a Toma.hk playlist embed) or within Tomahawk (with the XSPF link).
So you are able to listen to the tracks provided by those stations, without the ads and the chat !

= Create a new playlist =

In the Wordpress backend, you can create/manage playlists in the "Playlist Parsers" section of the left menu.
It works like regular posts (add your playlist title, description, featured image...), but there is a new metabox (Playlist Options) under the editor :
Fill the informations required (tracklist URL, tracks selector, ...) then save your post.

You can check 'XSPF link' to add the playlist link before the post content (or use template function 'xspf_plgen_get_xspf_permalink()'),
and you can directly embed the playlist (from toma.hk) by checking 'Embed playlist'.

You can also check an option to compare tracks data to MusicBrainz entries, which try to get more accurate metadatas for the tracks (but is slower).
 

== Installation ==

Upload the plugin to your blog and Activate it.

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 0.1 =
* First release

== Upgrade Notice ==

== Localization ==