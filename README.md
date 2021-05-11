## Craft Auto Migrate

A Composer plugin that runs `craft migrate/all` and `craft project-config/apply` after `composer install`, **if Craft is installed**.


### Install

Require the package as a dependency of a plugin or in the composer.json of your project.

```
composer require fortrabbit/craft-auto-migrate
```

### Disable

By setting the ENV var `DISABLE_CRAFT_AUTOMIGRATE=1` you disable the plugin.
