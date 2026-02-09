<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine\Exception;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class InvalidTypeName extends InvalidArgumentException
{
    public function __construct(string $className)
    {
        parent::__construct(sprintf(
            'The name %s is not a valid class',
            $className,
        ));
    }
}
