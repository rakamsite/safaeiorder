<?php
/**
 * Sales request workflow service.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Request_Workflow_Service {
	private $request_repository;

	public function __construct( CRPCRM_Request_Repository $request_repository = null ) {
		$this->request_repository = $request_repository ? $request_repository : new CRPCRM_Request_Repository();
	}

	public function add_sales_action( $request_id, $action_type, $data, $actor_user_id ) {
		$request_id    = absint( $request_id );
		$actor_user_id = absint( $actor_user_id );
		$action_type   = sanitize_key( $action_type );
		$request       = $this->request_repository->get( $request_id );

		if ( ! $request ) {
			return new WP_Error( 'request_not_found', 'درخواست موردنظر یافت نشد.' );
		}

		if ( ! $this->can_add_action( $request, $actor_user_id, $action_type ) ) {
			CRPCRM_Logger::warning( 'sales_action_access_denied', 'sales_action_access_denied', array( 'request_id' => $request_id, 'user_id' => $actor_user_id, 'action_type' => $action_type ) );
			if ( $this->is_closed_status( $request['status'] ) ) {
				return new WP_Error( 'request_closed_action_denied', 'این درخواست بسته شده و امکان ثبت اقدام جدید وجود ندارد.' );
			}
			return new WP_Error( 'sales_action_denied', 'شما اجازه ثبت اقدام برای این درخواست را ندارید.' );
		}

		$validated = $this->validate_action_data( $action_type, $data );
		if ( is_wp_error( $validated ) ) {
			CRPCRM_Logger::warning( 'sales_action_validation_failed', 'sales_action_validation_failed', array( 'request_id' => $request_id, 'user_id' => $actor_user_id, 'action_type' => $action_type, 'error' => $validated->get_error_code() ) );
			return $validated;
		}

		$result = $this->apply_action_to_request( $request, $action_type, $validated, $actor_user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		CRPCRM_Logger::info( 'sales_action_added', 'sales_action_added', array( 'request_id' => $request_id, 'user_id' => $actor_user_id, 'action_type' => $action_type ) );
		return true;
	}

	public function can_add_action( $request, $user_id, $action_type = '' ) {
		$user_id     = absint( $user_id );
		$action_type = sanitize_key( $action_type );
		if ( ! $request || ! CRPCRM_Request_Access_Service::can_view_request( $request, $user_id ) ) {
			return false;
		}

		if ( $this->is_closed_status( $request['status'] ) ) {
			return 'yes' === CRPCRM_Settings::get( 'allow_manager_note_on_closed_request', 'yes' ) && 'internal_note' === $action_type && CRPCRM_Request_Access_Service::can_manage_request( $request, $user_id );
		}

		if ( CRPCRM_Request_Access_Service::can_manage_request( $request, $user_id ) ) {
			return true;
		}

		$owner_id = isset( $request['owner_id'] ) ? absint( $request['owner_id'] ) : 0;
		return $owner_id === $user_id;
	}

	public function is_closed_status( $status ) {
		return in_array( sanitize_key( $status ), $this->get_closed_statuses(), true );
	}

	public function get_open_statuses() {
		return array( 'new', 'in_progress', 'no_answer', 'follow_up' );
	}

	public function get_closed_statuses() {
		return array( 'won', 'lost', 'invalid' );
	}

	public function get_action_labels() {
		return array(
			'call_answered'      => 'تماس پاسخ داده شد',
			'call_no_answer'     => 'تماس پاسخ داده نشد',
			'whatsapp_sent'      => 'پیام واتساپ ارسال شد',
			'internal_note'      => 'یادداشت داخلی',
			'schedule_follow_up' => 'تنظیم پیگیری بعدی',
			'mark_won'           => 'موفق شد',
			'mark_lost'          => 'ناموفق شد',
			'mark_invalid'       => 'نامعتبر شد',
		);
	}

	public function get_lost_reason_labels() {
		return array(
			'price_not_suitable'             => 'قیمت مناسب نبود',
			'customer_cancelled'             => 'مشتری منصرف شد',
			'no_response'                    => 'پاسخگو نبود',
			'not_real_need'                  => 'نیاز واقعی نداشت',
			'wrong_time'                     => 'زمان خرید مناسب نبود',
			'unavailable_service_or_product' => 'موجودی/خدمت قابل ارائه نبود',
			'duplicate_or_wrong'             => 'تکراری یا اشتباه ثبت شده',
			'other'                          => 'سایر',
		);
	}

	public function get_invalid_reason_labels() {
		return array(
			'wrong_number'       => 'شماره اشتباه',
			'fake_data'          => 'اطلاعات غیرواقعی',
			'spam'               => 'اسپم',
			'irrelevant_request' => 'درخواست نامرتبط',
			'other'              => 'سایر',
		);
	}

	public function get_success_message( $action_type ) {
		$messages = array(
			'call_answered'      => 'تماس پاسخ‌داده‌شده با موفقیت ثبت شد.',
			'call_no_answer'     => 'تماس ناموفق با موفقیت ثبت شد.',
			'whatsapp_sent'      => 'ارسال پیام واتساپ با موفقیت ثبت شد.',
			'internal_note'      => 'یادداشت داخلی با موفقیت ثبت شد.',
			'schedule_follow_up' => 'پیگیری بعدی با موفقیت ثبت شد.',
			'mark_won'           => 'درخواست با موفقیت به وضعیت موفق تغییر کرد.',
			'mark_lost'          => 'درخواست با موفقیت به وضعیت ناموفق تغییر کرد.',
			'mark_invalid'       => 'درخواست با موفقیت به وضعیت نامعتبر تغییر کرد.',
		);
		return isset( $messages[ $action_type ] ) ? $messages[ $action_type ] : 'اقدام با موفقیت ثبت شد.';
	}

	public function validate_action_data( $action_type, $data ) {
		$action_labels = $this->get_action_labels();
		if ( ! isset( $action_labels[ $action_type ] ) ) {
			return new WP_Error( 'invalid_action_type', 'نوع اقدام معتبر نیست.' );
		}

		$clean = array(
			'note'              => isset( $data['note'] ) ? trim( sanitize_textarea_field( $data['note'] ) ) : '',
			'next_follow_up_at' => '',
			'close_reason'      => '',
			'invalid_reason'    => '',
		);
		if ( '' === $clean['note'] ) {
			return new WP_Error( 'action_note_required', 'توضیحات الزامی است.' );
		}

		if ( 'schedule_follow_up' === $action_type ) {
			$raw = isset( $data['next_follow_up_at'] ) ? trim( sanitize_text_field( $data['next_follow_up_at'] ) ) : '';
			if ( '' === $raw ) {
				return new WP_Error( 'follow_up_required', 'تاریخ پیگیری بعدی الزامی است.' );
			}
			$normalized = CRPCRM_Helpers::normalize_datetime_input( $raw );
			$timestamp  = $normalized ? CRPCRM_Helpers::datetime_to_timestamp( $normalized ) : false;
			if ( ! $timestamp ) {
				return new WP_Error( 'follow_up_required', 'تاریخ پیگیری بعدی الزامی است.' );
			}
			if ( $timestamp < CRPCRM_Helpers::current_timestamp() ) {
				return new WP_Error( 'follow_up_in_past', 'تاریخ پیگیری نمی‌تواند در گذشته باشد.' );
			}
			$clean['next_follow_up_at'] = $normalized;
		}

		if ( 'mark_lost' === $action_type ) {
			$reason = isset( $data['close_reason'] ) ? sanitize_key( $data['close_reason'] ) : '';
			if ( '' === $reason || ! isset( $this->get_lost_reason_labels()[ $reason ] ) ) {
				return new WP_Error( 'close_reason_required', 'دلیل ناموفق بودن الزامی است.' );
			}
			$clean['close_reason'] = $reason;
		}

		if ( 'mark_invalid' === $action_type ) {
			$reason = isset( $data['invalid_reason'] ) ? sanitize_key( $data['invalid_reason'] ) : '';
			if ( '' === $reason || ! isset( $this->get_invalid_reason_labels()[ $reason ] ) ) {
				return new WP_Error( 'invalid_reason_required', 'دلیل نامعتبر بودن الزامی است.' );
			}
			$clean['invalid_reason'] = $reason;
		}

		return $clean;
	}

	public function apply_action_to_request( $request, $action_type, $data, $actor_user_id ) {
		global $wpdb;

		$old_status    = $request['status'];
		$new_status    = $old_status;
		$now           = CRPCRM_Helpers::current_datetime();
		$update        = array( 'last_action' => $action_type, 'last_activity_at' => $now );
		$activity_type = $action_type;
		$meta          = array();

		switch ( $action_type ) {
			case 'call_answered':
				$new_status       = 'new' === $old_status ? 'in_progress' : $old_status;
				$update['status'] = $new_status;
				break;
			case 'call_no_answer':
				$new_status       = 'no_answer';
				$update['status'] = $new_status;
				break;
			case 'whatsapp_sent':
				$new_status       = 'new' === $old_status ? 'in_progress' : $old_status;
				$update['status'] = $new_status;
				break;
			case 'internal_note':
				break;
			case 'schedule_follow_up':
				$new_status                   = 'follow_up';
				$activity_type                = 'follow_up_scheduled';
				$update['status']             = $new_status;
				$update['next_follow_up_at']  = $data['next_follow_up_at'];
				$meta['next_follow_up_at']    = $data['next_follow_up_at'];
				break;
			case 'mark_won':
				$new_status                  = 'won';
				$activity_type               = 'request_won';
				$update['status']            = $new_status;
				$update['closed_at']         = $now;
				$update['next_follow_up_at'] = null;
				break;
			case 'mark_lost':
				$new_status                  = 'lost';
				$activity_type               = 'request_lost';
				$update['status']            = $new_status;
				$update['closed_at']         = $now;
				$update['next_follow_up_at'] = null;
				$update['close_reason']      = $data['close_reason'];
				$meta['close_reason']        = $data['close_reason'];
				break;
			case 'mark_invalid':
				$new_status                  = 'invalid';
				$activity_type               = 'request_invalid';
				$update['status']            = $new_status;
				$update['closed_at']         = $now;
				$update['next_follow_up_at'] = null;
				$update['invalid_reason']    = $data['invalid_reason'];
				$meta['invalid_reason']      = $data['invalid_reason'];
				break;
		}

		$wpdb->query( 'START TRANSACTION' );
		if ( ! $this->request_repository->update( $request['id'], $update ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'sales_action_update_failed', 'ثبت اقدام انجام نشد.' );
		}

		$activity_result = CRPCRM_Activity::add_required(
			$request['id'],
			$activity_type,
			array(
				'customer_id'   => $request['customer_id'],
				'actor_user_id' => $actor_user_id,
				'actor_type'    => CRPCRM_Request_Access_Service::can_manage_request( $request, $actor_user_id ) ? 'sales_manager' : 'sales_agent',
				'old_status'    => $old_status,
				'new_status'    => $new_status,
				'note'          => $data['note'],
				'is_internal'   => 1,
				'meta'          => $meta,
			),
			'request_workflow_action'
		);

		if ( is_wp_error( $activity_result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $activity_result;
		}

		$wpdb->query( 'COMMIT' );

		if ( $new_status !== $old_status ) {
			CRPCRM_Logger::info( 'request_status_changed', 'request_status_changed', array( 'request_id' => absint( $request['id'] ), 'old_status' => $old_status, 'new_status' => $new_status ) );
		}
		if ( 'schedule_follow_up' === $action_type ) {
			CRPCRM_Logger::info( 'request_follow_up_scheduled', 'request_follow_up_scheduled', array( 'request_id' => absint( $request['id'] ), 'next_follow_up_at' => $data['next_follow_up_at'] ) );
		} elseif ( 'mark_won' === $action_type ) {
			CRPCRM_Logger::info( 'request_closed_won', 'request_closed_won', array( 'request_id' => absint( $request['id'] ) ) );
		} elseif ( 'mark_lost' === $action_type ) {
			CRPCRM_Logger::info( 'request_closed_lost', 'request_closed_lost', array( 'request_id' => absint( $request['id'] ), 'close_reason' => $data['close_reason'] ) );
		} elseif ( 'mark_invalid' === $action_type ) {
			CRPCRM_Logger::info( 'request_closed_invalid', 'request_closed_invalid', array( 'request_id' => absint( $request['id'] ), 'invalid_reason' => $data['invalid_reason'] ) );
		}

		return true;
	}
}
