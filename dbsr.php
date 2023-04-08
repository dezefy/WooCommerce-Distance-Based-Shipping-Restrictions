<?php
/*
Plugin Name: Distance-Based Shipping Restrictions
Plugin URI: https://dezefy.com/products/wordpress-plugins/woocommerce-distance-based-shipping-restrictions
Description: Blocks orders if the distance between the store location and the user's shipping location is more than a specified maximum distance.
Version: 1.0
Author: Dezefy
Author URI: https://dezefy.com/
*/

defined( 'ABSPATH' ) or die( 'No direct access allowed' );

// Add settings page to store plugin settings
add_action( 'admin_menu', 'dbsr_settings_page' );
function dbsr_settings_page() {
  add_options_page( 'WooCommerce Distance-Based Shipping Restrictions', 'WooCommerce Distance-Based Shipping Restrictions', 'manage_options', 'dbsr-settings', 'dbsr_settings_page_callback' );
}

function dbsr_settings_page_callback() {
  ?>
  <div class="wrap">
    <h2>WooCommerce Distance-Based Shipping Restrictions Settings</h2>
    <form method="post" action="options.php">
      <?php settings_fields( 'dbsr-settings-group' ); ?>
      <?php do_settings_sections( 'dbsr-settings-group' ); ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Google Maps API Key</th>
          <td><input type="text" name="dbsr_api_key" value="<?php echo esc_attr( get_option( 'dbsr_api_key' ) ); ?>" /></td>
        </tr>
        <tr valign="top">
          <th scope="row">Maximum Distance (in kilometers)</th>
          <td><input type="number" name="dbsr_max_distance" min="0" value="<?php echo esc_attr( get_option( 'dbsr_max_distance', 2 ) ); ?>" /></td>
        </tr>
        <tr valign="top">
          <th scope="row">Notice Message</th>
          <td><input type="text" name="dbsr_notice_message" value="<?php echo esc_attr( get_option( 'dbsr_notice_message', 'Sorry, we cannot deliver to this location as it is more than {distance} kilometers away from our store.' ) ); ?>" /></td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

// Register plugin settings
add_action( 'admin_init', 'dbsr_register_settings' );
function dbsr_register_settings() {
  register_setting( 'dbsr-settings-group', 'dbsr_api_key' );
  register_setting( 'dbsr-settings-group', 'dbsr_max_distance', 'intval' );
  register_setting( 'dbsr-settings-group', 'dbsr_notice_message' );
}

// Check distance and block order
add_action( 'woocommerce_checkout_process', 'dbsr_check_distance_and_block_order' );

add_shortcode('testdbsr', 'dbsr_check_distance_and_block_order');
function dbsr_check_distance_and_block_order() {
  global $woocommerce;
  $store_address = get_option( 'woocommerce_store_address' ); // get store address from WooCommerce settings
  $store_city = get_option( 'woocommerce_store_city' ); // get store city from WooCommerce settings
  $store_state = get_option( 'woocommerce_store_state' ); // get store state from WooCommerce settings
  $store_postcode = get_option( 'woocommerce_store_postcode' ); // get store postcode from WooCommerce settings
  $store_country = get_option( 'woocommerce_default_country' ); // get store country from WooCommerce settings
  $store_location = urlencode( "{$store_address}, {$store_city}, {$store_state} {$store_postcode}, {$store_country}" ); // encode store location for Google Maps API

 

 
  $user_shipping_address_1 = WC()->customer->get_shipping_address_1();
  $user_shipping_address_2 = WC()->customer->get_shipping_address_2();
  $user_shipping_city = WC()->customer->get_shipping_city();
  $user_shipping_state = WC()->customer->get_shipping_state();
  $user_shipping_postcode = WC()->customer->get_shipping_postcode();
  $user_shipping_country = WC()->customer->get_shipping_country();
  $user_shipping_address = urlencode( "{$user_shipping_address_1}, {$user_shipping_address_2}, {$user_shipping_city}, {$user_shipping_state} {$user_shipping_postcode}, {$user_shipping_country}" ); // encode user's shipping address for Google Maps API


  
  $google_maps_api_key = get_option( 'dbsr_api_key' ); // get Google Maps API key from plugin settings
  $max_distance = get_option( 'dbsr_max_distance', 2 ) * 1000; // convert kilometers to meters
  $notice_message = get_option( 'dbsr_notice_message', 'Sorry, we cannot deliver to this location as it is more than {distance} kilometers away from our store.' );
  
  // make API call to get distance between the two locations
  $url = "https://maps.googleapis.com/maps/api/distancematrix/json?units=metric&origins={$store_location}&destinations={$user_shipping_address}&key={$google_maps_api_key}";
  $response = wp_remote_get( $url );
  $data = json_decode( wp_remote_retrieve_body( $response ) );

  

  // check if distance is more than the maximum distance
  $distance = $data->rows[0]->elements[0]->distance->value;

  if ( $distance > $max_distance ) {
    $message = str_replace( '{distance}', number_format( $distance / 1000, 1 ), $notice_message );
    wc_add_notice( $message, 'error' );
    return false; // block order
  }

  return true; // allow order
}
