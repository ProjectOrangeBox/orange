<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

interface ViewInterface
{
    public function render(string $view = '', array $data = [], array $options = []): string;
    public function renderString(string $string, array $data = [], array $options = []): string;
    public function change(string $name, mixed $value): self;
    public function search(): DirectorySearchInterface;
}
