=== XSPF Playlists Generator ===
Contributors:grosbouff
Donate link:http://bit.ly/gbreant
Tags: Tomahawk, toma.hk, xspf, playlists, parser, music, Spotify, Grooveshark, Soundcloud, Ex.fm
Requires at least: 3.5
Tested up to: 4
Stable tag: trunk
License: GPLv2 or later

Parse tracklists from websites and generate a dynamic XSPF file out of it; and embed it as a Toma.hk playlist in your post.

== Description ==

This plugin allows you to parse any tracklist found on internet and generate a dynamic [XSPF](http://en.wikipedia.org/wiki/XSPF/) file out of it; and a [Toma.hk](http://toma.hk/) playlist URL.
The idea behind this plugin is that you can parse tracklists from radio stations websites (for example) and listen to them directly on your website (with a Toma.hk playlist embed) or within [Tomahawk](http://www.tomahawk-player.org/) (with the XSPF link).
So you are able to listen to the tracks provided by those stations, without the ads and the chat !

= Create a new playlist =

In the Wordpress backend, you can create/manage playlists in the "Playlist Parsers" section of the left menu.
It works like regular posts (add your playlist title, description, featured image...), but there is a new metabox (Wizard) under the editor :
Fill the informations required (tracklist URL, tracks selector, ...) then save your post.

You can check 'XSPF link' to add the playlist link before the post content, and you can directly embed the playlist (from toma.hk) by checking 'Embed playlist'.

You can also check an option to compare tracks data to [MusicBrainz](http://musicbrainz.org/) entries, which try to get more accurate metadatas for the tracks (but is slower), or embed a toma.hk playlist directly in your post.
 
= Demo =
See it in action [here](http://radios.pencil2d.org/playlist).

= Import playlists from our HQ ! =
We made an [XML export](https://github.com/gordielachance/xspf-playlists-generator/blob/master/HQstations.xml) of our stations. 
You can import it (Tools > Import > Wordpress) once the plugin has been installed !
Feel free to send us yours !

= Duplicating playlists =
If you consider to create playlists from several radio stations of a same network, consider installing the [Duplicate Post](https://wordpress.org/plugins/duplicate-post/) plugin, which will allow you to create "templates" that you will be able to clone.

= Contributors =
[Contributors are listed
here](https://github.com/gordielachance/xspf-playlists-generator/contributors)
= Notes =

For feature request and bug reports, [please use the
forums](http://wordpress.org/support/plugin/xspf-playlists-generator#postform).

If you are a plugin developer, [we would like to hear from
you](https://github.com/gordielachance/xspf-playlists-generator). Any contribution would be
very welcome.

== Installation ==

Upload the plugin to your blog and Activate it.

== Frequently Asked Questions ==

= How can I display the XSPF link in my templates ? =

See function xspf_plgen_get_xspf_permalink() in xspf-plgen-templates.php.

= How can I display a Toma.hk playlist in my templates ? =

See function xspf_plgen_get_tomahk_playlist() in xspf-plgen-templates.php.
You could also be interested by the functions xspf_plgen_get_tomahk_playlist_link() and xspf_plgen_get_tomahk_playlist_id() of the same file.

This last function is also responsible for the submission of the XSPF file to Toma.hk : it sends the XSPF file to Toma.hk, returns the Toma.hk playlist ID, and stores it as a post meta.
So the XSPF is only sent the first time; next time the value from the post meta is retrieved.

== Screenshots ==
1. Metabox shown under the editor, used to parse a web playlist

== Changelog ==
= 0.1.7 =
* renamed post type from 'xspf_plgen' to 'playlist'.  See http://stackoverflow.com/a/14918890/782013 to update your database if needed.
= 0.1.6 =
* replaced get_doc_content() by native wp_remote_get()
* regex stuff improvement
* bug fixes
= 0.1.5 =
* Improved wizard
= 0.1.4 =
* Minor
= 0.1.3 =
* Added /m option for regexes
* Escaping regex expression to display it in the input
= 0.1.2 =
* Fixed bug when updating a post with the Quick Edit
= 0.1.1 =
* Checks for beginning/trailing slash for the patterns, before executing the regexes for title/artist/album
* Improved the way we catch tracklists (with curl); so no more need to set time arguments in the url / set timezone / make regexes
= 0.1 =
* First release

== Upgrade Notice ==

== Localization ==