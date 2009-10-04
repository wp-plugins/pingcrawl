=== Plugin Name ===
Contributors: mikegogulski
Donate link: http://www.nostate.com/support-nostatecom/
Tags: pingback, trackback, tags, seo, google, post, plugin, links, pingbacks, trackbacks, posts
Requires at least: 2.8
Tested up to: 2.8.4
Stable tag: trunk

Pingcrawl adds a "Possibly related posts" capability by querying Google Blog Search for post tags and pinging the results. Limits are configurable.

== Description ==

Pingcrawl aims to replicate WordPress.com's "Possibly related posts" capability. It queries Google Blog Search for related blog posts (based on tags, or the post title if there are no tags), tries to verify that they are pingback/trackback compatible sites and, if so, adds links to such posts to the end of each blog post where it is invoked.

Scope-limiting configuration options are included to enable Pingcrawl to fit any WordPress installation's memory/execution time budget. 

== Installation ==

1. Download the plugin and extract the contents of the ZIP file to the wp-content/plugins/ folder. Alternately, use automatic installation from WordPress's admin screens.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to Settings -> Pingcrawl to configure options.

== Changelog ==

= 3.1 =

* Released to wordpress.org

= 3.0 =

* My initial release based on version [2.8 alpha](http://joshteam.wordpress.com/2009/06/17/pingcrawl2/) by [Josh Team](http://joshteam.wordpress.com/).
* Stripped down version requires PHP5, WP2.7 (at least, for the new HTTP API; **untested below 2.8.4**).
* No database tables; ping information is all stored in post metadata.
* Admin menus cleaned up, made to reflect plugin functionality.
* Ignores blogsearch results containing: htm, html, blogspot, livejournal, ning.com, aspx, vbulletin (which rarely if ever accept WP ping notifications).
* If no pingable posts for a tag are found, no output is added.
* If no pingable posts at all are found, nothing at all is added.
* Minimal debugging available through PHP error logging if function mjg() is enabled by uncommenting it in the source.
* Stripped out code that original author(s) were using to attract links to their own sites.

== Frequently Asked Questions ==

= Why does it take so long to post articles now? =

Pingcrawl needs to fetch and process a lot of web pages in order to do its job. Get patient.

= Why do I get a "Page not found" error when posting? =

The script is either running out of memory or executing for too long.

This can be remedied in two ways:

* Reduce the max tags, max pings per tag and/or Google hits options.
* Increase PHP's `max_execution_time` and/or `memory_limit` in your php.ini.

= How can I prevent Pingcrawl from running on a given post? =

Two ways:

* Disable the plugin until you want to use it again.
* Before publishing the post, create a custom field called 'pingcrawl_pinged' and set its value to '1'.

= How can I re-ping a given post? =

Delete the 'pingcrawl_pinged' custom field from the post.

= Why don't my pingbacks/trackbacks show up at the sites I'm pinging? =

* Blog owners may delete your ping/trackbacks.
* Blog owners may mark your ping/trackbacks as spam.
* If you've been spammy with pings in the past, your new pings might be marked as spam by anti-ping-spam plugins.

== Licence ==

Pingcrawl is free software released under the GPL. If you find it valuable, you're invited to make a [small donation](http://www.nostate.com/support-nostatecom/ "Donate with PayPal, MoneyBookers, etc.") toward its ongoing support and maintenance.
