<?php

/*
Plugin Name: Pingcrawl
Plugin URI: http://www.nostate.com/
Description: When you post, Pingcrawl searches Google blog search for similar posts based on the post tags or title. It then links to them at the bottom of the post as similar posts, which WordPress tries to ping automatically.
Version: 3.1
Author: Mike Gogulski
Author URI: http://www.nostate.com/
*/

// based on work by Josh Team (http://joshteam.wordpress.com/) and others

// try to increase the PHP maximum execution time
set_time_limit(360);

// debug function. Outputs $text to the PHP error log
function mjg ($text) {
	// uncomment the next line to enable debug logging
	//error_log($text);
}

// Download and return the page/file from the provided URL or FALSE if the download fails.
function download_page($url){
	$response = wp_remote_get($url);
	if (is_wp_error($response)) {
		return FALSE;
	}
	return $response['body'];	
}

if (!class_exists('Pingcrawl')) {
	class Pingcrawl {

		var $postID;

		function Pingcrawl() {
			add_action('publish_post', array($this , 'init'), 100);
		}

		function init($postId) {
			mjg('----------------------------------------------');
			mjg('Pingcrawl_init');
			if (did_action('publish_post') > 1) {
				mjg('publish_post > 1');
				return;
			}
			$post = get_post( $postId );
			$this->postID = $postId;
			if (get_post_meta($postId, 'pingcrawl_pinged', true) == "1") {
				mjg('pingcrawl_pinged = true');
				return;
			}
			if (get_post_meta($postId, 'pingcrawl_force', true) == "1" || get_option('pingcrawl_frequency') > rand(0, 99)) {
				delete_post_meta($opost->ID, "pingcrawl_pinged");
				mjg('forced or frequency ok');
				$this->savePost($post);
			}
		}
	
		function getRss($tag, $amount) {
			mjg('getRss: ' . $tag . ', ' . $amount);
			// filter out: .html, .htm, ning.com, .aspx, blogspot, livejournal, vbulletin
			// TODO: externalize this into a configurable list
			$bUri = 'http://blogsearch.google.com/blogsearch_feeds?num=%d&ie=UTF-8&output=rss&scoring=d&c2coff=1&safe=off&q=%s+-inurl%%3Ahtml+-inurl%%3Ahtm+-inurl%%3Aning-com+-inurl%%3Aaspx+-inurl%%3Ablogspot+-inurl%%3Alivejournal+-inurl%%3Avbulletin';
			$tagUri = str_replace(' ', '+', $tag);
			$aUri = sprintf($bUri, $amount, $tagUri);
			mjg('fetching ' . $aUri);
			$feed = download_page($aUri);
			return $feed;
		}
	
		function findPingback($url) {
			mjg('findPingback: ' . $url);
			$source = download_page($url);
			$puretext = strip_tags($source);
			if (stripos($puretext, 'trackback') === false && stripos($puretext, 'pingback') === false) {
				mjg('trackback/pingback text not found on page');
				return false;
			}
			// TODO: This regex could be improved
			$pattern = '<link rel="pingback" href="([^"]+)" ?/?>';
			preg_match($pattern, $source, $matches);
			if (count($matches) < 1) {
				mjg('no xmlrpc link');
				return false;
			}
			mjg($matches[1]);
			return $matches[1];
		}
	
		function getRelatedPosts($rss, $tag, $max) {
			mjg('getRelatedPosts: ' . $tag);
			$content = '';
			$found = 0;
			$feed = simplexml_load_string($rss);
			if (count($feed->channel->item) < 1)
				return $content;
			foreach ($feed->channel->item as $post) {
				$pb = $this->findPingback($post->link);
				if ($pb) {
					if ($found == 0)
						$content .= '<ul><li style="list-style: none;">Related posts on <b>' . $tag . '</b></li>';
					$content .= '<li><a href="' . $post->link . '">' . $post->title . '</a></li>';
					$found++;
					if ($found == $max)
						break;
				}
			}
			if ($found > 0)
				$content .= '</ul>';
			return $content;
		}
	
		function savePost($opost) {
			$maxGoogleHits = get_option('pingcrawl_max_google_hits');
			$maxPings = get_option('pingcrawl_max_pings_per_tag');
	
			$tags = get_the_tags($opost->ID);
			$data = '';
			if (is_array($tags)) {
				$tags = array_slice($tags, 0, get_option('pingcrawl_max_tags'));
				foreach ($tags as $tag) {
					$rss = $this->getRss('%%22' . $tag->name . '%%22', $maxGoogleHits);
					$pbData = $this->getRelatedPosts($rss, $tag->name, $maxPings);
					if ($pbData != '' && $data == '')
						$data .= '<h4>Possibly related posts: (automatically generated)</h4>';
					$data .= $pbData;
				}
			} else {
				// No tags? Use the title!
				$title = get_the_title($opost->ID);
				$rss = $this->getRss($tags, $maxGoogleHits);
				$pbData = $this->getRelatedPosts($rss, $title, $maxPings);
				if ($pbData != '' && $data == '')
					$data .= '<h4>Possibly related posts: (automatically generated)</h4>';
				$data .= $pbData;
			}
			$post = (object) NULL;
			$post->ID = $opost->ID;
			$post->post_content = $opost->post_content . $data;
			wp_update_post($post);
	
			update_post_meta($opost->ID, "pingcrawl_pinged", "1", true);
		}
	}
}

