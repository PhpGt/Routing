<?php
namespace Gt\Routing\Test\Path;

use Gt\Routing\Assembly;
use Gt\Routing\Path\DynamicPath;
use PHPUnit\Framework\TestCase;

class DynamicPathTest extends TestCase {
	public function testGet_noAssembly():void {
		$sut = new DynamicPath("/");
		self::assertNull($sut->get("something"));
	}

	public function testGet_withAssembly_noMatch():void {
		$assembly = self::createMock(Assembly::class);
		$assembly->method("current")
			->willReturnOnConsecutiveCalls(
				"page/@dynamic/something.html",
				"page/@dynamic/something-else.html"
			);
		$assembly->method("valid")
			->willReturn(
				true,
				true,
				false
			);
		$sut = new DynamicPath("/", $assembly);
		self::assertNull($sut->get("dynamic"));
	}

	public function testGet_byKey():void {
		$assembly = self::createMock(Assembly::class);
		$assembly->method("current")
			->willReturnOnConsecutiveCalls(
				"page/shop/_common.php",
				"page/shop/@category/@itemName.php",
				"page/shop/_common.php",
				"page/shop/@category/@itemName.php",
			);
		$assembly->method("valid")
			->willReturn(true);
		$sut = new DynamicPath("/shop/OnePlus/6T", $assembly);
		self::assertEquals("OnePlus", $sut->get("category"));
		self::assertEquals("6T", $sut->get("itemName"));
	}

	public function testGet_noKey_shouldReturnDeepest():void {
		$assembly = self::createMock(Assembly::class);
		$assembly->method("current")
			->willReturnOnConsecutiveCalls(
				"page/shop/_common.php",
				"page/shop/@category/@itemName.php",
				"page/shop/_common.php",
				"page/shop/@category/@itemName.php",
			);
		$assembly->method("valid")
			->willReturn(true);
		$sut = new DynamicPath("/shop/OnePlus/6T", $assembly);
		self::assertEquals("6T", $sut->get());
	}
}
