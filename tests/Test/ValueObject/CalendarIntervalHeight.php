<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizableEnumTrait;

enum CalendarIntervalHeight: int implements IntNormalizable
{
    use IntNormalizableEnumTrait;

    case EXTRA_SMALL = 21;
    case SMALL = 42;
    case DEFAULT = 84;
    case LARGE = 168;
    case EXTRA_LARGE = 252;
}
