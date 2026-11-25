<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\Doctrine;

use BuronDigital\SelfAwareNormalizers\Doctrine\ArrayNormalizableType;
use BuronDigital\SelfAwareNormalizers\Test\DTO\Project;

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
