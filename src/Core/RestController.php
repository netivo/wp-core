<?php
/**
 * Created by Netivo for Netivo Core Package.
 * User: Michal
 * Date: 23.10.2018
 * Time: 15:14
 *
 * @package Netivo\Core
 */

namespace Netivo\Core;

if ( ! defined( 'ABSPATH' ) ) {
    header( 'HTTP/1.0 403 Forbidden' );
    exit;
}

/**
 * Class RestController
 *
 * Abstract class to create Rest routes in WordPress.
 */
abstract class RestController {

	/**
	 * Namespace of Rest Endpoint
	 *
	 * @var string
	 */
	protected string $namespace = 'netivo';

	/**
	 * Base of Rest endpoint
	 *
	 * @var string
	 */
	protected string $base = '';

	/**
	 * Version of the Rest endpoint
	 *
	 * @var string
	 */
	protected string $version = 'v1';

	/**
	 * RestController constructor.
	 *
	 * @param bool $auto_register Whether to register WordPress hooks immediately. Pass
	 *                             false to construct the instance without touching any
	 *                             hook, then call register() explicitly when ready.
	 *                             Defaults to true to match the previous behaviour.
	 */
	public function __construct( bool $auto_register = true )
	{
		if ( $auto_register ) {
			$this->register();
		}
	}

	/**
	 * Registers this controller's WordPress hooks (hooks register_routes() to
	 * rest_api_init). Called automatically from the constructor unless it was built
	 * with $auto_register = false.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Abstract method to register routes, using method build_route or register_rest_route
	 *
	 * @return void
	 */
	public abstract function register_routes(): void;

	/**
	 * Registers single route in Rest with specified params.
	 *
	 * @param $callback mixed Callback function
	 * @param $method string Endpoint HTTP method
	 * @param $permission mixed Permission callback
	 * @param $params string Route params
	 *
	 * @return void
	 */
	protected function build_route(mixed $callback, string $method = 'GET', mixed $permission = '__return_true', string $params = ''): void {
		register_rest_route(
			$this->namespace . '/' . $this->version,
			'/'.$this->base.'/'.$params,
			array(
				array(
					'methods' => $method,
					'callback' => $callback,
					'permission_callback' => $permission
				)
			)
		);
	}
}