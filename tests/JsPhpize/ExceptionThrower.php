<?php

namespace Tests\JsPhpize;

class ExceptionThrower
{
    public function __get($name)
    {
        throw new \Exception('ExceptionThrower');
    }
}
