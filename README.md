# Enable value objects to normalize and denormalize themselves

A Symfony bundle to enable value objects and DTOs to normalize and denormalize themselves through implementing simple interfaces that normalize to scalar values and denormalize themselves from scalar values. Adding this kind of logic to the classes themselves might be considered a bad practice, but depending on the use case it will actually be better due to the fact that the data structure and the normalization need to be changed together.

As it's a central part of an application, it's tested thoroughly (including mutation testing).

[![Latest Stable Version](https://img.shields.io/badge/stable-0.1.0-blue)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)
[![PHP Version Require](https://img.shields.io/badge/php-8.3|8.4-5b5d95)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)
[![codecov](https://codecov.io/gh/digital-craftsman-de/self-aware-normalizers/branch/main/graph/badge.svg?token=BL0JKZYLBG)](https://codecov.io/gh/digital-craftsman-de/self-aware-normalizers)
![Packagist Downloads](https://img.shields.io/packagist/dt/digital-craftsman/self-aware-normalizers)
![Packagist License](https://img.shields.io/packagist/l/digital-craftsman/self-aware-normalizers)

## Installation and configuration

Install package through composer:

```shell
composer require digital-craftsman/self-aware-normalizers
```

> ⚠️ This bundle can be used (and is being used) in production, but hasn't reached version 1.0 yet. Therefore, there will be breaking changes between minor versions. I'd recommend that you require the bundle only with the current minor version like `composer require digital-craftsman/self-aware-normalizers:0.1.*`. Breaking changes are described in the releases and [the changelog](./CHANGELOG.md). Updates are described in the [upgrade guide](./UPGRADE.md).

## Usage

### Normalizers

To make the normalization process easier, there are the following normalizers included:

- `StringNormalizableNormalizer`
- `IntNormalizableNormalizer`
- `FloatNormalizableNormalizer`
- `BoolNormalizableNormalizer`
- `ArrayNormalizableNormalizer`

Additionally, there is an interface for each of the normalizers. Every class that implements one of the interfaces, will be automatically normalized to the respected type. This means putting the logic of how serialization of a class works within the class. That's not really seen as a good practice. In my experience, the data structure and the normalization need to be changed together. So, I like it better to have both in one place. I've used this approach in multiple large scale projects for years and haven't had a single issue with it yet. But your mileage may vary.

### Doctrine types

When using the normalizers, you can also use the same logic for doctrine types. Simply create a new doctrine type extending of one of the following types and register them:

- `StringNormalizableType`
- `StringEnumType`
- `IntNormalizableType`
- `FloatNormalizableType`
- `BoolNormalizableType`
- `ArrayNormalizableType`

As an added bonus, this makes sure, that the structure is always the same no matter if you're using Doctrine to read from the data or a normalizer.

## Additional documentation

- [Changelog](./CHANGELOG.md)
- [Upgrade guide](./UPGRADE.md)
