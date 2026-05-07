<?php
namespace Seoflix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu admin principal de Seoflix.
 */
final class Admin_Menu {

	public const SLUG = 'seoflix';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ], 9 );
	}

	public static function register_menu(): void {
		add_menu_page(
			'Seoflix',
			'Seoflix',
			'manage_options',
			self::SLUG,
			[ Admin_Dashboard::class, 'render' ],
			'dashicons-controls-play',
			3
		);

		add_submenu_page(
			self::SLUG,
			'Tableau de bord',
			'Tableau de bord',
			'manage_options',
			self::SLUG,
			[ Admin_Dashboard::class, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			'Vidéos à valider',
			'Vidéos à valider',
			'manage_options',
			'seoflix-pending',
			[ Admin_Pending::class, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			'Ingestion',
			'Ingestion',
			'manage_options',
			'seoflix-ingestion',
			[ Admin_Ingestion::class, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			'Stats affiliation',
			'Stats affiliation',
			'manage_options',
			'seoflix-affiliate-stats',
			[ Admin_Affiliate_Stats::class, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			'Réglages',
			'Réglages',
			'manage_options',
			'seoflix-settings',
			[ Admin_Settings::class, 'render' ]
		);
	}
}
