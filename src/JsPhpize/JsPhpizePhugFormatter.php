<?php

namespace JsPhpize;

use Exception;
use JsPhpize\Compiler\Exception as CompilerException;
use JsPhpize\Lexer\Exception as LexerException;
use JsPhpize\Parser\Exception as ParserException;
use Phug\Compiler;
use Phug\Formatter;
use Phug\FormatterModule;

class JsPhpizePhugFormatter extends FormatterModule
{
    /**
     * @var Compiler
     */
    protected $compiler;

    public function __construct(Compiler $compiler)
    {
        $this->compiler = $compiler;
    }

    public function injectFormatter(Formatter $formatter)
    {
        $compiler = $this->compiler;
        // 'dependencies_storage'
        $formatter->setOptionsRecursive([
            'dependencies_storage_getter' => function ($phpCode) {
                $phpCode = ltrim($phpCode);

                return substr($phpCode, 0, 1) === '$'
                    ? substr($phpCode, 1)
                    : $phpCode;
            },
            'patterns'                    => [
                'transform_expression' => function ($jsCode) use (&$compiler) {
                    /** @var JsPhpize $jsPhpize */
                    $jsPhpize = $compiler->getOption('jsphpize_engine');

                    try {
                        return rtrim(trim(preg_replace(
                            '/\{\s*\}$/',
                            '',
                            trim($jsPhpize->compile($jsCode))
                        )), ';');
                    } catch (Exception $e) {
                        if (
                            $e instanceof LexerException ||
                            $e instanceof ParserException ||
                            $e instanceof CompilerException
                        ) {
                            return $jsCode;
                        }

                        throw $e;
                    }
                },
            ],
        ]);

        return parent::injectFormatter($formatter);
    }
}
