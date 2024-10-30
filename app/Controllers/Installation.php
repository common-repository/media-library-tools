<?php

namespace TinySolutions\mlt\Controllers;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

class Installation {
    /**
     * @return void
     */
    public static function activate() {
        if ( ! get_option( 'tsmlt_plugin_version' ) ) {
            $tsmlt_media = get_option( 'tsmlt_settings' , [] );
	        $tsmlt_media['media_per_page'] = absint( $tsmlt_media['media_per_page'] ?? 20 ) ;
            if( empty( $tsmlt_media['media_table_column'] ) ){
                $tsmlt_media['media_table_column'] = [
                    'Image',
                    'Title',
                    'Alt',
                    'Caption',
                    'Description',
                ] ;
            }
	        // Create table.
	        self::migration();
            update_option( 'tsmlt_settings', $tsmlt_media );
	        update_option('tsmlt_plugin_version', TSMLT_VERSION);
            update_option('tsmlt_plugin_activation_time', strtotime( 'now' ) );
        }
    }

	/**
	 * @return void
	 */
	public static function deactivation() {
		wp_clear_scheduled_hook( 'tsmlt_upload_dir_scan' );
		wp_clear_scheduled_hook( 'tsmlt_upload_inner_file_scan' );
	}

	/**
	 * @return void
	 */
	public static function migration() {
		$prev_version = get_option( 'tsmlt_plugin_version' );
		if ( ! $prev_version || version_compare( TSMLT_VERSION, $prev_version, ">" ) ) {
			self::create_tables();
			update_option('tsmlt_plugin_version', TSMLT_VERSION );
		}
	}

	/**
	 * @return void
	 */
	public static function create_tables(){
		global $wpdb;
		if ( ! function_exists('dbDelta') ) {
			require_once ABSPATH.'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();
		$sql_query = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}tsmlt_unlisted_file` (	
				 	`id` INT AUTO_INCREMENT PRIMARY KEY,
					`attachment_id` INT DEFAULT 0,
					`file_path` VARCHAR(255) NOT NULL,
					`file_type` VARCHAR(50) DEFAULT NULL,
					`status` VARCHAR(50) DEFAULT 'show',
					`meta_data` VARCHAR(50) DEFAULT NULL
				) $charset_collate";
		dbDelta($sql_query);
	}



}