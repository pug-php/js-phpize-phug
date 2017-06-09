<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpizePhug;
use Phug\Compiler;

class JsPhpizePhugTest extends \PHPUnit_Framework_TestCase
{
    public function testPlug()
    {
        $compiler = new Compiler([
            'modules' => [JsPhpizePhug::class],
        ]);

        self::assertSame('array()', $compiler->compile('a(data-foo={message: "Hello"})'));
    }
}
