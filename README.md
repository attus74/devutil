# Drupal 8/9 Development Utilities

A tool for creating Drupal code quick through drush commands.
Install: 
```
composer require attus/devutil:^1.0 --dev
drush en devutil
```

## New Entity Type
```
drush devu-ent entity_type_name "Entity Type Label" --bundles --module=existing_module_name --path=module_relative_path --name="Your Name"
```
Either the name of a module or a path for a new module shall be used. Path shall be relative to Web folder, e.g. "modules/custom".
Use the bundles argument, if you want your new custom contenty entity type to have bundles. 

## New Annotation Plugin
```
drush devu-plugin plugin_name --module=existing_module_name --name="Your Name"
```
