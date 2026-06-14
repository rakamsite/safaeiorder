<?php
/**
 * Minimal line icon pack used by request form cards and the form builder.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Form_Icon_Pack {
	const DEFAULT_ICON = 'repair';

	public static function get_icons() {
		return array(
			'car'      => array(
				'label' => 'خودرو',
				'svg'   => '<path d="M3.5 12.5h14v12h-14z" /><path d="M17.5 15.5h5l4.5 5v4.5h-3" /><path d="M17.5 24.5h-6" /><path d="M3.5 24.5h2" /><path d="M21.5 18.5v3h5" /><circle cx="8.5" cy="24.5" r="3" /><circle cx="24.5" cy="24.5" r="3" />',
			),
			'parts'    => array(
				'label' => 'قطعه و لوازم',
				'svg'   => '<circle cx="16" cy="16" r="4" /><path d="M16 4.5v4" /><path d="M16 23.5v4" /><path d="M4.5 16h4" /><path d="M23.5 16h4" /><path d="M7.9 7.9l2.8 2.8" /><path d="M21.3 21.3l2.8 2.8" /><path d="M24.1 7.9l-2.8 2.8" /><path d="M10.7 21.3l-2.8 2.8" />',
			),
			'repair'   => array(
				'label' => 'تعمیر',
				'svg'   => '<path d="M21.5 5.5a6.2 6.2 0 0 0-6.6 8.2L6.2 22.4a2.8 2.8 0 0 0 4 4l8.7-8.7a6.2 6.2 0 0 0 7.6-7.7l-4.1 4.1-3.5-3.5 4.1-4.1a6.1 6.1 0 0 0-1.5-1Z" /><path d="M8.2 24.4h.1" />',
			),
			'phone'    => array(
				'label' => 'تماس',
				'svg'   => '<path d="M10.3 6.8c1.1-.8 2.7-.7 3.5.4l1.9 2.6c.7 1 .6 2.3-.2 3.1l-1.5 1.5c1.3 2.5 3.3 4.5 5.8 5.8l1.5-1.5c.8-.8 2.1-.9 3.1-.2l2.6 1.9c1 .8 1.2 2.3.4 3.5l-1.1 1.5c-.8 1.1-2.2 1.6-3.5 1.2-7-2-12.6-7.6-14.6-14.6-.4-1.3.1-2.7 1.2-3.5Z" />',
			),
			'chat'     => array(
				'label' => 'گفتگو',
				'svg'   => '<path d="M6.5 8.5h19v12h-8l-4.5 4v-4h-6.5z" /><path d="M11 14h.1" /><path d="M16 14h.1" /><path d="M21 14h.1" />',
			),
			'calendar' => array(
				'label' => 'زمان‌بندی',
				'svg'   => '<rect x="6.5" y="8.5" width="19" height="17" rx="2.5" /><path d="M6.5 13h19" /><path d="M11 5.5v6" /><path d="M21 5.5v6" /><path d="M11 17h4" /><path d="M11 20.5h6" />',
			),
			'shield'   => array(
				'label' => 'اطمینان',
				'svg'   => '<path d="M16 4.5l8 3v7.2c0 5.5-3.4 9.5-8 12.8-4.6-3.3-8-7.3-8-12.8V7.5l8-3Z" /><path d="M12.5 16l2.5 2.5 4.5-5" />',
			),
			'box'      => array(
				'label' => 'بسته',
				'svg'   => '<path d="M6.5 11.5 16 7.5l9.5 4-9.5 4-9.5-4Z" /><path d="M6.5 11.5v9l9.5 4 9.5-4v-9" /><path d="M16 15.5v9" />',
			),
			'clipboard' => array(
				'label' => 'فرم',
				'svg'   => '<path d="M11 6.5h2a3 3 0 0 1 6 0h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H11a2 2 0 0 1-2-2v-14a2 2 0 0 1 2-2Z" /><path d="M13.5 6.5h5a1.5 1.5 0 0 1-1.5-1.5 1.5 1.5 0 0 0-3 0 1.5 1.5 0 0 1-0.5 1.5Z" /><path d="M13.5 13h5" /><path d="M13.5 17h5" /><path d="M13.5 20h3" />',
			),
		);
	}

	public static function get_icon( $icon_key ) {
		$icon_key = sanitize_key( $icon_key );
		$icons    = self::get_icons();
		return isset( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : $icons[ self::DEFAULT_ICON ];
	}

	public static function get_icon_keys() {
		return array_keys( self::get_icons() );
	}

	public static function get_icon_label( $icon_key ) {
		$icon = self::get_icon( $icon_key );
		return isset( $icon['label'] ) ? $icon['label'] : '';
	}

	public static function sanitize_icon_key( $icon_key ) {
		$icon_key = sanitize_key( $icon_key );
		return in_array( $icon_key, self::get_icon_keys(), true ) ? $icon_key : self::DEFAULT_ICON;
	}

	public static function get_icon_markup( $icon_key ) {
		$icon = self::get_icon( $icon_key );
		return '<svg viewBox="0 0 32 32" focusable="false" aria-hidden="true">' . $icon['svg'] . '</svg>';
	}
}
