<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralisation des clés post_meta pour éviter les fautes de frappe.
 */
final class Meta_Keys {

	// Video
	public const VIDEO_YOUTUBE_ID         = '_seoflix_youtube_id';
	public const VIDEO_DURATION           = '_seoflix_duration';
	public const VIDEO_PUBLISHED_AT       = '_seoflix_published_at';
	public const VIDEO_VIEW_COUNT         = '_seoflix_view_count';
	public const VIDEO_THUMBNAIL_URL      = '_seoflix_thumbnail_url';
	public const VIDEO_YOUTUBE_URL        = '_seoflix_youtube_url';
	public const VIDEO_CHANNEL_ID         = '_seoflix_channel_id';        // ID du post seoflix_channel
	public const VIDEO_KEY_CONCEPTS       = '_seoflix_key_concepts';      // JSON array
	public const VIDEO_PRODUCTS           = '_seoflix_products';          // JSON array of product post IDs
	public const VIDEO_TRANSCRIPT_AVAILABLE = '_seoflix_transcript_available';

	// Channel
	public const CHANNEL_HANDLE           = '_seoflix_handle';
	public const CHANNEL_YOUTUBE_ID       = '_seoflix_youtube_channel_id';
	public const CHANNEL_REAL_NAME        = '_seoflix_real_name';
	public const CHANNEL_SUBSCRIBER_COUNT = '_seoflix_subscriber_count';
	public const CHANNEL_THUMBNAIL_URL    = '_seoflix_channel_thumbnail';
	public const CHANNEL_YOUTUBE_URL      = '_seoflix_channel_url';

	// Product
	public const PRODUCT_OFFICIAL_URL     = '_seoflix_official_url';
	public const PRODUCT_AFFILIATE_URL    = '_seoflix_affiliate_url';
	public const PRODUCT_PRICING          = '_seoflix_pricing';
	public const PRODUCT_LOGO_URL         = '_seoflix_logo_url';
}
