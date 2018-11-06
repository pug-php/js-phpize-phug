<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpizePhug;
use PHPUnit\Framework\TestCase;
use Phug\Compiler;

class JsPhpCommentsTest extends TestCase
{
    public function testLanguageKeyWord()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $a = '5';
        $b = '6';
        $php = $compiler->compile(implode("\n", [
            '//- @language js',
            'p=a + b',
            '//- @language php',
            'p=$a + $b',
            '//- @language js',
            'p=a + b',
            '//- @language php',
            'p=$a + $b',
        ]));
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<p>56</p><p>11</p><p>56</p><p>11</p>',
            $html
        );
    }

    public function testNodeLanguageKeyWord()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $php = $compiler->compile(implode("\n", [
            'div',
            '  p=(("5" + "6") === "56" ? "js" : "php")',
            '  //- @node-language php',
            '  p=(("5" + "6") === "56" ? "js" : "php")',
            '  p=(("5" + "6") === "56" ? "js" : "php")',
            '  //- @node-language php',
            '  div',
            '    p=(("5" + "6") === "56" ? "js" : "php")',
            '    p=(("5" + "6") === "56" ? "js" : "php")',
            '  p=(("5" + "6") === "56" ? "js" : "php")',
            'p=(("5" + "6") === "56" ? "js" : "php")',
        ]));
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<div><p>js</p><p>php</p><p>js</p><div><p>php</p><p>php</p></div><p>js</p></div><p>js</p>',
            $html
        );
    }

    /**
     * @group i
     */
    public function testDocumentLanguageKeyWord()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $php = $compiler->compileFile(__DIR__ . '/../templates/page.pug');
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<body><header>js</header><div><p>js</p><article>php</article></div><div><p>php</p><article>php</article></div><h1>php</h1><div><p>js</p><h2>php</h2></div><div><p>php</p><h2>php</h2></div><h3>php</h3><footer>php</footer></body>',
            $html
        );
    }
}
