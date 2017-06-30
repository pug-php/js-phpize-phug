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

    public function compile(JsPhpize $jsPhpize, $code)
    {
        try {
            return rtrim(trim(preg_replace(
                '/\{\s*\}$/',
                '',
                trim($jsPhpize->compile($code, $this->compiler->getFileName()))
            )), ';');
        } catch (Exception $exception) {
            if (
                $exception instanceof LexerException ||
                $exception instanceof ParserException ||
                $exception instanceof CompilerException
            ) {
                return $exception;
            }

            throw $exception;
        }
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
                    $code = str_replace('$' . $pugModuleName, $pugModuleName, $jsCode);

                    $compilation = $this->compile($jsPhpize, $code);

                    if (!($compilation instanceof Exception)) {
                        return $compilation;
                    }

                    return $jsCode;
                },
            ],
        ]);

        return parent::injectFormatter($formatter);
    }
}
