<?php
namespace App\Output;

class Greeter {
	public function go(string $name):void {
		echo "Hello, $name!", PHP_EOL;
		echo "This message was rendered from App\\Output\\Greeter::go()", PHP_EOL;
	}
}
