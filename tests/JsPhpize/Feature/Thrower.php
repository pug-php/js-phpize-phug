<?php

namespace Tests;

use JsPhpize\JsPhpize;

class Thrower extends JsPhpize
{
    /**
     * @param string $input
     * @param null   $filename
     *
     * @throws \Exception
     *
     * @return string|void
     */
    public function compile($input, $filename = null)
    {
        throw new \Exception('Unhandled');
    }
}
