=== XSPF Stations ===
Contributors:grosbouff
Donate link:http://bit.ly/gbreant
Tags: music,Tomahawk, Hatchet, hatchet.is, xspf, playlists, parser, music, Spotify,Soundcloud, Ex.fm, Subsonic
Requires at least: 3.5
Tested up to: 4.3
Stable tag: trunk
License: GPLv2 or later

Extract online datas (from radio stations, music services, tracklists, XML files, RSS feeds...); and generate a XSPF playlist that stays synced with your source.

== Description ==

Run the Station Wizard to extract online datas (from radio stations, music services, tracklists, XML files, RSS feeds...); and generate a [XSPF](http://en.wikipedia.org/wiki/XSPF/) playlist that stays synced with your source.

You can then listen to your station directly on your website (coming soon, via [Hatchet](http://hatchet.is/); or using [Tomahawk](http://www.tomahawk-player.org/).

Just enjoy the music, without the ads and the chat !

You can of course also use the Station Wizard to create a static playlist; that will not be updated (synced).

This plugin has been developed for the website [Spiff Radio](http://spiff-radio.org/).
If you just want quickly create one station / playlist for your own use, maybe you don't need to install the plugin : 
head towards Spiff Radio and [create directly your playlist](http://www.spiff-radio.org/wordpress/wp-admin/post-new.php?post_type=station) there.

= Features =
* Wizard
* Source feed can be a webpage, an XML file, a RSS feed, ...
* Hatchet.is embeddable widgets to play tracks (coming soon)
* Caching
* Presets for popular services (Slacker, Somafm, Spotify, Radionomy, ...)
* Supports services URLs with variables (eg. https://soundcloud.com/%username/likes)
* Validate tracklist with [MusicBrainz](http://musicbrainz.org/) (slower)

= Create a new station =
*(Requires a basic knowledge of CSS and HTML)*
In the Wordpress backend, head towards the "XSPF Stations" section of the left menu.
It works like regular posts (add your station title, description, featured image...), but there is a new metabox (Station Wizard) under the editor :

Fill the informations required (Base URLs, Tracks Selector, ...) then save your post.
 
= Demo =
See it in action [here](http://spiff-radio.org/).

= Duplicating stations =
If you consider to create several stations sharing almost the same settings, consider installing the [Duplicate Post](https://wordpress.org/plugins/duplicate-post/) plugin, which will allow you to create station "templates".

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


== Screenshots ==
1. List of playlists (backend)
2. Station Wizard Metabox (backend)
3. Options page (backend)

== Changelog ==

= 0.4.0 =
* Presets
* Supports services URLs with variables (eg. https://soundcloud.com/%username/likes)
* Supports JSON and XML as feed source, in addition of HTML
* Improved cache
* Improved (A LOT !) the wizard
* Major release !

= 0.3.2 =
* Added support for 'author' in the custom post type
* Hide PHP warnings & errors when generating XSPF
* Added filters "xspfpl_get_track_artist","xspfpl_get_track_title", "xspfpl_get_track_album".
* Updated tracks cache code.

= 0.3.1 =
* Added "Tomahawk Friendly" plugin's option; to use Tomahawk protocol for playlists links.
* Added functions xspfpl_playlist_links() and xspfpl_get_playlist_links().
* Added "Order" playlist option
* Cleaned up some code

= 0.3.0 =
* + lots of bugs fixes and improvements
* static playlists (do not re-parse the URL each time) option.
* custom taxonomy music_tag (replacing post_tag) - migration is automatical.
* custom capabilities
* less options in wizard (simplier)
* sanitize playlist options when saved
* new : options page
* updated toma.hk stuff to hatchet.is
* new : function xspfpl_get_last_track() / updated function xspfpl_get_last_track()

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
* xspfpl_get_health_status() : each time tracks are populated, check if tracks are found. Health is calculated on this.
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
