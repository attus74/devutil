# Drupal 8/9 Development Utilities

A tool for creating Drupal code quick through drush commands.

[![Maintenance](https://img.shields.io/badge/Maintained%3F-yes-green.svg)](https://GitHub.com/attus74/devutil/graphs/commit-activity)
[![GitHub license](https://img.shields.io/github/license/attus74/devutil.svg)](https://github.com/attus74/devutil/blob/master/LICENSE)
[![GitHub release](https://img.shields.io/github/release/attus74/devutil.svg)](https://GitHub.com/attus74/devutil/releases/)
[![GitHub issues](https://img.shields.io/github/issues/attus74/devutil.svg)](https://GitHub.com/attus74/devutil/issues/)


Install: 
```
composer require attus/devutil:^1.0 --dev
drush en devutil
```

## New Entity Type
```
// Content Entity Type
drush devu-nt-ent entity_type_name "Entity Type Label" --bundles --module=existing_module_name --path=module_relative_path --name="Your Name"

/ Configuration Entity Type
drush devu-nf-ent entity_type_name "Entity Type Label" --module=existing_module_name --path=module_relative_path --name="Your Name"

```
Either the name of a module or a path for a new module shall be used. Path shall be relative to Web folder, e.g. "modules/custom".
Use the bundles argument, if you want your new custom contenty entity type to have bundles. 

## New Annotation Plugin
```
drush devu-plugin plugin_name --module=existing_module_name --name="Your Name"
```
