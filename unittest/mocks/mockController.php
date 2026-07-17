<?php

declare(strict_types=1);

class mockController
{
    public function __construct() {}

    public function index()
    {
        return '<h1>foobar</h1>';
    }

    public function passone(string $one)
    {
        return $one;
    }

    public function passtwo(string $one, string $two)
    {
        return $one . '+' . $two;
    }

    public function passonenull(string $one, string $two = '')
    {
        return $one . '+' . $two;
    }

    protected function secret()
    {
        return 'should not be reachable via routing';
    }
}
