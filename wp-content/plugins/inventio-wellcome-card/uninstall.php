<?php
/**
 * Κατά την απεγκατάσταση.
 *
 * @package Inventio_Wellcome_Card
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'inventio_wellcome_template_id' );
delete_option( 'inventio_wellcome_presets_data' );
delete_option( 'inventio_wellcome_active_preset' );
