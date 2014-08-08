=== XSPF Playlists Generator ===
Contributors:grosbouff
Donate link:http://bit.ly/gbreant
Tags: Tomahawk, Hatchet, hatchet.is, xspf, playlists, parser, music, Spotify, Grooveshark, Soundcloud, Ex.fm, Subsonic
Requires at least: 3.5
Tested up to: 4
Stable tag: trunk
License: GPLv2 or later

Parse tracklists from websites and generate a dynamic XSPF file out of it !

== Description ==

This plugin allows you to parse any tracklist found on internet and generate a dynamic [XSPF](http://en.wikipedia.org/wiki/XSPF/) file out of it.

The idea behind this plugin is that you can parse tracklists from radio stations websites (for example, but it could also be static playlists) and listen to them directly on your website (with a Hatchet.is playlist embed) or within [Tomahawk](http://www.tomahawk-player.org/) (with the XSPF link).
So you are able to listen to the tracks provided by those stations, without the ads and the chat !

This plugin has been developped for the website [XSPF Radios HQ](http://radios.pencil2d.org/).
If you just want quickly create a dynamic XSPF file, maybe you don't need to install the plugin : 
head towards XSPF Radios HQ and [create directly your playlist](http://radios.pencil2d.org/wordpress/wp-admin/post-new.php?post_type=playlist) there !

= Create a new playlist =

In the Wordpress backend, you can create/manage playlists in the "Playlist Parsers" section of the left menu.
It works like regular posts (add your playlist title, description, featured image...), but there is a new metabox (Playlist Parser Wizard) under the editor :
Fill the informations required (tracklist URL, tracks selector, ...) then save your post.

You can also check an option to compare tracks data to [MusicBrainz](http://musicbrainz.org/) entries, which try to get more accurate metadatas for the tracks (but is slower).
Other options are available under Playlist Parsers > Options

 
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

See function xspfpl_get_xspf_permalink() in xspfpl-templates.php.

= How can I display a Hatchet.is playlist in my templates ? =

First, you have to install and activate the [Hatchet](https://wordpress.org/plugins/wp-hatchet/) plugin.
Then, you can enable embedding playlists widget in Playlist Parsers > Options, or you can call the function xspfpl_get_widget_playlist() in xspfpl-templates.php.

== Screenshots ==
1. List of playlists (backend)
2. Playlist Parser Wizard Metabox (backend)
3. Options page (backend)

== Changelog ==
= 0.3.0 =
* + lots of bugs fixes and improvements
* static playlists (do not re-parse the URL each time) option.
* custom taxonomy playlist_tag (replacing post_tag) - migration is automatical.
* custom capabilities
* less options in wizard (simplier)
* sanitize playlist options when saved
* new : options page
* updated toma.hk stuff to hatchet.is
* new : function xspfpl_get_last_cached_tracks() / updated function xspfpl_get_last_cached_track()
= 0.2.1 =
* fixed bug in post_column_register()
= 0.2.0 =
* Clean-up on filenames, class names, meta key names.
= 0.1.9 =
* Merged wizard settings into one single meta; + database update for previous versions
= 0.1.8 =
* new column "XSPF Requests" - shows how many times an XSPF playlist has been requested.
* last (cached) track : admin column & template function
* 2 minutes cache for get_tracks()
* xspfpl_get_health() : each time tracks are populated, check if tracks are found. Health is calculated on this.
* improved get_tracks()
* splitted files
= 0.1.7 =
* renamed post type from 'xspf-plgen' to 'playlist'.  See http://stackoverflow.com/a/14918890/782013 to update your database if needed.
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

= To Do =

* Publish playlists on Hatchet (//Hatchet.is API : https://api.hatchet.is/apidocs/#!/playlists)
* Send posts to Radios HQ using XML-RPC (http://www.skyverge.com/blog/extending-the-wordpress-xml-rpc-api/)

== Upgrade Notice ==

== Localization ==