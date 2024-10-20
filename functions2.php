<?php

/**
 * v.2.1
 * Get CRM access token.
 */
function get_crm_access_token() {
	$url = 'https://api.moysklad.ru/api/remap/1.2/security/token';
	$credentials = base64_encode('user@company:password'); // Base64-encoded login:password
	$response = wp_remote_post($url, array(
			'headers' => array(
					'Authorization' => 'Basic ' . $credentials,
					'Content-Type'  => 'application/json',
			),
	));
	if (is_wp_error($response)) {
			return 'Error: ' . $response->get_error_message();
	}
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body);

	if (isset($data->access_token)) {
			return $data->access_token;
	} else {
			return 'Error: Unable to retrieve access token.';
	}
}


/**
 * v.2.1
 * Fetch all product variations (modifications) from CRM.
 */
function get_all_crm_variations() {
	$access_token = get_crm_access_token();

	if (strpos($access_token, 'Error') !== false) {
			return $access_token;  // Return the error if token retrieval fails
	}

	$variant_api_url = 'https://api.moysklad.ru/api/remap/1.2/entity/variant';

	$response = wp_remote_get($variant_api_url, array(
			'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
			),
	));

	if (is_wp_error($response)) {
			return 'Error: ' . $response->get_error_message();
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if (isset($data['rows']) && !empty($data['rows'])) {
			return $data['rows'];
	}

	return 'Error: No variation data found.';
}


/**
 * v.2.1
 * Fetch stock information for a specific product variation using a filter.
 */
function get_stock_by_variant_bystore($variant_id) {
	$access_token = get_crm_access_token();

	if (strpos($access_token, 'Error') !== false) {
			return $access_token;  // Return the error if token retrieval fails
	}

	$variant_href = "https://api.moysklad.ru/api/remap/1.2/entity/variant/" . $variant_id;
	$stock_api_url = "https://api.moysklad.ru/api/remap/1.2/report/stock/bystore?filter=variant=" . urlencode($variant_href);

	$response = wp_remote_get($stock_api_url, array(
			'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
			),
	));

	if (is_wp_error($response)) {
			return 'Error: ' . $response->get_error_message();
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if (isset($data['rows'][0]['stockByStore']) && !empty($data['rows'][0]['stockByStore'])) {
			return $data['rows'][0]['stockByStore'];
	}

	return 'Error: No stock data found.';
}


/**
 * v.2.1
 * Save or update stock data for multiple stores in the database.
 */
function save_stock_to_database($variation_data, $store_stock_info) {
	global $wpdb;

	// Initialize stock values for MSK and SPB stores
	$stock_msk = 0;
	$stock_spb = 0;

	// Loop through stock information from the API
	foreach ($store_stock_info as $stock) {
			// Check for MSK store (ID: 94f319ff-87ad-11ef-0a80-654562313213)
			if ($stock['meta']['href'] === 'https://api.moysklad.ru/api/remap/1.2/entity/store/94f319ff-87ad-11ef-0a80-654562313213') {
					$stock_msk = isset($stock['stock']) ? (int)$stock['stock'] : 0;
			}
			// Check for SPB store (ID: af861619-8a2b-11ef-0a80-78965we4434442)
			if ($stock['meta']['href'] === 'https://api.moysklad.ru/api/remap/1.2/entity/store/af861619-8a2b-11ef-0a80-78965we4434442') {
					$stock_spb = isset($stock['stock']) ? (int)$stock['stock'] : 0;
			}
	}

	// Check if product exists in the database
	$existing_product = $wpdb->get_row(
			$wpdb->prepare(
					"SELECT * FROM var_stock_ms WHERE pro_ex_code = %s",
					$variation_data['externalCode']
			)
	);

	// Prepare the data for insertion or update
	$data = array(
			'pro_name' => $variation_data['name'], // Product name
			'pro_id' => $variation_data['id'], // WooCommerce product ID
			'pro_ex_code' => $variation_data['externalCode'], // External code from CRM
			'stock_msk' => $stock_msk, // Stock for MSK store
			'stock_spb' => $stock_spb // Stock for SPB store
	);

	// If the product already exists, update it; otherwise, insert it
	if ($existing_product) {
			// Update the existing product in the database
			$wpdb->update(
					'var_stock_ms',
					$data,
					array('pro_ex_code' => $variation_data['externalCode']) // Where clause
			);
	} else {
			// Insert a new product into the database
			$wpdb->insert('var_stock_ms', $data);
	}
}

?>
