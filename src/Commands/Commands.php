<?php

namespace Drupal\devutil\Commands;

use Drush\Commands\DrushCommands;
use Drupal\devutil\EntityManager;
use Drupal\devutil\PluginManager;

/**
 * Drush Commands
 * 
 * @author Attila Németh, UBG
 * 13.11.2019
 */
class Commands extends DrushCommands {
  
  /**
   * Creates a custom Content Entity Type
   * 
   * @param string $machineName
   * @param string $label
   * @command devutil:entity
   * @aliases devu-ent
   * @options msg
   * @usage drush devu-ent entity_type_name "Entity Type Label" --bundles --module=existing_module_name --path=modle_relative_path --name="Your Name"
   */
  public function entity(string $machineName, string $label, array $options = [
    'bundles' => FALSE,
    'module' => '',
    'name' => '',
    'path' => '',
  ])
  {
    if (!empty($options['module']) && !empty($options['path'])) {
      throw new \Exception('Path may only be used if a new module shall be created');
    }
    $manager = new EntityManager();
    if ($options['module'] == '') {
      $moduleName = $machineName;
    }
    else {
      $moduleName = $options['module'];
    }
    $manager->create($machineName, $label, $options['bundles'], $moduleName, $options);
    echo "Your code is created\n";
  }
  
  /**
   * Creates a custom annotation based plugin
   * @param string $name
   * @param array $options
   * @command devutil:plugin
   * @aliases devu-plugin
   * @usage drush devu-plugin plugin_name --module=existing_module_name --name="Your Name"
   */
  public function plugin(string $name, array $options = [
    'module' => '',
    'name' => '',
  ]): void
  {
    $manager = new PluginManager();
    if ($options['module'] == '') {
      $options['module'] = $name;
    }
    $manager->create($name, $options['module'], $options['name']);
  }
  
}