function install_pingcrawl() {
	require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
	add_option('pingcrawl_max_tags', 3);
	add_option('pingcrawl_max_pings_per_tag', 3);
	add_option('pingcrawl_max_google_hits', 15);
	add_option('pingcrawl_frequency', 100);
}

if( class_exists( 'Pingcrawl' ) ) {
	add_action( 'plugins_loaded', create_function( '', 'global $pc; $pc = new Pingcrawl();' ) );
	register_activation_hook(__FILE__, 'install_pingcrawl');
}

if (is_admin()) {
	add_action('admin_menu', 'pingcrawl_menu');
	add_action('admin_init', 'register_pingcrawl_settings');
	add_filter('plugin_row_meta', 'register_plugin_links', 10, 2);
}

function register_plugin_links($links, $file) {
	$plugin = plugin_basename(__FILE__);
	if ($file == $plugin) {
		$links[] = '<a href="options-general.php?page=pingcrawl">' . __('Settings') . '</a>';
		$links[] = '<a href="http://www.nostate.com/support-nostatecom/">' . __('Donate') . '</a>';
	}
	return $links;
}

function pingcrawl_menu() {
	add_options_page('Pingcrawl Options', 'Pingcrawl', 8, 'pingcrawl', 'pingcrawl_options');
}

function register_pingcrawl_settings() {
	if (function_exists('register_setting')) {
		register_setting('pingcrawl_options', 'pingcrawl_max_tags');
		register_setting('pingcrawl_options', 'pingcrawl_max_pings_per_tag');
		register_setting('pingcrawl_options', 'pingcrawl_max_google_hits');
		register_setting('pingcrawl_options', 'pingcrawl_frequency');
	}
}

function HtmlPrintBoxHeader($id, $title) {
	?>
	<div id="<?php echo $id; ?>" class="postbox">
	<h3 class="hndle"><span><?php echo $title ?></span></h3>
       	<div class="inside">
       	<?php
}

function HtmlPrintBoxFooter() {
       	?>
       	</div>
       	</div>
       	<?php
}

