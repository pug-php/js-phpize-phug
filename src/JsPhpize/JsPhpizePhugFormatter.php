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
        $formatter->setOptionsRecursive([
            'patterns' => [
                'transform_expression' => function ($jsCode) use (&$compiler, &$formatter) {
                    /** @var JsPhpize $jsPhpize */
                    $jsPhpize = $compiler->getOption('jsphpize_engine');
                    $pugModuleName = $formatter->getOption('dependencies_storage');
                    $newCode = str_replace('$'.$pugModuleName, $pugModuleName, $jsCode);

                    try {
                        return rtrim(trim(preg_replace(
                            '/\{\s*\}$/',
                            '',
                            trim($jsPhpize->compile($newCode))
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
