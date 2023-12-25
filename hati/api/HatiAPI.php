<?php

namespace hati\api;

use hati\util\Request;
use RuntimeException;

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
	 * Fetches the request body as either JSON or raw text.
	 *
	 * @param string $as The format the request body to be fetched in.
	 * @return string|array|null array for JSON type, string for raw type.
	 * Otherwise null is returned.
	 * */
	protected final function requestBody(string $as = 'json'): string|array|null {

		if (!in_array($as, ['json', 'raw'])) {
			throw new RuntimeException('Request body can only be fetched either as json or as raw value');
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
		throw new RuntimeException('API is not implemented yet');
	}

	/**
	 * Default handler method for GET request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function post(array $args, array $params): void {
		throw new RuntimeException('API is not implemented yet');
	}

	/**
	 * Default handler method for POST request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function put(array $args, array $params): void {
		throw new RuntimeException('API is not implemented yet');
	}

	/**
	 * Default handler method for PATCH request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function patch(array $args, array $params): void {
		throw new RuntimeException('API is not implemented yet');
	}

	/**
	 * Default handler method for DELETE request for the API.
	 *
	 * @param array $args containing arguments found after the API path
	 * @param array $params containing query parameters found in the API path
	 * @noinspection PhpUnusedParameterInspection
	 * */
	public function delete(array $args, array $params): void {
		throw new RuntimeException('API is not implemented yet');
	}

}