function pingcrawl_options() {
	?>

	<style type="text/css">
	.pc-padded .inside { margin:12px!important; }
	.pc-padded .inside ul { margin:6px 0 12px 0; }
	.pc-padded .inside input { padding:1px; margin:0; }
	a.pc_giftPayPal { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-paypal.gif); }
	a.pc_giftAmazon { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-amazon.gif); }
	a.pc_giftRevmoney { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-revmoney.gif); }
	a.pc_giftPecunix { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-pecunix.gif); }
	a.pc_giftMoneybookers { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-moneybookers.gif); }
	a.pc_pluginHome { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-nostate.gif); }
	a.pc_pluginSupport { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-wordpress.gif); }
	a.pc_pluginBugs { background-image:url(<?php echo trailingslashit(plugins_url(basename(dirname(__FILE__)))); ?>img/icon-trac.gif); }
	a.pc_button {
		padding:4px;
		display:block;
		padding-left:25px;
		background-repeat:no-repeat;
		background-position:5px 50%;
		text-decoration:none;
		border:none;
	}
	
	a.pc_button:hover {
		border-bottom-width:1px;
	}
	</style>

	<div class="wrap">
	<form method="post" action="options.php">
		<input type="hidden" name="action" value="update" />
		<?php wp_nonce_field('update-options'); ?>
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="pingcrawl_max_tags,pingcrawl_max_pings_per_tag,pingcrawl_max_google_hits,pingcrawl_frequency" />
	<h2>Pingcrawl Options</h2>

	<div id="poststuff" class="metabox-holder has-right-sidebar">
	<div class="inner-sidebar">
	<div id="side-sortables" class="meta-box-sortabless ui-sortable" style="position:relative;">
	<?php HtmlPrintBoxHeader('pingcrawl_support', 'Support'); ?>
	<p>Gifts to support development of Pingcrawl and other plugins are most welcome!</p>
	<ul>
	<li><a class="pc_button pc_giftPayPal" href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=mikegogulski%40gmail%2ecom&item_name=Wordpress%20Plugin%20(Pingcrawl)&no_shipping=0&no_note=1&tax=0&currency_code=USD&charset=UTF%2d8&lc=US" title="Send a gift via PayPal">Send a gift via PayPal</a></li>
	<li><a class="pc_button pc_giftRevmoney" href="https://www.revolutionmoneyexchange.com/paybyrmx.aspx?sellertype=PAY&amp;selleremail=mike%40gogulski.com&amp;amount=&amp;desc=Pingcrawl%20gift%20to%20Mike%20Gogulski" title="Send a gift via Revolution MoneyExchange">Send a gift via Revolution MoneyExchange</a>
	<li><a class="pc_button pc_giftPecunix" href="https://www.pecunix.com/pay.me?mike@gogulski.com" title="Send a gift via Pecunix">Send a gift via Pecunix</a>
	<li><a class="pc_button pc_giftMoneybookers" href="https://www.moneybookers.com/app/?rid=3271107" title="Send a gift via Moneybookers">Sign up and send a gift to mike@gogulski.com via Moneybookers</a>
	<li><a class="pc_button pc_giftAmazon" href="http://www.amazon.co.uk/registry/wishlist/1VP7NMTZDHP8F" title="My Amazon wishlist">My Amazon wishlist</a></li>
	</ul>
	<?php HtmlPrintBoxFooter(); ?>
	</div></div>

	<div class="has-sidebar pc-padded" >
	<div id="post-body-content" class="has-sidebar-content">
	<div class="meta-box-sortabless">
	<?php HtmlPrintBoxHeader('pingcrawl_options', 'Options'); ?>

	<ul>
		<li>Here you can customize the behavior of Pingcrawl to suit your needs.</li>
		<li><strong>Max tags to use:</strong> Pingcrawl will get the first x tags on the post based on the number set here. Default is 3. Higher numbers could make posting very slow.</li>
		<li><strong>Max pingbacks per tag:</strong> The maximum number of pingbacks to do per tag. Default is 3.</li>
		<li><strong>Max Google hits:</strong> The number of results to request from Google blog search. Default is 15. Higher values will slow posting.</li>
		<li><strong>Pingcrawl frequency:</strong> The probability that Pingcrawl will execute when publishing a post. Default is 100%. If you set this lower, you can force Pingcrawl to run on any post by adding the custom field 'pingcrawl_force' with a value of 1.</li>
		<li>Max tags to use <input type="text" name="pingcrawl_max_tags" value="<?php echo get_option('pingcrawl_max_tags');?>" /></li>
		<li>Max pingbacks per tag <input type="text" name="pingcrawl_max_pings_per_tag" value="<?php echo get_option('pingcrawl_max_pings_per_tag');?>" /></li>
		<li>Max Google hits <input type="text" name="pingcrawl_max_google_hits" value="<?php echo get_option('pingcrawl_max_google_hits');?>" /></li>
		<li>Pingcrawl frequency <input type="text" name="pingcrawl_frequency" value="<?php echo get_option('pingcrawl_frequency');?>" />%</li>
</ul>
		<p><input type="submit" class="button-primary" value="Save Changes" /></p>
	<?php HtmlPrintBoxFooter(); ?>

	</div></div></div>
	</form>
	<?php echo '</div>';
}

?>
