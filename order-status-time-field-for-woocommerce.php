<?php
/**
 * Plugin Name: Order Status Time Field for WooCommerce
 * Description: Records the time of an order status change as an order meta field for reporting purposes.
 * Version: 1.0.0
 * Author: Potent Plugins
 * Author URI: http://potentplugins.com/?utm_source=order-status-time-field-for-woocommerce&utm_medium=link&utm_campaign=wp-plugin-author-uri
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */

if (!defined('ABSPATH'))
	die();

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'pp_wcostf_action_links');
function pp_wcostf_action_links($links) {
	array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'admin.php?page=pp_wcostf')).'">Settings</a>');
	return $links;
}
	
add_action('init', 'pp_wcostf_init');
function pp_wcostf_init() {
	$settings = get_option('pp_wcostf_settings', array('statuses_on' => array('wc-completed' => array(1, 'completed_time')), 'statuses_off' => array()));
	foreach ($settings['statuses_on'] as $orderStatus => $fieldName) {
		add_action('woocommerce_order_status_'.(substr($orderStatus, 0, 3) == 'wc-' ? substr($orderStatus, 3) : $orderStatus), 'pp_wcostf_status_changed');
	}
}

function pp_wcostf_status_changed($orderId) {
	$settings = get_option('pp_wcostf_settings', array('statuses_on' => array('wc-completed' => array(1, 'completed_time')), 'statuses_off' => array()));
	$orderStatus = get_post_status($orderId);
	if (!empty($settings['statuses_on'][$orderStatus])) {
		update_post_meta($orderId, $settings['statuses_on'][$orderStatus], current_time('Y-m-d H:i:s'));
	}
}

add_action('admin_menu', 'pp_wcostf_admin_menu');
function pp_wcostf_admin_menu() {
	add_submenu_page('woocommerce', 'Order Status Time Field', 'Order Status Time Field', 'manage_woocommerce', 'pp_wcostf', 'pp_wcostf_page');
}


function pp_wcostf_page() {
	
	// Print header
	echo('
		<div class="wrap">
			<h2>Order Status Time Field for WooCommerce</h2>
			<style scoped>
				p {
					max-width: 600px;
				}
				table {
					margin-bottom: 10px;
				}
				td, th {
					padding: 5px 10px;
				}
				.alignleft {
					text-align: left;
				}
				.aligncenter {
					text-align: center;
				}
				form {
					padding-bottom: 50px;
				}
			</style>
	');
	
	// Check for WooCommerce
	if (!class_exists('WooCommerce')) {
		echo('<div class="error"><p>This plugin requires that WooCommerce is installed and activated.</p></div></div>');
		return;
	} else if (!function_exists('wc_get_order_statuses')) {
		echo('<div class="error"><p>This plugin requires WooCommerce 2.2 or higher. Please update your WooCommerce install.</p></div></div>');
		return;
	}
	
	$orderStatuses = wc_get_order_statuses();
	
	if (empty($_POST)) {
		$settings = get_option('pp_wcostf_settings', array('statuses_on' => array('wc-completed' => array(1, 'completed_time')), 'statuses_off' => array()));
	} else {
		check_admin_referer('pp_wcostf_settings_save');
		$settings = array('statuses_on' => array(), 'statuses_off' => array());
		foreach ($orderStatuses as $orderStatusId => $orderStatusName) {
			$settings[empty($_POST[$orderStatusId.'_on']) ? 'statuses_off' : 'statuses_on'][$orderStatusId] = (empty($_POST[$orderStatusId.'_name']) ? (substr($orderStatusId, 0, 3) == 'wc-' ? substr($orderStatusId, 3) : $orderStatusId).'_time' : $_POST[$orderStatusId.'_name']);
		}
		update_option('pp_wcostf_settings', $settings);
	}
	$statusFields = array_merge($settings['statuses_on'], $settings['statuses_off']);
	
	echo('
		<p>This plugin records the date and time of an order\'s most recent change to a specific status in an order meta field for reporting purposes. To enable this functionality for a particular order status, select the corresponding checkbox and (optionally) customize the name of the meta field where the date and time will be saved. To report on this data, check out <a href="https://potentplugins.com/downloads/export-order-items-pro-wordpress-plugin/?utm_source=order-status-time-field-for-woocommerce&utm_medium=link&utm_campaign=wp-plugin-referral" target="_blank">Export Order Items Pro</a>.</p>
		<p>Format: '.current_time('Y-m-d H:i:s').'</p>
		
		<form action="" method="post">
			<table>
				<thead>
					<tr><th class="aligncenter">Record?</th><th class="alignleft">Order Status</th><th style="text-align: left;">Status Time Field Name</th></tr>
				</thead>
				<tbody>
	');
	
	foreach ($orderStatuses as $orderStatusId => $orderStatusName) {
		echo('<tr>
				<td class="aligncenter"><input type="checkbox" name="'.$orderStatusId.'_on" value="1"'.(isset($settings['statuses_on'][$orderStatusId]) ? ' checked="checked"' : '').' /></td>
				<td>'.htmlspecialchars($orderStatusName).'</td>
				<td><input type="text" name="'.$orderStatusId.'_name" value="'.(empty($statusFields[$orderStatusId]) ? (substr($orderStatusId, 0, 3) == 'wc-' ? substr($orderStatusId, 3) : $orderStatusId).'_time' : $statusFields[$orderStatusId]).'" /></td>
			</tr>');
	}
	echo('		</tbody>
			</table>
	');
	wp_nonce_field('pp_wcostf_settings_save');
	echo('
			<button type="submit" class="button-primary">Save Changes</button>
		</form>');
		
	$potent_slug = 'order-status-time-field-for-woocommerce';
	include(__DIR__.'/plugin-credit.php');
	
	echo('</div>');
}

register_activation_hook(__FILE__, 'pp_wcostf_activate');
function pp_wcostf_activate() {
	$settings = get_option('pp_wcostf_settings', false);
	if ($settings !== false) {
		if (isset($settings['_inactive']))
			unset($settings['_inactive']);
		update_option('pp_wcostf_settings', $settings, true);
	}
}

register_deactivation_hook(__FILE__, 'pp_wcostf_deactivate');
function pp_wcostf_deactivate() {
	$settings = get_option('pp_wcostf_settings', false);
	if ($settings !== false) {
		$settings['_inactive'] = 1;
		update_option('pp_wcostf_settings', $settings, false);
	}
}

?>