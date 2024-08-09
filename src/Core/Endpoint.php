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
 * Class Endpoint
 *
 * Abstract class to create custom endpoint in WordPress.
 */
abstract class Endpoint
{
    /**
     * Name of the endpoint. It is name of query var.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Type of endpoint. One of: template, action
     * template - endpoint will load custom template
     * action - endpoint will do action and exit
     *
     * @var string
     */
    protected string $type = 'template';

    /**
     * Endpoint mask describing the places the endpoint should be added.
     *
     * @var int
     */
    protected int $place = EP_NONE;

    /**
     * Template name to load. Works only when type is "template".
     *
     * @var string
     */
    protected string $template = '';

    /**
     * Endpoint constructor.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_endpoint' ] );
        add_action( 'template_redirect', [ $this, 'redirect_template' ] );
    }

    /**
     * Registers endpoint with specified name
     */
    public function register_endpoint(): void
    {
        add_rewrite_endpoint( $this->name, $this->place );
    }

    /**
     * Redirect template or do action, concerning the endpoint type.
     */
    public function redirect_template(): void
    {
        if ( get_query_var( $this->name ) ) {
            if ( $this->type == 'template' ) {
                locate_template( $this->template, true );
                exit();

            } elseif ( $this->type == 'action' ) {

                $this->doAction( get_query_var( $this->name ) );

                exit();
            }
        }
    }

    /**
     * Action to be done.
     *
     * @param mixed $var Query variable data.
     */
    abstract public function doAction( mixed $var ): void;

}