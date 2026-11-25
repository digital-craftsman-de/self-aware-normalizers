# Upgrade guide

## From 1.5.* to 2.0.0

### Renamed package and namespace

The package moved from `digital-craftsman/self-aware-normalizers` to `buron-digital/self-aware-normalizers` and the namespace from `DigitalCraftsman\SelfAwareNormalizers` to `BuronDigital\SelfAwareNormalizers`. Apart from the names, nothing changed, so the upgrade is a plain search and replace.

1. Replace the package in your `composer.json`.

   Before:
   ```json
   "digital-craftsman/self-aware-normalizers": "^1.5"
   ```

   After:
   ```json
   "buron-digital/self-aware-normalizers": "^2.0.0"
   ```

2. Replace the namespace in your whole project – not only in `src` and `tests`, but also in `config` (e.g. `config/bundles.php` and service or Doctrine configuration), migrations, templates and all other files that contain class names. Search for `DigitalCraftsman\SelfAwareNormalizers\` and replace it with `BuronDigital\SelfAwareNormalizers\` (e.g. with "Replace in Files" of your IDE) or run the following command in the root of your project. It also covers escaped class names like `'DigitalCraftsman\\SelfAwareNormalizers\\…'` in strings:

   ```shell
   grep -rlE 'DigitalCraftsman\\+SelfAwareNormalizers' --exclude-dir=vendor --exclude-dir=var --exclude-dir=node_modules --exclude-dir=.git . \
     | xargs perl -pi -e 's/DigitalCraftsman(\\+)SelfAwareNormalizers/BuronDigital$1SelfAwareNormalizers/g'
   ```

   When you switch all packages (`self-aware-normalizers`, `ids`, `date-time-precision`, `deserializing-connection`, `cqs-routing`) at once, you can replace `DigitalCraftsman` with `BuronDigital` instead.

3. Update the packages and clear the cache:

   ```shell
   composer update "buron-digital/*"
   bin/console cache:clear
   ```

4. Check where class names are stored outside of your code. For example, PHP-serialized messages in Symfony Messenger queues or objects in persistent cache pools still contain the previous class names. Let the queues run empty and clear those pools when deploying the change.

## From 1.5.0 to 1.5.1

Nothing to do.

## From 1.4.* to 1.5.0

Nothing to do. The new `KnownConstructionNormalizer` is disabled by default, see [README - KnownConstructionNormalizer](./README.md#knownconstructionnormalizer) to enable it.

## From 1.3.* to 1.4.0

### Dropped support for PHP 8.4

Update to at least PHP 8.5.

## From 1.2.* to 1.3.0

### Switch to automatic doctrine types

- Add the directories that contain implementations of the interfaces to `self_aware_normalizers.implementation_directories`.
- Use the full class string in the doctrine column `type` instead of the custom names (see [README - Automatic doctrine types](./README.md#automatic-doctrine-types)).
- Implement the necessary additional interfaces when needed.
- Remove all custom doctrine types.

## From 1.1.* to 1.2.0

### Dropped support for PHP 8.3

Update to at least PHP 8.4.

### Dropped support for Symfony 7.3 and below

Update to at least the LTS version 7.4.

## From 1.0.* to 1.1.0

Nothing to do.

## From 0.3.* to 1.0.0

Nothing to do.

## From 0.2.* to 0.3.0

Nothing to do.

## From 0.1.* to 0.2.0

Nothing to do.
