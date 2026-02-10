<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\CalendarIntervalHeight;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntNormalizableEnumTraitTest extends TestCase
{
    #[Test]
    public function denormalize_and_normalize_works(): void
    {
        // -- Arrange
        $calendarIntervalHeight = CalendarIntervalHeight::LARGE;
        $data = 168;

        // -- Act
        $denormalized = CalendarIntervalHeight::denormalize($data);
        $normalized = $calendarIntervalHeight->normalize();

        // -- Assert
        $this->assertSame($calendarIntervalHeight, $denormalized);
        $this->assertSame($data, $normalized);
    }
}
