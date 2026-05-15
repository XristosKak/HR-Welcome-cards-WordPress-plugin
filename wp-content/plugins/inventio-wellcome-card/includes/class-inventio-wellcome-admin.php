<?php
/**
 * Διαχείριση wp-admin: ρυθμίσεις καμβά και φόρμα παραγωγής JPG.
 *
 * @package Inventio_Wellcome_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Inventio_Wellcome_Admin
 */
class Inventio_Wellcome_Admin {

	/** Όνομα πεδίου POST για το attachment ID καμβά (ίδιο με την παλιά επιλογή για συμβατότητα). */
	const FIELD_TEMPLATE_ATTACHMENT = 'inventio_wellcome_template_id';

	const FIELD_PRESET_KEY = 'inventio_wellcome_preset_key';

	/** POST: attachment ID φωτογραφίας προσώπου από τη βιβλιοθήκη μέσων (εναλλακτικό του file upload). */
	const FIELD_EMPLOYEE_PHOTO_ATTACHMENT = 'inventio_wellcome_employee_photo_id';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_inventio_wellcome_save_template', array( $this, 'handle_save_template' ) );
		add_action( 'admin_post_inventio_wellcome_generate', array( $this, 'handle_generate' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media' ) );
	}

	/**
	 * @return string
	 */
	public static function required_capability() {
		return apply_filters( 'inventio_wellcome_required_cap', 'upload_files' );
	}

	public function register_menu() {
		// Θέση 3: αμέσως μετά το Dashboard (2), πριν το διαχωριστικό και τα Posts (5).
		add_menu_page(
			__( 'Inventio Wellcome Card', 'inventio-wellcome-card' ),
			__( 'Wellcome Card', 'inventio-wellcome-card' ),
			self::required_capability(),
			'inventio-wellcome-card',
			array( $this, 'render_page' ),
			'dashicons-format-image',
			3
		);
	}

	public function register_settings() {
		register_setting(
			'inventio_wellcome_settings',
			Inventio_Wellcome_Presets::OPTION_DATA,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_presets_data_option' ),
				'default'           => array(),
			)
		);
		register_setting(
			'inventio_wellcome_settings',
			Inventio_Wellcome_Presets::OPTION_ACTIVE,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => '',
			)
		);
	}

	/**
	 * @param mixed $value
	 * @return array<string, array{template_id: int}>
	 */
	public function sanitize_presets_data_option( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$defs = Inventio_Wellcome_Presets::definitions();
		$out  = array();
		foreach ( $value as $k => $row ) {
			$k = sanitize_key( (string) $k );
			if ( '' === $k || ! isset( $defs[ $k ] ) ) {
				continue;
			}
			$row = is_array( $row ) ? $row : array();
			$out[ $k ] = array(
				'template_id' => isset( $row['template_id'] ) ? absint( $row['template_id'] ) : 0,
			);
		}
		return $out;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function validated_preset_key( $key ) {
		$key  = sanitize_key( (string) $key );
		$defs = Inventio_Wellcome_Presets::definitions();
		if ( '' !== $key && isset( $defs[ $key ] ) ) {
			return $key;
		}
		$first = array_key_first( $defs );
		return $first ? $first : 'default';
	}

	/**
	 * Προεπιλογές πεδίων gradient στο admin (αν το ενεργό preset δεν είναι team, χρησιμοποιείται το balanced).
	 *
	 * @return array<string, mixed>
	 */
	private function team_admin_defaults_source() {
		$defs   = Inventio_Wellcome_Presets::definitions();
		$active = Inventio_Wellcome_Presets::get_ui_preset_key();
		$cur    = isset( $defs[ $active ] ) ? $defs[ $active ] : null;
		if ( is_array( $cur ) && isset( $cur['layout'] ) && 'team_portrait' === $cur['layout'] ) {
			return $cur;
		}
		return isset( $defs['balanced'] ) && is_array( $defs['balanced'] ) ? $defs['balanced'] : array();
	}

	/**
	 * @param array<string, mixed> $src
	 */
	private function team_val( array $src, $key, $fallback ) {
		return ( isset( $src[ $key ] ) && is_scalar( $src[ $key ] ) ) ? (string) $src[ $key ] : (string) $fallback;
	}

	/**
	 * Δεδομένα για το admin JS (επιλογή preset / προεπισκόπηση καμβά).
	 *
	 * @return array{active: string, list: array<string, array<string, mixed>>}
	 */
	private function get_presets_localize_payload() {
		$defs   = Inventio_Wellcome_Presets::definitions();
		$active = Inventio_Wellcome_Presets::get_ui_preset_key();
		$list   = array();

		foreach ( $defs as $key => $def ) {
			$tid = Inventio_Wellcome_Presets::get_template_id_for_preset( $key );
			$url = $tid ? (string) wp_get_attachment_url( $tid ) : '';

			$row = array(
				'label'                => $def['label'],
				'templateId'           => $tid,
				'previewUrl'           => $url,
				'splitRatio'           => isset( $def['split_ratio'] ) ? (float) $def['split_ratio'] : 0.42,
				'headline'             => isset( $def['headline'] ) ? (string) $def['headline'] : '',
				'layout'               => isset( $def['layout'] ) ? sanitize_key( (string) $def['layout'] ) : 'split_aboard',
				'headlinePreserveCase' => ! empty( $def['headline_preserve_case'] ),
			);

			if ( isset( $def['layout'] ) && 'team_portrait' === $def['layout'] ) {
				$row['teamPhotoBg']    = isset( $def['team_photo_bg'] ) ? (string) $def['team_photo_bg'] : '#bfe8e8';
				$row['teamCardName']  = isset( $def['team_card_name'] ) ? (string) $def['team_card_name'] : '';
				$row['teamCardTitle'] = isset( $def['team_card_title'] ) ? (string) $def['team_card_title'] : '';
				for ( $i = 1; $i <= 5; $i++ ) {
					$bk                    = 'team_body_' . $i;
					$row[ 'teamBody' . $i ] = isset( $def[ $bk ] )
						? Inventio_Wellcome_Renderer::limit_team_body_text( (string) $def[ $bk ] )
						: '';
					$tk                    = 'team_body_' . $i . '_tone';
					$tv                    = isset( $def[ $tk ] ) ? sanitize_key( (string) $def[ $tk ] ) : '';
					if ( $i <= 2 ) {
						$row[ 'teamBody' . $i . 'Tone' ] = 'light' === $tv ? 'light' : 'dark';
					} else {
						$row[ 'teamBody' . $i . 'Tone' ] = 'dark' === $tv ? 'dark' : 'light';
					}
				}
			}

			$list[ $key ] = $row;
		}

		return array(
			'active' => $active,
			'list'   => $list,
		);
	}

	public function enqueue_media( $hook ) {
		// Σε ορισμένα περιβάλλοντα το $hook μπορεί να διαφέρει ελαφρά — ελέγχουμε το slug της σελίδας.
		if ( false === strpos( (string) $hook, 'inventio-wellcome-card' ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'inventio-wellcome-admin',
			plugins_url( 'assets/admin-wellcome.css', INVENTIO_WELCOME_PLUGIN_FILE ),
			array(),
			INVENTIO_WELCOME_VERSION
		);

		wp_enqueue_script(
			'inventio-wellcome-admin',
			plugins_url( 'assets/admin-wellcome.js', INVENTIO_WELCOME_PLUGIN_FILE ),
			array( 'jquery', 'media-models', 'media-views', 'media-editor' ),
			INVENTIO_WELCOME_VERSION,
			true
		);

		wp_localize_script(
			'inventio-wellcome-admin',
			'inventioWellcomeAdmin',
			array(
				'pickTitle'          => __( 'Επιλογή καμβά', 'inventio-wellcome-card' ),
				'pickEmployeeTitle'  => __( 'Επιλογή φωτογραφίας προσώπου', 'inventio-wellcome-card' ),
				'presets'            => $this->get_presets_localize_payload(),
				'teamBodyMaxChars'   => Inventio_Wellcome_Renderer::TEAM_BODY_MAX_CHARS,
				'teamBodyAtLimit'    => sprintf(
					/* translators: %d: maximum number of characters */
					__( 'Έχετε φτάσει το μέγιστο όριο των %d χαρακτήρων.', 'inventio-wellcome-card' ),
					(int) Inventio_Wellcome_Renderer::TEAM_BODY_MAX_CHARS
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( self::required_capability() ) ) {
			wp_die( esc_html__( 'Δεν έχετε δικαίωμα πρόσβασης.', 'inventio-wellcome-card' ) );
		}

		$defs        = Inventio_Wellcome_Presets::definitions();
		$active_key  = Inventio_Wellcome_Presets::get_ui_preset_key();
		$def_active  = Inventio_Wellcome_Presets::get_definition_for( $active_key );
		$template_id = Inventio_Wellcome_Presets::get_template_id_for_preset( $active_key );
		$template_url = $template_id ? wp_get_attachment_url( $template_id ) : '';
		$split_default    = $def_active && isset( $def_active['split_ratio'] ) ? (float) $def_active['split_ratio'] : 0.42;
		$headline_default = $def_active && isset( $def_active['headline'] ) ? (string) $def_active['headline'] : 'WELLCOME ABOARD';
		$team_src          = $this->team_admin_defaults_source();

		$flash = get_transient( 'inventio_wellcome_flash_error' );
		if ( is_string( $flash ) && '' !== $flash ) {
			delete_transient( 'inventio_wellcome_flash_error' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $flash ) . '</p></div>';
		}

		$ok = isset( $_GET['inventio_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['inventio_saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '1' === $ok ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ο καμβάς αποθηκεύτηκε για το επιλεγμένο template.', 'inventio-wellcome-card' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Inventio — Κάρτα καλωσορίσματος', 'inventio-wellcome-card' ); ?></h1>
			<p><?php echo esc_html__( 'Δύο σχέδια (1080×1350 px): για κάθε template ανεβάζετε τον δικό σας καμβά από Canva/Photoshop — το σύστημα κλιμακώνει σε 1080×1350 αν χρειάζεται και τοποθετεί κείμενο και φωτογραφία πάνω του.', 'inventio-wellcome-card' ); ?></p>

			<h2><?php echo esc_html__( 'Προκαθορισμένο template', 'inventio-wellcome-card' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Η αλλαγή εδώ ενημερώνει την προεπισκόπηση καμβά και τα προτεινόμενα πεδία· ο καμβάς αποθηκεύεται ανά template με το κουμπί «Αποθήκευση καμβά».', 'inventio-wellcome-card' ); ?></p>
			<p>
				<label for="inventio-active-preset" class="screen-reader-text"><?php echo esc_html__( 'Template', 'inventio-wellcome-card' ); ?></label>
				<select id="inventio-active-preset" class="regular-text">
					<?php foreach ( $defs as $slug => $row ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, $active_key ); ?>><?php echo esc_html( $row['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<h2><?php echo esc_html__( 'Καμβάς για το επιλεγμένο template', 'inventio-wellcome-card' ); ?></h2>
			<form id="inventio-form-save-canvas" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="inventio_wellcome_save_template" />
				<?php wp_nonce_field( 'inventio_wellcome_save_template', 'inventio_wellcome_template_nonce' ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::FIELD_PRESET_KEY ); ?>" class="inventio-preset-key-field" value="<?php echo esc_attr( $active_key ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="inventio-template-id"><?php echo esc_html__( 'Αρχείο καμβά στη βιβλιοθήκη μέσων', 'inventio-wellcome-card' ); ?></label></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::FIELD_TEMPLATE_ATTACHMENT ); ?>" id="inventio-template-id" value="<?php echo esc_attr( (string) $template_id ); ?>" />
							<button type="button" class="button" id="inventio-pick-template"><?php echo esc_html__( 'Επιλογή εικόνας…', 'inventio-wellcome-card' ); ?></button>
							<button type="button" class="button" id="inventio-clear-template"><?php echo esc_html__( 'Καθαρισμός', 'inventio-wellcome-card' ); ?></button>
							<p id="inventio-template-preview-wrap" style="margin-top:8px;">
								<img id="inventio-template-preview" src="<?php echo $template_url ? esc_url( $template_url ) : ''; ?>" alt="" style="max-width:280px;height:auto;border:1px solid #ccd0d4;<?php echo $template_url ? '' : 'display:none;'; ?>" />
								<span id="inventio-template-preview-empty" class="description" style="<?php echo $template_url ? 'display:none;' : ''; ?>"><?php echo esc_html__( 'Δεν έχει οριστεί καμβάς για αυτό το template.', 'inventio-wellcome-card' ); ?></span>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Αποθήκευση καμβά', 'inventio-wellcome-card' ) ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Παραγωγή JPG', 'inventio-wellcome-card' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="inventio_wellcome_generate" />
				<?php wp_nonce_field( 'inventio_wellcome_generate', 'inventio_wellcome_nonce' ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::FIELD_PRESET_KEY ); ?>" class="inventio-preset-key-field" value="<?php echo esc_attr( $active_key ); ?>" />

				<table class="form-table" role="presentation">
					<tr class="inventio-row-split-ratio">
						<th scope="row"><label for="split_ratio"><?php echo esc_html__( 'Σχίσιμο αριστερά/δεξιά (0–1)', 'inventio-wellcome-card' ); ?></label></th>
						<td>
							<input name="split_ratio" id="split_ratio" type="number" step="0.01" min="0.2" max="0.8" value="<?php echo esc_attr( (string) $split_default ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Ισχύει για το template «WELLCOME ABOARD»: πού χωρίζει αριστερά (φωτό) / δεξιά (κείμενο).', 'inventio-wellcome-card' ); ?></p>
						</td>
					</tr>
					<tr class="inventio-row-headline">
						<th scope="row"><label for="headline"><?php echo esc_html__( 'Επικεφαλίδα (1η γραμμή)', 'inventio-wellcome-card' ); ?></label></th>
						<td>
							<input name="headline" id="headline" type="text" class="regular-text" value="<?php echo esc_attr( $headline_default ); ?>" />
							<p class="description"><?php echo esc_html__( 'Ισχύει μόνο για το template «WELLCOME ABOARD» (σχίσιμο). Στο gradient design δεν εμφανίζεται επικεφαλίδα.', 'inventio-wellcome-card' ); ?></p>
						</td>
					</tr>
					<tr class="inventio-only-split">
						<th scope="row"><label for="name_line_1"><?php echo esc_html__( 'Όνομα — γραμμή 1', 'inventio-wellcome-card' ); ?></label></th>
						<td><input name="name_line_1" id="name_line_1" type="text" class="regular-text" value="" /></td>
					</tr>
					<tr class="inventio-only-split">
						<th scope="row"><label for="name_line_2"><?php echo esc_html__( 'Όνομα — γραμμή 2', 'inventio-wellcome-card' ); ?></label></th>
						<td><input name="name_line_2" id="name_line_2" type="text" class="regular-text" value="" /></td>
					</tr>
					<tr class="inventio-only-split">
						<th scope="row"><label for="role_line_1"><?php echo esc_html__( 'Ρόλος — γραμμή 1', 'inventio-wellcome-card' ); ?></label></th>
						<td><input name="role_line_1" id="role_line_1" type="text" class="regular-text" value="" /></td>
					</tr>
					<tr class="inventio-only-split">
						<th scope="row"><label for="role_line_2"><?php echo esc_html__( 'Ρόλος — γραμμή 2', 'inventio-wellcome-card' ); ?></label></th>
						<td><input name="role_line_2" id="role_line_2" type="text" class="regular-text" value="" /></td>
					</tr>
					<?php
					$team_bg = sanitize_hex_color( $this->team_val( $team_src, 'team_photo_bg', '#bfe8e8' ) );
					if ( ! is_string( $team_bg ) || '' === $team_bg ) {
						$team_bg = '#bfe8e8';
					}
					?>
					<tr class="inventio-only-team">
						<th scope="row"><label for="team_photo_bg"><?php echo esc_html__( 'Χρώμα φόντου πίσω από τη φωτογραφία', 'inventio-wellcome-card' ); ?></label></th>
						<td>
							<input name="team_photo_bg" id="team_photo_bg" type="color" value="<?php echo esc_attr( $team_bg ); ?>" />
							<p class="description"><?php echo esc_html__( 'Μόνο το ορθογώνιο πίσω από τη φωτογραφία (και το λευκό πλαίσιο)· ο υπόλοιπος καμβάς Canva μένει ως έχει.', 'inventio-wellcome-card' ); ?></p>
						</td>
					</tr>
					<tr class="inventio-only-team">
						<th scope="row"><label for="team_card_name"><?php echo esc_html__( 'Όνομα στην κάρτα', 'inventio-wellcome-card' ); ?></label></th>
						<td><input name="team_card_name" id="team_card_name" type="text" class="regular-text" value="<?php echo esc_attr( $this->team_val( $team_src, 'team_card_name', '' ) ); ?>" /></td>
					</tr>
					<tr class="inventio-only-team">
						<th scope="row"><label for="team_card_title"><?php echo esc_html__( 'Τίτλος / ρόλος (ξεχωριστό πεδίο)', 'inventio-wellcome-card' ); ?></label></th>
						<td><input name="team_card_title" id="team_card_title" type="text" class="regular-text" value="<?php echo esc_attr( $this->team_val( $team_src, 'team_card_title', '' ) ); ?>" /></td>
					</tr>
					<?php
					for ( $bi = 1; $bi <= 5; $bi++ ) :
						$btxt = Inventio_Wellcome_Renderer::limit_team_body_text(
							$this->team_val( $team_src, 'team_body_' . $bi, '' )
						);
						$bt   = sanitize_key( $this->team_val( $team_src, 'team_body_' . $bi . '_tone', $bi <= 2 ? 'dark' : 'light' ) );
						if ( $bi <= 2 ) {
							$bt = ( 'light' === $bt ) ? 'light' : 'dark';
						} else {
							$bt = ( 'dark' === $bt ) ? 'dark' : 'light';
						}
						?>
					<tr class="inventio-only-team">
						<th scope="row">
							<label for="team_body_<?php echo (int) $bi; ?>"><?php
								echo esc_html(
									sprintf(
										/* translators: %d: text block index 1–5 */
										__( 'Κείμενο %d (μικρότερη γραμματοσειρά)', 'inventio-wellcome-card' ),
										$bi
									)
								);
							?></label>
						</th>
						<td>
							<p>
								<label class="screen-reader-text" for="team_body_<?php echo (int) $bi; ?>_tone"><?php echo esc_html__( 'Χρώμα κειμένου', 'inventio-wellcome-card' ); ?></label>
								<select name="team_body_<?php echo (int) $bi; ?>_tone" id="team_body_<?php echo (int) $bi; ?>_tone">
									<option value="dark"<?php selected( 'dark', $bt ); ?>><?php echo esc_html__( 'Σκούρο (σαν μαύρο)', 'inventio-wellcome-card' ); ?></option>
									<option value="light"<?php selected( 'light', $bt ); ?>><?php echo esc_html__( 'Ανοιχτό (λευκό)', 'inventio-wellcome-card' ); ?></option>
								</select>
							</p>
							<div class="inventio-team-body-field-wrap" id="inventio-team-body-wrap-<?php echo (int) $bi; ?>">
								<textarea name="team_body_<?php echo (int) $bi; ?>" id="team_body_<?php echo (int) $bi; ?>" rows="3" class="large-text inventio-team-body-textarea" maxlength="<?php echo (int) Inventio_Wellcome_Renderer::TEAM_BODY_MAX_CHARS; ?>"><?php echo esc_textarea( $btxt ); ?></textarea>
							</div>
							<p class="description inventio-team-body-counter-line" id="inventio-team-body-line-<?php echo (int) $bi; ?>" aria-live="polite">
								<span id="inventio-team-body-count-<?php echo (int) $bi; ?>" class="inventio-team-body-count"></span>
								<span id="inventio-team-body-limit-msg-<?php echo (int) $bi; ?>" class="inventio-team-body-limit-msg" hidden></span>
							</p>
							<p class="description"><?php
								echo esc_html(
									sprintf(
										/* translators: %d: max characters per body field */
										__( 'Μέχρι %d χαρακτήρες (συμπεριλαμβανομένων κενών και στίξης).', 'inventio-wellcome-card' ),
										(int) Inventio_Wellcome_Renderer::TEAM_BODY_MAX_CHARS
									)
								);
							?></p>
						</td>
					</tr>
					<?php endfor; ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Φωτογραφία προσώπου', 'inventio-wellcome-card' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::FIELD_EMPLOYEE_PHOTO_ATTACHMENT ); ?>" id="inventio-employee-photo-id" value="0" />
							<p class="description" style="margin-top:0;">
								<?php echo esc_html__( 'Επιλέξτε εικόνα από τη βιβλιοθήκη μέσων ή ανεβάστε αρχείο από τον υπολογιστή (JPG, PNG, WebP). Αν ανεβάσετε αρχείο, αυτό έχει προτεραιότητα.', 'inventio-wellcome-card' ); ?>
							</p>
							<p>
								<button type="button" class="button" id="inventio-pick-employee-photo"><?php echo esc_html__( 'Επιλογή από βιβλιοθήκη μέσων…', 'inventio-wellcome-card' ); ?></button>
								<button type="button" class="button" id="inventio-clear-employee-photo"><?php echo esc_html__( 'Καθαρισμός επιλογής βιβλιοθήκης', 'inventio-wellcome-card' ); ?></button>
							</p>
							<p id="inventio-employee-library-preview-wrap" style="margin-top:8px;display:none;">
								<img id="inventio-employee-library-preview" src="" alt="" style="max-width:140px;height:auto;border:1px solid #ccd0d4;vertical-align:middle;margin-right:8px;" />
								<span class="description"><?php echo esc_html__( 'Θα χρησιμοποιηθεί αυτή η εικόνα από τη βιβλιοθήκη (εκτός αν επιλέξετε νέο αρχείο παρακάτω).', 'inventio-wellcome-card' ); ?></span>
							</p>
							<p style="margin-bottom:4px;"><strong><?php echo esc_html__( 'Ανέβασμα από τον υπολογιστή', 'inventio-wellcome-card' ); ?></strong></p>
							<p>
								<label for="employee_photo" class="screen-reader-text"><?php echo esc_html__( 'Αρχείο φωτογραφίας', 'inventio-wellcome-card' ); ?></label>
								<input name="employee_photo" id="employee_photo" type="file" accept="image/jpeg,image/png,image/webp" />
							</p>
							<p class="description"><?php echo esc_html__( 'Προαιρετικό: PNG με διαφάνεια για «κοπή» πάνω στον καμβά.', 'inventio-wellcome-card' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Λήψη JPG', 'inventio-wellcome-card' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_template() {
		if ( ! current_user_can( self::required_capability() ) ) {
			wp_die( esc_html__( 'Δεν έχετε δικαίωμα.', 'inventio-wellcome-card' ) );
		}

		check_admin_referer( 'inventio_wellcome_save_template', 'inventio_wellcome_template_nonce' );

		$preset_key = isset( $_POST[ self::FIELD_PRESET_KEY ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD_PRESET_KEY ] ) ) : '';
		$preset_key = $this->validated_preset_key( $preset_key );

		$id = isset( $_POST[ self::FIELD_TEMPLATE_ATTACHMENT ] ) ? absint( wp_unslash( $_POST[ self::FIELD_TEMPLATE_ATTACHMENT ] ) ) : 0;

		$data = get_option( Inventio_Wellcome_Presets::OPTION_DATA, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data[ $preset_key ] = array( 'template_id' => $id );
		update_option( Inventio_Wellcome_Presets::OPTION_DATA, $this->sanitize_presets_data_option( $data ) );

		Inventio_Wellcome_Presets::set_active_preset_key( $preset_key );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'inventio-wellcome-card',
					'inventio_saved'  => '1',
					'inventio_preset' => $preset_key,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_generate() {
		if ( ! current_user_can( self::required_capability() ) ) {
			wp_die( esc_html__( 'Δεν έχετε δικαίωμα.', 'inventio-wellcome-card' ) );
		}

		check_admin_referer( 'inventio_wellcome_generate', 'inventio_wellcome_nonce' );

		$preset_key = isset( $_POST[ self::FIELD_PRESET_KEY ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD_PRESET_KEY ] ) ) : '';
		$preset_key = $this->validated_preset_key( $preset_key );
		Inventio_Wellcome_Presets::set_active_preset_key( $preset_key );

		$template_id = Inventio_Wellcome_Presets::get_template_id_for_preset( $preset_key );
		if ( $template_id < 1 ) {
			$this->redirect_with_error( __( 'Αποθηκεύστε πρώτα τον καμβά για αυτό το template από τη βιβλιοθήκη μέσων.', 'inventio-wellcome-card' ) );
		}

		$template_path = get_attached_file( $template_id );
		if ( ! $template_path || ! file_exists( $template_path ) ) {
			$this->redirect_with_error( __( 'Το αρχείο καμβά δεν βρέθηκε στο δίσκο.', 'inventio-wellcome-card' ) );
		}

		$photo_path       = '';
		$unlink_photo_tmp = false;

		if ( ! empty( $_FILES['employee_photo'] ) && isset( $_FILES['employee_photo']['tmp_name'] ) && is_uploaded_file( $_FILES['employee_photo']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['employee_photo']['error'] ) {
			$file = $_FILES['employee_photo'];

			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! preg_match( '#^image/(jpeg|png|webp)$#', $check['type'] ) ) {
				$this->redirect_with_error( __( 'Επιτρέπονται μόνο JPG, PNG ή WebP για τη φωτογραφία.', 'inventio-wellcome-card' ) );
			}

			$upload = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => array(
						'jpg|jpeg|jpe' => 'image/jpeg',
						'png'          => 'image/png',
						'webp'         => 'image/webp',
					),
				)
			);

			if ( isset( $upload['error'] ) ) {
				$this->redirect_with_error( $upload['error'] );
			}

			$photo_path       = $upload['file'];
			$unlink_photo_tmp = true;
		} else {
			$lib_id = isset( $_POST[ self::FIELD_EMPLOYEE_PHOTO_ATTACHMENT ] ) ? absint( wp_unslash( $_POST[ self::FIELD_EMPLOYEE_PHOTO_ATTACHMENT ] ) ) : 0;
			if ( $lib_id > 0 ) {
				$att = get_post( $lib_id );
				if ( ! $att instanceof WP_Post || 'attachment' !== $att->post_type ) {
					$this->redirect_with_error( __( 'Η επιλεγμένη εικόνα από τη βιβλιοθήκη δεν είναι έγκυρο συνημμένο.', 'inventio-wellcome-card' ) );
				}
				if ( ! current_user_can( 'edit_post', $lib_id ) ) {
					$this->redirect_with_error( __( 'Δεν έχετε δικαίωμα να χρησιμοποιήσετε αυτή την εικόνα.', 'inventio-wellcome-card' ) );
				}
				$mime = (string) $att->post_mime_type;
				if ( ! preg_match( '#^image/(jpeg|png|webp)$#', $mime ) ) {
					$this->redirect_with_error( __( 'Από τη βιβλιοθήκη επιτρέπονται μόνο JPG, PNG ή WebP.', 'inventio-wellcome-card' ) );
				}
				$lib_path = get_attached_file( $lib_id );
				if ( ! $lib_path || ! is_readable( $lib_path ) ) {
					$this->redirect_with_error( __( 'Το αρχείο της εικόνας από τη βιβλιοθήκη δεν βρέθηκε.', 'inventio-wellcome-card' ) );
				}
				$verify = wp_check_filetype_and_ext( $lib_path, basename( $lib_path ) );
				if ( empty( $verify['type'] ) || ! preg_match( '#^image/(jpeg|png|webp)$#', $verify['type'] ) ) {
					$this->redirect_with_error( __( 'Η εικόνα από τη βιβλιοθήκη δεν πέρασε τον έλεγχο τύπου αρχείου.', 'inventio-wellcome-card' ) );
				}
				$photo_path       = $lib_path;
				$unlink_photo_tmp = false;
			}
		}

		$pdef = Inventio_Wellcome_Presets::get_definition_for( $preset_key );
		$pdef = is_array( $pdef ) ? $pdef : array();

		$split = isset( $_POST['split_ratio'] ) ? (float) wp_unslash( $_POST['split_ratio'] ) : 0.42;
		$split = min( 0.8, max( 0.2, $split ) );

		$layout = isset( $pdef['layout'] ) ? sanitize_key( (string) $pdef['layout'] ) : 'split_aboard';
		if ( '' === $layout ) {
			$layout = 'split_aboard';
		}

		$headline     = '';
		$headline_sub = '';
		if ( 'team_portrait' !== $layout ) {
			$headline = isset( $_POST['headline'] ) ? sanitize_text_field( wp_unslash( $_POST['headline'] ) ) : '';
		}

		$args = array(
			'template_path'          => $template_path,
			'photo_path'             => $photo_path,
			'split_ratio'            => $split,
			'layout'                 => $layout,
			'canvas_title'           => $headline,
			'headline'               => $headline,
			'headline_sub'           => $headline_sub,
			'headline_preserve_case' => ! empty( $pdef['headline_preserve_case'] ),
			'name_line_1'            => isset( $_POST['name_line_1'] ) ? sanitize_text_field( wp_unslash( $_POST['name_line_1'] ) ) : '',
			'name_line_2'            => isset( $_POST['name_line_2'] ) ? sanitize_text_field( wp_unslash( $_POST['name_line_2'] ) ) : '',
			'role_line_1'            => isset( $_POST['role_line_1'] ) ? sanitize_text_field( wp_unslash( $_POST['role_line_1'] ) ) : '',
			'role_line_2'            => isset( $_POST['role_line_2'] ) ? sanitize_text_field( wp_unslash( $_POST['role_line_2'] ) ) : '',
		);

		if ( 'team_portrait' === $layout ) {
			$raw_bg = isset( $_POST['team_photo_bg'] ) ? trim( (string) wp_unslash( $_POST['team_photo_bg'] ) ) : '';
			$hx     = sanitize_hex_color( $raw_bg );
			$args['team_photo_bg'] = ( is_string( $hx ) && '' !== $hx ) ? $hx : '#bfe8e8';
			$args['team_card_name'] = isset( $_POST['team_card_name'] ) ? sanitize_text_field( wp_unslash( $_POST['team_card_name'] ) ) : '';
			$args['team_card_title'] = isset( $_POST['team_card_title'] ) ? sanitize_text_field( wp_unslash( $_POST['team_card_title'] ) ) : '';
			for ( $ti = 1; $ti <= 5; $ti++ ) {
				$bk = 'team_body_' . $ti;
				$raw = isset( $_POST[ $bk ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $bk ] ) ) : '';
				$args[ $bk ] = Inventio_Wellcome_Renderer::limit_team_body_text( $raw );
				$tk          = $bk . '_tone';
				$tone        = isset( $_POST[ $tk ] ) ? sanitize_key( wp_unslash( $_POST[ $tk ] ) ) : '';
				if ( $ti <= 2 ) {
					$args[ $tk ] = ( 'light' === $tone ) ? 'light' : 'dark';
				} else {
					$args[ $tk ] = ( 'dark' === $tone ) ? 'dark' : 'light';
				}
			}
		}

		$jpeg = Inventio_Wellcome_Renderer::render_jpeg( $args );

		if ( $unlink_photo_tmp && '' !== $photo_path ) {
			@unlink( $photo_path );
		}

		if ( is_wp_error( $jpeg ) ) {
			$this->redirect_with_error( $jpeg->get_error_message() );
		}

		$filename = 'inventio-wellcome-' . gmdate( 'Y-m-d-His' ) . '.jpg';
		nocache_headers();
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $jpeg ) );
		echo $jpeg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param string $message
	 */
	private function redirect_with_error( $message ) {
		set_transient( 'inventio_wellcome_flash_error', $message, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=inventio-wellcome-card' ) );
		exit;
	}
}
