<?php

namespace JsPhpize;

use Exception;
use JsPhpize\Compiler\Exception as CompilerException;
use JsPhpize\Lexer\Exception as LexerException;
use JsPhpize\Parser\Exception as ParserException;
use Phug\AbstractCompilerModule;
use Phug\Compiler;
use Phug\CompilerEvent;
use Phug\Renderer;
use Phug\Util\ModuleContainerInterface;

class JsPhpizePhug extends AbstractCompilerModule
{
    public function __construct(ModuleContainerInterface $container)
    {
        parent::__construct($container);

        if ($container instanceof Renderer) {
            $container->setOptionsRecursive([
                'compiler_modules' => [$this],
            ]);

            return;
        }

        /* @var Compiler $compiler */
        $compiler = $container;

        //Make sure we can retrieve the module options from the container
        $compiler->setOptionsRecursive([
            'module_options' => [
                'jsphpize' => [],
            ],
        ]);

        //Set default options
        $this->setOptionsRecursive([
            'allowTruncatedParentheses' => true,
            'catchDependencies' => true,
            'ignoreDollarVariable' => true,
            'helpers' => [
                'dot' => 'dotWithArrayPrototype',
            ],
        ]);

        //Apply options from container
        $this->setOptionsRecursive($compiler->getOption(['module_options', 'jsphpize']));

        $formatter = $compiler->getFormatter();
        $formatter->setOptionsRecursive([
            'patterns' => [
                'transform_expression' => function ($jsCode) use ($compiler, $formatter) {

                    /** @var JsPhpize $jsPhpize */
                    $jsPhpize = $compiler->getOption('jsphpize_engine');
                    $pugModuleName = $formatter->getOption('dependencies_storage');
                    $code = str_replace('$' . $pugModuleName, $pugModuleName, $jsCode);

                    $compilation = $this->compile($jsPhpize, $code, $compiler->getPath());

                    if (!($compilation instanceof Exception)) {
                        return $compilation;
                    }

                    return $jsCode;
                },
            ],
        ]);
    }

    public function compile(JsPhpize $jsPhpize, $code, $fileName)
    {
        try {
            return rtrim(trim(preg_replace(
                '/\{\s*\}$/',
                '',
                trim($jsPhpize->compile($code, $fileName))
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

    public function getEventListeners()
    {
        return [
            CompilerEvent::COMPILE => function (Compiler\Event\CompileEvent $event) {
                $event->getTarget()->setOption('jsphpize_engine', new JsPhpize($this->getOptions()));
            },

            CompilerEvent::OUTPUT => function (Compiler\Event\OutputEvent $event) {
                $compiler = $event->getTarget();

                /** @var JsPhpize $jsPhpize */
                $jsPhpize = $compiler->getOption('jsphpize_engine');
                $output = preg_replace(
                    '/\{\s*\?><\?(?:php)?\s*\}/',
                    '{}',
                    $event->getOutput()
                );
                $output = preg_replace(
                    '/\}\s*\?><\?(?:php)?\s*(' .
                    'else(if)?|for|while|switch|function' .
                    ')(?![a-zA-Z0-9_])/',
                    '} $1',
                    $output
                );

                $dependencies = $jsPhpize->compileDependencies();
                if ($dependencies !== '') {
                    $output = $compiler->getFormatter()->handleCode($dependencies) . $output;
                }

                $event->setOutput($output);

                $jsPhpize->flushDependencies();
                $compiler->unsetOption('jsphpize_engine');
            },
        ];
    }
}
