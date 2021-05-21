<?php

namespace Drupal\devutil;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;

/**
 * Entity Type Manager
 * 
 * @author Attila Németh
 * 19.02.2019
 */
class EntityManager {
  
  private     $_moduleHandler;
  
  private     $_name;
  private     $_label;
  private     $_phpName;
  private     $_hasBundle;
  
  private     $_moduleName;
  private     $_moduleDir;
  
  private     $_yourName          = 'Your Name';
  
  public function __construct() {
    $this->_moduleHandler = \Drupal::service('module_handler');
  }
  
  /**
   * Create Code for a new Entity Type
   * @param string $name
   *  Machine readable type name
   * @param string $label
   *  Human readable type label
   * @param boolean $bundle
   *  Bundles
   * @param string $module
   *  Module Name
   * @param array $options
   *  - name: Your Name
   */
  public function create($name, $label, $bundle, $module, array $options = [])
  {
    if (array_key_exists('name', $options) && !empty($options['name'])) {
      $this->_yourName = $options['name'];
    }
    $this->_createModule($module, $label, $options['path']);
    $this->_name      = $name;
    $this->_label     = $label;
    $parts = explode('_', $name);
    $ucParts = [];
    foreach($parts as $part) {
      $ucParts[] = ucfirst(strtolower($part));
    }
    $this->_phpName = implode($ucParts);
    if ($bundle) {
      $this->_hasBundle = TRUE;
    }
    else {
      $this->_hasBundle = FALSE;
    }
    $this->_createPermissions();
    $this->_createRouting();
    if ($bundle) {
      $this->_createBundle();
    }
    $this->_createLinks();
    $this->_createEntityClass();
    $this->_createForms();
    $this->_createAccessControl();
    $this->_createTemplate();
    $this->_createThemeHook();
  }
  
  /**
   * Create Bundle
   * 
   * 29.03.2019
   */
  private function _createBundle() 
  {
    $this->_createBundleRouting();
    $this->_createBundleClass();
    $this->_createBundleForm();
  }
  
  /**
   * Bundle Name
   * @return string
   * 
   * 29.03.2019
   */
  private function _getBundleName()
  {
    return $this->_name . '_type';
  }
  
