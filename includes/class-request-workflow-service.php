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
	const STATUS_IN_PROGRESS     = 'in_progress';
	const STATUS_FOLLOWED_UP     = 'followed_up';
	const STATUS_FUTURE_FOLLOWUP = 'future_followup';

	private $request_repository;

	public function __construct( CRPCRM_Request_Repository $request_repository = null ) {
		$this->request_repository = $request_repository ? $request_repository : new CRPCRM_Request_Repository();
	}

	public static function get_default_status() {
		return self::STATUS_IN_PROGRESS;
	}

	public static function get_followup_statuses() {
		return array( 'follow_up', self::STATUS_FUTURE_FOLLOWUP );
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
			CRPCRM_Logger::warning(
				'sales_action_access_denied',
				'sales_action_access_denied',
				array(
					'request_id'   => $request_id,
					'user_id'      => $actor_user_id,
					'action_type'  => $action_type,
				)
			);

			if ( $this->is_closed_status( $request['status'] ) ) {
				return new WP_Error( 'request_closed_action_denied', 'این درخواست بسته شده و امکان ثبت اقدام جدید وجود ندارد.' );
			}

			return new WP_Error( 'sales_action_denied', 'شما اجازه ثبت اقدام برای این درخواست را ندارید.' );
		}

		$validated = $this->validate_action_data( $action_type, $data );
		if ( is_wp_error( $validated ) ) {
			CRPCRM_Logger::warning(
				'sales_action_validation_failed',
				'sales_action_validation_failed',
				array(
					'request_id'   => $request_id,
					'user_id'      => $actor_user_id,
					'action_type'  => $action_type,
					'error'        => $validated->get_error_code(),
				)
			);

			return $validated;
		}

		$result = $this->apply_action_to_request( $request, $action_type, $validated, $actor_user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		CRPCRM_Logger::info(
			'sales_action_added',
			'sales_action_added',
			array(
				'request_id'  => $request_id,
				'user_id'     => $actor_user_id,
				'action_type' => $action_type,
			)
		);

		return true;
	}

	public function can_add_action( $request, $user_id, $action_type = '' ) {
		$user_id     = absint( $user_id );
		$action_type = sanitize_key( $action_type );
		if ( ! $request || ! CRPCRM_Request_Access_Service::can_view_request( $request, $user_id ) ) {
			return false;
		}

		if ( $this->is_closed_status( $request['status'] ) ) {
			return false;
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
		return array( 'new', self::STATUS_IN_PROGRESS, 'no_answer', 'follow_up', self::STATUS_FUTURE_FOLLOWUP );
	}

	public function get_closed_statuses() {
		return array( self::STATUS_FOLLOWED_UP, 'won', 'lost', 'invalid' );
	}

	public function get_action_labels() {
		return array(
			'in_progress'       => 'درحال پیگیری',
			'followed_up'       => 'پیگیری شد',
			'schedule_followup' => 'تنظیم پیگیری بعدی',
		);
	}

	public function get_success_message( $action_type ) {
		$messages = array(
			'in_progress'       => 'اقدام «درحال پیگیری» ثبت شد.',
			'followed_up'       => 'اقدام «پیگیری شد» ثبت شد.',
			'schedule_followup' => 'پیگیری بعدی با موفقیت تنظیم شد.',
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
		);

		if ( '' === $clean['note'] ) {
			return new WP_Error( 'action_note_required', 'توضیح کارشناس الزامی است.' );
		}

		if ( 'schedule_followup' === $action_type ) {
			$raw = isset( $data['next_follow_up_at'] ) ? trim( sanitize_text_field( $data['next_follow_up_at'] ) ) : '';
			if ( '' === $raw ) {
				return new WP_Error( 'follow_up_required', 'تاریخ و ساعت پیگیری بعدی الزامی است.' );
			}

			$normalized = CRPCRM_Helpers::normalize_datetime_input( $raw );
			$timestamp  = $normalized ? CRPCRM_Helpers::datetime_to_timestamp( $normalized ) : false;
			if ( ! $timestamp ) {
				return new WP_Error( 'follow_up_required', 'تاریخ و ساعت پیگیری بعدی الزامی است.' );
			}
			if ( $timestamp < CRPCRM_Helpers::current_timestamp() ) {
				return new WP_Error( 'follow_up_in_past', 'تاریخ و ساعت پیگیری نمی‌تواند در گذشته باشد.' );
			}

			$clean['next_follow_up_at'] = $normalized;
		}

		return $clean;
	}

	public function apply_action_to_request( $request, $action_type, $data, $actor_user_id ) {
		global $wpdb;

		$old_status    = sanitize_key( $request['status'] ?? '' );
		$new_status    = $old_status;
		$now           = CRPCRM_Helpers::current_datetime();
		$update        = array(
			'last_action'     => $action_type,
			'last_activity_at'=> $now,
		);
		$activity_type = $action_type;
		$meta          = array();

		switch ( $action_type ) {
			case 'in_progress':
				$new_status                   = self::STATUS_IN_PROGRESS;
				$update['status']             = $new_status;
				$update['next_follow_up_at']  = null;
				$update['closed_at']          = null;
				break;
			case 'followed_up':
				$new_status                   = self::STATUS_FOLLOWED_UP;
				$update['status']             = $new_status;
				$update['next_follow_up_at']  = null;
				$update['closed_at']          = $now;
				break;
			case 'schedule_followup':
				$new_status                   = self::STATUS_FUTURE_FOLLOWUP;
				$activity_type                = 'follow_up_scheduled';
				$update['status']             = $new_status;
				$update['next_follow_up_at']  = $data['next_follow_up_at'];
				$update['closed_at']          = null;
				$meta['next_follow_up_at']    = $data['next_follow_up_at'];
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
			CRPCRM_Logger::info(
				'request_status_changed',
				'request_status_changed',
				array(
					'request_id'  => absint( $request['id'] ),
					'old_status'  => $old_status,
					'new_status'  => $new_status,
				)
			);
		}

		if ( 'schedule_followup' === $action_type ) {
			CRPCRM_Logger::info(
				'request_follow_up_scheduled',
				'request_follow_up_scheduled',
				array(
					'request_id'         => absint( $request['id'] ),
					'next_follow_up_at'  => $data['next_follow_up_at'],
				)
			);
		}

		return true;
	}
}
