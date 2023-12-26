<?php

namespace hati\api;

use hati\Trunk;
use hati\util\Request;

/**
 * An abstract implementation for APIs. Hati APIs must implement this class so that
 * the Hati API handler can invoke correct methods to server the API request. It
 * provides command useful methods needed while writing API implementations.
 *
 * @since 5.0.0
 * */

abstract class HatiAPI {

	/** Catches the request body for JSON & raw */
	private array $reqBody = [];

	/**
	 * Prevent direct access to the API handler file by checking whether
	 * the HATI_API_CALL constant was defined by the hati_api_handler.php
	 * file.
	 *
	 * This method should be the first call in the API handler files to
	 * prevent direct access.
	 * */
	public static function noDirectAccess(): void {
		if (!defined('HATI_API_CALL')) {
			$trunk = Trunk::error403('No direct access');
			$trunk -> report();
		}
	}

	/**
	 * Fetches the request body as either JSON or raw text.
	 *
	 * @param string $as The format the request body to be fetched in.
	 * @return string|array|null array for JSON type, string for raw type.
	 * Otherwise null is returned.
	 * */
	protected final function requestBody(string $as = 'json'): string|array|null {

		if (!in_array($as, ['json', 'raw'])) {
			throw Trunk::error400('Request body can only be fetched either as json or as raw value');
		}

		if (array_key_exists($as, $this -> reqBody)) {
			return $this -> reqBody[$as];
		}

		$data = Request::body($as);
		$this -> reqBody[$as] = $data;

		return $data;
	}

	/**
	 * Default handler method for GET request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function get(array $args, array $params): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for GET request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function post(array $args, array $params): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for POST request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function put(array $args, array $params): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for PATCH request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function patch(array $args, array $params): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for DELETE request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function delete(array $args, array $params): void {
		throw Trunk::error501('API is not implemented yet');
	}

}