<?php
/**
 * Main FilterHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

use enshrined\svgSanitize\Sanitizer;
use TinySolutions\mlt\Helpers\Fns;

defined( 'ABSPATH' ) || exit();

/**
 * Main FilterHooks class.
 */
class FilterHooks {
	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {

		// Plugins Setting Page.
		add_filter( 'plugin_action_links_' . TSMLT_BASENAME, [ __CLASS__, 'plugins_setting_links' ] );
		add_filter( 'manage_media_columns', [ __CLASS__, 'media_custom_column' ] );
		add_filter( 'manage_upload_sortable_columns', [ __CLASS__, 'media_sortable_columns' ] );
		add_filter( 'posts_clauses', [ __CLASS__, 'media_sortable_columns_query' ], 1, 2 );
		add_filter( 'request', [ __CLASS__, 'media_sort_by_alt' ], 20, 2 );
		add_filter( 'media_row_actions', [ __CLASS__, 'filter_post_row_actions' ], 11, 2 );
		add_filter( 'default_hidden_columns', [ __CLASS__, 'hidden_columns' ], 99, 2 );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'plugin_row_meta' ], 10, 2 );

		// SVG File Permission.
		add_filter( 'mime_types', [ __CLASS__, 'add_support_mime_types' ], 99 );
		add_filter( 'wp_check_filetype_and_ext', [ __CLASS__, 'allow_svg_upload' ], 10, 4 );
		// Sanitize the SVG file before it is uploaded to the server.
		add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'sanitize_svg' ] );
		// Cron Interval for check image file.
		add_filter( 'image_downsize', [ __CLASS__, 'fix_svg_size_attributes' ], 10, 2 );
	}
	/**
	 * Sanitize an uploaded SVG file.
	 *
	 * @param array $file Uploaded file information.
	 *
	 * @return array
	 * @since 1.1.3
	 */
	public static function sanitize_svg( $file ) {
		// Only proceed if the file is an SVG.
		if ( 'image/svg+xml' !== $file['type'] ) {
			return $file;
		}
		// Set maximum file size (500KB max).
		$max_file_size = apply_filters( 'tsmlt_upload_max_svg_file_size', 500 * 1024 );
		$size_in_kb    = $max_file_size / 1024;
		$size_in_mb    = $size_in_kb / 1024;
		$size_message  = ( $size_in_kb < 1024 ) ? $size_in_kb . 'KB' : number_format( $size_in_mb, 2 ) . 'MB';
		// Validate file size.
		if ( $file['size'] > $max_file_size ) {
			$file['error'] = sprintf(
			/* translators: file size */
				esc_html__( 'The uploaded SVG exceeds the maximum allowed file size of %s.', 'tsmlt-media-tools' ),
				esc_html( $size_message )
			);
			return $file;
		}
		// Sanitize the SVG file.
		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		$sanitizer->removeXMLTag( true );
		$sanitizer->minify( true );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg_content = file_get_contents( $file['tmp_name'] );
		$clean_svg   = $sanitizer->sanitize( $svg_content );
		// If the file is not safe, return an error.
		if ( false === $clean_svg ) {
			$file['error'] = esc_html__( 'This SVG file contains unsafe content and cannot be uploaded.', 'tsmlt-media-tools' );
			return $file;
		}
		// Write sanitized SVG content back to the file.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file['tmp_name'], $clean_svg );
		return $file;
	}
	/**
	 * Fix SVG size.
	 *
	 * @param array|boolean $out output.
	 * @param int           $id image id.
	 *
	 * @return array|mixed
	 */
	public static function fix_svg_size_attributes( $out, $id ) {
		if ( ! is_admin() ) {
			return $out;
		}
		$image_url = wp_get_attachment_url( $id );
		$file_ext  = pathinfo( $image_url, PATHINFO_EXTENSION );

		if ( 'svg' !== $file_ext ) {
			return $out;
		}

		return [ $image_url, null, null, false ];
	}

	/**
	 * Check template screen
	 *
	 * @return array
	 */
	public static function allow_svg_upload( $data, $file, $filename, $mimes ) {
		$filetype = wp_check_filetype( $filename, $mimes );

		return [
			'ext'             => $filetype['ext'],
			'type'            => $filetype['type'],
			'proper_filename' => $data['proper_filename'],
		];
	}

	/**
	 * Check template screen
	 *
	 * @return boolean
	 */
	public static function hidden_columns( $hidden, $screen ) {
		if ( ! empty( $hidden ) || empty( $screen->base ) || 'upload' !== $screen->base ) {
			return $hidden;
		}
		$hidden[] = 'parent';
		$hidden[] = 'author';
		$hidden[] = 'comments';
		$hidden[] = 'date';

		return $hidden;
	}

	/**
	 * @param $mimes
	 *
	 * @return array
	 */
	public static function add_support_mime_types( $mimes ) {
		$options = Fns::get_options();
		if ( empty( $options['others_file_support'] ) || ! is_array( $options['others_file_support'] ) ) {
			return $mimes;
		}

		if ( in_array( 'svg', $options['others_file_support'] ) ) {
			$mimes['svg|svgz'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Check template screen
	 *
	 * @return boolean
	 */
	public static function is_attachment_screen() {
		global $pagenow, $typenow;

		return 'upload.php' === $pagenow && 'attachment' === $typenow;
	}

	/**
	 * @param $actions
	 *
	 * @return mixed
	 */
	public static function filter_post_row_actions( $actions, $post ) {

		$att_title = _draft_or_post_title();
		if ( ! self::is_attachment_screen() ) {
			return $actions;
		}

		$actions['trash'] = sprintf(
			'<a href="%s" class="submitdelete aria-button-if-js" aria-label="%s">%s</a>',
			wp_nonce_url( "post.php?action=trash&amp;post=$post->ID", 'trash-post_' . $post->ID ),
			/* translators: %s: Attachment title. */
			esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $att_title ) ),
			_x( 'Trash', 'verb' )
		);
		$delete_ays        = " onclick='return showNotice.warn();'";
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete aria-button-if-js"%s aria-label="%s">%s</a>',
			wp_nonce_url( "post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID ),
			$delete_ays,
			/* translators: %s: Attachment title. */
			esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $att_title ) ),
			__( 'Delete Permanently' )
		);

		return $actions;
	}

	/**
	 * Sortable column function.
	 *
	 * @param array $vars query var.
	 *
	 * @return array
	 */
	public static function media_sort_by_alt( $vars ) {

		if ( ! isset( $vars['orderby'] ) ) {
			return $vars;
		}

		if ( 'alt' !== $vars['orderby'] ) {
			return $vars;
		}
		// TODO:: IF key is not exist then ignoting the items. Ii need to fix.
		$vars = array_merge(
			$vars,
			[
				'orderby'    => 'meta_value',
				'meta_query' => [
					'relation' => 'OR',
					[
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					],
					[
						'relation' => 'OR', // Add a nested "OR" relation to handle empty alt text
						[
							'key'     => '_wp_attachment_image_alt',
							'compare' => 'EXISTS',
							'value'   => '',
						],
						[
							'key'     => '_wp_attachment_image_alt',
							'compare' => 'EXISTS',
						],
					],
				],
			]
		);
		return $vars;
	}

	/**
	 * Add new column to media table
	 *
	 * @param array $columns customize column.
	 *
	 * @return array
	 */
	public static function media_custom_column( $columns ) {
		$author   = $columns['author'] ?? '';
		$date     = $columns['date'] ?? '';
		$comments = $columns['comments'] ?? '';
		$parent   = $columns['parent'] ?? '';
		unset( $columns['author'] );
		unset( $columns['date'] );
		unset( $columns['comments'] );
		unset( $columns['parent'] );
		$columns['alt']         = __( 'Alt', 'media-library-helper' );
		$columns['caption']     = __( 'Caption', 'media-library-helper' );
		$columns['description'] = __( 'Description', 'media-library-helper' );
		$columns['category']    = __( 'Category', 'media-library-helper' );
		$columns['parent']      = $parent;
		$columns['author']      = $author;
		$columns['comments']    = $comments;
		$columns['date']        = $date;

		return $columns;
	}

	/**
	 * SHortable column.
	 *
	 * @param string $columns shortable column.
	 *
	 * @return array
	 */
	public static function media_sortable_columns( $columns ) {
		$columns['alt']         = 'alt';
		$columns['caption']     = 'caption';
		$columns['description'] = 'description';

		return $columns;
	}

	/**
	 * Undocumented function
	 *
	 * @param array  $pieces query.
	 * @param object $query post query.
	 *
	 * @return array
	 */
	public static function media_sortable_columns_query( $pieces, $query ) {
		global $wpdb;
		if ( ! $query->is_main_query() ) {
			return $pieces;
		}
		$orderby = $query->get( 'orderby' );
		if ( ! $orderby ) {
			return $pieces;
		}
		$order = strtoupper( $query->get( 'order' ) );
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			return $pieces;
		}
		switch ( $orderby ) {
			case 'caption':
				$pieces['orderby'] = " $wpdb->posts.post_excerpt $order ";
				break;
			case 'description':
				$pieces['orderby'] = " $wpdb->posts.post_content $order ";
				break;

		}

		return $pieces;
	}

	/**
	 * @param array $links default plugin action link
	 *
	 * @return array [array] plugin action link
	 */
	public static function plugins_setting_links( $links ) {
		$new_links                       = [];
		$new_links['mediaedit_settings'] = '<a href="' . admin_url( 'upload.php?page=tsmlt-media-tools' ) . '">' . esc_html__( 'Start Editing', 'tsmlt-media-tools' ) . '</a>';
		/*
		 * TODO:: Next Version
		 *
		 */
		if ( ! tsmlt()->has_pro() ) {
			$links['tsmlt_pro'] = '<a href="' . esc_url( tsmlt()->pro_version_link() ) . '" style="color: #39b54a; font-weight: bold;" target="_blank">' . esc_html__( 'Go Pro', 'tsmlt-media-tools' ) . '</a>';
		}

		return array_merge( $new_links, $links );
	}

	/**
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( $file == TSMLT_BASENAME ) {
			$report_url         = 'https://www.wptinysolutions.com/contact';// home_url( '/wp-admin/upload.php?page=tsmlt-media-tools' );
			$row_meta['issues'] = sprintf( '%2$s <a target="_blank" href="%1$s">%3$s</a>', esc_url( $report_url ), esc_html__( 'Facing issue?', 'tsmlt-media-tools' ), '<span style="color: red">' . esc_html__( 'Please open a support ticket.', 'tsmlt-media-tools' ) . '</span>' );

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}
}
