<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpizePhug;
use JsPhpize\JsPhpizePhugFormatter;
use Phug\Compiler;
use Phug\Formatter;

class JsPhpizePhugTest extends \PHPUnit_Framework_TestCase
{
    public function testPlug()
    {
        $compiler = new Compiler([
            'modules' => [JsPhpizePhug::class],
        ]);

        self::assertSame('array()', $compiler->compile('a(data-foo={message: "Hello"})'));
    }

    public function testDependencyStorageFixer()
    {
        $formatter = new Formatter([
            'modules' => [JsPhpizePhugFormatter::class],
        ]);
        $getter = $formatter->getOption('dependencies_storage_getter');

        self::assertSame('foo', $getter('  foo'));
        self::assertSame('foo', $getter('  $foo'));
    }
}
