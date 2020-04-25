<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpize;
use JsPhpize\JsPhpizePhug;
use PHPUnit\Framework\TestCase;
use Phug\Compiler;
use Phug\CompilerEvent;
use Phug\Renderer;
use Tests\Thrower;

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
        $exception = null;

        try {
            eval('?>' . $php);
        } catch (\Exception $exp) {
            $exception = $exp->getMessage();
        } catch (\Throwable $exp) {
            $exception = $exp->getMessage();
        }

        $html = ob_get_contents();
        ob_end_clean();
        self::assertNull($exception, "`$php` threw an exception:\n$exception");
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

    public function testNamespaceInsertion()
    {
        include_once __DIR__ . '/Example/Example.php';

        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);
        $compiler->attach(CompilerEvent::OUTPUT, function (Compiler\Event\OutputEvent $outputEvent) {
            $outputEvent->prependCode('namespace Tests\JsPhpize\Example;');
        });

        $user = [
            'name' => 'Bob',
        ];

        ob_start();
        $php = $compiler->compile('a(title=user.name foo=Example::foo())');
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();

        self::assertSame(
            '<a title="Bob" foo="bar"></a>',
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

        $php7Syntax = '$GLOBALS[\'__jpv_dotWithArrayPrototype_with_ref\']($items, ' .
            '\'forEach\')(function ($item) {';
        $php5Syntax = 'call_user_func(call_user_func($GLOBALS[\'__jpv_dotWithArrayPrototype\'], $items, ' .
            '\'forEach\'), function ($item) {';
        $actual = $jsPhpize('items.forEach(function (item) {');

        self::assertContains(
            $jsPhpize('items.forEach(function (item) {'),
            [$php5Syntax, $php7Syntax],
            "`items.forEach(function (item) {` should compile into rather `$php5Syntax` or `$php7Syntax`, but ir compile into `$actual`"
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

    public function testCodeKeptAsIt()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        $jsPhpize = $compiler->getFormatter()->getOption(['patterns', 'transform_expression']);

        self::assertSame(
            'Layout::title()',
            $jsPhpize('Layout::title()')
        );
    }

    public function testRenderer()
    {
        if (!class_exists(Renderer::class)) {
            include_once __DIR__ . '/Feature/Renderer.php';
        }

        $renderer = new Renderer([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        self::assertInstanceOf(Renderer::class, $renderer);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Unhandled
     */
    public function testUnhandledException()
    {
        if (!class_exists(Thrower::class)) {
            include_once __DIR__ . '/Feature/Thrower.php';
        }

        $adapter = new JsPhpizePhug(new Compiler());

        $adapter->compile(new Thrower(), 'foobar', 'foobar');
    }
}
