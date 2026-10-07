<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Fixtures;

use Netivo\Core\Theme;

/**
 * Minimal concrete Theme subclass, only so Theme's protected methods can be tested;
 * instances are always created via ReflectionClass::newInstanceWithoutConstructor().
 */
class ConcreteTheme extends Theme {

	protected function init(): void {
	}
}
