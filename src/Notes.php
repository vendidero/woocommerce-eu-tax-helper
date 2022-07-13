<?php

namespace Vendidero\TaxHelper;

use Automattic\WooCommerce\Admin\Notes\Note;

defined( 'ABSPATH' ) || exit;

class Notes {

	/**
	 * Constructor.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'admin_post_oss_hide_notice', array( __CLASS__, 'hide_notice' ) );
	}

	public static function hide_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'oss_hide_notice' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die();
		}

		$notice_id = isset( $_GET['notice'] ) ? wc_clean( wp_unslash( $_GET['notice'] ) ) : '';

		foreach ( self::get_notes() as $oss_note ) {
			if ( $oss_note::get_id() === $notice_id ) {
				update_option( 'oss_hide_notice_' . sanitize_key( $oss_note::get_id() ), 'yes' );

				if ( self::supports_wc_admin() ) {
					self::delete_wc_admin_note( $oss_note );
				}

				break;
			}
		}

		wp_safe_redirect( wp_get_referer() );
		exit();
	}

	/**
	 * @param AdminNote $oss_note
	 */
	public static function delete_wc_admin_note( $oss_note ) {
		if ( ! self::supports_wc_admin() ) {
			return false;
		}

		try {
			if ( $note = self::get_wc_admin_note( $oss_note::get_id() ) ) {
				$note->delete( true );
				return true;
			}

			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public static function admin_notices() {
		$screen         = get_current_screen();
		$screen_id      = $screen ? $screen->id : '';
		$supports_notes = self::supports_wc_admin();

		if ( ! $supports_notes || in_array( $screen_id, array( 'dashboard', 'plugins' ), true ) ) {
			foreach ( self::get_notes() as $note ) {
				if ( $note::is_enabled() ) {
					$note::render();
				}
			}
		}
	}

	public static function on_wc_admin_note_update( $note_id ) {
		try {
			if ( self::supports_wc_admin() ) {
				$note = new Note( $note_id );

				foreach ( self::get_notes() as $oss_note ) {
					$wc_admin_note_name = self::get_wc_admin_note_name( $oss_note::get_id() );

					if ( $note->get_name() === $wc_admin_note_name ) {
						/**
						 * Update notice hide in case note has been actioned (e.g. button click by user)
						 */
						if ( Note::E_WC_ADMIN_NOTE_ACTIONED === $note->get_status() ) {
							update_option( 'oss_hide_notice_' . sanitize_key( $oss_note::get_id() ), 'yes' );
						}

						break;
					}
				}
			}
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * @return AdminNote[]
	 */
	public static function get_notes() {
		$notes = array( 'Vendidero\TaxHelper\DeliveryThresholdWarning' );

		if ( ! Package::enable_auto_observer() ) {
			$notes = array();
		}

		return $notes;
	}

	public static function supports_wc_admin() {
		$supports_notes = class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' );

		try {
			$data_store = \WC_Data_Store::load( 'admin-note' );
		} catch ( \Exception $e ) {
			$supports_notes = false;
		}

		return $supports_notes;
	}

	protected static function get_wc_admin_note_name( $oss_note_id ) {
		return 'oss_' . $oss_note_id;
	}

	protected static function get_wc_admin_note( $oss_note_id ) {
		$note_name  = self::get_wc_admin_note_name( $oss_note_id );
		$data_store = \WC_Data_Store::load( 'admin-note' );
		$note_ids   = $data_store->get_notes_with_name( $note_name );

		if ( ! empty( $note_ids ) && ( $note = \Automattic\WooCommerce\Admin\Notes\Notes::get_note( $note_ids[0] ) ) ) {
			return $note;
		}

		return false;
	}

	public static function queue_wc_admin_notes() {
		if ( self::supports_wc_admin() ) {
			foreach ( self::get_notes() as $oss_note ) {
				$note = self::get_wc_admin_note( $oss_note::get_id() );

				if ( ! $note && $oss_note::is_enabled() ) {
					$note = new Note();
					$note->set_title( $oss_note::get_title() );
					$note->set_content( $oss_note::get_content() );
					$note->set_content_data( (object) array() );
					$note->set_type( 'update' );
					$note->set_name( self::get_wc_admin_note_name( $oss_note::get_id() ) );
					$note->set_source( 'oss-woocommerce' );
					$note->set_status( Note::E_WC_ADMIN_NOTE_UNACTIONED );

					foreach ( $oss_note::get_actions() as $action ) {
						$note->add_action(
							'oss_' . sanitize_key( $action['title'] ),
							$action['title'],
							$action['url'],
							Note::E_WC_ADMIN_NOTE_ACTIONED,
							$action['is_primary'] ? true : false
						);
					}

					$note->save();
				} elseif ( $oss_note::is_enabled() && $note ) {
					$note->set_status( Note::E_WC_ADMIN_NOTE_UNACTIONED );
					$note->save();
				}
			}
		}
	}
}
