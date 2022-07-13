<?php

namespace Vendidero\TaxHelper;

defined( 'ABSPATH' ) || exit;

class Admin {

	public static function init() {
		foreach ( array( 'delete', 'refresh', 'cancel' ) as $action ) {
			add_action( 'admin_post_oss_' . $action . '_report', array( __CLASS__, $action . '_report' ) );
		}

		add_action( 'admin_post_oss_init_observer', array( __CLASS__, 'init_observer' ) );
	}

	public static function get_settings_url() {
		return apply_filters( 'oss_woocommerce_get_settings_url', '' );
	}

	public static function get_threshold_notice_content() {
		return sprintf( _x( 'Seems like you have reached (or are close to reaching) the delivery threshold for the current year. Please make sure to check the <a href="%s" target="_blank">report details</a> and take action in case necessary.', 'oss', 'woocommerce-eu-tax-helper' ), esc_url( Package::get_observer_report()->get_url() ) );
	}

	public static function get_threshold_notice_title() {
		return _x( 'Delivery threshold reached (OSS)', 'oss', 'woocommerce-eu-tax-helper' );
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

	public static function cancel_report() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '', 'oss_cancel_report' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die();
		}

		$report_id = isset( $_GET['report_id'] ) ? wc_clean( wp_unslash( $_GET['report_id'] ) ) : '';

		if ( ! empty( $report_id ) && Queue::is_running( $report_id ) ) {
			Queue::cancel( $report_id );

			$referer = self::get_clean_referer();

			/**
			 * Do not redirect deleted, refreshed reports back to report details page
			 */
			if ( strstr( $referer, '&report=' ) ) {
				$referer = admin_url( 'admin.php?page=oss-reports' );
			}

			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'report_cancelled' => $report_id ), $referer ) ) );
			exit();
		}

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
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
