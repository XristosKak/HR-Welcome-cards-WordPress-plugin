<?php
/**
 * Προκαθορισμένα templates — ξεχωριστός καμβάς (attachment) ανά preset.
 *
 * Επέκταση: add_filter( 'inventio_wellcome_preset_definitions', function( $defs ) { ... return $defs; } );
 *
 * @package Inventio_Wellcome_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Inventio_Wellcome_Presets
 */
class Inventio_Wellcome_Presets {

	const OPTION_DATA   = 'inventio_wellcome_presets_data';
	const OPTION_ACTIVE = 'inventio_wellcome_active_preset';

	/**
	 * Ορισμοί preset (ετικέτα, διάταξη, προεπιλογές παραγωγής). Κλειδιά: default / balanced (σταθερά για αποθηκευμένους καμβάδες).
	 *
	 * layout: split_aboard (διχοτόμηση) | team_portrait (gradient).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions() {
		$defaults = array(
			'default'  => array(
				'label'                  => __( 'Template «WELLCOME ABOARD» (σχίσιμο 50/50 · καμβάς 1080×1350)', 'inventio-wellcome-card' ),
				'layout'                 => 'split_aboard',
				'split_ratio'            => 0.5,
				'headline'               => '',
				'headline_sub'           => '',
				'headline_preserve_case' => false,
			),
			'balanced' => array(
				'label'                  => __( 'Template «Welcome to the Team» (gradient · καμβάς 1080×1350)', 'inventio-wellcome-card' ),
				'layout'                 => 'team_portrait',
				'split_ratio'            => 0.45,
				'headline'               => '',
				'headline_sub'           => '',
				'headline_preserve_case' => true,
				'team_photo_bg'          => '#bfe8e8',
				'team_card_name'         => '',
				'team_card_title'        => '',
				'team_body_1'            => '',
				'team_body_2'            => '',
				'team_body_3'            => '',
				'team_body_4'            => '',
				'team_body_5'            => '',
				'team_body_1_tone'       => 'dark',
				'team_body_2_tone'       => 'dark',
				'team_body_3_tone'       => 'light',
				'team_body_4_tone'       => 'light',
				'team_body_5_tone'       => 'light',
			),
		);

		/**
		 * Προσθήκη/τροποποίηση preset.
		 *
		 * @param array<string, array<string, mixed>> $defaults
		 */
		return apply_filters( 'inventio_wellcome_preset_definitions', $defaults );
	}

	/**
	 * Μετανάστευση από inventio_wellcome_template_id σε presets_data['default'].
	 */
	public static function maybe_migrate_legacy() {
		$data = get_option( self::OPTION_DATA, null );
		if ( null !== $data ) {
			return;
		}

		$legacy = (int) get_option( 'inventio_wellcome_template_id', 0 );
		$new    = array();
		if ( $legacy > 0 ) {
			$new['default'] = array( 'template_id' => $legacy );
			delete_option( 'inventio_wellcome_template_id' );
		}
		update_option( self::OPTION_DATA, $new );

		$defs  = self::definitions();
		$first = $defs ? array_key_first( $defs ) : '';
		$cur   = get_option( self::OPTION_ACTIVE, false );
		if ( $first && ( false === $cur || '' === (string) $cur ) ) {
			update_option( self::OPTION_ACTIVE, $first );
		}
	}

	/**
	 * @param string $preset_key
	 * @return int Attachment ID καμβά ή 0.
	 */
	public static function get_template_id_for_preset( $preset_key ) {
		$preset_key = sanitize_key( $preset_key );
		$defs       = self::definitions();
		if ( ! isset( $defs[ $preset_key ] ) ) {
			$preset_key = array_key_first( $defs );
		}
		$data = get_option( self::OPTION_DATA, array() );
		if ( ! is_array( $data ) ) {
			return 0;
		}
		$id = isset( $data[ $preset_key ]['template_id'] ) ? absint( $data[ $preset_key ]['template_id'] ) : 0;

		/**
		 * Προκαθορισμένο attachment καμβά ανά preset (π.χ. από θέμα).
		 *
		 * @param int    $id          Αποθηκευμένο ID ή 0.
		 * @param string $preset_key  Slug preset.
		 */
		return (int) apply_filters( 'inventio_wellcome_preset_template_id', $id, $preset_key );
	}

	/**
	 * @param string $preset_key
	 * @return array<string, mixed>|null
	 */
	public static function get_definition_for( $preset_key ) {
		$preset_key = sanitize_key( $preset_key );
		$defs       = self::definitions();
		return $defs[ $preset_key ] ?? null;
	}

	/**
	 * @return string
	 */
	public static function get_active_preset_key() {
		$defs   = self::definitions();
		$active = get_option( self::OPTION_ACTIVE, '' );
		$active = is_string( $active ) ? sanitize_key( $active ) : '';
		if ( '' !== $active && isset( $defs[ $active ] ) ) {
			return $active;
		}
		$first = array_key_first( $defs );
		return $first ? $first : 'default';
	}

	/**
	 * Ποιο preset εμφανίζεται στη σελίδα (query ?inventio_preset= ή αποθηκευμένο ενεργό).
	 *
	 * @return string
	 */
	public static function get_ui_preset_key() {
		$defs = self::definitions();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- απλή εμφάνιση UI, όχι ενέργεια.
		if ( isset( $_GET['inventio_preset'] ) ) {
			$k = sanitize_key( wp_unslash( $_GET['inventio_preset'] ) );
			if ( isset( $defs[ $k ] ) ) {
				return $k;
			}
		}
		return self::get_active_preset_key();
	}

	/**
	 * @param string $preset_key
	 */
	public static function set_active_preset_key( $preset_key ) {
		$preset_key = sanitize_key( $preset_key );
		$defs       = self::definitions();
		if ( ! isset( $defs[ $preset_key ] ) ) {
			$preset_key = array_key_first( $defs );
		}
		if ( $preset_key ) {
			update_option( self::OPTION_ACTIVE, $preset_key );
		}
	}
}
