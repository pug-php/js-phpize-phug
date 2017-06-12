<?php

namespace JsPhpize;

use Phug\Compiler;
use Phug\CompilerModule;

class JsPhpizePhug extends CompilerModule
{
    public function injectCompiler(Compiler $compiler)
    {
        // 'dependencies_storage'
        $compiler->setOptionsRecursive([
            'formatter_options' => [
                'modules' => [new JsPhpizePhugFormatter($compiler)],
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
