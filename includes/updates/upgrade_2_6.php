<?php

function pmpro_upgrade_2_6(){

	global $wpdb;
	$wpdb->show_errors();

	$wpdb->pmpro_membership_levels = $wpdb->prefix . 'pmpro_membership_levels';
	$wpdb->pmpro_discount_codes_levels = $wpdb->prefix . 'pmpro_discount_codes_levels';

	$sqlQuery = "
		ALTER TABLE  `" . $wpdb->pmpro_membership_levels . "` MODIFY `expiration_period` enum('Hour', 'Day','Week','Month','Year') NOT NULL
	";
	$wpdb->query($sqlQuery);

	$sqlQuery = "
		ALTER TABLE  `" . $wpdb->pmpro_discount_codes_levels . "` MODIFY `expiration_period` enum('Hour', 'Day','Week','Month','Year') NOT NULL
	";
	$wpdb->query($sqlQuery);

	/**
	 * Reschedule Cron Job for Hourly Checks
	 */
	$timestamp = wp_next_scheduled( 'pmpro_cron_expire_memberships' );

	wp_unschedule_event( $timestamp, 'pmpro_cron_expire_memberships' );

	wp_schedule_event( current_time( 'timestamp' ), 'pmpro_expiration_schedule', 'pmpro_cron_expire_memberships' );

	/**
	 * Update all existing orders - Do we still need to do this? 
	 */
	
	// $sqlQuery = "SELECT * 
 //                 FROM $wpdb->pmpro_membership_orders
 //                    AND status = 'success'
	// 			ORDER BY id";
	// $orders = $wpdb->get_results( $sqlQuery );
	
	// if(!empty($orders)) {
	// 	if(count($orders) > 10) {
	// 		//if more than 10 orders, we'll need to do this via AJAX
	// 		pmpro_addUpdate( 'pmpro_upgrade_2_6_ajax' );
	// 	} else {
	// 		//less than 10, let's just do them now
	// 		$stripe = new PMProGateway_stripe();
	// 		require_once( ABSPATH . "/wp-includes/pluggable.php" );
 //            foreach($orders as $order) {                
 //                $subscription = $stripe->getSubscription( $order );
                
 //                if ( ! empty( $subscription ) ) {
 //                    $sqlQuery = "UPDATE $wpdb->pmpro_membership_orders SET subscription_transaction_id = '" . esc_sql( $subscription->id ) . "' WHERE id = '" . esc_sql( $order->id ) . "' LIMIT 1";
 //                    $wpdb->query( $sqlQuery );
 //                }
	// 		}
	// 		update_option( 'pmpro_upgrade_2_6_last_order_id', $last_order_id );
	// 	}
	// }


}

function pmpro_upgrade_2_6_ajax(){

	//keeping track of which order we're working on
	$last_order_id = get_option( 'pmpro_upgrade_2_6_last_order_id', 0 );

	//get orders
	$sqlQuery = "SELECT * 
                 FROM $wpdb->pmpro_membership_orders
                 WHERE id > '" . esc_sql( $last_order_id ) . "'
                    AND status = 'success' AND enddate IS NOT NULL AND enddate <> '0000-00-00 00:00:00'
				ORDER BY id";
	$orders = $wpdb->get_results( $sqlQuery );

	if(empty($orders)) {
		//done with this update
		pmpro_removeUpdate('pmpro_upgrade_2_4_ajax');
		delete_option( 'pmpro_upgrade_2_6_last_order_id' );
	} else {
		//less than 10, let's just do them now
		$stripe = new PMProGateway_stripe();
		require_once( ABSPATH . "/wp-includes/pluggable.php" );
		foreach($orders as $order) {                
			$subscription = $stripe->getSubscription( $order );
			
			if ( ! empty( $subscription ) ) {
				$sqlQuery = "UPDATE $wpdb->pmpro_membership_orders SET subscription_transaction_id = '" . esc_sql( $subscription->id ) . "' WHERE id = '" . esc_sql( $order->id ) . "' LIMIT 1";
				$wpdb->query( $sqlQuery );
			}
			
			$last_order_id = $order->id;
		}

		update_option( 'pmpro_upgrade_2_6_last_order_id', $last_order_id );
	}

}