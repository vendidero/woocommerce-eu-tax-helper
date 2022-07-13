<?php

namespace Vendidero\OSS;

defined( 'ABSPATH' ) || exit;

class Admin {

	public static function init() {
		add_action( 'admin_post_oss_create_report', array( __CLASS__, 'create_report' ) );

		foreach ( array( 'delete', 'refresh', 'cancel' ) as $action ) {
			add_action( 'admin_post_oss_' . $action . '_report', array( __CLASS__, $action . '_report' ) );
		}

		add_action( 'admin_post_oss_switch_procedure', array( __CLASS__, 'switch_procedure' ) );
		add_action( 'admin_post_oss_init_observer', array( __CLASS__, 'init_observer' ) );
	}

	public static function get_settings_url() {
		return apply_filters( 'oss_woocommerce_get_settings_url', '' );
	}

	public static function get_threshold_notice_content() {
		return sprintf( _x( 'Seems like you have reached (or are close to reaching) the delivery threshold for the current year. Please make sure to check the <a href="%s" target="_blank">report details</a> and take action in case necessary.', 'oss', 'oss-woocommerce' ), esc_url( Package::get_observer_report()->get_url() ) );
	}

	public static function get_threshold_notice_title() {
		return _x( 'Delivery threshold reached (OSS)', 'oss', 'oss-woocommerce' );
	}

	public static function init_observer() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'oss_init_observer' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die();
		}

		if ( ! Queue::get_running_observer() ) {
			Package::update_observer_report();
		}

		wp_safe_redirect( wp_get_referer() );
		exit();
	}

	public static function switch_procedure() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'oss_switch_procedure' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die();
		}

		if ( Package::oss_procedure_is_enabled() ) {
			update_option( 'oss_use_oss_procedure', 'no' );

			Tax::import_default_tax_rates();

			do_action( 'woocommerce_oss_disabled_oss_procedure' );
		} else {
			update_option( 'woocommerce_tax_based_on', 'shipping' );
			update_option( 'oss_use_oss_procedure', 'yes' );

			Tax::import_oss_tax_rates();

			do_action( 'woocommerce_oss_enabled_oss_procedure' );
		}

		do_action( 'woocommerce_oss_switched_oss_procedure_status' );

		wp_safe_redirect( wp_get_referer() );
		exit();
	}

	public static function delete_report() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'oss_delete_report' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die();
		}

		$report_id = isset( $_GET['report_id'] ) ? wc_clean( wp_unslash( $_GET['report_id'] ) ) : '';

		if ( ! empty( $report_id ) && ( $report = Package::get_report( $report_id ) ) ) {
			$report->delete();

			$referer = self::get_clean_referer();

			/**
			 * Do not redirect deleted, refreshed reports back to report details page
			 */
			if ( strstr( $referer, '&report=' ) ) {
				$referer = admin_url( 'admin.php?page=oss-reports' );
			}

			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'report_deleted' => $report_id ), $referer ) ) );
			exit();
		}

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
		exit();
	}

	protected static function get_clean_referer() {
		$referer = wp_get_referer();

		return remove_query_arg( array( 'report_created', 'report_deleted', 'report_restarted', 'report_cancelled' ), $referer );
	}

	public static function refresh_report() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'oss_refresh_report' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die();
		}

		$report_id = isset( $_GET['report_id'] ) ? wc_clean( wp_unslash( $_GET['report_id'] ) ) : '';

		if ( ! empty( $report_id ) && ( $report = Package::get_report( $report_id ) ) ) {
			Queue::start( $report->get_type(), $report->get_date_start(), $report->get_date_end() );

			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'report_restarted' => $report_id ), self::get_clean_referer() ) ) );
			exit();
		}

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
		exit();
	}
}
