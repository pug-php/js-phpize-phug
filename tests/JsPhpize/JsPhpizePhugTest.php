<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpize;
use JsPhpize\JsPhpizePhug;
use PHPUnit\Framework\TestCase;
use Phug\Compiler;

class JsPhpizePhugTest extends TestCase
{
    public function testPlug()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $php = $compiler->compile('a(data-foo={message: "Hello"})');
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();

        self::assertSame(
            '<a data-foo="{&quot;message&quot;:&quot;Hello&quot;}"></a>',
            $html
        );

        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
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
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $foo = '<a>" b="b';
        $php = $compiler->compile('a(foo?!=foo)');
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<a foo="<a>" b="b"></a>',
            $html
        );

        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $php = $compiler->compile('a(foo=array("foo" => "bar")[\'foo\'])');
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<a foo="bar"></a>',
            $html
        );
    }

    public function testTruncatedCode()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        $compiler->setOption('jsphpize_engine', new JsPhpize([
            'allowTruncatedParentheses' => true,
            'catchDependencies' => true,
            'helpers' => [
                'dot' => 'dotWithArrayPrototype',
            ],
        ]));

        $jsPhpize = $compiler->getFormatter()->getOption(['patterns', 'transform_expression']);

        self::assertSame(
            'call_user_func(call_user_func($GLOBALS[\'__jpv_dotWithArrayPrototype\'], $items, ' .
            '\'forEach\'), function ($item) {',
            $jsPhpize('items.forEach(function (item) {')
        );

        self::assertSame(
            '',
            $jsPhpize('')
        );

        self::assertSame(
            '}',
            $jsPhpize('}')
        );

        self::assertSame(
            'if (false)',
            $jsPhpize('if (false) {}')
        );

        self::assertSame(
            'if (\'works\')',
            $jsPhpize('if (\'works\')')
        );

        self::assertSame(
            '} else {',
            $jsPhpize('} else {')
        );
    }
}
