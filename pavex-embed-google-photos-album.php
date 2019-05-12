<?php
/**
	Plugin Name: Embed Google Photos album
	Description: Embed your Google Photos album direct from Google Photos
	Version: 2.0.7
	Plugin URI: https://www.publicalbum.org/blog/embedding-google-photos-albums
	Author: pavex@ines.cz
	Author URI: https://www.publicalbum.org/blog/about-pavex
	License: GPLv2
	Text Domain: pavex-embed-google-photos-album
*/


class Pavex_embed_google_photos_album {

// Embed player component
	private $player_js = "https://cdn.jsdelivr.net/npm/publicalbum@latest/dist/pa-embed-player.min.js";

	static public $name = "pavex-embed-player";
	static private $index = 1;


	public function __construct()
	{
		add_shortcode('embed-google-photos-album', array($this, 'shortcode'));
	}





	private function get_dimmension_attr($attrs, $name, $default)
	{
		if (isset($attrs[$name])) {
			if (strtolower($attrs[$name]) == 'auto') {
				return 0;
			}
			elseif (intval($attrs[$name]) > 0) {
				return intval($attrs[$name]);
			}
		}
		return $default;
	}





	public function shortcode($attrs)
	{
		if (count($attrs) == 0) {
			return NULL;
		}
		$link = isset($attrs['link']) ? $attrs['link'] : $attrs[0];
//
		$width = $this -> get_dimmension_attr($attrs, 'width', 0);
		$height = $this -> get_dimmension_attr($attrs, 'height', 480);
		$imageWidth = isset($attrs['image-width']) ? intval($attrs['image-width']) : 1920;
		$imageHeight = isset($attrs['image-height']) ? intval($attrs['image-height']) : 1080;
		$includeThumbnails = isset($attrs['include-thumbnails']) ? strtolower($attrs['include-thumbnails']) == 'true' : NULL;
		$attachMetadata = isset($attrs['attach-metadata']) ? strtolower($attrs['attach-metadata']) == 'true' : NULL;
		$slideshowAutoplay = isset($attrs['slideshow-autoplay']) ? strtolower($attrs['slideshow-autoplay']) == 'true' : NULL;
		$slideshowDelay = isset($attrs['slideshow-delay']) ? intval($attrs['slideshow-delay']) : NULL;
		$slideshowRepeat = isset($attrs['slideshow-repeat']) ? strtolower($attrs['slideshow-repeat']) == 'true' : NULL;
//
		return $this -> get_html([$link,
			$width, $height, $imageWidth, $imageHeight,
			$includeThumbnails, $slideshowAutoplay, $slideshowDelay, $slideshowRepeat
		]);
	}





	public function getcode($link, $width = 0, $height = 480, $imageWidth = 1920, $imageHeight = 1080,
			$includeThumbnails = NULL, $slideshowAutoplay = NULL, $slideshowDelay = NULL, $slideshowRepeat = NULL)
	{
		return $this -> get_html([$link,
			$width, $height, $imageWidth, $imageHeight,
			$includeThumbnails, $slideshowAutoplay, $slideshowDelay, $slideshowRepeat
		]);
	}





	private function get_html(array $props)
	{
		if (self::$index == 1) {
			wp_enqueue_script(self::$name, $this -> player_js);
		}
//
		global $post;
		$transient = sprintf('%s-%d-%d', self::$name, $post -> ID, self::$index++);
//
		if ($html = get_transient($transient)) {
			return $html;
		}
		if ($html = $this -> get_embed_player_html_code($props)) {
			set_transient($transient, $html);
			return $html;
		}
		return NULL;
	}





	private function get_remote_contents($url)
	{
		$response = wp_remote_get($url);
		if (!is_wp_error($response)) {
			return wp_remote_retrieve_body($response);
		}
		return NULL;
	}





	private function parse_ogtags($contents)
	{
		$m = NULL;
		preg_match_all('~<\s*meta\s+property="(og:[^"]+)"\s+content="([^"]*)~i', $contents, $m);
		$ogtags = array();
		for($i = 0; $i < count($m[1]); $i++) {
			$ogtags[$m[1][$i]] = $m[2][$i];
		}
		return $ogtags;
	}





	private function parse_photos($contents)
	{
		$m = NULL;
		preg_match_all('~\"(http[^"]+)"\,[0-9^,]+\,[0-9^,]+~i', $contents, $m);
		return array_unique($m[1]);
	}





	private function get_embed_player_html_code(array $props)
	{
		list(
			$link,
			$width,
			$height,
			$imageWidth,
			$imageHeight,
			$includeThumbnails,
			$slideshowAutoplay,
			$slideshowDelay,
			$slideshowRepeat
		) = $props;
//
		if ($contents = $this -> get_remote_contents($link)) {
			$og = $this -> parse_ogtags($contents);
			$title = isset($og['og:title']) ? $og['og:title'] : NULL;
			$photos = $this -> parse_photos($contents);
//
			$style = 'display:none;'
				. 'width:' . ($width === 0 ? '100%' : ($width . 'px')) . ';'
				. 'height:' . ($height === 0 ? '100%' : ($height . 'px')) . ';';
//
			$items_code = '';
			foreach ($photos as $photo) {
				$src = sprintf('%s=w%d-h%d', $photo, $imageWidth, $imageHeight);
				$items_code .= '<img data-src="' . $src . '" src="" alt="" />';
			}
			return "<!-- publicalbum.org embed player -->\n"
				. '<div class="pa-embed-player" style="' . $style . '"'
				. ($title ? ' data-title="' . $title . '"' : '')
				. ($slideshowAutoplay !== NULL ? ' data-slideshow-autoplay="' . ($slideshowAutoplay ? 'true' : 'false') . '"' : '')
				. ($slideshowDelay > 0 ? ' data-slideshow-delay="' . $slideshowDelay . '"' : '')
				. ($slideshowRepeat !== NULL ? ' data-slideshow-repeat="' . ($slideshowRepeat ? 'true' : 'false') . '"' : '')
				. '>' . $items_code . '</div>' . "\n";
		}
		return NULL;
	}
}





add_action('init', function() {
	new Pavex_embed_google_photos_album();
});





add_action('save_post', function($post_id) {
	$index = 1;
	while (get_transient($transient = sprintf('%s-%d-%d', Pavex_embed_google_photos_album::$name, $post_id, $index++))) {
		delete_transient($transient);
	}
});
