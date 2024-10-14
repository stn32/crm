<?php


/**
 * v.1.8
 * get crm access token
 */
function get_crm_access_token() {
	$url = 'https://api.moysklad.ru/api/remap/1.2/security/token';
	$credentials = base64_encode('admin@394894839:dsKo-73847387348'); // Base64-encoded login:password
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
// Example usage in your product page
add_action('woocommerce_single_product_summary', function() {
	$token = get_crm_access_token();
	echo '<p>CRM Access Token: ' . esc_html($token) . '</p>';
	echo '<p id="stock-info">stock-info:</p>';
});





/**
 * v.1.8
 * Get the stock data from CRM for a WooCommerce variation by SKU using quantityMode.
 */
function get_crm_stock_data_for_variation($sku) {
	// Get the access token from CRM
	$access_token = get_crm_access_token();

	if (strpos($access_token, 'Error') !== false) {
			return $access_token;  // Return the error if token retrieval fails
	}

	// CRM API endpoint to search for product stock by externalCode (using SKU as article)
	$stock_api_url = 'https://api.moysklad.ru/api/remap/1.2/report/stock/bystore?filter=externalCode=' . urlencode($sku) . '&quantityMode=positiveOnly';

	// Make the GET request to the CRM API
	$response = wp_remote_get($stock_api_url, array(
			'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
			),
	));

	// Handle errors in the API request
	if (is_wp_error($response)) {
			return 'Error: ' . $response->get_error_message();
	}

	// Retrieve and decode the response body
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	// Check if stock data is returned
	if (isset($data['rows']) && !empty($data['rows'])) {
			return $data['rows'];  // Assuming 'rows' contains stock information per store
	}

	return 'no_stock';  // Return 'no_stock' if no positive balance found
}





/**
 * v.1.8
 * display the stock information on the product page
 */
add_action('woocommerce_single_product_summary', 'display_variation_stock_per_store', 30);

function display_variation_stock_per_store() {
    global $product;

    // Ensure we are dealing with a variable product
    if ($product->is_type('variable')) {
        // Get available variations
        $available_variations = $product->get_available_variations();

        echo '<h3>Stock Information for Variations:</h3>';
        foreach ($available_variations as $variation) {
            $variation_id = $variation['variation_id'];
            $variation_sku = $variation['sku'];

            // Check if SKU is set for this variation
            if (empty($variation_sku)) {
                echo '<p>No SKU assigned for this variation (ID: ' . $variation_id . ').</p>';
                continue;
            }

            // Fetch stock data for this variation SKU from CRM using quantityMode
            $stock_data = get_crm_stock_data_for_variation($variation_sku);

            // If stock data is not found, display "No"
            if ($stock_data === 'no_stock') {
                echo '<p>Variation SKU: ' . esc_html($variation_sku) . ' - In Stock: <strong>No</strong></p>';
            } elseif (is_string($stock_data) && strpos($stock_data, 'Error') !== false) {
                // If there's an error, display it
                echo '<p>' . esc_html($stock_data) . '</p>';
            } else {
                // Display "Yes" if stock data is available
                echo '<p>Variation SKU: ' . esc_html($variation_sku) . ' - In Stock: <strong>Yes</strong></p>';
            }
        }
    }
}


?>