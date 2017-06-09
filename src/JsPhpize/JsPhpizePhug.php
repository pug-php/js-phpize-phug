<?php

namespace JsPhpize;

use Exception;
use Phug\Compiler;
use Phug\CompilerModule;
use JsPhpize\Compiler\Exception as CompilerException;
use JsPhpize\Lexer\Exception as LexerException;
use JsPhpize\Parser\Exception as ParserException;

class JsPhpizePhug extends CompilerModule
{
    public function injectCompiler(Compiler $compiler)
    {
        $compiler->setOptionsRecursive([
            'formatter_options' => [
                'patterns' => [
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
            ],
        ]);
        $compiler->addHook('pre_compile', 'jsphpize', function ($pugCode) use (&$compiler) {
            $compiler->setOption('jsphpize_engine', new JsPhpize([
                'catchDependencies' => true,
            ]));

            return $pugCode;
        });
        $compiler->addHook('post_compile', 'jsphpize', function ($phpCode) use (&$compiler) {
            /** @var JsPhpize $jsPhpize */
            $jsPhpize = $compiler->getOption('jsphpize_engine');
            $dependencies = $jsPhpize->compileDependencies();
            if ($dependencies !== '') {
                $phpCode = $compiler->getFormatter()->handleCode($dependencies).$phpCode;
            }
            $jsPhpize->flushDependencies();
            $compiler->unsetOption('jsphpize_engine');

            return $phpCode;
        });

        return parent::injectCompiler($compiler);
    }
}
