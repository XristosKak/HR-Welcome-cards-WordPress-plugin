<?php
/**
 * Σύνθεση καμβά + φωτογραφία + κείμενο → JPEG (GD).
 *
 * @package Inventio_Wellcome_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Inventio_Wellcome_Renderer
 */
class Inventio_Wellcome_Renderer {

	const CANVAS_TARGET_W = 1080;
	const CANVAS_TARGET_H = 1350;

	/** Μέγιστο μήκος (UTF-8 χαρακτήρες, με κενά/στίξη) για team_body_1 … team_body_5. */
	const TEAM_BODY_MAX_CHARS = 180;

	/**
	 * Περικόπτει κείμενο σώματος team σε μέγιστο αριθμό χαρακτήρων Unicode.
	 *
	 * @param string   $text
	 * @param int|null $max_chars null = TEAM_BODY_MAX_CHARS
	 * @return string
	 */
	public static function limit_team_body_text( $text, $max_chars = null ) {
		if ( null === $max_chars ) {
			$max_chars = (int) self::TEAM_BODY_MAX_CHARS;
		}
		$max_chars = max( 1, min( 500, (int) $max_chars ) );
		$text      = is_string( $text ) ? $text : '';
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text, 'UTF-8' ) <= $max_chars ) {
				return $text;
			}
			return (string) mb_substr( $text, 0, $max_chars, 'UTF-8' );
		}
		if ( strlen( $text ) <= $max_chars ) {
			return $text;
		}
		return substr( $text, 0, $max_chars );
	}

	/**
	 * Επιστρέφει δυαδικό περιεχόμενο JPEG ή WP_Error.
	 *
	 * @param array $args Ορίσματα διάταξης και περιεχομένου.
	 * @return string|\WP_Error
	 */
	public static function render_jpeg( array $args ) {
		if ( ! extension_loaded( 'gd' ) ) {
			return new WP_Error( 'inventio_no_gd', __( 'Η επέκταση GD δεν είναι διαθέσιμη στον διακομιστή.', 'inventio-wellcome-card' ) );
		}

		$defaults = array(
			'template_path'           => '',
			'photo_path'              => '',
			'canvas_title'            => '',
			'headline'                => '',
			'headline_sub'            => '',
			'headline_preserve_case'  => false,
			'name_line_1'             => '',
			'name_line_2'             => '',
			'role_line_1'             => '',
			'role_line_2'             => '',
			'split_ratio'             => 0.42,
			'layout'                  => 'split_aboard',
			'jpeg_quality'            => 92,
			'font_path'               => INVENTIO_WELCOME_PLUGIN_DIR . 'assets/fonts/NotoSans-Bold.ttf',
			'scale_headline'          => 0.052,
			'scale_name'              => 0.032,
			'scale_role'              => 0.022,
			// Split Aboard — κείμενα: κάθετο Y, μέγεθος γραμματοσειράς και max width ως ποσοστά καμβά.
			'split_title_y_ratio'     => 0.09,
			'split_title_scale_ratio' => 0.052,
			'split_title_max_w_ratio' => 0.9,
			'split_name_y_ratio'      => 0.38,
			'split_name_scale_ratio'  => 0.032,
			'split_name_max_w_ratio'  => 0.45,
			'split_name_line_gap_ratio'  => 0.012,
			'split_name_after_gap_ratio' => 0.018,
			'split_role_scale_ratio'     => 0.022,
			'split_role_max_w_ratio'     => 0.42,
			'split_role_line_gap_ratio'  => 0.008,
			'photo_max_w_ratio'       => 0.88,
			'photo_max_h_ratio'       => 0.8,
			'photo_bottom_gap'        => 0,
			// Gradient (team_portrait) — θέση/μέγεθος φωτογραφίας: μέθοδος overlay_photo_team().
			// team_photo_anchor_y_ratio : 0 = πάνω στη ζώνη, 1 = κάτω (μεγαλύτερη τιμή → πιο χαμηλά).
			// team_photo_fit_max_w_ratio / team_photo_fit_max_h_ratio : μέγιστο πλάτος/ύψος ως ποσοστό 1080×1350.
			// team_header_band_ratio : ύψος άνω ζώνης (ποσοστό του ύψους καμβά).
			// team_header_photo_zone_w_ratio : πλάτος αριστερής στήλης τοποθέτησης (ποσοστό πλάτους).
			// team_photo_box_inset : εσωτερικό padding στη στήλη (μικρότερο → περισσότερος χώρος για τη φωτό).
			// team_photo_slot_extend_down_ratio : επέκταση κάτω ορίου slot ως ποσοστό ύψους καμβά (0–0.35).
			// Με anchor=1 το κάτω της φωτό ακουμπά στο κάτω του slot· χωρίς επέκταση, το slot τελειώνει στο header.
			'team_header_band_ratio'                  => 0.24,
			'team_header_photo_zone_w_ratio'        => 0.45,
			'team_photo_box_inset'                    => 1,
			'team_photo_border_px'                  => 3,
			'team_photo_bg'                         => '#bfe8e8',
			'team_photo_bg_pad_px'                  => 8,
			'team_photo_fit_max_w_ratio'            => 0.85,
			'team_photo_fit_max_h_ratio'            => 0.23,
			'team_photo_anchor_y_ratio'             => 0.78,
			'team_photo_slot_extend_down_ratio'     => 0.08,
			'team_card_name'                 => '',
			'team_card_title'                => '',
			'team_card_name_max_w_ratio'     => 0.44,
			'team_name_below_header_ratio'   => 0.058,
			'team_card_after_name_gap_ratio' => 0.028,
			'team_body_1'                    => '',
			'team_body_2'                    => '',
			'team_body_3'                    => '',
			'team_body_4'                    => '',
			'team_body_5'                    => '',
			'team_body_1_tone'               => 'dark',
			'team_body_2_tone'               => 'dark',
			'team_body_3_tone'               => 'light',
			'team_body_4_tone'               => 'light',
			'team_body_5_tone'               => 'light',
			'team_name_block_left_ratio'     => 0.52,
			'team_body_left_x_ratio'         => 0.048,
			'team_body_right_x_ratio'        => 0.535,
			'team_body_col_max_w_ratio'     => 0.43,
			// Κάθε μπλοκ 1–5: δικό του μέγεθος (scale), πλάτος wrap, κενά πριν/μετά (ποσοστά ύψους).
			'team_body_paragraph_gap_ratio'  => 0.011,
			'team_body_1_max_w_ratio'        => 0.25,
			'team_body_1_scale_ratio'      => 0.017,
			'team_body_1_before_gap_ratio' => 0.004,
			'team_body_1_after_gap_ratio'  => 0.002,
			'team_body_2_max_w_ratio'      => 0.24,
			'team_body_2_scale_ratio'      => 0.017,
			'team_body_2_before_gap_ratio' => 0.011,
			'team_body_2_after_gap_ratio'  => 0.04,
			'team_body_3_max_w_ratio'      => 0.24,
			'team_body_3_scale_ratio'      => 0.018,
			'team_body_3_before_gap_ratio' => 0,
			'team_body_3_after_gap_ratio'  => 0.018,
			'team_body_4_max_w_ratio'      => 0.25,
			'team_body_4_scale_ratio'      => 0.017,
			'team_body_4_before_gap_ratio' => 0.012,
			'team_body_4_after_gap_ratio'  => 0.022,
			'team_body_5_max_w_ratio'      => 0.25,
			'team_body_5_scale_ratio'      => 0.018,
			'team_body_5_before_gap_ratio' => 0.01,
			'team_body_5_after_gap_ratio'  => 0.26,
			'team_body_start_y_ratio'        => 0.48,
			'scale_team_body'                => 0.019,
			'team_photo_col_w_ratio'         => 0.44,
			'team_photo_top_ratio'           => 0.19,
			'team_photo_max_h_ratio'         => 0.36,
			'team_photo_margin_ratio'        => 0.04,
			'team_name_left_ratio'           => 0.44,
			'team_name_top_ratio'            => 0.17,
		);

		$config = wp_parse_args( $args, $defaults );
		$config = apply_filters( 'inventio_wellcome_render_config', $config );
		if ( '' === trim( (string) $config['canvas_title'] ) && '' !== trim( (string) $config['headline'] ) ) {
			$config['canvas_title'] = (string) $config['headline'];
		}

		for ( $ti = 1; $ti <= 5; $ti++ ) {
			$bk = 'team_body_' . $ti;
			if ( isset( $config[ $bk ] ) ) {
				$config[ $bk ] = self::limit_team_body_text( (string) $config[ $bk ] );
			}
		}

		if ( empty( $config['template_path'] ) || ! is_readable( $config['template_path'] ) ) {
			return new WP_Error( 'inventio_no_template', __( 'Λείπει ή δεν διαβάζεται το αρχείο καμβά (template).', 'inventio-wellcome-card' ) );
		}

		if ( empty( $config['font_path'] ) || ! is_readable( $config['font_path'] ) ) {
			return new WP_Error( 'inventio_no_font', __( 'Λείπει η γραμματοσειρά για απόδοση κειμένου.', 'inventio-wellcome-card' ) );
		}

		if ( ! function_exists( 'imagettfbbox' ) || ! function_exists( 'imagettftext' ) ) {
			return new WP_Error(
				'inventio_no_freetype',
				__( 'Το PHP GD χρειάζεται FreeType για κείμενο (imagettftext). Ενεργοποιήστε το στον διακομιστή.', 'inventio-wellcome-card' )
			);
		}

		$canvas = self::image_from_file( $config['template_path'] );
		if ( is_wp_error( $canvas ) ) {
			return $canvas;
		}

		$canvas = self::normalize_canvas_size( $canvas, self::CANVAS_TARGET_W, self::CANVAS_TARGET_H );
		if ( is_wp_error( $canvas ) ) {
			return $canvas;
		}

		$layout = sanitize_key( (string) $config['layout'] );
		if ( '' === $layout ) {
			$layout = 'split_aboard';
		}

		if ( 'team_portrait' === $layout ) {
			$placed = self::overlay_photo_team( $canvas, $config );
			if ( is_wp_error( $placed ) ) {
				imagedestroy( $canvas );
				return $placed;
			}
		} elseif ( ! empty( $config['photo_path'] ) && is_readable( $config['photo_path'] ) ) {
			$placed = self::overlay_photo(
				$canvas,
				$config['photo_path'],
				(float) $config['split_ratio'],
				(float) $config['photo_max_w_ratio'],
				(float) $config['photo_max_h_ratio'],
				(float) $config['photo_bottom_gap']
			);
			if ( is_wp_error( $placed ) ) {
				imagedestroy( $canvas );
				return $placed;
			}
		}

		if ( 'team_portrait' === $layout ) {
			self::draw_team_portrait_text( $canvas, $config );
		} else {
			self::draw_split_aboard_text( $canvas, $config );
		}

		$quality = (int) $config['jpeg_quality'];
		$quality = min( 100, max( 60, $quality ) );

		ob_start();
		imagejpeg( $canvas, null, $quality );
		$jpeg = ob_get_clean();
		imagedestroy( $canvas );

		if ( false === $jpeg || '' === $jpeg ) {
			return new WP_Error( 'inventio_jpeg_fail', __( 'Αποτυχία δημιουργίας JPEG.', 'inventio-wellcome-card' ) );
		}

		return $jpeg;
	}

	/**
	 * @param resource|\GdImage $canvas
	 * @param int               $target_w
	 * @param int               $target_h
	 * @return resource|\GdImage|\WP_Error
	 */
	private static function normalize_canvas_size( $canvas, $target_w, $target_h ) {
		$w = imagesx( $canvas );
		$h = imagesy( $canvas );
		if ( $w < 1 || $h < 1 ) {
			imagedestroy( $canvas );
			return new WP_Error( 'inventio_bad_canvas', __( 'Μη έγκυρο μέγεθος καμβά.', 'inventio-wellcome-card' ) );
		}
		if ( $w === $target_w && $h === $target_h ) {
			return $canvas;
		}

		$dst = imagecreatetruecolor( $target_w, $target_h );
		if ( false === $dst ) {
			imagedestroy( $canvas );
			return new WP_Error( 'inventio_resize_canvas', __( 'Αποτυχία κλιμάκωσης καμβά σε 1080×1350.', 'inventio-wellcome-card' ) );
		}

		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
		imagefill( $dst, 0, 0, $transparent );
		imagealphablending( $dst, true );
		imagecopyresampled( $dst, $canvas, 0, 0, 0, 0, $target_w, $target_h, $w, $h );
		imagedestroy( $canvas );

		return $dst;
	}

	/**
	 * @param resource|\GdImage $canvas
	 * @param array             $config
	 */
	private static function draw_split_aboard_text( $canvas, array $config ) {
		$w = imagesx( $canvas );
		$h = imagesy( $canvas );

		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		if ( false === $white ) {
			return;
		}

		$split_x        = (int) round( $w * (float) $config['split_ratio'] );
		$right_center_x = $split_x + (int) round( ( $w - $split_x ) / 2 );

		$font = $config['font_path'];

		$headline_size = max( 12, (int) round( $w * (float) $config['split_title_scale_ratio'] ) );
		$headline_text = self::format_headline( (string) $config['canvas_title'], (bool) $config['headline_preserve_case'] );
		self::draw_wrapped_block_center(
			$canvas,
			$font,
			$headline_size,
			$white,
			(int) round( $w / 2 ),
			(int) round( $h * (float) $config['split_title_y_ratio'] ),
			$headline_text,
			self::ratio_to_px( $w, $config['split_title_max_w_ratio'], 80 )
		);

		$name_size = max( 11, (int) round( $w * (float) $config['split_name_scale_ratio'] ) );
		$role_size = max( 10, (int) round( $w * (float) $config['split_role_scale_ratio'] ) );

		$cursor_y    = (int) round( $h * (float) $config['split_name_y_ratio'] );
		$name_max_w  = self::ratio_to_px( $w, $config['split_name_max_w_ratio'], 80 );
		$role_max_w  = self::ratio_to_px( $w, $config['split_role_max_w_ratio'], 80 );
		$name_gap_px = (int) round( $h * (float) $config['split_name_line_gap_ratio'] );
		$role_gap_px = (int) round( $h * (float) $config['split_role_line_gap_ratio'] );

		$nl1 = self::format_name_role( (string) $config['name_line_1'] );
		$nl2 = self::format_name_role( (string) $config['name_line_2'] );
		if ( '' !== trim( $nl1 ) ) {
			$cursor_y = self::draw_wrapped_block_center( $canvas, $font, $name_size, $white, $right_center_x, $cursor_y, $nl1, $name_max_w );
			$cursor_y += $name_gap_px;
		}
		if ( '' !== trim( $nl2 ) ) {
			$cursor_y = self::draw_wrapped_block_center( $canvas, $font, $name_size, $white, $right_center_x, $cursor_y, $nl2, $name_max_w );
			$cursor_y += (int) round( $h * (float) $config['split_name_after_gap_ratio'] );
		}

		$rl1 = self::format_name_role( (string) $config['role_line_1'] );
		$rl2 = self::format_name_role( (string) $config['role_line_2'] );
		if ( '' !== trim( $rl1 ) ) {
			$cursor_y = self::draw_wrapped_block_center( $canvas, $font, $role_size, $white, $right_center_x, $cursor_y, $rl1, $role_max_w );
			$cursor_y += $role_gap_px;
		}
		if ( '' !== trim( $rl2 ) ) {
			self::draw_wrapped_block_center( $canvas, $font, $role_size, $white, $right_center_x, $cursor_y, $rl2, $role_max_w );
		}
	}

	/**
	 * Μέγιστο πλάτος wrap για team_body_N: team_body_N_max_w_ratio ή team_body_col_max_w_ratio.
	 *
	 * @param int   $canvas_w
	 * @param array $config
	 * @param int   $block_index 1–5
	 * @return int
	 */
	private static function team_body_block_max_w_px( $canvas_w, array $config, $block_index ) {
		$key = 'team_body_' . (int) $block_index . '_max_w_ratio';
		if ( array_key_exists( $key, $config ) && (float) $config[ $key ] > 0 ) {
			return max( 40, (int) round( $canvas_w * (float) $config[ $key ] ) );
		}
		return max( 40, (int) round( $canvas_w * (float) $config['team_body_col_max_w_ratio'] ) );
	}

	/**
	 * Επιπλέον κενό πριν το μπλοκ N (ποσοστό ύψους καμβά).
	 *
	 * @param int   $canvas_h
	 * @param array $config
	 * @param int   $block_index 1–5
	 * @return int pixels
	 */
	private static function team_body_block_before_gap_px( $canvas_h, array $config, $block_index ) {
		$key = 'team_body_' . (int) $block_index . '_before_gap_ratio';
		if ( array_key_exists( $key, $config ) ) {
			return (int) round( $canvas_h * (float) $config[ $key ] );
		}
		return 0;
	}

	/**
	 * Κενό μετά το μπλοκ N: team_body_N_after_gap_ratio ή team_body_paragraph_gap_ratio.
	 *
	 * @param int   $canvas_h
	 * @param array $config
	 * @param int   $block_index 1–5
	 * @return int pixels
	 */
	private static function team_body_block_after_gap_px( $canvas_h, array $config, $block_index ) {
		$key = 'team_body_' . (int) $block_index . '_after_gap_ratio';
		if ( array_key_exists( $key, $config ) ) {
			return (int) round( $canvas_h * (float) $config[ $key ] );
		}
		$fallback = isset( $config['team_body_paragraph_gap_ratio'] ) ? (float) $config['team_body_paragraph_gap_ratio'] : 0.022;
		return (int) round( $canvas_h * $fallback );
	}

	/**
	 * Μέγεθος γραμματοσειράς (px) για team_body_N: team_body_N_scale_ratio ή scale_team_body (ποσοστό πλάτους καμβά).
	 *
	 * @param int   $canvas_w
	 * @param array $config
	 * @param int   $block_index 1–5
	 * @return int
	 */
	private static function team_body_block_scale_px( $canvas_w, array $config, $block_index ) {
		$key = 'team_body_' . (int) $block_index . '_scale_ratio';
		$ratio = (float) $config['scale_team_body'];
		if ( array_key_exists( $key, $config ) && (float) $config[ $key ] > 0 ) {
			$ratio = (float) $config[ $key ];
		}
		return max( 9, (int) round( $canvas_w * $ratio ) );
	}

	/**
	 * Διάταξη gradient (team portrait): ζώνη φωτογραφίας με χρώμα + περίγραμμα, όνομα/τίτλος, 5 κείμενα, footer.
	 *
	 * @param resource|\GdImage $canvas
	 * @param array             $config
	 */
	private static function draw_team_portrait_text( $canvas, array $config ) {
		$w = imagesx( $canvas );
		$h = imagesy( $canvas );

		$white    = imagecolorallocate( $canvas, 255, 255, 255 );
		$dark_txt = imagecolorallocate( $canvas, 8, 22, 40 );
		if ( false === $white || false === $dark_txt ) {
			return;
		}

		$font = $config['font_path'];

		$header_h = (int) round( $h * (float) $config['team_header_band_ratio'] );

		$name_x = (int) round( $w * (float) $config['team_name_block_left_ratio'] );
		$y_nm   = $header_h + (int) round( $h * (float) $config['team_name_below_header_ratio'] );
		$nm     = trim( (string) $config['team_card_name'] );
		$jt     = trim( (string) $config['team_card_title'] );
		$ns     = max( 14, (int) round( $w * 0.036 ) );
		$ts     = max( 11, (int) round( $w * 0.022 ) );
		$max_nm = max( 120, (int) round( $w * (float) $config['team_card_name_max_w_ratio'] ) );

		if ( '' !== $nm ) {
			$y_nm = self::draw_wrapped_block_left( $canvas, $font, $ns, $white, $name_x, $y_nm, $nm, $max_nm );
			$y_nm += (int) round( $h * (float) $config['team_card_after_name_gap_ratio'] );
		}
		if ( '' !== $jt ) {
			$y_nm = self::draw_wrapped_block_left( $canvas, $font, $ts, $white, $name_x, $y_nm, $jt, $max_nm );
		}

		$lx        = (int) round( $w * (float) $config['team_body_left_x_ratio'] );
		$rx        = (int) round( $w * (float) $config['team_body_right_x_ratio'] );
		$y_l       = (int) round( $h * (float) $config['team_body_start_y_ratio'] );
		$y_r       = $y_l;

		foreach ( array( 1, 2 ) as $i ) {
			$txt  = trim( (string) $config[ 'team_body_' . $i ] );
			$tone = ( isset( $config[ 'team_body_' . $i . '_tone' ] ) && 'light' === $config[ 'team_body_' . $i . '_tone' ] ) ? 'light' : 'dark';
			$col  = 'light' === $tone ? $white : $dark_txt;
			if ( '' === $txt ) {
				continue;
			}
			$y_l      += self::team_body_block_before_gap_px( $h, $config, $i );
			$max_l     = self::team_body_block_max_w_px( $w, $config, $i );
			$block_sz  = self::team_body_block_scale_px( $w, $config, $i );
			$y_l       = self::draw_wrapped_block_left( $canvas, $font, $block_sz, $col, $lx, $y_l, $txt, $max_l );
			$y_l      += self::team_body_block_after_gap_px( $h, $config, $i );
		}

		foreach ( array( 3, 4, 5 ) as $i ) {
			$txt  = trim( (string) $config[ 'team_body_' . $i ] );
			$tone = ( isset( $config[ 'team_body_' . $i . '_tone' ] ) && 'light' === $config[ 'team_body_' . $i . '_tone' ] ) ? 'light' : 'dark';
			$col  = 'light' === $tone ? $white : $dark_txt;
			if ( '' === $txt ) {
				continue;
			}
			$y_r      += self::team_body_block_before_gap_px( $h, $config, $i );
			$max_r     = self::team_body_block_max_w_px( $w, $config, $i );
			$block_sz  = self::team_body_block_scale_px( $w, $config, $i );
			$y_r       = self::draw_wrapped_block_left( $canvas, $font, $block_sz, $col, $rx, $y_r, $txt, $max_r );
			$y_r      += self::team_body_block_after_gap_px( $h, $config, $i );
		}
	}

	/**
	 * @param string $text
	 * @param bool   $preserve_case
	 */
	private static function format_headline( $text, $preserve_case ) {
		$t = trim( (string) $text );
		if ( '' === $t ) {
			return '';
		}
		return $preserve_case ? $t : self::upper_text( $t );
	}

	private static function format_name_role( $text ) {
		return self::upper_text( (string) $text );
	}

	/**
	 * Φωτογραφία gradient: ζώνη από team_header_* + inset, μέγεθος από team_photo_fit_max_*,
	 * κάθετη θέση από team_photo_anchor_y_ratio (0–1 μέσα στο διαθέσιμο ύψος)·
	 * το κάτω όριο του slot μπορεί να κατέβει με team_photo_slot_extend_down_ratio (μετά το header).
	 *
	 * @param resource|\GdImage $canvas
	 * @param array             $config
	 * @return true|\WP_Error
	 */
	private static function overlay_photo_team( $canvas, array $config ) {
		$cw = imagesx( $canvas );
		$ch = imagesy( $canvas );

		$header_h = (int) round( $ch * (float) $config['team_header_band_ratio'] );
		$zone_w   = (int) round( $cw * (float) $config['team_header_photo_zone_w_ratio'] );
		$header_h = max( 80, min( $ch - 1, $header_h ) );
		$zone_w   = max( 60, min( $cw - 1, $zone_w ) );

		if ( empty( $config['photo_path'] ) || ! is_readable( $config['photo_path'] ) ) {
			return true;
		}

		$inset   = (int) max( 2, min( 40, (int) $config['team_photo_box_inset'] ) );
		$box_x0  = $inset;
		$box_y0  = $inset;
		$box_x1  = $zone_w - $inset;
		$extend_ratio = isset( $config['team_photo_slot_extend_down_ratio'] ) ? (float) $config['team_photo_slot_extend_down_ratio'] : 0.0;
		$extend_ratio = min( 0.35, max( 0.0, $extend_ratio ) );
		$extend_px    = (int) round( $ch * $extend_ratio );
		$box_y1       = min( $ch - 1 - $inset, $header_h - $inset + $extend_px );
		$inner_w = max( 24, $box_x1 - $box_x0 );
		$inner_h = max( 24, $box_y1 - $box_y0 );

		$mw    = (int) round( $cw * (float) $config['team_photo_fit_max_w_ratio'] );
		$mh    = (int) round( $ch * (float) $config['team_photo_fit_max_h_ratio'] );
		$max_w = max( 24, min( $inner_w, $mw ) );
		$max_h = max( 24, min( $inner_h, $mh ) );

		$photo = self::image_from_file( $config['photo_path'] );
		if ( is_wp_error( $photo ) ) {
			return $photo;
		}

		$resized = self::resize_to_fit( $photo, $max_w, $max_h );
		imagedestroy( $photo );

		if ( is_wp_error( $resized ) ) {
			return $resized;
		}

		$pw = imagesx( $resized );
		$ph = imagesy( $resized );

		$anchor = (float) $config['team_photo_anchor_y_ratio'];
		$anchor = min( 1.0, max( 0.0, $anchor ) );
		$slot_h = $box_y1 - $box_y0;
		$py     = (int) round( $box_y0 + ( $slot_h - $ph ) * $anchor );
		$px     = (int) round( ( $box_x0 + $box_x1 - $pw ) / 2 );

		$border = (int) max( 1, min( 8, (int) $config['team_photo_border_px'] ) );
		$pad    = (int) max( 2, min( 40, (int) $config['team_photo_bg_pad_px'] ) );

		$bg_x0 = max( 0, $px - $border - $pad );
		$bg_y0 = max( 0, $py - $border - $pad );
		$bg_x1 = min( $cw - 1, $px + $pw + $border - 1 + $pad );
		$bg_y1 = min( $ch - 1, $py + $ph + $border - 1 + $pad );

		$bg_color = self::allocate_color_hex( $canvas, (string) $config['team_photo_bg'] );
		if ( false !== $bg_color ) {
			imagealphablending( $canvas, true );
			imagefilledrectangle( $canvas, $bg_x0, $bg_y0, $bg_x1, $bg_y1, $bg_color );
		}

		imagealphablending( $canvas, true );
		imagecopy( $canvas, $resized, $px, $py, 0, 0, $pw, $ph );
		imagedestroy( $resized );

		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		if ( false !== $white ) {
			$x1 = $px - $border;
			$y1 = $py - $border;
			$x2 = $px + $pw + $border - 1;
			$y2 = $py + $ph + $border - 1;
			self::draw_thick_rect_outline( $canvas, $x1, $y1, $x2, $y2, $white, $border );
		}

		return true;
	}

	/**
	 * @param resource|\GdImage $canvas
	 * @param string            $photo_path
	 * @param float             $split_ratio
	 * @param float             $max_w_ratio
	 * @param float             $max_h_ratio
	 * @param float             $bottom_gap_ratio
	 * @return true|\WP_Error
	 */
	private static function overlay_photo( $canvas, $photo_path, $split_ratio, $max_w_ratio, $max_h_ratio, $bottom_gap_ratio ) {
		$photo = self::image_from_file( $photo_path );
		if ( is_wp_error( $photo ) ) {
			return $photo;
		}

		$cw = imagesx( $canvas );
		$ch = imagesy( $canvas );

		$left_w = (int) round( $cw * $split_ratio );
		$max_w  = (int) round( $left_w * $max_w_ratio );
		$max_h  = (int) round( $ch * $max_h_ratio );

		$resized = self::resize_to_fit( $photo, $max_w, $max_h );
		imagedestroy( $photo );

		if ( is_wp_error( $resized ) ) {
			return $resized;
		}

		$pw = imagesx( $resized );
		$ph = imagesy( $resized );

		$px = (int) round( ( $left_w - $pw ) / 2 );
		$py = (int) round( $ch - $ph - ( $ch * $bottom_gap_ratio ) );

		imagealphablending( $canvas, true );
		imagecopy( $canvas, $resized, $px, $py, 0, 0, $pw, $ph );
		imagedestroy( $resized );

		return true;
	}

	/**
	 * @param resource|\GdImage $src
	 * @param int               $max_w
	 * @param int               $max_h
	 * @return resource|\GdImage|\WP_Error
	 */
	private static function resize_to_fit( $src, $max_w, $max_h ) {
		$sw = imagesx( $src );
		$sh = imagesy( $src );
		if ( $sw < 1 || $sh < 1 ) {
			return new WP_Error( 'inventio_bad_photo', __( 'Μη έγκυρη εικόνα προσώπου.', 'inventio-wellcome-card' ) );
		}

		$ratio = min( $max_w / $sw, $max_h / $sh );
		$nw    = max( 1, (int) round( $sw * $ratio ) );
		$nh    = max( 1, (int) round( $sh * $ratio ) );

		$dst = imagecreatetruecolor( $nw, $nh );
		if ( false === $dst ) {
			return new WP_Error( 'inventio_resize', __( 'Αποτυχία αλλαγής μεγέθους φωτογραφίας.', 'inventio-wellcome-card' ) );
		}

		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
		imagefill( $dst, 0, 0, $transparent );
		imagealphablending( $dst, true );

		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh );

		return $dst;
	}

	/**
	 * @param string $path
	 * @return resource|\GdImage|\WP_Error
	 */
	private static function image_from_file( $path ) {
		$mime      = '';
		$data      = @getimagesize( $path );
		if ( false === $data ) {
			return new WP_Error( 'inventio_bad_image', __( 'Το αρχείο δεν είναι έγκυρη εικόνα.', 'inventio-wellcome-card' ) );
		}

		if ( ! empty( $data['mime'] ) ) {
			$mime = $data['mime'];
		}

		$size_info = $data;
		if ( ! empty( $size_info[2] ) && defined( 'IMAGETYPE_JPEG' ) ) {
			switch ( $size_info[2] ) {
				case IMAGETYPE_JPEG:
					$mime = 'image/jpeg';
					break;
				case IMAGETYPE_PNG:
					$mime = 'image/png';
					break;
				case IMAGETYPE_GIF:
					$mime = 'image/gif';
					break;
				case IMAGETYPE_WEBP:
					$mime = 'image/webp';
					break;
				default:
					$mime = '';
			}
		}

		switch ( $mime ) {
			case 'image/jpeg':
				$im = imagecreatefromjpeg( $path );
				break;
			case 'image/png':
				$im = imagecreatefrompng( $path );
				break;
			case 'image/gif':
				$im = imagecreatefromgif( $path );
				break;
			case 'image/webp':
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					$im = imagecreatefromwebp( $path );
				} else {
					return new WP_Error( 'inventio_webp', __( 'Το WebP δεν υποστηρίζεται από αυτή την εγκατάσταση GD.', 'inventio-wellcome-card' ) );
				}
				break;
			default:
				return new WP_Error( 'inventio_mime', __( 'Μη υποστηριζόμενος τύπος εικόνας.', 'inventio-wellcome-card' ) );
		}

		if ( false === $im ) {
			return new WP_Error( 'inventio_load', __( 'Αποτυχία φόρτωσης εικόνας.', 'inventio-wellcome-card' ) );
		}

		return $im;
	}

	/**
	 * @param resource|\GdImage $img
	 * @param string              $font
	 * @param int                 $size
	 * @param int                 $color
	 * @param int                 $center_x
	 * @param int                 $approx_top_y
	 * @param string              $text
	 */
	private static function draw_centered_line( $img, $font, $size, $color, $center_x, $approx_top_y, $text ) {
		if ( '' === $text ) {
			return;
		}

		$bbox = imagettfbbox( $size, 0, $font, $text );
		if ( false === $bbox ) {
			return;
		}

		$text_width = abs( $bbox[2] - $bbox[0] );
		$baseline   = $approx_top_y - $bbox[7];

		$x = (int) round( $center_x - ( $text_width / 2 ) );

		imagettftext( $img, $size, 0, $x, (int) round( $baseline ), $color, $font, $text );
	}

	/**
	 * Αριστερή στοίχιση (anchor πάνω-αριστερά της γραμμής).
	 *
	 * @param resource|\GdImage $img
	 * @param string              $font
	 * @param int                 $size
	 * @param int                 $color
	 * @param int                 $left_x
	 * @param int                 $approx_top_y
	 * @param string              $text
	 */
	private static function draw_left_line( $img, $font, $size, $color, $left_x, $approx_top_y, $text ) {
		if ( '' === $text ) {
			return;
		}
		$bbox = imagettfbbox( $size, 0, $font, $text );
		if ( false === $bbox ) {
			return;
		}
		$baseline = $approx_top_y - $bbox[7];
		imagettftext( $img, $size, 0, $left_x, (int) round( $baseline ), $color, $font, $text );
	}

	private static function line_height( $size, $font, $text ) {
		$bbox = imagettfbbox( $size, 0, $font, $text );
		if ( false === $bbox ) {
			return (int) round( $size * 1.2 );
		}
		return (int) round( abs( $bbox[1] - $bbox[7] ) );
	}

	private static function ratio_to_px( $base_px, $ratio, $min_px ) {
		$px = (int) round( (int) $base_px * (float) $ratio );
		return max( (int) $min_px, $px );
	}

	private static function upper_text( $text ) {
		if ( function_exists( 'mb_convert_case' ) ) {
			return mb_convert_case( trim( $text ), MB_CASE_UPPER, 'UTF-8' );
		}
		return strtoupper( trim( $text ) );
	}

	/**
	 * Περίγραμμα ορθογωνίου (λευκό πλαίσιο γύρω από τη φωτογραφία).
	 *
	 * @param resource|\GdImage $canvas
	 * @param int               $x1
	 * @param int               $y1
	 * @param int               $x2
	 * @param int               $y2
	 * @param int               $color
	 * @param int               $thickness
	 */
	private static function draw_thick_rect_outline( $canvas, $x1, $y1, $x2, $y2, $color, $thickness ) {
		$thickness = max( 1, $thickness );
		if ( function_exists( 'imagesetthickness' ) ) {
			imagesetthickness( $canvas, $thickness );
			imagerectangle( $canvas, $x1, $y1, $x2, $y2, $color );
			imagesetthickness( $canvas, 1 );
			return;
		}
		for ( $i = 0; $i < $thickness; $i++ ) {
			imagerectangle( $canvas, $x1 - $i, $y1 - $i, $x2 + $i, $y2 + $i, $color );
		}
	}

	/**
	 * @param resource|\GdImage $img
	 * @param string              $hex
	 * @return int|false
	 */
	private static function allocate_color_hex( $img, $hex ) {
		$rgb = self::parse_hex_rgb( $hex );
		if ( null === $rgb ) {
			$rgb = array( 'r' => 191, 'g' => 232, 'b' => 232 );
		}
		return imagecolorallocate( $img, $rgb['r'], $rgb['g'], $rgb['b'] );
	}

	/**
	 * @param string $hex
	 * @return array{r: int, g: int, b: int}|null
	 */
	private static function parse_hex_rgb( $hex ) {
		$hex = is_string( $hex ) ? trim( $hex ) : '';
		if ( '' === $hex ) {
			return null;
		}
		if ( '#' === $hex[0] ) {
			$hex = substr( $hex, 1 );
		}
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return null;
		}
		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * @param resource|\GdImage $img
	 * @param string              $font
	 * @param int                 $size
	 * @param int                 $color
	 * @param int                 $left_x
	 * @param int                 $approx_top_y
	 * @param string              $text
	 */
	private static function draw_left_line_preserve( $img, $font, $size, $color, $left_x, $approx_top_y, $text ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return;
		}
		$bbox = imagettfbbox( $size, 0, $font, $text );
		if ( false === $bbox ) {
			return;
		}
		$baseline = $approx_top_y - $bbox[7];
		imagettftext( $img, $size, 0, $left_x, (int) round( $baseline ), $color, $font, $text );
	}

	/**
	 * @param string $font
	 * @param int    $size
	 * @param string $text
	 * @return int|null
	 */
	private static function text_width_px( $font, $size, $text ) {
		$box = imagettfbbox( $size, 0, $font, (string) $text );
		if ( false === $box ) {
			return null;
		}
		return abs( $box[2] - $box[0] );
	}

	/**
	 * @param string $text
	 * @return string[]
	 */
	private static function split_text_chars( $text ) {
		$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( is_array( $chars ) && array() !== $chars ) {
			return $chars;
		}
		return str_split( (string) $text );
	}

	/**
	 * Breaks oversized words so width limits still apply to pasted/test text with no spaces.
	 *
	 * @param string $font
	 * @param int    $size
	 * @param string $word
	 * @param int    $max_width_px
	 * @return string[]
	 */
	private static function split_word_for_width( $font, $size, $word, $max_width_px ) {
		$chars = self::split_text_chars( $word );
		if ( count( $chars ) <= 1 ) {
			return array( (string) $word );
		}

		$chunks = array();
		$chunk  = '';
		foreach ( $chars as $char ) {
			$try = $chunk . $char;
			$tw  = self::text_width_px( $font, $size, $try );
			if ( null === $tw || $tw <= $max_width_px || '' === $chunk ) {
				$chunk = $try;
				continue;
			}

			$chunks[] = $chunk;
			$chunk    = $char;
		}

		if ( '' !== $chunk ) {
			$chunks[] = $chunk;
		}

		return array() === $chunks ? array( (string) $word ) : $chunks;
	}

	/**
	 * @param string $font
	 * @param int    $size
	 * @param string $text
	 * @param int    $max_width_px
	 * @return string[]
	 */
	private static function wrap_lines_for_width( $font, $size, $text, $max_width_px ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return array();
		}
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || array() === $words ) {
			return array();
		}
		$lines = array();
		$line  = '';
		foreach ( $words as $word ) {
			$word_parts = array( $word );
			$word_width = self::text_width_px( $font, $size, $word );
			if ( null !== $word_width && $word_width > $max_width_px ) {
				$word_parts = self::split_word_for_width( $font, $size, $word, $max_width_px );
			}

			foreach ( $word_parts as $word_part ) {
				$try = '' === $line ? $word_part : $line . ' ' . $word_part;
				$tw  = self::text_width_px( $font, $size, $try );
				if ( null === $tw ) {
					$line = $try;
					continue;
				}
				if ( $tw > $max_width_px && '' === $line ) {
					$lines[] = $word_part;
					$line    = '';
					continue;
				}
				if ( $tw <= $max_width_px || '' === $line ) {
					$line = $try;
				} else {
					$lines[] = $line;
					$line    = $word_part;
				}
			}
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * Πολλαπλές γραμμές αριστερής στοίχισης· επιστρέφει νέο Y μετά το block.
	 *
	 * @param resource|\GdImage $canvas
	 * @param string              $font
	 * @param int                 $size
	 * @param int                 $color
	 * @param int                 $left_x
	 * @param int                 $start_y
	 * @param string              $text
	 * @param int                 $max_width_px
	 * @return int
	 */
	private static function draw_wrapped_block_left( $canvas, $font, $size, $color, $left_x, $start_y, $text, $max_width_px ) {
		$lines = self::wrap_lines_for_width( $font, $size, $text, $max_width_px );
		$y     = $start_y;
		// Παλιά σταθερά +4px leading ήταν πολύ λίγο → γραμμές «κολλημένες» σε μεγάλο καμβά.
		$extra_leading = max( 8, (int) round( $size * 0.38 ) );
		foreach ( $lines as $ln ) {
			self::draw_left_line_preserve( $canvas, $font, $size, $color, $left_x, $y, $ln );
			$h = self::line_height( $size, $font, $ln );
			$h = max( $h, (int) round( $size * 1.12 ) );
			$y += $h + $extra_leading;
		}
		return $y;
	}

	private static function draw_wrapped_block_center( $canvas, $font, $size, $color, $center_x, $start_y, $text, $max_width_px ) {
		$lines = self::wrap_lines_for_width( $font, $size, $text, $max_width_px );
		$y     = $start_y;
		$extra_leading = max( 8, (int) round( $size * 0.38 ) );
		foreach ( $lines as $ln ) {
			self::draw_centered_line( $canvas, $font, $size, $color, $center_x, $y, $ln );
			$line_h = self::line_height( $size, $font, $ln );
			$line_h = max( $line_h, (int) round( $size * 1.12 ) );
			$y     += $line_h + $extra_leading;
		}
		return $y;
	}
}
