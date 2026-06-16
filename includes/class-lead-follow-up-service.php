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
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'lead_followup' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function clear_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function create_due_follow_ups() {
		if ( ! CRPCRM_Feature_Manager::is_enabled( 'lead_followup' ) ) {
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
			$reason            = $profile_completed
				? 'پروفایل تکمیل شده است، اما مشتری هنوز درخواستی ثبت نکرده است.'
				: 'مشتری پس از 24 ساعت هنوز اطلاعات پروفایل خود را تکمیل نکرده است.';
			$system_metadata   = CRPCRM_System_Request_Types::get_metadata( CRPCRM_System_Request_Types::LEAD_FOLLOW_UP );
			$landing_attr      = CRPCRM_Customer_Repository::get_landing_attribution( $customer );
			$landing_touch     = ! empty( $landing_attr['last_touch'] )
				? $landing_attr['last_touch']
				: ( ! empty( $landing_attr['first_touch'] ) ? $landing_attr['first_touch'] : array() );
			$request_data      = array(
				'profile_completed' => $profile_completed ? 'yes' : 'no',
				'lead_reason'       => $reason,
				'form_id'           => $system_metadata['form_id'],
				'form_version'      => $system_metadata['form_version'],
			);

			if ( ! empty( $landing_attr ) ) {
				$request_data['_landing_attribution'] = $landing_attr;
			}

			$request_id = $this->request_repository->create(
				array(
					'customer_id'          => absint( $customer['id'] ),
					'user_id'              => absint( $customer['user_id'] ),
					'request_type'         => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP,
					'form_id'              => $system_metadata['form_id'],
					'form_version'         => $system_metadata['form_version'],
					'request_title'        => 'پیگیری سرنخ',
					'request_summary'      => $reason,
					'request_data'         => $request_data,
					'request_source'       => ! empty( $landing_touch['source_code'] ) ? $landing_touch['source_code'] : ( ! empty( $customer['last_source'] ) ? $customer['last_source'] : ( ! empty( $customer['first_source'] ) ? $customer['first_source'] : 'direct' ) ),
					'request_medium'       => ! empty( $landing_touch['medium_code'] ) ? $landing_touch['medium_code'] : ( ! empty( $customer['last_medium'] ) ? $customer['last_medium'] : ( ! empty( $customer['first_medium'] ) ? $customer['first_medium'] : 'none' ) ),
					'request_campaign'     => ! empty( $landing_touch['campaign_code'] ) ? $landing_touch['campaign_code'] : ( ! empty( $customer['last_campaign'] ) ? $customer['last_campaign'] : ( $customer['first_campaign'] ?? '' ) ),
					'request_content'      => ! empty( $landing_touch['content_code'] ) ? $landing_touch['content_code'] : ( ! empty( $customer['last_content'] ) ? $customer['last_content'] : ( $customer['first_content'] ?? '' ) ),
					'request_term'         => ! empty( $landing_touch['term_code'] ) ? $landing_touch['term_code'] : ( ! empty( $customer['last_term'] ) ? $customer['last_term'] : ( $customer['first_term'] ?? '' ) ),
					'request_landing_id'   => absint( $landing_touch['landing_id'] ?? 0 ),
					'request_landing_slug' => sanitize_key( $landing_touch['landing_slug'] ?? '' ),
					'request_landing_page' => ! empty( $landing_attr['conversion_page'] ) ? $landing_attr['conversion_page'] : ( ! empty( $landing_touch['destination_url'] ) ? $landing_touch['destination_url'] : '' ),
					'request_referrer'     => ! empty( $landing_attr['referrer'] ) ? $landing_attr['referrer'] : ( $customer['last_referrer'] ?? '' ),
					'last_action'          => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CREATED,
				)
			);

			if ( ! $request_id ) {
				continue;
			}

			$request = $this->request_repository->get( $request_id );
			CRPCRM_Activity::add(
				$request_id,
				CRPCRM_System_Request_Types::LEAD_FOLLOW_UP_CREATED,
				array(
					'customer_id' => absint( $customer['id'] ),
					'actor_type'  => 'system',
					'new_status'  => 'new',
					'note'        => $reason,
					'is_internal' => 1,
				)
			);

			if ( $request && ! empty( $landing_attr ) ) {
				$this->request_repository->save_landing_attribution( $request_id, $landing_attr );

				if ( class_exists( 'CRPCRM_Landing_Manager' ) ) {
					( new CRPCRM_Landing_Manager() )->record_conversion_for_request(
						$request_id,
						array(
							'click_id'     => absint( $landing_touch['click_id'] ?? 0 ),
							'visitor_id'   => sanitize_text_field( $landing_attr['visitor_id'] ?? '' ),
							'landing_id'   => absint( $landing_touch['landing_id'] ?? 0 ),
							'landing_slug' => sanitize_key( $landing_touch['landing_slug'] ?? '' ),
							'converted_at' => $landing_attr['converted_at'] ?? CRPCRM_Helpers::current_datetime(),
						)
					);
				}
			}

			if ( $request ) {
				$notification_service = new CRPCRM_Notification_Service();
				$notification_service->notify_new_request(
					$request_id,
					$request['request_title'] ?? 'پیگیری سرنخ',
					$request['request_code'] ?? '',
					0,
					admin_url( 'admin.php?page=crpcrm-requests&request_id=' . $request_id )
				);
			}

			$created++;
		}

		CRPCRM_Logger::info(
			'system_request_cron_completed',
			'request',
			array(
				'request_type' => CRPCRM_System_Request_Types::LEAD_FOLLOW_UP,
				'eligible'     => count( $customers ),
				'created'      => $created,
			)
		);

		return $created;
	}
}
