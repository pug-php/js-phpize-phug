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

        self::assertSame(
            '<a data-foo="<?= htmlspecialchars((is_array($_pug_temp = array( \'message\' => "Hello" )) || (is_object($_pug_temp) && !method_exists($_pug_temp, "__toString")) ? json_encode($_pug_temp) : strval($_pug_temp))) ?>"></a>',
            $compiler->compile('a(data-foo={message: "Hello"})')
        );

        self::assertSame(
            '<a foo="<?= (is_array($_pug_temp = $foo) || (is_object($_pug_temp) && !method_exists($_pug_temp, "__toString")) ? json_encode($_pug_temp) : strval($_pug_temp)) ?>"></a>',
            $compiler->compile('a(foo?!=foo)')
        );

        self::assertSame(
            '<a foo="<?= (is_array($_pug_temp = array("foo" => "bar")[\'foo\']) || (is_object($_pug_temp) && !method_exists($_pug_temp, "__toString")) ? json_encode($_pug_temp) : strval($_pug_temp)) ?>"></a>',
            $compiler->compile('a(foo?!=array("foo" => "bar")[\'foo\'])')
        );
    }
}
