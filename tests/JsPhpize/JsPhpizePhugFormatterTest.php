<?php

namespace Tests\JsPhpize;

use JsPhpize\JsPhpizePhug;
use Phug\Compiler;

class JsPhpizePhugFormatterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage ExceptionThrower
     */
    public function testPlug()
    {
        include_once __DIR__ . '/ExceptionThrower.php';

        $compiler = new Compiler([
            'modules' => [new JsPhpizePhug([
                'patterns' => [
                    new ExceptionThrower(),
                ],
            ])],
        ]);

        $compiler->compile('a=foo');
    }
}
