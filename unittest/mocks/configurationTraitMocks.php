<?php

declare(strict_types=1);

use orange\framework\traits\ConfigurationTrait;

/**
 * Host class providing everything ConfigurationTrait expects to find:
 * a changeableTypeCheck map, matching properties and a setter.
 */
class configurationTraitMock
{
    use ConfigurationTrait;

    protected array $changeableTypeCheck = [
        'color' => 'is_string',
        'age' => 'is_integer',
        'when' => \DateTime::class,
        'viaSetter' => 'is_string',
        // deliberately has neither a property nor a setViaGhost() method
        'ghost' => 'is_string',
    ];

    public string $color = 'red';
    public int $age = 0;
    public ?\DateTime $when = null;

    protected string $viaSetter = '';
    public string $setterCalledWith = '';

    protected function setViaSetter(string $value): void
    {
        $this->viaSetter = $value;
        $this->setterCalledWith = $value;
    }
}

/** changeOption() is unsupported without a changeableTypeCheck property */
class configurationTraitNoTypeCheckMock
{
    use ConfigurationTrait;
}

/** changeableTypeCheck present but not an array */
class configurationTraitBadTypeCheckMock
{
    use ConfigurationTrait;

    protected mixed $changeableTypeCheck = 'not an array';
}