  /**
   * Create Bundle Class and Interface
   * 
   * 29.03.2019
   */
  private function _createBundleClass()
  {
    // Interface
    $dir = $this->_moduleDir . '/src';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . ';',
      '',
      'use Drupal\Core\Config\Entity\ConfigEntityInterface;',
      '',
      '/**',
      ' * ' . (string)t('@type Type Interface', [
        '@type' => $this->_label,
      ]),
      ' */',
      'interface ' . $this->_phpName . 'TypeInterface extends ConfigEntityInterface {',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'TypeInterface.php', implode("\n", $lines));
    // Classes
    $dir = $this->_moduleDir . '/src/Entity';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Entity;',
      '',
      'use Drupal\Core\Config\Entity\ConfigEntityBundleBase;;',
      'use Drupal\\' . $this->_moduleName . '\\' . $this->_phpName . 'TypeInterface;',
      '',
      '/**',
      ' * Entity ' . $this->_label . ' Type',
      ' *',
      ' * @author ' . $this->_yourName,
      ' * ' . date('d.m.Y'),
      ' *',
      ' * @ConfigEntityType(',
      ' *   id = "' . $this->_getBundleName() . '",',
      ' *   label = @Translation("' . (string)t('@label Type', ['@label' => $this->_label]) . '"),',
      ' *   bundle_of = "' . $this->_name . '",',
      ' *   handlers = {',
      ' *     "list_builder" = "Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_phpName . 'TypeList",',
      ' *     "form" = {',
      ' *       "default" = "Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'TypeForm",',
      ' *       "add" = "Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'TypeForm",',
      ' *       "edit" = "Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'TypeForm",',
      ' *     }',
      ' *   },',
      ' *   config_prefix = "' . $this->_getBundleName() . '",',
      ' *   admin_permission = "administer ' . $this->_name . '",',
      ' *   entity_keys = {',
      ' *     "id" = "id",',
      ' *     "label" = "label",',
      ' *     "uuid" = "uuid",',
      ' *   },',
      ' *   config_export = {',
      ' *     "id",',
      ' *     "label",',
      ' *     "uuid",',
      ' *   },',
      ' *   links = {',
      ' *     "add-form" = "/admin/structure/' . str_replace('_', '/', $this->_name) . '/type/add",',
      ' *     "edit-form" = "/admin/structure/' . str_replace('_', '/', $this->_name) . '/type/{' . $this->_getBundleName() . '}/edit",',
      ' *     "collection" = "/admin/structure/' . str_replace('_', '/', $this->_name . '/type') . '",',
      ' *   }',
      ' * )',
      ' */',
      'class ' . $this->_phpName . 'Type extends ConfigEntityBundleBase {',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'Type.php', implode("\n", $lines));
    // List Builder
    $dir = $this->_moduleDir . '/src/Controller';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Controller;',
      '',
      'use Drupal\Core\Entity\EntityListBuilder;',
      'use Drupal\Core\Entity\EntityInterface;',
      '',
      '/**',
      ' * ' . (string)t('@label Type List', [
        '@label' => $this->_label
      ]),
      ' * ',
      ' * @author ' . $this->_yourName,
      ' * ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'TypeList extends EntityListBuilder {',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function buildHeader() {',
      '    $header = [',
      '      t(\'Name\'),',
      '    ];',
      '    return $header + parent::buildHeader();',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function buildRow(EntityInterface $bundleEntity) {',
      '    $row = [',
      '      $bundleEntity->label(),',
      '    ];',
      '    return $row + parent::buildRow($bundleEntity);',
      '  }',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'TypeList.php', implode("\n", $lines));
    // Controller
    $dir = $this->_moduleDir . '/src/Controller';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Controller;',
      '',
      'use Drupal\Core\Controller\ControllerBase;',
      '',
      '/**',
      ' * ' . (string)t('@type Controller', [
        '@type' => $this->_label,
      ]),
      ' * ',
      ' * @author ' . $this->_yourName,
      ' * ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . ' extends ControllerBase {',
      '',
      '  /**',
      '   * ' . (string)t('Select @type Type', [
        '@type' => $this->_label,
      ]),
      '   * ',
      '   * Diese Funktion wählt automatisch den ersten Typ aus. Haben Sie mehr, sollen Sie',
      '   * diese Funktion weiterentwickeln',
      '   */',
      '  public function addSelect() ',
      '  {',
      '    $types = \Drupal::entityTypeManager()->getStorage(\'' . $this->_getBundleName() . '\')->loadMultiple();',
      '    $type = current($types);',
      '    return $this->redirect(\'entity.' . $this->_name . '.add-form\', [',
      '      \'' . $this->_getBundleName() . '\' => $type->id(),',
      '    ]);',
      '  }',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . '.php', implode("\n", $lines));
  }
  
  /**
   * Bundle Form
   * 
   * 29.03.2019
   */
  private function _createBundleForm()
  {
    $dir = $this->_moduleDir . '/src/Form';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Form;',
      '',
      'use Drupal\Core\Entity\BundleEntityFormBase;',
      'use Drupal\Core\Form\FormStateInterface;',
      '',
      '/**',
      ' * @file ' . (string)t('Create or edit @type Type', [
         '@type' => $this->_label,
       ]),
      ' *',
      ' * @author ' . $this->_yourName,
      ' * @date ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'TypeForm extends BundleEntityFormBase {',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function form(array $form, FormStateInterface $form_state) {',
      '    $form = parent::form($form, $form_state);',
      '    $entityType = $this->entity;',
      '    $form[\'label\'] = [',
      '      \'#type\' => \'textfield\',',
      '      \'#title\' => $this->t(\'Name\'),',
      '      \'#maxlength\' => 255,',
      '      \'#default_value\' => $entityType->label(),',
      '      \'#description\' => $this->t(\'Name of @label Type\', [',
      '        \'@label\' => \'' . $this->_label . '\'',
      '      ]),',
      '      \'#required\' => TRUE,',
      '    ];',
      '    $form[\'id\'] = [',
      '      \'#type\' => \'machine_name\',',
      '      \'#default_value\' => $entityType->id(),',
      '      \'#machine_name\' => [',
      '        \'exists\' => \'\Drupal\\' . $this->_moduleName . '\Entity\\' .  $this->_phpName . 'Type::load\',',
      '      ],',
      '      \'#disabled\' => !$entityType->isNew(),',
      '    ];',
      '    return $this->protectBundleIdElement($form);',
      '  }',
      '',
      '  /**',
      '  * {@inheritdoc}',
      '  */',
      '  public function save(array $form, FormStateInterface $form_state) {',
      '    $entityType = $this->entity;',
      '    $status = $entityType->save();',
      '    $messagePparams = [',
      '      \'%label\' => $entityType->label(),',
      '      \'%content_entity_id\' => $entityType->getEntityType()->getBundleOf(),',
      '    ];',
      '    switch ($status) {',
      '      case SAVED_NEW:',
      '        \Drupal::messenger()->addStatus($this->t(\'@label type is created\', [',
      '          \'@label\' => \'' . $this->_name . '\'',
      '        ]));',
      '        break;',
      '     default:',
      '        \Drupal::messenger()->addStatus($this->t(\'@label Type is updated\', [',
      '          \'@label\' => \'' . $this->_name . '\'',
      '        ]));',
      '    }',
      '    $form_state->setRedirectUrl($entityType->toUrl(\'collection\'));',
      '  }',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'TypeForm.php', implode("\n", $lines));
  }
  
  private function _createBundleRouting()
  {
    $routing = $this->_getExistingYmlContent('routing');
    $routing['entity.' . $this->_getBundleName() . '.collection'] = [
      'path' => 'admin/structure/' . str_replace('_', '/', $this->_name),
      'defaults' => [
        '_entity_list' => $this->_getBundleName(),
        '_title' => (string)t('@type Type', ['@type' => $this->_label]),
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_name,
      ],
    ];
    $routing[$this->_getBundleName() . '.add'] = [
      'path' => 'admin/structure/' . str_replace('_', '/', $this->_name) . '/type/add',
      'defaults' => [
        '_entity_form' => $this->_getBundleName() . '.add',
        '_title' => (string)t('Add @label Type', [
          '@label' => $this->_label,
        ]),
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_name,
      ],
    ];
    $routing['entity.' . $this->_getBundleName() . '.edit_form'] = [
      'path' => 'admin/structure/' . str_replace('_', '/', $this->_name) . '/type/{' . $this->_getBundleName() . '}/edit',
      'defaults' => [
        '_entity_form' => $this->_getBundleName() . '.edit',
        '_title' => (string)t('Edit'),
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_name,
      ],
    ];
    $this->_setYmlContent('routing', $routing);
  }
  
  /**
   * A module will be created if it does not exist
   * @param string $name
   */
  private function _createModule($name, $label = FALSE, $path = FALSE)
  {
    if ($this->_moduleHandler->moduleExists($name)) {
      $this->_moduleName = $name;
      $this->_moduleDir  = drupal_get_path('module', $name);
    }
    else {
      if ($path && !empty($path)) {
        $path = str_replace('\\', '/', $path);
        $dir = $path . '/' . $name;
      }
      else {
        $dir = dirname(drupal_get_path('module', 'devutil')) . '/' . $name;
      }
      if (!$label) {
        $label = $name;
      }
      \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
      $info = [
        'name' => $label,
        'description' => (string)t('Entity Type @name', [
          '@name' => $label,
        ]),
        'type' => 'module',
        'core_version_requirement' => '^8.9 || ^9'
      ];
      file_put_contents($dir . '/' . $name . '.info.yml', Yaml::encode($info));
      $this->_moduleName = $name;
      $this->_moduleDir  = $dir;
    }
  }
  
  private function _createPermissions()
  {
    $ops = [
      'view'        => 'display',
      'edit'        => 'edit',
      'delete'      => 'delete',
      'create'      => 'create',
      'administer'  => 'administer',
    ];
    $perms = $this->_getExistingYmlContent('permissions');
    foreach($ops as $name => $label) {
      $perms[$name . ' ' . $this->_name] = [
        'title' => (string)t('@op @entity', [
          '@entity' => $this->_label,
          '@op' => $label,
        ]),
      ];
      if ($name == 'administer') {
        $perms[$name . ' ' . $this->_name]['restrict access'] = TRUE;
      }
    }
    $this->_setYmlContent('permissions', $perms);
  }
  
  private function _createRouting()
  {
    $routing = $this->_getExistingYmlContent('routing');
    $routing['entity.' . $this->_name . '.canonical'] = [
      'path' => str_replace('_', '/', $this->_name) . '/{' . $this->_name . '}',
      'defaults' => [
        '_entity_view' => $this->_name,
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
      ],
      'requirements' => [
        '_entity_access' => $this->_name . '.view',
      ],
    ];
    $routing['entity.' . $this->_name . '.collection'] = [
      'path' => str_replace('_', '/', $this->_name),
      'defaults' => [
        '_entity_list' => $this->_name,
        '_title' => $this->_label,
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_name,
      ],
      'options' => [
        '_admin_route' => 'TRUE',
      ],
    ];
    if ($this->_hasBundle) {
      $routing[$this->_name . '.add.select'] = [
        'path' => str_replace('_', '/', $this->_name) . '/add',
        'defaults' => [
          '_controller' => 'Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_phpName . '::addSelect',
          '_title' => (string)t('Add @label', [
            '@label' => $this->_label,
          ]),
        ],
        'requirements' => [
          '_entity_create_access' => $this->_name,
        ],
        'options' => [
          '_admin_route' => 'TRUE',
        ],
      ];
      $routing['entity.' . $this->_name . '.add-form'] = [
        'path' => str_replace('_', '/', $this->_name) . '/add/{' . $this->_getBundleName() . '}',
        'defaults' => [
          '_entity_form' => $this->_name . '.add',
          '_title' => (string)t('Add @label', [
            '@label' => $this->_label,
          ]),
        ],
        'requirements' => [
          '_entity_create_access' => $this->_name,
        ],
        'options' => [
          '_admin_route' => 'TRUE',
          'parameters' => [
            $this->_getBundleName() => [
              'type' => 'entity:' . $this->_getBundleName(),
            ],
          ],
        ],
      ];
    }
    else {
      $routing['entity.' . $this->_name . '.add_form'] = [
        'path' => str_replace('_', '/', $this->_name) . '/add',
        'defaults' => [
          '_entity_form' => $this->_name . '.add',
          '_title' => (string)t('Add @label', [
            '@label' => $this->_label,
          ]),
        ],
        'requirements' => [
          '_entity_create_access' => $this->_name,
        ],
        'options' => [
          '_admin_route' => 'TRUE',
        ],
      ];
    }
    $routing['entity.' . $this->_name . '.edit_form'] = [
      'path' => str_replace('_', '/', $this->_name) . '/{' . $this->_name . '}/edit',
      'defaults' => [
        '_entity_form' => $this->_name . '.edit',
        '_title' => (string)t('Edit'),
      ],
      'requirements' => [
        '_entity_access' => $this->_name . '.edit',
      ],
      'options' => [
        '_admin_route' => 'TRUE',
      ],
    ];
    $routing['entity.' . $this->_name . '.delete_form'] = [
      'path' => str_replace('_', '/', $this->_name) . '/{' . $this->_name . '}/delete',
      'defaults' => [
        '_entity_form' => $this->_name . '.delete',
        '_title' => (string)t('Delete'),
      ],
      'requirements' => [
        '_entity_access' => $this->_name . '.delete',
      ],
      'options' => [
        '_admin_route' => 'TRUE',
      ],
    ];
    if ($this->_hasBundle) {
      $routing[$this->_name . '.settings'] = [
        'path' => 'admin/structure/' . str_replace('_', '/', $this->_name),
        'defaults' => [
          '_entity_list' => $this->_getBundleName(),
          '_title' => (string)t('Settings'),
        ],
        'requirements' => [
          '_permission' => 'administer ' . $this->_name,
        ],
      ];
    }
    else {
      $routing[$this->_name . '.settings'] = [
        'path' => 'admin/structure/' . str_replace('_', '/', $this->_name),
        'defaults' => [
          '_form' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'SettingsForm',
          '_title' => (string)t('Settings'),
        ],
        'requirements' => [
          '_permission' => 'administer ' . $this->_name,
        ],
      ];
    }
    $this->_setYmlContent('routing', $routing);
  }
  
  private function _createLinks()
  {
    $menu = $this->_getExistingYmlContent('links.menu');
    $menu['entity.' . $this->_name . '.collection'] = [
      'title' => $this->_label,
      'route_name' => 'entity.' . $this->_name . '.collection',
      'parent' => 'system.admin_content',
    ];
    $menu[$this->_name . '.settings'] = [
      'title' => $this->_label,
      'route_name' => $this->_name . '.settings',
      'parent' => 'system.admin_structure',
    ];
    $this->_setYmlContent('links.menu', $menu);
    
    $actions = $this->_getExistingYmlContent('links.action');
    if ($this->_hasBundle) {
      $actions[$this->_name . '.add.select'] = [
        'route_name' => $this->_name . '.add.select',
        'title' => (string)t('Add @label', [
                      '@label' => $this->_label,
                    ]),
        'appears_on' => [
          'entity.' . $this->_name . '.collection',
        ],
      ];
      $actions[$this->_getBundleName() . '.add'] = [
        'route_name' => $this->_getBundleName() . '.add',
        'title' => (string)t('Create a new type'),
        'appears_on' => [
          $this->_name . '.settings',
          'entity.' . $this->_getBundleName() . '.collection',
        ],
      ];
    }
    else {
      $actions['entity.' . $this->_name . '.add_form'] = [
        'route_name' => 'entity.' . $this->_name . '.add_form',
        'title' => (string)t('Add @label', [
                      '@label' => $this->_label,
                    ]),
        'appears_on' => [
          'entity.' . $this->_name . '.collection',
        ],
      ];
    }
    $this->_setYmlContent('links.action', $actions);
    
    $tasks = $this->_getExistingYmlContent('links.task');
    $tasks['entity.' . $this->_name . '.canonical'] = [
      'route_name' => 'entity.' . $this->_name . '.canonical',
      'base_route' => 'entity.' . $this->_name . '.canonical',
      'weight' => -9,
      'title' => (string)t('View'),
    ];
    $tasks['entity.' . $this->_name . '.edit_form'] = [
      'route_name' => 'entity.' . $this->_name . '.edit_form',
      'base_route' => 'entity.' . $this->_name . '.canonical',
      'weight' => 8,
      'title' => (string)t('Edit'),
    ];
    $tasks['entity.' . $this->_name . '.delete_form'] = [
      'route_name' => 'entity.' . $this->_name . '.delete_form',
      'base_route' => 'entity.' . $this->_name . '.canonical',
      'weight' => 9,
      'title' => (string)t('Delete'),
    ];
    $tasks[$this->_name . '.settings'] = [
      'route_name' => $this->_name . '.settings',
      'base_route' => $this->_name . '.settings',
      'weight' => -9,
      'title' => (string)t('Settings'),
    ];
    if ($this->_hasBundle) {
      $tasks['entity.' . $this->_name . '.edit_form'] = [
        'route_name' => 'entity.' . $this->_name . '.edit_form',
        'base_route' => 'entity.' . $this->_name . '.edit_form',
        'weight' => -9,
        'title' => (string)t('Edit'),
      ];
    }
    $this->_setYmlContent('links.task', $tasks);
  }
  
  private function _createEntityClass()
  {
    $dir = $this->_moduleDir . '/src';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . ';',
      '',
      'use Drupal\Core\Entity\ContentEntityInterface;',
      'use Drupal\user\EntityOwnerInterface;',
      'use Drupal\Core\Entity\EntityChangedInterface;',
      '',
      '/**',
      ' * ' . (string)t('@type Interface', [
        '@type' => $this->_label,
      ]),
      ' */',
      'interface ' . $this->_phpName . 'Interface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'Interface.php', implode("\n", $lines));
    $dir = $this->_moduleDir . '/src/Entity';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if ($this->_hasBundle) {
      $bundleLine = ' *   bundle_entity_type = "' . $this->_getBundleName() . '",';
      $bundleKeyLine = ' *     "bundle" = "type",';
      $typeLine = '    $fields[\'type\'] = BaseFieldDefinition::create(\'entity_reference\')
                ->setLabel(t(\'Typ\'))
                ->setDescription(t(\'' . t('@label Type', ['@label' => $this->_label]) . '\'))
                ->setSetting(\'target_type\', \'' . $this->_getBundleName() . '\')
                ->setReadOnly(TRUE);';
      $fieldUiLine = ' *   field_ui_base_route = "entity.' . $this->_getBundleName() . '.edit_form",';
      $addFormLine = ' *     "add-form" = "/' . str_replace('_', '/', $this->_name) . '/add/{' . $this->_getBundleName() . '}",';
    }
    else {
      $bundleLine = '';
      $bundleKeyLine = '';
      $typeLine = '// No Bundles';
      $fieldUiLine = ' *   field_ui_base_route = "' . $this->_name . '.settings' . '",';
      $addFormLine = ' *     "add-form" = "/' . str_replace('_', '/', $this->_name) . '/add",';
    }
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Entity;',
      '',
      'use Drupal\Core\Entity\EntityStorageInterface;',
      'use Drupal\Core\Field\BaseFieldDefinition;',
      'use Drupal\Core\Entity\ContentEntityBase;',
      'use Drupal\Core\Entity\EntityTypeInterface;',
      'use Drupal\Core\Entity\EntityChangedTrait;',
      'use Drupal\user\UserInterface;',
      'use Drupal\\' . $this->_moduleName . '\\' . $this->_phpName . 'Interface;',
      '',
      '/**',
      ' * Entity Type ' . $this->_label,
      ' *',
      ' * @author ' . $this->_yourName,
      ' * ' . date('d.m.Y'),
      ' *',
      ' * @ContentEntityType(',
      ' *   id = "' . $this->_name . '",',
      ' *   label = @Translation("' . $this->_label . '"),',
      ' *   handlers = {',
      ' *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",',
      ' *     "list_builder" = "Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_phpName . 'List",',
      ' *     "views_data" = "Drupal\views\EntityViewsData",',
      ' *     "form" = {',
      ' *       "add" = "Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'Form",',
      ' *       "edit" = "Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'Form",',
      ' *       "delete" = "Drupal\\' . $this->_moduleName . '\Form\\' . $this->_phpName . 'DeleteForm",',
      ' *     },',
      ' *     "access" = "Drupal\\' . $this->_moduleName . '\\' . $this->_phpName . 'Access",',
      ' *   },',
      ' *   base_table = "' . $this->_name . '",',
      ' *   admin_permission = "administer ' . $this->_name . '",',
      $bundleLine,
      ' *   fieldable = TRUE,',
      ' *   entity_keys = {',
      ' *     "id" = "id",',
      ' *     "label" = "title",',
      ' *     "uuid" = "uuid",',
      $bundleKeyLine,
      ' *   },',
      ' *   links = {',
      ' *     "canonical" = "/' . str_replace('_', '/', $this->_name) . '/{' . $this->_name . '}",',
      $addFormLine,
      ' *     "edit-form" = "/' . str_replace('_', '/', $this->_name) . '/{' . $this->_name . '}/edit",',
      ' *     "delete-form" = "/' . str_replace('_', '/', $this->_name) . '/{' . $this->_name . '}/delete",',
      ' *     "collection" = "/' . str_replace('_', '/', $this->_name) . '",',
      ' *   },',
      $fieldUiLine,
      ' * )',
      ' */',
      'class ' . $this->_phpName . ' extends ContentEntityBase implements ' . $this->_phpName . 'Interface {',
      '',
      '  use EntityChangedTrait; // Implements methods defined by EntityChangedInterface.',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {',
      '   parent::preCreate($storage_controller, $values);',
      '    $values += [',
      '      \'user_id\' => \Drupal::currentUser()->id(),',
      '    ];',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function getCreatedTime() {',
      '    return $this->get(\'created\')->value;',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function getOwner() {',
      '    return $this->get(\'user_id\')->entity;',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function getOwnerId() {',
      '    return $this->get(\'user_id\')->target_id;',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function setOwnerId($uid) {',
      '    $this->set(\'user_id\', $uid);',
      '    return $this;',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function setOwner(UserInterface $account) {',
      '    $this->set(\'user_id\', $account->id());',
      '    return $this;',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   *',
      '   * ' . (string)t('This method may need a manual edit'),
      '   *',
      '   */',
      '  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {',
      '    $fields[\'id\'] = BaseFieldDefinition::create(\'integer\')',
      '      ->setLabel(t(\'ID\'))',
      '      ->setDescription(t(\'Entity Id\'))',
      '      ->setReadOnly(TRUE);',
      '    $fields[\'uuid\'] = BaseFieldDefinition::create(\'uuid\')',
      '      ->setLabel(t(\'UUID\'))',
      '      ->setDescription(t(\'Entity UUID\'))',
      '      ->setReadOnly(TRUE);',
      '    $fields[\'title\'] = BaseFieldDefinition::create(\'string\')',
      '      ->setLabel(t(\'Title\'))',
      '      ->setDescription(t(\'' . (string)t('@type label', [
                                                  '@type' => $this->_label,
                                                ]) . '\'))',
      '      ->setSettings([',
      '        \'default_value\' => \'\',',
      '        \'max_length\' => 255,',
      '        \'text_processing\' => 0,',
      '      ])',
      '      ->setDisplayOptions(\'view\', [',
      '        \'label\' => \'above\',',
      '        \'type\' => \'string\',',
      '        \'weight\' => -19,',
      '      ])',
      '      ->setDisplayOptions(\'form\', [',
      '        \'type\' => \'string_textfield\',',
      '        \'weight\' => -19,',
      '      ])',
      '      ->setDisplayConfigurable(\'form\', TRUE)',
      '      ->setDisplayConfigurable(\'view\', TRUE);',
      $typeLine,
      '    $fields[\'user_id\'] = BaseFieldDefinition::create(\'entity_reference\')',
      '      ->setLabel(t(\'User\'))',
      '      ->setSetting(\'target_type\', \'user\')',
      '      ->setSetting(\'handler\', \'default\')',
      '      ->setReadOnly(TRUE);',
      '    $fields[\'created\'] = BaseFieldDefinition::create(\'created\')',
      '      ->setLabel(t(\'Created\'))',
      '      ->setDescription(t(\'Create Date\'));',
      '    $fields[\'changed\'] = BaseFieldDefinition::create(\'changed\')',
      '      ->setLabel(t(\'Changed\'))',
      '      ->setDescription(t(\'Change Date\'));',
      '',
      '    // ' . (string)t('You can add additional fields here'),
      '',
      '    return $fields;',
      '  }',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . '.php', implode("\n", $lines));
    $dir = $this->_moduleDir . '/src/Controller';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Controller;',
      '',
      'use Drupal\Core\Entity\EntityInterface;',
      'use Drupal\Core\Entity\EntityListBuilder;',
      'use Drupal\Core\Url;',
      '',
      '/**',
      ' * ' . t('@type List', [
        '@type' => $this->_label,
      ]),
      ' *',
      ' * @author ' . $this->_yourName,
      ' * ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'List extends EntityListBuilder {',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function buildHeader() {',
      '    $header = [',
      '      t(\'Title\')',
      '    ];',
      '    // You can add custom header elements, e.g.:',
      '    // $header[\'fieldName\'] = t(\'Field Name\');',
      '    return $header + parent::buildHeader();',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function buildRow(EntityInterface $entity) {',
      '    $row = [',
      '      $entity->toLink()->toString(),',
      '    ];',
      '    // You can add custom row elements, e.g.:',
      '    // $row[\'fieldName\'] = $entity->get(\'fieldName\')->get(0)->get(\'value\')->getValue();',
      '    return $row + parent::buildRow($entity);',
      '  }',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'List.php', implode("\n", $lines));
  }
  
  private function _createForms()
  {
    $dir = $this->_moduleDir . '/src/Form';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Form;',
      '',
      'use Drupal\Core\Entity\ContentEntityForm;',
      '',
      '/**',
      ' * @file ' . (string)t('Create or edit @type', [
         '@type' => $this->_label,
       ]),
      ' *',
      ' * @author ' . $this->_yourName,
      ' * @date ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'Form extends ContentEntityForm {',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'Form.php', implode("\n", $lines));
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Form;',
      '',
      'use Drupal\Core\Entity\ContentEntityDeleteForm;',
      '',
      '/**',
      ' * @file ' . (string)t('Delete @type', [
         '@type' => $this->_label,
       ]),
      ' *',
      ' * @author ' . $this->_yourName,
      ' * @date ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'DeleteForm extends ContentEntityDeleteForm {',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'DeleteForm.php', implode("\n", $lines));
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\Form;',
      '',
      'use Drupal\Core\Form\FormBase;',
      '',
      '/**',
      ' * @file Settings',
      ' *',
      ' * @author ' . $this->_yourName,
      ' * @date ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'SettingsForm extends FormBase {',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function getFormId() {',
      '    return \'' . $this->_name . '_settings_form\';',
      '  }',
      '',
      '  /**',
      '  * {@inheritdoc}',
      '  *',
      '  * If you have custom settings you can build a form here.',
      '  */',
      '  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {',
      '    $form = [',
      '      \'#markup\' => t(\'Fields can be configured here\'),',
      '    ];',
      '    return $form;',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {',
      '    return parent::submitForm($form, $form_state);',
      '  }',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'SettingsForm.php', implode("\n", $lines));
  }
  
  private function _createAccessControl()
  {
    $dir = $this->_moduleDir . '/src';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . ';',
      '',
      'use Drupal\Core\Access\AccessResult;',
      'use Drupal\Core\Entity\EntityAccessControlHandler;',
      'use Drupal\Core\Entity\EntityInterface;',
      'use Drupal\Core\Session\AccountInterface;',
      '',
      '/**',
      ' * Zugriffskontrolle',
      ' *',
      ' * @author ' . $this->_yourName,
      ' * @date ' . date('d.m.Y'),
      ' */',
      'class ' . $this->_phpName . 'Access extends EntityAccessControlHandler {',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {',
      '    switch ($operation) {',
      '      case \'view\':',
      '        return AccessResult::allowedIfHasPermission($account, \'view ' . $this->_name . '\');',
      '      case \'edit\':',
      '      case \'update\':',
      '        return AccessResult::allowedIfHasPermission($account, \'edit ' . $this->_name . '\');',
      '      case \'delete\':',
      '        return AccessResult::allowedIfHasPermission($account, \'delete ' . $this->_name . '\');',
      '      default:',
      '        throw new \Exception(t(\'Unknown Operation: @op\', [',
      '          \'@op\' => $op,',
      '        ]));',
      '    }',
      '  }',
      '',
      '  /**',
      '   * {@inheritdoc}',
      '   */',
      '  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {',
      '    return AccessResult::allowedIfHasPermission($account, \'create ' . $this->_name . '\');',
      '  }',
      '',
      '}',
    ];
    file_put_contents($dir . '/' . $this->_phpName . 'Access.php', implode("\n", $lines));
  }
  
  /**
   * Current file content or an empty array
   * @param string $fileName
   * @return array
   */
  private function _getExistingYmlContent($fileName)
  {
    $filePath = $this->_moduleDir . '/' . $this->_moduleName . '.' .$fileName . '.yml';
    if (file_exists($filePath)) {
      $content = file_get_contents($filePath);
      return Yaml::decode($content);
    }
    else {
      return [];
    }
  }
  
  private function _setYmlContent($fileName, $content)
  {
    $filePath = $this->_moduleDir . '/' . $this->_moduleName . '.' . $fileName . '.yml';
    file_put_contents($filePath, Yaml::encode($content));
  }
    
  private function _createTemplate()
  {
    $dir = $this->_moduleDir . '/theme';
    if (\Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $content = '{# ' . $this->_label . ' #}';
      $content .= "\n\n";
      $content .= "{{ content }}\n\n";
      $content .= "{#\nYou can change this template according to your goals\n#}";
      file_put_contents($dir . '/' . str_replace('_', '-', $this->_name) . '.html.twig', $content);
    }
    else {
      \Drupal::logger('DevUtil')->error('Theme Directory can not be created');
    }
  }
  
  private function _createThemeHook()
  {
    $moduleFile = $this->_moduleDir . '/' . $this->_moduleName . '.module'; 
    if (!is_file($moduleFile)) {
      $lines = [
        '<?php',
        '',
        '/**',
        ' * ' . $this->_label,
        ' *',
        ' * @author ' . $this->_yourName,
        ' * @date ' . date('d.m.Y'),
        ' */',
        '',
      ];
      file_put_contents($moduleFile, implode("\n", $lines));
    }
    $moduleContent = file_get_contents($moduleFile);
    $functionName = $this->_moduleName . '_theme()';
    $p = strpos(str_replace(["\n", "\r"], '', $moduleContent), $functionName);
    if (!$p) {
      $lines = [
        '/**',
        ' * Implements hook_theme()',
        ' */',
        'function ' . $this->_moduleName . '_theme()',
        '{',
        '  $hooks = [',
        '  ];',
        '  return $hooks;',
        '}',
      ];
      file_put_contents($moduleFile, $moduleContent . implode("\n", $lines));
    }
    $moduleLines = file($moduleFile);
    foreach($moduleLines as $index => $line) {
      if (preg_match('/^function ' . $functionName . '/', $line)) {
        $functionIndex = $index;
      }
    }
    $lines = [];
    foreach($moduleLines as $index => $line) {
      if ($index < $functionIndex + 3) {
        $lines[] = str_replace(["\n", "\r"], '', $line);
      }
    }
    $hookLines = [
      '    //' . $this->_label,
      '    \'' . $this->_name . '\' => [',
      '      \'render element\' => \'elements\',',
      '      \'template\' => \'' . str_replace('_', '-', $this->_name) . '\',',
      '      \'path\' => drupal_get_path(\'module\', \'' . $this->_moduleName . '\') . \'/theme\',',
      '    ],',
    ];
    $lines = array_merge($lines, $hookLines);
    foreach($moduleLines as $index => $line) {
      if ($index >= $functionIndex + 3) {
        $lines[] = str_replace(["\n", "\r"], '', $line);
      }
    }
    $lines[] = '';
    $lines[] = 'function template_preprocess_' . $this->_name . '(&$variables): void';
    $lines[] = '{';
    $lines[] = '  $variables[\'content\'] = $variables[\'elements\'];';
    $lines[] = '}';
    function template_preprocess_feed(&$variables): void
{
  $variables['content'] = $variables['elements'];
}
    file_put_contents($moduleFile, implode("\n", $lines));
  }
  
}
