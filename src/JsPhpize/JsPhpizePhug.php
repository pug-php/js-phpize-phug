<?php

namespace JsPhpize;

use Exception;
use JsPhpize\Compiler\Exception as CompilerException;
use JsPhpize\Lexer\Exception as LexerException;
use JsPhpize\Parser\Exception as ParserException;
use Phug\AbstractCompilerModule;
use Phug\Compiler;
use Phug\CompilerEvent;
use Phug\CompilerInterface;
use Phug\Parser\Node\CommentNode;
use Phug\Parser\Node\TextNode;
use Phug\Renderer;
use Phug\Util\ModuleContainerInterface;

class JsPhpizePhug extends AbstractCompilerModule
{
    public function __construct(ModuleContainerInterface $container)
    {
        parent::__construct($container);

        if ($container instanceof Renderer) {
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

        $compiler->getParser()->setOptionsRecursive([
            'on_node' => [$this, 'handleNodeEvent'],
        ]);
        $compiler->setOptionsRecursive([
            'patterns' => [
                'transform_expression' => function ($jsCode) use ($compiler) {
                    return $this->transformExpression($this->getJsPhpizeEngine($compiler), $jsCode, $compiler->getPath());
                },
            ],
            'checked_variable_exceptions' => [
                'js-phpize' => [static::class, 'checkedVariableExceptions'],
            ],
        ]);
    }

    public function handleNodeEvent(Compiler\Event\NodeEvent $event)
    {
        $node = $event->getNode();
        var_dump($this->eventListeners);
        exit;
        if ($node instanceof CommentNode && !$node->isVisible() && $node->hasChildAt(0)) {
            $this->handleComment($node);
        }
    }

    protected function handleComment(CommentNode $node)
    {
        $firstChild = $node->getChildAt(0);
        if ($firstChild instanceof TextNode) {
            $comment = trim($firstChild->getValue());
            var_dump($comment);
            exit;
        }
    }

    protected function transformExpression(JsPhpize $jsPhpize, $jsCode, $fileName)
    {
        $compilation = $this->compile($jsPhpize, $jsCode, $fileName);

        if (!($compilation instanceof Exception)) {
            return $compilation;
        }

        return $jsCode;
    }

    public static function checkedVariableExceptions($variable, $index, $tokens)
    {
        return $index > 2 &&
            $tokens[$index - 1] === '(' &&
            $tokens[$index - 2] === ']' &&
            !preg_match('/^__?pug_/', $variable) &&
            is_array($tokens[$index - 3]) &&
            $tokens[$index - 3][0] === T_CONSTANT_ENCAPSED_STRING &&
            preg_match('/_with_ref\'$/', $tokens[$index - 3][1]);
    }

    /**
     * @return JsPhpize
     */
    public function getJsPhpizeEngine(CompilerInterface $compiler)
    {
        if (!$compiler->hasOption('jsphpize_engine')) {
            $compiler->setOption('jsphpize_engine', new JsPhpize($this->getOptions()));
        }

        return $compiler->getOption('jsphpize_engine');
    }

    /**
     * @param JsPhpize $jsPhpize
     * @param int      $code
     * @param string   $fileName
     *
     * @throws Exception
     *
     * @return Exception|string
     */
    public function compile(JsPhpize $jsPhpize, $code, $fileName)
    {
        try {
            $phpCode = trim($jsPhpize->compile($code, $fileName ?: 'raw string'));
            $phpCode = preg_replace('/\{\s*\}$/', '', $phpCode);
            $phpCode = preg_replace(
                '/^(?<!\$)\$+(\$[a-zA-Z\\\\\\x7f-\\xff][a-zA-Z0-9\\\\_\\x7f-\\xff]*\s*[=;])/',
                '$1',
                $phpCode
            );

            return rtrim(trim($phpCode), ';');
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

    /**
     * @param CompilerInterface $compiler
     * @param string            $output
     *
     * @return string
     */
    protected function parseOutput($compiler, $output)
    {
        $jsPhpize = $this->getJsPhpizeEngine($compiler);
        $output = preg_replace(
            '/\{\s*\?><\?(?:php)?\s*\}/',
            '{}',
            $output
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

        $jsPhpize->flushDependencies();

        return $output;
    }

    public function handleOutputEvent(Compiler\Event\OutputEvent $event)
    {
        /** @var CompilerInterface $compiler */
        $compiler = $event->getTarget();

        $event->setOutput($this->parseOutput($compiler, $event->getOutput()));

        $compiler->unsetOption('jsphpize_engine');
    }

    /**
     * @return array
     */
    public function getEventListeners()
    {
        return [
            CompilerEvent::OUTPUT => [$this, 'handleOutputEvent'],
        ];
    }
}
