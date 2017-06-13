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
        $this->options = array_merge([
            'catchDependencies' => true,
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
