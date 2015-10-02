<?php
/**
 * Plugin Name:     Easy Digital Downloads - Mixpanel Lite
 * Plugin URI:      http://eddmixpanel.com/
 * Description:     Easily integrate Mixpanel analytics with Easy Digital Downloads.
 * Version:         1.0.3
 * Author:          Dylan Ryan
 * Author URI:      http://dylanryan.co
 * Domain Path:     /languages
 * Text Domain:     edd-mixpanel
 * GitHub URI:      https://github.com/irkanu/EDD-Mixpanel-Lite
 * GitHub Branch:   master
 * License:         GPLv2 or later
 *
 * Note:            This code is heavily inspired by Pippin and his original edd-mixpanel repository.
 *                  https://github.com/easydigitaldownloads/edd-mixpanel
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package         EDD\EDD_Mixpanel
 * @author          Dylan Ryan
 * @copyright       Copyright (c) Dylan Ryan
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EDD_Mixpanel' ) ) {

	/**
	 * Main EDD_Mixpanel class
	 *
	 * @since 1.0.0
	 */
	class EDD_Mixpanel {

		/**
		 * Equals true when we are tracking.
		 *
		 * @var     bool
		 * @since   1.0.0
		 */
		private $track = false;

		/**
		 * Mixpanel instance.
		 *
		 * @var     Mixpanel
		 * @since   1.0.0
		 */
		private $mixpanel;

		/**
		 * EDD_Mixpanel instance.
		 *
		 * @var         EDD_Mixpanel
		 * @since       1.0.0
		 */
		private static $instance;


		/**
		 * Get active instance.
		 *
		 * @access      public
		 * @since       1.0.0
		 * @return      object self::$instance The one true EDD_Mixpanel
		 */
		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance = new EDD_Mixpanel();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->load_textdomain();
				self::$instance->hooks();
			}

			return self::$instance;
		}


		/**
		 * Setup plugin constants.
		 *
		 * @access      private
		 * @since       1.0.0
		 * @return      void
		 */
		private function setup_constants() {
			define( 'EDD_MIXPANEL_VER', '1.0.3' );
			define( 'EDD_MIXPANEL_DIR', plugin_dir_path( __FILE__ ) );
			define( 'EDD_MIXPANEL_URL', plugin_dir_url( __FILE__ ) );
		}


		/**
		 * Include necessary files.
		 *
		 * @access      private
		 * @since       1.0.0
		 * @return      void
		 */
		private function includes() {
			// Mixpanel PHP Library.
			require_once EDD_MIXPANEL_DIR . 'vendor/mixpanel/mixpanel-php/lib/Mixpanel.php';
		}


		/**
		 * Run action and filter hooks.
		 *
		 * @access      private
		 * @since       1.0.0
		 * @return      void
		 */
		private function hooks() {
			global $edd_options;

			// Register settings.
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ), 1 );

			// Track items added to the cart.
			if ( true === $edd_options['edd_mixpanel_track_added_to_cart'] && isset( $edd_options['edd_mixpanel_track_added_to_cart'] ) ) {
				add_action( 'edd_post_add_to_cart', array( $this, 'track_added_to_cart' ) );
			}

			// Track completed purchases.
			if ( true === $edd_options['edd_mixpanel_track_completed_purchases'] && isset( $edd_options['edd_mixpanel_track_completed_purchases'] ) ) {
				add_action( 'edd_update_payment_status', array( $this, 'track_completed_purchase' ), 100, 3 );
			}
		}


		/**
		 * Internationalization.
		 *
		 * @access      public
		 * @since       1.0.0
		 * @return      void
		 */
		public function load_textdomain() {
			// Set filter for language directory.
			$lang_dir = EDD_MIXPANEL_DIR . '/languages/';
			$lang_dir = apply_filters( 'edd_mixpanel_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter.
			$locale = apply_filters( 'plugin_locale', get_locale(), 'edd-mixpanel' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'edd-mixpanel', $locale );

			// Setup paths to current locale file.
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/edd-mixpanel/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-mixpanel/ folder.
				load_textdomain( 'edd-mixpanel', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-mixpanel/languages/ folder.
				load_textdomain( 'edd-mixpanel', $mofile_local );
			} else {
				// Load the default language files.
				load_plugin_textdomain( 'edd-mixpanel', false, $lang_dir );
			}
		}


		/**
		 * Add settings.
		 *
		 * @access      public
		 * @since       1.0.0
		 *
		 * @param       array $settings The existing EDD settings array.
		 *
		 * @return      array The modified EDD settings array
		 */
		public function settings( $settings ) {
			$new_settings = array(
				array(
					'id'   => 'edd_mixpanel_settings',
					'name' => '<strong>' . __( 'Mixpanel Settings', 'edd-mixpanel' ) . '</strong>',
					'desc' => __( 'Configure Mixpanel Settings', 'edd-mixpanel' ),
					'type' => 'header',
				),
				array(      // API Key.
					'id'   => 'edd_mixpanel_api_key',
					'name' => __( 'Project Token', 'edd-mixpanel' ),
					'desc' => __( 'Enter the Token for the Mixpanel Project you want to track data for.', 'edd-mixpanel' ),
					'type' => 'text',
					'size' => 'regular',
				),
				array(      // Added to cart.
					'id'   => 'edd_mixpanel_track_added_to_cart',
					'name' => __( 'Track "Added To Cart"?', 'edd-mixpanel' ),
					'desc' => __( 'Check this box to track users that added items to their cart.', 'edd-mixpanel' ),
					'type' => 'checkbox',
				),
				array(      // Added to cart label.
					'id'   => 'edd_mixpanel_track_added_to_cart_label',
					'name' => __( '"Added To Cart" Label', 'edd-mixpanel' ),
					'desc' => __( 'Enter the label that identifies this action in Mixpanel. Default: EDD Added To Cart', 'edd-mixpanel' ),
					'type' => 'text',
					'size' => 'medium',
					'std'  => __( 'EDD Added To Cart', 'edd-mixpanel' ),
				),
				array(      // Completed purchases.
					'id'   => 'edd_mixpanel_track_completed_purchases',
					'name' => __( 'Track "Completed Purchases"?', 'edd-mixpanel' ),
					'desc' => __( 'Check this box to track completed purchases.', 'edd-mixpanel' ),
					'type' => 'checkbox',
				),
				array(      // Completed purchases label.
					'id'   => 'edd_mixpanel_track_completed_purchases_label',
					'name' => __( '"Completed Purchases" Label', 'edd-mixpanel' ),
					'desc' => __( 'Enter the label that identifies this action in Mixpanel. Default: EDD Completed Purchase', 'edd-mixpanel' ),
					'type' => 'text',
					'size' => 'medium',
					'std'  => __( 'EDD Completed Purchase', 'edd-mixpanel' ),
				),
			);

			return array_merge( $settings, $new_settings );
		}


		/**
		 * Helper function to set Mixpanel token.
		 *
		 * @access      private
		 * @since       1.0.0
		 */
		private function set_token() {
			global $edd_options;

			// Grab the API Key.
			$token = isset( $edd_options['edd_mixpanel_api_key'] ) ? trim( $edd_options['edd_mixpanel_api_key'] ) : false;

			// Setup the Mixpanel instance.
			$this->mixpanel = Mixpanel::getInstance( $token );

			// If we don't have a key, bail.
			if ( ! empty( $token ) ) {
				$this->track = true;
			}
		}


		/**
		 * Track items added to a user's cart.
		 *
		 * @since   1.0.0
		 *
		 * @param int   $download_id Download ID number.
		 * @param array $options     Optional parameters, used for defining variable prices.
		 */
		public function track_added_to_cart( $download_id = 0, $options = array() ) {
			global $edd_options;

			// Store tracked event properties.
			$event_props  = array();
			$person_props = array();

			// Setup Mixpanel instance.
			$this->set_token();

			// If we failed, bail.
			if ( ! $this->track ) {
				return;
			}

			// If we are logged in, grab IP & current user data.
			if ( is_user_logged_in() ) {
				$person_props['ip']         = edd_get_ip();
				$event_props['distinct_id'] = get_current_user_id();
				$this->mixpanel->people->set( get_current_user_id(), $person_props );
			}

			// Send the product, session, and user data.
			$event_props['ip']            = edd_get_ip();
			// $event_props['session_id']    = session_id();
			$event_props['product_name']  = get_the_title( $download_id );
			$event_props['product_price'] = edd_get_cart_item_price( $download_id, $options );

			// If subscription (Restrict Content Pro) data exists, send it too.
			if ( function_exists( 'rcp_get_subscription' ) && is_user_logged_in() ) {
				$event_props['subscription'] = rcp_get_subscription( get_current_user_id() );
			}

			// Prepare the label to send to Mixpanel.
			if ( isset( $edd_options['edd_mixpanel_track_added_to_cart_label'] ) ) {
				// Grab the label from EDD Options.
				$label = trim( $edd_options['edd_mixpanel_track_added_to_cart_label'] );
			} else {
				// If we don't have one set, then default.
				$label = 'EDD Added to Cart';
			}

			// Log event in Mixpanel.
			$this->mixpanel->track( $label, $event_props );
		}


		/**
		 * Track when a user completes a purchase.
		 *
		 * @param int    $payment_id Payment ID.
		 * @param string $new_status New payment status.
		 * @param string $old_status Old payment status.
		 */
		public function track_completed_purchase( $payment_id, $new_status, $old_status ) {
			// Store tracked event, person, and product properties.
			$event_props  = array();
			$person_props = array();
			$products     = array();

			// Setup Mixpanel instance.
			$this->set_token();

			// If we failed, bail.
			if ( ! $this->track ) {
				return;
			}

			// Make sure that payments are only completed once.
			if ( 'publish' === $old_status  || 'complete' === $old_status ) {
				return;
			}

			// Make sure the payment completion is only processed when new status is complete.
			if ( 'publish' !== $new_status && 'complete' !== $new_status ) {
				return;
			}

			$user_info = edd_get_payment_meta_user_info( $payment_id );
			$user_id   = edd_get_payment_user_id( $payment_id );
			$downloads = edd_get_payment_meta_cart_details( $payment_id );
			$amount    = edd_get_payment_amount( $payment_id );
			if ( $user_id <= 0 ) {
				$distinct = $user_info['email'];
			} else {
				$distinct = $user_id;
			}
			$person_props['$first_name'] = $user_info['first_name'];
			$person_props['$last_name']  = $user_info['last_name'];
			$person_props['$email']      = $user_info['email'];
			$person_props['ip']          = edd_get_ip();
			$this->mixpanel->people->set( $distinct, $person_props );

			$event_props['distinct_id']   = $distinct;
			$event_props['amount']        = $amount;
			// $event_props['session_id']    = session_id();
			$event_props['purchase_date'] = strtotime( get_post_field( 'post_date', $payment_id ) );

			foreach ( $downloads as $download ) {
				$products[] = get_the_title( $download['id'] );
			}

			$event_props['products'] = implode( ', ', $products );

			// Prepare the label to send to Mixpanel.
			if ( isset( $edd_options['edd_mixpanel_track_completed_purchases_label'] ) ) {
				// Grab the label from EDD Options.
				$label = trim( $edd_options['edd_mixpanel_track_completed_purchases_label'] );
			} else {
				// If we don't have one set, then default.
				$label = 'EDD Completed Purchase';
			}

			// Log event in Mixpanel.
			$this->mixpanel->track( $label, $event_props );

			// Log charge to customer in Mixpanel.
			$this->mixpanel->people->trackCharge( $distinct, $amount );
		}
	}
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Mixpanel
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Mixpanel The one true EDD_Mixpanel
 */
function edd_mixpanel_load() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
		if ( ! class_exists( 'EDD_Extension_Activation' ) ) {
			include_once 'includes/class.extension-activation.php';
		}

		$activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
		$activation->run();
	} else {
		return EDD_Mixpanel::instance();
	}

	return null;
}

add_action( 'plugins_loaded', 'edd_mixpanel_load' );
