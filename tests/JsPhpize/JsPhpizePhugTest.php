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

        $compiler = new Compiler([
            'modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $other = array('message' => 'Bye');
        $php = $compiler->compile('a(data-foo={message: other.message})');
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<a data-foo="{&quot;message&quot;:&quot;Bye&quot;}"></a>',
            $html
        );

        $code = preg_replace('/\n +`/', "\n", '
            `-
            `    item = "uno";
            `    string = item.charAt(0)
            `    
            `      .toUpperCase() +
            `    item.slice(1);
            `p=string');

        ob_start();
        $php = $compiler->compile($code);
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<p>Uno</p>',
            $html
        );

        $compiler = new Compiler([
            'modules' => [JsPhpizePhug::class],
        ]);

        self::assertSame(
            '<a foo="<?= (is_array($_pug_temp = $foo) || (is_object($_pug_temp) && !method_exists($_pug_temp, "__toString")) ? json_encode($_pug_temp) : strval($_pug_temp)) ?>"></a>',
            $compiler->compile('a(foo?!=foo)')
        );

        $compiler = new Compiler([
            'modules' => [JsPhpizePhug::class],
        ]);

        self::assertSame(
            '<a foo="<?= (is_array($_pug_temp = array("foo" => "bar")[\'foo\']) || (is_object($_pug_temp) && !method_exists($_pug_temp, "__toString")) ? json_encode($_pug_temp) : strval($_pug_temp)) ?>"></a>',
            $compiler->compile('a(foo?!=array("foo" => "bar")[\'foo\'])')
        );
    }
}
