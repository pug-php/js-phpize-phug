<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpizePhug;
use PHPUnit\Framework\TestCase;
use Phug\Compiler;

class JsPhpCommentsTest extends TestCase
{
    public function testPlug()
    {
        $compiler = new Compiler([
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $php = $compiler->compile(implode("\n", [
            '// @language(js)',
            '// @language(php)',
        ]));
        eval('?>' . $php);
        $html = ob_get_contents();
        ob_end_clean();
        self::assertSame(
            '<a foo="bar"></a>',
            $html
        );
    }
}
