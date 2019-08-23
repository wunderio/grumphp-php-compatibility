# grumphp-php-compatibility

Check if files are compatible with X version of PHP.

### grumphp.yml:
````yml
parameters:
    tasks:
        php_compatibility:
            extensions:  [php, inc, module, install]
    extensions:
        - wunderio\PhpCompatibilityTask\ExtensionLoader
````

### Composer

``composer require --dev wunderio/grumphp-php-compatibility``
