<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception;

/**
 * A type the plan can't express, for cases the type extractor of the PropertyNormalizer doesn't produce (a typed promoted property
 * without type, a key type other than int and string). Caught by the PlanFactory, which reports it as a limitation, so the class is left
 * to the PropertyNormalizer.
 *
 * @internal
 *
 * @psalm-immutable
 */
final class TypeCanNotBePlanned extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The type can not be expressed by a plan.');
    }
}
