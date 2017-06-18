<?php

namespace JsPhpize;

use Phug\Compiler;
use Phug\CompilerModule;

class JsPhpizePhug extends CompilerModule
{
    /**
     * @var array
     */
    protected $options = [];

    public function __construct(array $options = [])
    {
        $this->options = array_merge_recursive([
            'allowTruncatedParentheses' => true,
            'catchDependencies' => true,
            'ignoreDollarVariable' => true,
            'helpers' => [
                'dot' => 'dotWithArrayPrototype',
            ],
        ], $options);
    }

    public function injectCompiler(Compiler $compiler)
    {
        $options = $this->options;
        $compiler->setOptionsRecursive([
            'formatter_options' => [
                'modules' => [new JsPhpizePhugFormatter($compiler)],
            ],
        ]);
        $compiler->addHook('pre_compile', 'jsphpize', function ($pugCode) use (&$compiler, $options) {
            $compiler->setOption('jsphpize_engine', new JsPhpize($options));

            return $pugCode;
        });
        $compiler->addHook('post_compile', 'jsphpize', function ($phpCode) use (&$compiler) {
            /** @var JsPhpize $jsPhpize */
            $jsPhpize = $compiler->getOption('jsphpize_engine');
            $phpCode = preg_replace(
                '/\{\s*\?><\?(?:php)?\s*\}/',
                '{}',
                $phpCode
            );
            $phpCode = preg_replace(
                '/\}\s*\?><\?(?:php)?\s*(' .
                'else(if)?|for|while|switch|function' .
                ')(?![a-zA-Z0-9_])/',
                '} $1',
                $phpCode
            );
            $dependencies = $jsPhpize->compileDependencies();
            if ($dependencies !== '') {
                $phpCode = $compiler->getFormatter()->handleCode($dependencies) . $phpCode;
            }
            $jsPhpize->flushDependencies();
            $compiler->unsetOption('jsphpize_engine');

            return $phpCode;
        });

        return parent::injectCompiler($compiler);
    }
}
