<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\ArrayNormalizableType;
use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\Project;

final class ProjectType extends ArrayNormalizableType
{
    #[\Override]
    public static function getTypeName(): string
    {
        return 'project';
    }

    #[\Override]
    public static function getClass(): string
    {
        return Project::class;
    }
}
