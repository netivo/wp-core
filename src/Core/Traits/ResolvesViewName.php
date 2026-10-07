<?php
/**
 * Created by Netivo for wp-core
 *
 * @package Netivo\Core\Traits
 */

namespace Netivo\Core\Traits;

use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Trait ResolvesViewName
 *
 * Shared by Page, MetaBox, TermMeta and Gutenberg: each resolves its view/block name either
 * from a one-argument attribute (e.g. `Netivo\Attributes\View`, `Netivo\Attributes\Block`) on
 * the subclass, or by falling back to the subclass's own lower-cased file name.
 */
trait ResolvesViewName {

	/**
	 * Reads a one-argument attribute's value off the current instance.
	 *
	 * @param string $attribute_class Fully-qualified attribute class name to look for.
	 *
	 * @return string|null The attribute's first argument, or null if the attribute isn't present.
	 */
	protected function resolve_view_attribute( string $attribute_class ): ?string {
		$obj = new ReflectionClass( $this );
		foreach ( $obj->getAttributes() as $attribute ) {
			if ( $attribute->getName() === $attribute_class ) {
				return $attribute->getArguments()[0];
			}
		}

		return null;
	}

	/**
	 * Lower-cased basename of the current instance's own file, used as the default
	 * view/block name when no attribute is present.
	 *
	 * @return string
	 */
	protected function resolve_view_name_from_filename(): string {
		$obj      = new ReflectionClass( $this );
		$filename = str_replace( '.php', '', $obj->getFileName() );

		return strtolower( basename( $filename ) );
	}
}
