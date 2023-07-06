<?php

namespace Drupal\devutil\Commands;

use Drush\Commands\DrushCommands;
use Drupal\devutil\EntityManager;
use Drupal\devutil\EntityManagerInterface;
use Drupal\devutil\EntityBundleManager;
use Drupal\devutil\PluginManagerInterface;
use Drupal\devutil\EntityBundleManagerInterface;

/**
 * Drush Commands
 * 
 * @author Attila Németh
 * 13.11.2019
 */
class Commands extends DrushCommands {
  
  private     $_pluginManager;
  private     $_configEntitymanager;
  private     $_contentEntityManager;
  private     $_entityBundleManager;
  
  public function __construct(PluginManagerInterface $pluginManager,
        EntityManagerInterface $configEntityManager,
        EntityManagerInterface $contentEntityManager,
        EntityBundleManagerInterface $entityBundleManager) {
    $this->_pluginManager = $pluginManager;
    $this->_configEntitymanager = $configEntityManager;
    $this->_contentEntityManager = $contentEntityManager;
    $this->_entityBundleManager = $entityBundleManager;
  }
  
  /**
   * Creates a custom Content Entity Type
   * 
   * @param string $machineName
   * @param string $label
   * @command devutil:content-entity
   * @aliases devu-nt-ent
   * @options msg
   * @usage drush devu-nt-ent entity_type_name "Entity Type Label" --bundles --bundle-classes --module=existing_module_name --path=module_relative_path --name="Your Name"
   */
  public function contentEntity(string $machineName, string $label, array $options = [
    'bundles' => FALSE,
    'bundle-classes' => false,
    'module' => '',
    'name' => '',
    'path' => '',
  ])
  {
    if (!empty($options['module']) && !empty($options['path'])) {
      throw new \Exception('Path may only be used if a new module shall be created');
    }
    if ($options['bundle-classes'] && !$options['bundles']) {
      throw new \Exception('Bundle classes may only be created if the entity type has bundles');
    }
    $this->_contentEntityManager->createCode($machineName, $label, $options);
    echo "Your code is created\n";
  }
  
  /**
   * Creates a Bundle for a Custom Content Entity Type
   * This command may only be used if the content entity type exists and can be found
   *     
   * @param string $entityTypeId
   * @param string $bundleId
   * @param string $bundleLabel
   * @command devutil:content-entity-bundle
   * @aliases devu-nt-bundle
   * @options
   * @usage drush devu-nt-bundle entity_type_id bundle_id "Bundle Label" --name="Your Name"
   */
  public function contentEntityBundle(string $entityTypeId, string $bundleId, string $bundleLabel, array $options = [
    'name' => null,
  ]): void
  {
    $manager = $this->_entityBundleManager;
    $manager->setEntityType($entityTypeId);
    $manager->createCode($bundleId, $bundleLabel, $options);
  }
  
  /**
   * Creates a custom configuration entity type
   * @param string $machineName
   * @param string $label
   * @command devutil:config-entity
   * @aliases devu-nf-ent
   * @options msg
   * @usage drush devu-nf-ent entity_type_name "Entity Type Label" --module=existing_module_name --path=module_relative_path --name="Your Name"
   */
  public function configEntity(string $machineName, string $label, array $options = [
    'module' => '',
    'name' => '',
    'path' => '',
  ])
  {
    if (!empty($options['module']) && !empty($options['path'])) {
      throw new \Exception('Path may only be used if a new module shall be created');
    }
    $this->_configEntitymanager->createCode($machineName, $label, $options);
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
    if ($options['module'] == '') {
      $options['module'] = $name;
    }
    $this->_pluginManager->create($name, $options['module'], $options['name']);
  }
  
}
