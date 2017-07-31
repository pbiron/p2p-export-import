# P2P Exporter/Importer

Demonstrates how the hooks in [WordPress Exporter Redux][] and [WordPress Importer Redux][] allow
[P2P][] information can be exported and imported.

## Description

For this plugin to do anything at all, you will need to install/activate [WordPress Exporter Redux][]
and/or [WordPress Importer Redux][].

**Note:** This plugin is **NOT** an official part of [P2P][] and any problems it may cause are solely my
responsibility.

[WordPress Importer Redux]: https://github.com/pbiron/wordpress-importer-v2
[WordPress Importer Redux: Issues]: https://github.com/pbiron/wordpress-importer-v2/issues
[WordPress Exporter Redux]: https://github.com/pbiron/wordpress-exporter-v2
[WordPress Exporter Redux: Issues]: https://github.com/pbiron/wordpress-exporter-v2/issues
[P2P]: https://github.com/scribu/wp-posts-to-posts
[GitHub Updater]: https://github.com/afragen/github-updater

## Install

1. Install [WordPress Exporter Redux][].
1. Install [WordPress Importer Redux][].
1. Install [Composer](https://getcomposer.org/)
1. Install this plugin, either:
   1. directly from GitHub. ([Download as a ZIP](archive/master.zip))
   1. via [GitHub Updater][].
1. After that install the dependencies using composer in the plugin directory:

```bash
composer install
```

## How do I use it?

### To Export P2P information

1. Activate [WordPress Exporter Redux][].
1. Activate this plugin
1. Head to Tools
1. Select "Export (v2)"
1. Follow the on-screen instructions.

### To Import P2P information

1. Activate [WordPress Importer Redux][].
1. Activate this plugin
1. Head to Tools &raquo; Import
1. Select "WordPress (v2)"
1. Follow the on-screen instructions.

## Change Log

### 0.2

* Rewrite to use WP_XMLWriter

### 0.1.1

* Fixed bug in `P2P_Import::remap_xml_keys()`

### 0.1

* Init commit

## To Do's

1. P2P can create relationships from any object to any other object (e.g., from posts to users,
	from posts to posts, from users to terms, etc).  At this time,  we assume that
	ALL P2P extension markup in a WXR instance are from posts to posts, for two reasons:

   1. that is all is necessary to create a mirror of <https://developer.wordpress.org/reference/>;
   1. I'm not all that familiar with how P2P stores other kinds of relationships and
   	think that we'd also have to export additional information (probably at the /rss/channel
   	level) to be able to handle other kind of relationships.

## How can I help?

Have a comment/suggestion about the hooks in [WordPress Exporter Redux][] that this plugin demonstrations?
Head on over to [WordPress Exporter Redux: Issues][] and open an issue.

Have a comment/suggestion about the hooks in [WordPress Importer Redux][] that this plugin demonstrations?
Head on over to [WordPress Importer Redux: Issues][] and open an issue.

Have a comment about this plugin, per se?  I might not respond (after all, this is only a "demo")...but
if it is something really serious, then go ahead and open an issue here.
