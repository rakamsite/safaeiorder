<?php
/**
 * Creates follow-up requests for customer leads that have not submitted a request.
 *
 * @package CRPCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRPCRM_Lead_Follow_Up_Service {
	const CRON_HOOK = CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CRON;

	private $customer_repository;
	private $request_repository;

	public function __construct() {
		$this->customer_repository = new CRPCRM_Customer_Repository();
		$this->request_repository  = new CRPCRM_Request_Repository();
	}

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'create_due_follow_ups' ) );
		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function clear_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function create_due_follow_ups() {
		if ( ! CRPCRM_Business_Profile_Manager::is_setup_completed() ) {
			return 0;
		}

		$cutoff    = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );
		$customers = $this->customer_repository->find_customers_without_requests( $cutoff, 100 );
		$created   = 0;

		foreach ( $customers as $customer ) {
			$user = get_userdata( absint( $customer['user_id'] ) );
			if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) ) {
				continue;
			}

			$profile_completed = ! empty( $customer['profile_completed'] );
			$reason            = $profile_completed ? 'پروفایل تکمیل شده است، اما مشتری هنوز درخواستی ثبت نکرده است.' : 'مشتری پس از ۲۴ ساعت هنوز اطلاعات پروفایل خود را تکمیل نکرده است.';
			$profile_id        = CRPCRM_Business_Profile_Manager::get_instance()->get_active_profile_id();
			$system_metadata   = CRPCRM_System_Request_Types::get_metadata( CRPCRM_System_Request_Types::LEAD_FOLLOW_UP );
			$request_id        = $this->request_repository->create(
				array(
					'customer_id'      => absint( $customer['id'] ),
					'user_id'          => absint( $customer['user_id'] ),
					'request_type'     => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP,
					'business_profile' => $profile_id,
					'form_id'          => $system_metadata['form_id'],
					'form_version'     => $system_metadata['form_version'],
					'request_title'    => 'پیگیری سرنخ',
					'request_summary'  => $reason,
					'request_data'     => array( 'profile_completed' => $profile_completed ? 'yes' : 'no', 'lead_reason' => $reason, 'business_profile' => $profile_id, 'form_id' => $system_metadata['form_id'], 'form_version' => $system_metadata['form_version'] ),
					'request_source'   => ! empty( $customer['first_source'] ) ? $customer['first_source'] : 'direct',
					'request_medium'   => ! empty( $customer['first_medium'] ) ? $customer['first_medium'] : 'none',
					'request_campaign' => isset( $customer['first_campaign'] ) ? $customer['first_campaign'] : '',
					'last_action'      => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CREATED,
				)
			);

			if ( ! $request_id ) {
				continue;
			}

			CRPCRM_Activity::add( $request_id, CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CREATED, array( 'customer_id' => absint( $customer['id'] ), 'actor_type' => 'system', 'new_status' => 'new', 'note' => $reason, 'is_internal' => 1 ) );
			$created++;
		}

		CRPCRM_Logger::info( 'system_request_cron_completed', 'request', array( 'request_type' => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP, 'eligible' => count( $customers ), 'created' => $created ) );
		return $created;
	}
}
