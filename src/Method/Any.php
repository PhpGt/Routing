<?php
namespace Gt\Routing\Method;

use Attribute;
use Gt\Routing\HttpRoute;

/** @codeCoverageIgnore  */
#[Attribute]
class Any extends HttpRouteMethod {
	public function __construct(
		?string $path = null,
		?string $function = null,
		?string $name = null,
		?string $accept = null
	) {
		parent::__construct(
			HttpRoute::METHODS_ALL,
			$path,
			$function,
			$name,
			$accept,
		);
	}
}
