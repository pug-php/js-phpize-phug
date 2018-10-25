<?php

namespace JsPhpize;

use Exception;
use JsPhpize\Compiler\Exception as CompilerException;
use JsPhpize\Lexer\Exception as LexerException;
use JsPhpize\Parser\Exception as ParserException;
use Phug\AbstractCompilerModule;
use Phug\Compiler;
use Phug\Compiler\Event\NodeEvent;
use Phug\CompilerEvent;
use Phug\CompilerInterface;
use Phug\Formatter\Element\DocumentElement;
use Phug\Formatter\Element\KeywordElement;
use Phug\Formatter\Event\FormatEvent;
use Phug\FormatterEvent;
use Phug\Parser\Node\CommentNode;
use Phug\Parser\Node\KeywordNode;
use Phug\Parser\Node\TextNode;
use Phug\Renderer;
use Phug\Util\ModuleContainerInterface;
use SplObjectStorage;

class JsPhpizePhug extends AbstractCompilerModule
{
    protected $documentLanguages;
    protected $languages = ['js', 'php'];

    public function __construct(ModuleContainerInterface $container)
    {
        $this->documentLanguages = new SplObjectStorage();

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
            'language' => 'js',
            'allowTruncatedParentheses' => true,
            'catchDependencies' => true,
            'ignoreDollarVariable' => true,
            'helpers' => [
                'dot' => 'dotWithArrayPrototype',
            ],
        ]);

        //Apply options from container
        $this->setOptionsRecursive($compiler->getOption(['module_options', 'jsphpize']));

        $compiler->attach(CompilerEvent::NODE, [$this, 'handleNodeEvent']);
        $compiler->attach(FormatterEvent::FORMAT, [$this, 'handleFormatEvent']);

        $compiler->setOptionsRecursive([
            'keywords' => [
                'language' => [$this, 'handleLanguageKeyword'],
                'node-language' => [$this, 'handleNodeLanguageKeyword'],
                'document-language' => [$this, 'handleDocumentLanguageKeyword'],
                'file-language' => [$this, 'handleDocumentLanguageKeyword'],
            ],
            'patterns' => [
                'transform_expression' => function ($code) use ($compiler) {
                    return $this->transformExpression($this->getJsPhpizeEngine($compiler), $code, $compiler->getPath());
                },
            ],
            'checked_variable_exceptions' => [
                'js-phpize' => [static::class, 'checkedVariableExceptions'],
            ],
        ]);
    }

    public function handleFormatEvent(FormatEvent $event)
    {
        $document = $event->getElement();
        if ($document && $document instanceof DocumentElement && $this->documentLanguages->offsetExists($document)) {
            $this->setOption('language', $this->documentLanguages->offsetGet($document));
        }
    }

    public function handleNodeEvent(NodeEvent $event)
    {
        $node = $event->getNode();
        if ($node instanceof CommentNode && !$node->isVisible() && $node->hasChildAt(0)) {
            $firstChild = $node->getChildAt(0);
            if ($firstChild instanceof TextNode) {
                $comment = trim($firstChild->getValue());

                if (preg_match('/^@((?:node-|document-|file-)?lang(?:uage)?)([\s(].*)$/', $comment, $match)) {
                    $keyword = new KeywordNode();
                    $keyword->setName($match[1]);
                    $keyword->setValue($match[2]);
                    $event->setNode($keyword);
                }
            }
        }
    }

    protected function getLanguageKeywordValue($value, KeywordElement $keyword, $name)
    {
        $value = trim($value, "()\"' \t\n\r\0\x0B");

        if (!in_array($value, $this->languages)) {
            $file = 'unknown';
            $line = 'unknown';
            $offset = 'unknown';
            $node = $keyword->getOriginNode();
            if ($node && ($location = $node->getSourceLocation())) {
                $file = $location->getPath();
                $line = $location->getLine();
                $offset = $location->getOffset();
            }

            throw new \InvalidArgumentException(sprintf(
                "Invalid argument for %s keyword: %s. Possible values are: %s\nFile: %s\nLine: %s\nOffset:%s",
                $name,
                $value,
                implode(', ', $this->languages),
                $file,
                $line,
                $offset
            ));
        }

        return $value;
    }

    public function handleNodeLanguageKeyword($value, KeywordElement $keyword, $name)
    {
        $value = $this->getLanguageKeywordValue($value, $keyword, $name);

        if ($next = $keyword->getNextSibling()) {
            $next->prependChild(new KeywordElement('language', $value));
            $next->appendChild(new KeywordElement('language', $this->getOption('language')));
        }

        return '';
    }

    public function handleDocumentLanguageKeyword($value, KeywordElement $keyword, $name)
    {
        $value = $this->getLanguageKeywordValue($value, $keyword, $name);

        $this->setOption('language', $value);

        $document = $keyword->getParent();
        while ($document && !($document instanceof DocumentElement)) {
            $document = $document->getParent();
        }

        if ($document) {
            if (!$this->documentLanguages->offsetExists($document)) {
                $this->documentLanguages->offsetSet($document, $this->getOption('language'));
            }
        }

        return '';
    }

    public function handleLanguageKeyword($value, KeywordElement $keyword, $name)
    {
        $value = $this->getLanguageKeywordValue($value, $keyword, $name);

        $this->setOption('language', $value);

        return '';
    }

    protected function transformExpression(JsPhpize $jsPhpize, $code, $fileName)
    {
        if ($this->getOption('language') === 'php') {
            return $code;
        }

        $compilation = $this->compile($jsPhpize, $code, $fileName);

        if (!($compilation instanceof Exception)) {
            return $compilation;
        }

        return $code;
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
