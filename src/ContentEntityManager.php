<?php

namespace Drupal\devutil;

use PhpParser\ParserFactory;
use PhpParser\BuilderFactory;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\NodeTraverser;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\AssignOp\Plus as AssignPlus;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Param;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;
use Drupal\Core\File\FileSystemInterface;
use Drupal\devutil\EntityManagerBase;

/**
 * Content Entity Manager
 *
 * @author Attila Németh, UBG
 * @date 03.07.2023
 */
class ContentEntityManager extends EntityManagerBase {
  
  private     $_hasBundle               = false;
  private     $_hasBundleCasses         = false;
  
  /**
   * {@inheritDoc}
   */
  public function createCode(string $name, string $label, array $options): void {
    
    $this->_entityTypeName = $name;
    $this->_entityTypeLabel = $label;
    if (array_key_exists('name', $options)) {
      $this->_author = $options['name'];
    }
    else {
      $this->_author = NULL;
    }
    if (array_key_exists('module', $options) && !empty($options['module'])) {
      $this->_createModule($options['module']);
    }
    else {
      if (array_key_exists('path', $options) && !empty($options['path'])) {
        $this->_modulePath = $options['path'];
      }
      $this->_createModule($name);
    }
    if (array_key_exists('bundles', $options) && !empty($options['bundles'])) {
      $this->_hasBundle = TRUE;
      if (array_key_exists('bundle-classes', $options) && $options['bundle-classes']) {
        $this->_hasBundleCasses = true;
      }
    }
    else {
      $this->_hasBundle = FALSE;
    }
    $this->_createPermissions();
    $this->_createRouting();
    if ($this->_hasBundle) {
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
   * Permissions.yml
   */
  protected function _createPermissions(): void
  {
    $perms = $this->_loadYml('permissions');
    $perms['administer ' . $this->_entityTypeName] = [
      'title' => (string)t('Administer @label', $this->_getTranslation()),
      'description' => (string)t('Configure @label', $this->_getTranslation()),
      'restrict access' => TRUE,
    ];
    $perms['create ' . $this->_entityTypeName] = [
      'title' => (string)t('Create @label', $this->_getTranslation()),
      'description' => (string)t('Create @label', $this->_getTranslation()),
    ];
    $perms['edit ' . $this->_entityTypeName] = [
      'title' => (string)t('Edit @label', $this->_getTranslation()),
      'description' => (string)t('Update @label', $this->_getTranslation()),
    ];
    $perms['delete ' . $this->_entityTypeName] = [
      'title' => (string)t('Delete @label', $this->_getTranslation()),
      'description' => (string)t('Delete @label', $this->_getTranslation()),
    ];
    $perms['view ' . $this->_entityTypeName] = [
      'title' => (string)t('View @label', $this->_getTranslation()),
      'description' => (string)t('View @label', $this->_getTranslation()),
    ];
    $this->_saveYml('permissions', $perms);
  }
  
  /**
   * Routing.yml
   */
  private function _createRouting(): void
  {
    $routing = $this->_loadYml('routing');
    $routing['entity.' . $this->_entityTypeName . '.canonical'] = [
      'path' => str_replace('_', '/', $this->_entityTypeName) . '/{' . $this->_entityTypeName . '}',
      'defaults' => [
        '_entity_view' => $this->_entityTypeName,
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
      ],
      'requirements' => [
        '_entity_access' => $this->_entityTypeName . '.view',
      ],
    ];
    $routing['entity.' . $this->_entityTypeName . '.collection'] = [
      'path' => str_replace('_', '/', $this->_entityTypeName),
      'defaults' => [
        '_entity_list' => $this->_entityTypeName,
        '_title' => $this->_entityTypeLabel,
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_entityTypeName,
      ],
      'options' => [
        '_admin_route' => 'TRUE',
      ],
    ];
    if ($this->_hasBundle) {
      $routing[$this->_entityTypeName . '.add.select'] = [
        'path' => str_replace('_', '/', $this->_entityTypeName) . '/add',
        'defaults' => [
          '_controller' => 'Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_getEntityNameClass() . '::addSelect',
          '_title' => (string)t('Add @label', [
            '@label' => $this->_entityTypeLabel,
          ]),
        ],
        'requirements' => [
          '_entity_create_access' => $this->_entityTypeName,
        ],
        'options' => [
          '_admin_route' => 'TRUE',
        ],
      ];
      $routing['entity.' . $this->_entityTypeName . '.add-form'] = [
        'path' => str_replace('_', '/', $this->_entityTypeName) . '/add/{' . $this->_getBundleName() . '}',
        'defaults' => [
          '_entity_form' => $this->_entityTypeName . '.add',
          '_title' => (string)t('Add @label', [
            '@label' => $this->_entityTypeLabel,
          ]),
        ],
        'requirements' => [
          '_entity_create_access' => $this->_entityTypeName . ':{' . $this->_getBundleName() . '}',
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
      $routing['entity.' . $this->_entityTypeName . '.add_form'] = [
        'path' => str_replace('_', '/', $this->_entityTypeName) . '/add',
        'defaults' => [
          '_entity_form' => $this->_entityTypeName . '.add',
          '_title' => (string)t('Add @label', [
            '@label' => $this->_entityTypeLabel,
          ]),
        ],
        'requirements' => [
          '_entity_create_access' => $this->_entityTypeName,
        ],
        'options' => [
          '_admin_route' => 'TRUE',
        ],
      ];
    }
    $routing['entity.' . $this->_entityTypeName . '.edit_form'] = [
      'path' => str_replace('_', '/', $this->_entityTypeName) . '/{' . $this->_entityTypeName . '}/edit',
      'defaults' => [
        '_entity_form' => $this->_entityTypeName . '.edit',
        '_title' => (string)t('Edit'),
      ],
      'requirements' => [
        '_entity_access' => $this->_entityTypeName . '.edit',
      ],
      'options' => [
        '_admin_route' => 'TRUE',
      ],
    ];
    $routing['entity.' . $this->_entityTypeName . '.delete_form'] = [
      'path' => str_replace('_', '/', $this->_entityTypeName) . '/{' . $this->_entityTypeName . '}/delete',
      'defaults' => [
        '_entity_form' => $this->_entityTypeName . '.delete',
        '_title' => (string)t('Delete'),
      ],
      'requirements' => [
        '_entity_access' => $this->_entityTypeName . '.delete',
      ],
      'options' => [
        '_admin_route' => 'TRUE',
      ],
    ];
    if ($this->_hasBundle) {
      $routing[$this->_entityTypeName . '.settings'] = [
        'path' => 'admin/structure/' . str_replace('_', '/', $this->_entityTypeName),
        'defaults' => [
          '_entity_list' => $this->_getBundleName(),
          '_title' => (string)t('Settings'),
        ],
        'requirements' => [
          '_permission' => 'administer ' . $this->_entityTypeName,
        ],
      ];
    }
    else {
      $routing[$this->_entityTypeName . '.settings'] = [
        'path' => 'admin/structure/' . str_replace('_', '/', $this->_entityTypeName),
        'defaults' => [
          '_form' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'SettingsForm',
          '_title' => (string)t('Settings'),
        ],
        'requirements' => [
          '_permission' => 'administer ' . $this->_entityTypeName,
        ],
      ];
    }
    $this->_saveYml('routing', $routing);
  }
  
  /**
   * Create Bundle Code
   */
  private function _createBundle(): void
  {
    $this->_createBundleRouting();
    $this->_createBundleClass();
    if ($this->_hasBundleCasses) {
      $this->_createEntityBundleClass();
    }
    $this->_createBundleForm();
  }
  
  /**
   * Bundle Name
   * @return string
   */
  private function _getBundleName()
  {
    return $this->_entityTypeName . '_type';
  }
  
  /**
   * Routing.yml extension for the bundle
   */
  private function _createBundleRouting(): void
  {
    $routing = $this->_loadYml('routing');
    $routing['entity.' . $this->_getBundleName() . '.collection'] = [
      'path' => 'admin/structure/' . str_replace('_', '/', $this->_entityTypeName),
      'defaults' => [
        '_entity_list' => $this->_getBundleName(),
        '_title' => (string)t('@type Type', ['@type' => $this->_entityTypeLabel]),
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_entityTypeName,
      ],
    ];
    if (!$this->_hasBundleCasses) {
      // If there are bundle classes, all bundles must be defined in code.
      // Therefore it is not possible to create new bundles manually.
      $routing[$this->_getBundleName() . '.add'] = [
        'path' => 'admin/structure/' . str_replace('_', '/', $this->_entityTypeName) . '/type/add',
        'defaults' => [
          '_entity_form' => $this->_getBundleName() . '.add',
          '_title' => (string)t('Add @label Type', [
            '@label' => $this->_entityTypeLabel,
          ]),
        ],
        'requirements' => [
          '_permission' => 'administer ' . $this->_entityTypeName,
        ],
      ];
    }
    $routing['entity.' . $this->_getBundleName() . '.edit_form'] = [
      'path' => 'admin/structure/' . str_replace('_', '/', $this->_entityTypeName) . '/type/{' . $this->_getBundleName() . '}/edit',
      'defaults' => [
        '_entity_form' => $this->_getBundleName() . '.edit',
        '_title' => (string)t('Edit'),
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_entityTypeName,
      ],
    ];
    $this->_saveYml('routing', $routing);
  }
  
  /**
   * Create Bundle Base class and Controllers
   */
  private function _createBundleClass(): void
  {
    // Interface
    $dir = $this->_modulePath . '/src';
    $result = $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$result) {
      throw new \Exception('SRC Directory can not be created');
    }
    $factory = new BuilderFactory();
    $interfaceNode = $factory->namespace('Drupal\\' . $this->_moduleName)
                    ->addStmt($factory->use('Drupal\Core\Config\Entity\ConfigEntityInterface'))
                    ->addStmt($factory->interface($this->_getEntityNameClass() . 'TypeInterface')
                                                ->extend('ConfigEntityInterface'))
                    ->setDocComment($this->_getDocComment('Configuration Entity Type ' . $this->_entityTypeLabel . ' Type'))
                    ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'TypeInterface', $interfaceNode);
    // Type Class
    $entityDir = $dir . '/Entity';
    $entityResult = $this->_fileSystem->prepareDirectory($entityDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$entityResult) {
      throw new \Exception('Entity Directory can not be created');
    }
    $typeClassAnnotations = [
      'ConfigEntityType' => [
        'id' => $this->_getBundleName(),
        'label' => '@Translation("' . (string)t('@label Type', ['@label' => $this->_entityTypeLabel]) . '")',
        'bundle_of' => $this->_entityTypeName,
        'handlers' => [
          'list_builder' => 'Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_getEntityNameClass() . 'TypeList',
          'form' => [
            'default' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'TypeForm',
            'add' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'TypeForm',
            'edit' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'TypeForm',
          ],
        ],
        'config_prefix' => $this->_getBundleName(),
        'admin_permission' => 'administer ' . $this->_entityTypeName,
        'entity_keys' => [
          'id' => 'id',
          'label' => 'label',
          'uuid' => 'uuid',
        ],
        'config_export' => [
          'id',
          'label',
          'uuid',
        ],
        'links' => [
          'add-form' => '/admin/structure/' . $this->_getEntityNamePath() . '/type/add',
          'edit-form' => '/admin/structure/' . $this->_getEntityNamePath() . '/type/{' . $this->_getBundleName() . '}/edit',
          'collection' => '/admin/structure/' . $this->_getEntityNamePath() . '/type',
        ],
      ],
    ];
    $typeClassNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Entity')
          ->addStmt($factory->use('Drupal\Core\Config\Entity\ConfigEntityBundleBase'))
          ->addStmt($factory->use('Drupal\\' . $this->_moduleName . '\\' . $this->_getEntityNameClass() . 'TypeInterface'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'Type')
                ->extend('ConfigEntityBundleBase')
                ->setDocComment($this->_getDocComment('Entity ' . $this->_entityTypeLabel . ' Type',  $typeClassAnnotations)))
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'Type', $typeClassNode, 'Entity');
    // List Builder
    $listBuilderDir = $dir . '/Controller';
    $listBuilderResult = $this->_fileSystem->prepareDirectory($listBuilderDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$listBuilderResult) {
      throw new \Exception('Controller Directory can not be created');
    }
    $listBuilderComment = $this->_getDocComment((string)t('@label Type List', [
      '@label' => $this->_entityTypeLabel
    ]));
    $listBuilderNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Contoller')
          ->addStmt($factory->use('Drupal\Core\Entity\EntityListBuilder'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityInterface'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'List')
                ->extend('EntityListBuilder')
                ->setDocComment($listBuilderComment)
                ->addStmt($factory->method('buildHeader')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addStmt(new Assign(new Variable('header'), new Array_([
                        new ArrayItem(new String_(t('Name')))
                      ])))
                      ->addStmt(new Return_(new Plus(new Variable('header'), $factory->staticCall('parent', 'buildHeader'))))
                )
                ->addStmt($factory->method('buildRow')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('bundleEntity'), null, 'EntityInterface'))
                      ->addStmt(new Assign(new Variable('row'), new Array_([
                        new ArrayItem($factory->methodCall(new Variable('bundleEntity'), 'label'))
                      ])))
                      ->addStmt(new Return_(new Plus(new Variable('row'), $factory->staticCall('parent', 'buildRow', [new Variable('bundleEntity')]))))
                )
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'TypeList', $listBuilderNode, 'Controller');
    // Controller
    $controllerNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Contoller')
          ->addStmt($factory->use('Drupal\Core\Controller\ControllerBase'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityTypeManagerInterface'))
          ->addStmt($factory->class($this->_getEntityNameClass())
                ->extend('ControllerBase')
                ->setDocComment($this->_getDocComment(t('@type Controller', [
                  '@type' => $this->_entityTypeLabel,
                ])))
                ->addStmt($factory->property('_entityTypeManager')->makePrivate())
                ->addStmt($factory->method('__construct')
                      ->makePublic()
                      ->addParam(new Param(new Variable('entityTypeManager'), null, 'EntityTypeManagerInterface'))
                      ->addStmt(new Assign(new Variable('this->_entityTypeManager'), new Variable('entityTypeManager')))
                )
                ->addStmt($factory->method('addSelect')
                      ->makePublic()
                      ->setDocComment("/**\n * This method selects simply the first option\n * Expand it if you need another logic.\n */")
                      ->addStmt(new Assign(new Variable('types'), $factory->methodCall($factory->methodCall(
                            new Variable('this->_entityTypeManager'), 'getStorage', [
                              new ArrayItem(new String_($this->_getBundleName()))
                            ]
                      ), 'loadMultiple')))
                      ->addStmt(new Assign(new Variable('type'), $factory->funcCall('current', [new Variable('types')])))
                      ->addStmt(new Return_($factory->methodCall(new Variable('this'), 'redirect', [
                        new String_('entity.' . $this->_entityTypeName . '.add-form'),
                        new Array_([
                          new ArrayItem($factory->methodCall(new Variable('type'), 'id'), new String_($this->_getBundleName())),
                        ])
                      ])))
                )
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass(), $controllerNode, 'Controller');
  }
  
  /**
   * Bundle Form is created
   * @throws \Exception
   */
  private function _createBundleForm(): void
  {
    $factory = new BuilderFactory();
    $dir = $this->_modulePath . '/src/Form';
    $result = $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$result) {
      throw new \Exception('Form Directory can not be created');
    }
    $node = $factory->namespace('Drupal\\' . $this->_moduleName . '\Form')
          ->addStmt($factory->use('Symfony\Component\DependencyInjection\ContainerInterface'))
          ->addStmt($factory->use('Drupal\Core\Entity\BundleEntityFormBase'))
          ->addStmt($factory->use('Drupal\Core\Form\FormStateInterface'))
          ->addStmt($factory->use('Drupal\Core\Messenger\MessengerInterface'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'TypeForm')
                ->extend('BundleEntityFormBase')
                ->setDocComment($this->_getDocComment(t('Create or Edit @type Type', [
                  '@type' => $this->_entityTypeLabel,
                ])))
                ->addStmt($factory->property('_messenger')->makePrivate())
                ->addStmt($factory->method('__construct')
                      ->makePublic()
                      ->addParam(new Param(new Variable('messenger'), null, 'MessengerInterface'))
                      ->addStmt(new Assign(new Variable('this->_messenger'), new Variable('messenger')))
                )
                ->addStmt($factory->method('create')
                      ->makeStatic()
                      ->makePublic()
                      ->addParam(new Param(new Variable('container'), null, 'ContainerInterface'))
                      ->addStmt(new Return_($factory->new('static', [
                        $factory->methodCall(new Variable('container'), 'get', [new String_('messenger')])
                      ])))
                )
                ->addStmt($factory->method('form')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('form'), null, 'array'))
                      ->addParam(new Param(new Variable('form_state'), null, 'FormStateInterface'))
                      ->addStmt(new Assign(new Variable('form'), $factory->staticCall('parent', 'form', [
                        new Variable('form'),
                        new Variable('form_state')
                      ])))
                      ->addStmt(new Assign(
                            new ArrayDimFetch(new Variable('form'), new String_('label'), []),
                            new Array_([
                              new ArrayItem(new String_('textfield'), new String_('#type')),
                              new ArrayItem($factory->methodCall(new Variable('this'), 't', [
                                'Name'
                              ]), new String_('#title')),
                              new ArrayItem($factory->methodCall(new Variable('this'), 't', [
                                'Name of @label Type', [
                                  '@label' => $this->_entityTypeLabel,
                                ]
                              ]), new String_('#description')),
                              new ArrayItem(new LNumber(255), new String_('#maxlength')),
                              new ArrayItem($factory->methodCall(new Variable('this->entity'), 'label'), new String_('#default_value')),
                              new ArrayItem(new ConstFetch(new Name('true')), new String_('#required')),
                            ])
                      ))
                      ->addStmt(new Assign(
                            new ArrayDimFetch(new Variable('form'), new String_('id'), []),
                            new Array_([
                              new ArrayItem(new String_('machine_name'), new String_('#type')),
                              new ArrayItem($factory->methodCall(new Variable('this->entity'), 'id'), new String_('#default_value')),
                              new ArrayItem(new Array_([
                                new ArrayItem(new String_('\Drupal\\' . $this->_moduleName . '\Entity\\' . $this->_getBundleName() . 'Type::load'), new String_('exists'))
                              ]), new String_('#machine_name')),
                              new ArrayItem(new BooleanNot($factory->methodCall(new Variable('this->entity'), 'isNew')), new String_('#disabled')),
                            ])
                      ))
                      ->addStmt(new Return_($factory->methodCall(new Variable('this'), 'protectBundleIdElement', [
                        new Variable('form')
                      ])))
                )
                ->addStmt($factory->method('save')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('form'), null, 'array'))
                      ->addParam(new Param(new Variable('form_state'), null, 'FormStateInterface'))
                      ->addStmt(new Assign(new Variable('status'), $factory->methodCall(new Variable('this->entity'), 'save')))
                      ->addStmt(new Assign(new Variable('params'), new Array_([
                        new ArrayItem($factory->methodCall(new Variable('this->entity'), 'label'), new String_('%label')),
                        new ArrayItem($factory->methodCall($factory->methodCall(new Variable('this->entity'), 'getEntityType'), 'getBundleOf'), new String_('%content_entity_id')),
                      ])))
                      ->addStmt(new Switch_(new Variable('status'), [
                        new Case_(new ConstFetch(new Name('SAVED_NEW')), [
                          new Expression($factory->methodCall(new Variable('this->_messenger'), 'addStatus', [
                            $factory->methodCall(new Variable('this'), 't', [
                              '@label type is created',
                              ['@label' => $this->_entityTypeLabel]
                            ])
                          ])),
                          new Break_,
                        ]),
                        new Case_(null, [
                          new Expression($factory->methodCall(new Variable('this->_messenger'), 'addStatus', [
                            $factory->methodCall(new Variable('this'), 't', [
                              '@label type is updated',
                              ['@label' => $this->_entityTypeLabel]
                            ])
                          ])),
                          new Break_,
                        ])
                      ]))
                      ->addStmt($factory->methodCall(new Variable('form_state'), 'setRedirectUrl', [
                        $factory->methodCall(new Variable('this->entity'), 'toUrl', [
                          new String_('collection'),
                        ])
                      ]))
                      ->addStmt(new Return_(new Variable('status')))
                )
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'TypeForm', $node, 'Form');
  }
  
  /**
   * links.menu.yml, links.action.yml, links.task.yml
   */
  private function _createLinks(): void
  {
    // Menu
    $menu = $this->_loadYml('links.menu');
    $menu['entity.' . $this->_entityTypeName . '.collection'] = [
      'title' => $this->_entityTypeLabel,
      'route_name' => 'entity.' . $this->_entityTypeName . '.collection',
      'parent' => 'system.admin_content',
    ];
    $menu[$this->_entityTypeName . '.settings'] = [
      'title' => $this->_entityTypeLabel,
      'route_name' => $this->_entityTypeName . '.settings',
      'parent' => 'system.admin_structure',
    ];
    $this->_saveYml('links.menu', $menu);
    // Actions
    $actions = $this->_loadYml('links.actions');
    if ($this->_hasBundle) {
      $actions[$this->_entityTypeName . '.add.select'] = [
        'route_name' => $this->_entityTypeName . '.add.select',
        'title' => (string)t('Add @label', [
                      '@label' => $this->_entityTypeLabel,
                    ]),
        'appears_on' => [
          'entity.' . $this->_entityTypeName . '.collection',
        ],
      ];
      if (!$this->_hasBundleCasses) {
      // If there are bundle classes, all bundles must be defined in code.
      // Therefore it is not possible to create new bundles manually.
        $actions[$this->_getBundleName() . '.add'] = [
          'route_name' => $this->_getBundleName() . '.add',
          'title' => (string)t('Create a new type'),
          'appears_on' => [
            $this->_entityTypeName . '.settings',
            'entity.' . $this->_getBundleName() . '.collection',
          ],
        ];
      }
    }
    else {
      $actions['entity.' . $this->_entityTypeName . '.add_form'] = [
        'route_name' => 'entity.' . $this->_entityTypeName . '.add_form',
        'title' => (string)t('Add @label', [
                      '@label' => $this->_entityTypeLabel,
                    ]),
        'appears_on' => [
          'entity.' . $this->_entityTypeName . '.collection',
        ],
      ];
    }
    $this->_saveYml('links.actions', $actions);
    // Task
    $tasks = $this->_loadYml('links.task');
     $tasks['entity.' . $this->_entityTypeName . '.canonical'] = [
      'route_name' => 'entity.' . $this->_entityTypeName . '.canonical',
      'base_route' => 'entity.' . $this->_entityTypeName . '.canonical',
      'weight' => -9,
      'title' => (string)t('View'),
    ];
    $tasks['entity.' . $this->_entityTypeName . '.edit_form'] = [
      'route_name' => 'entity.' . $this->_entityTypeName . '.edit_form',
      'base_route' => 'entity.' . $this->_entityTypeName . '.canonical',
      'weight' => 8,
      'title' => (string)t('Edit'),
    ];
    $tasks['entity.' . $this->_entityTypeName . '.delete_form'] = [
      'route_name' => 'entity.' . $this->_entityTypeName . '.delete_form',
      'base_route' => 'entity.' . $this->_entityTypeName . '.canonical',
      'weight' => 9,
      'title' => (string)t('Delete'),
    ];
    $tasks[$this->_entityTypeName . '.settings'] = [
      'route_name' => $this->_entityTypeName . '.settings',
      'base_route' => $this->_entityTypeName . '.settings',
      'weight' => -9,
      'title' => (string)t('Settings'),
    ];
    if ($this->_hasBundle) {
      $tasks['entity.' . $this->_getBundleName() . '.edit_form'] = [
        'route_name' => 'entity.' . $this->_getBundleName() . '.edit_form',
        'base_route' => 'entity.' . $this->_getBundleName() . '.edit_form',
        'weight' => -9,
        'title' => (string)t('Edit'),
      ];
    }
    $this->_saveYml('links.task', $tasks);
  }
  
  public function _createEntityClass(): void
  {
    $factory = new BuilderFactory();
    // Interface
    $interfaceDir = $this->_modulePath . '/src';
    $interfaceResult = $this->_fileSystem->prepareDirectory($interfaceDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$interfaceResult) {
      throw new \Exception('SRC Directory can not be created');
    }
    $interfaceNode = $factory->namespace('Drupal\\' . $this->_moduleName)
          ->addStmt($factory->use('Drupal\Core\Entity\ContentEntityInterface'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityChangedInterface'))
          ->addStmt($factory->use('Drupal\user\EntityOwnerInterface'))
          ->addStmt($factory->interface($this->_getEntityNameClass() . 'Interface')
                ->extend('ContentEntityInterface')
                ->extend('EntityChangedInterface')
                ->extend('EntityOwnerInterface')
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'Interface', $interfaceNode);
    // Entity
    $entityDir = $interfaceDir . '/Entity';
    $entityResult = $this->_fileSystem->prepareDirectory($entityDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$entityResult) {
      throw new \Exception('SRC Directory can not be created');
    }
    $annotations = [
      'ContentEntityType' => [
        'id' => $this->_entityTypeName,
        'label' => '@Translation("' . $this->_entityTypeLabel . '")',
        'label_collection' => '@Translation("' . $this->_entityTypeLabel . '")',
        'label_singular' => '@Translation("' . $this->_entityTypeLabel . '")',
        'label_plural' => '@Translation("' . $this->_entityTypeLabel . 's")',
        'handlers' => [
          'view_builder' => 'Drupal\Core\Entity\EntityViewBuilder',
          'list_builder' => 'Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_getEntityNameClass() . 'List',
          'views_data' => 'Drupal\views\EntityViewsData',
        ],
        'form' => [
          'add' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'Form',
          'edit' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'Form',
          'delete' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'DeleteForm',
        ],
        'access' => 'Drupal\\' . $this->_moduleName . '\\' . $this->_getEntityNameClass() . 'Access',
        'base_table' => $this->_entityTypeName,
        'admin_permission' => 'administer ' . $this->_entityTypeName,
        'fieldable' => true,
        'entity_keys' => [
          'id' => 'id',
          'title' => 'title',
          'uuid' => 'uuid',
        ],
        'links' => [
          'collection' => '/' . $this->_getEntityNamePath(),
          'canonical' => '/' . $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}',
          'edit-form' => '/' .  $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}/edit',
          'delete-form' => '/' . $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}/delete',
        ],
      ],
    ];
    if ($this->_hasBundle) {
      $annotations['ContentEntityType']['bundle_entity_type'] = $this->_getBundleName();
      $annotations['ContentEntityType']['entity_keys']['bundle'] = 'type';
      $annotations['ContentEntityType']['field_ui_base_route'] = 'entity.' . $this->_getBundleName() . '.edit_form';
      $annotations['ContentEntityType']['links']['add-form'] = '/' . $this->_getEntityNamePath() . '/add/{' . $this->_getBundleName() . '}';
    }
    else {
      $annotations['ContentEntityType']['field_ui_base_route'] = $this->_entityTypeName . '.settings';
      $annotations['ContentEntityType']['links']['add-form'] = '/' . $this->_getEntityNamePath() . '/add';
    }
    $baseFieldStms = $factory->method('baseFieldDefinitions')
          ->makePublic()
          ->makeStatic()
          ->addParam(new Param(new Variable('entity_type'), null, 'EntityTypeInterface'))
          ->setDocComment("/**\n * {@inheritDoc}\n\n * This method may need a manual edit\n */")
          ->addStmt(new Assign(
                        new ArrayDimFetch(new Variable('fields'), new String_('id')),
                        $factory->methodCall(
                          $factory->methodCall(
                            $factory->methodCall($factory->staticCall('BaseFieldDefinition', 'create', ['integer']), 'setLabel', [$factory->funcCall('t', [new String_('ID')])]),
                            'setDescription',
                            [$factory->funcCall('t', [new String_('Entity ID')])]
                          ),
                          'setReadOnly',
                          [true]
                        )
                    )
          )
          ->addStmt(new Assign(
                      new ArrayDimFetch(new Variable('fields'), new String_('uuid')),
                      $factory->methodCall(
                        $factory->methodCall(
                          $factory->methodCall(
                                $factory->staticCall('BaseFieldDefinition', 'create', ['uuid']),
                                'setLabel',
                                [$factory->funcCall('t', [new String_('UUID')])]
                          ),
                          'setDescription',
                          [$factory->funcCall('t', [new String_('Entity UUID')])]
                        ),
                        'setReadOnly',
                        [true],
                      )
                    )
          )
          ->addStmt(new Assign(
                      new ArrayDimFetch(new Variable('fields'), new String_('title')),
                      $factory->methodCall(
                        $factory->methodCall(
                          $factory->methodCall(
                            $factory->methodCall(
                              $factory->methodCall(
                                $factory->methodCall(
                                  $factory->methodCall(
                                        $factory->staticCall('BaseFieldDefinition', 'create', ['uuid']),
                                        'setLabel',
                                        [$factory->funcCall('t', [new String_('Title')])]
                                  ),
                                  'setDescription',
                                  [$factory->funcCall('t', [new String_('@type Label'), ['@type' => $this->_entityTypeLabel]])]
                                ),
                                'setSettings',
                                [
                                  new Array_([
                                    new ArrayItem(new String_(''), new String_('default_value')),
                                    new ArrayItem(new LNumber(255), new String_('max_length')),
                                    new ArrayItem(new LNumber(0), new String_('text_processing')),
                                  ])
                                ]
                              ),
                              'setDisplayOptions',
                              [
                                'view',
                                new Array_([
                                  new ArrayItem(new String_('above'), new String_('label')),
                                  new ArrayItem(new String_('string'), new String_('type')),
                                  new ArrayItem(new LNumber(-19), new String_('weight')),
                                ])
                              ]
                            ),
                            'setDisplayOptions',
                            [
                              'view',
                              new Array_([
                                new ArrayItem(new String_('string_textfield'), new String_('type')),
                                new ArrayItem(new LNumber(-19), new String_('weight')),
                              ])
                            ]
                          ),
                          'setDisplayConfigurable',
                          ['form', true]
                        ),
                        'setDisplayConfigurable',
                        ['view', true]
                      ),
                    )
          );
    if ($this->_hasBundle) {
      $baseFieldStms->addStmt(new Assign(
            new ArrayDimFetch(new Variable('fields'), new String_('type')),
            $factory->methodCall(
              $factory->methodCall(
                $factory->methodCall(
                  $factory->methodCall(
                    $factory->staticCall('BaseFieldDefinition', 'create', ['entity_reference']),
                    'setLabel',
                    [$factory->funcCall('t', [new String_('Type')])]
                  ),
                  'setDescription',
                  [$factory->funcCall('t', [new String_('@label Type'), ['@label' => $this->_entityTypeLabel]])]
                ),
                'setSetting',
                [new String_('target_type'), new String_($this->_getBundleName())]
              ),
              'setReadOnly',
              [true]
            )
      ));
    }
    $baseFieldStms->addStmt(new Assign(
          new ArrayDimFetch(new Variable('fields'), new String_('user_id')),
          $factory->methodCall(
            $factory->methodCall(
              $factory->methodCall(
                $factory->methodCall(
                  $factory->methodCall(
                    $factory->staticCall('BaseFieldDefinition', 'create', ['entity_reference']),
                    'setLabel',
                    [$factory->funcCall('t', [new String_('User')])]
                  ),
                  'setDescription',
                  [$factory->funcCall('t', [new String_('Content Author')])]  
                ),
                'setSetting',
                [new String_('target_type'), new String_('user')]
              ),
              'setSetting',
              [new String_('handler'), new String_('default')]
            ),
            'setReadOnly',
            [true]
          )
    ));
    $baseFieldStms->addStmt(new Assign(
          new ArrayDimFetch(new Variable('fields'), new String_('created')),
          $factory->methodCall(
            $factory->methodCall(
              $factory->staticCall('BaseFieldDefinition', 'create', ['created']),
              'setLabel',
              [$factory->funcCall('t', [new String_('Created')])]
            ),
            'setDescription',
            [$factory->funcCall('t', [new String_('Create Date')])]  
          ),
    ));
    $baseFieldStms->addStmt(new Assign(
          new ArrayDimFetch(new Variable('fields'), new String_('changed')),
          $factory->methodCall(
            $factory->methodCall(
              $factory->staticCall('BaseFieldDefinition', 'create', ['changed']),
              'setLabel',
              [$factory->funcCall('t', [new String_('Changed')])]
            ),
            'setDescription',
            [$factory->funcCall('t', [new String_('Change Date')])]  
          ),
    ));
    $baseFieldStms->addStmt(new Return_(new Variable('fields')));
    $classNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Entity')
          ->addStmt($factory->use('Drupal\Core\Entity\EntityStorageInterface'))
          ->addStmt($factory->use('Drupal\Core\Field\BaseFieldDefinition'))
          ->addStmt($factory->use('Drupal\Core\Entity\ContentEntityBase'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityTypeInterface'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityChangedTrait'))
          ->addStmt($factory->use('Drupal\user\UserInterface'))
          ->addStmt($factory->use('Drupal\\' . $this->_moduleName . '\\' . $this->_getEntityNameClass() . 'Interface'))
          ->addStmt($factory->class($this->_getEntityNameClass())
                ->extend('ContentEntityBase')
                ->implement($this->_getEntityNameClass() . 'Interface')
                ->setDocComment($this->_getDocComment('Entity Type ' . $this->_entityTypeLabel, $annotations))
                ->addStmt(new TraitUse([new Name('EntityChangedTrait')]))
                ->addStmt($factory->method('preCreate')
                      ->makePublic()
                      ->makeStatic()
                      ->addParam(new Param(new Variable('storage_controller'), null, 'EntityStorageInterface'))
                      ->addParam(new Param(new Variable('values'), null, 'array', true))
                      ->addStmt($factory->staticCall('parent', 'preCreate', [
                        new Variable('storage_controller'),
                        new Variable('values')
                      ]))
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addStmt(new AssignPlus(new Variable('values'), new Array_([
                        new ArrayItem($factory->methodCall($factory->staticCall('\Drupal', 'currentUser'), 'id'), new String_('user_id'))
                      ])))
                )
                ->addStmt($factory->method('getCreatedTime')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addStmt(new Return_($factory->propertyFetch($factory->methodCall(new Variable('this'), 'get', ['created']), 'value')))
                )
                ->addStmt($factory->method('getOwner')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addStmt(new Return_($factory->propertyFetch($factory->methodCall(new Variable('this'), 'get', ['user_id']), 'entity')))
                )
                ->addStmt($factory->method('getOwnerId')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addStmt(new Return_($factory->propertyFetch($factory->methodCall(new Variable('this'), 'get', ['user_id']), 'target_id')))
                )
                ->addStmt($factory->method('setOwner')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('account'), null, 'UserInterface'))
                      ->addStmt($factory->methodCall(new Variable('this'), 'set', [
                        'user_id',
                        $factory->methodCall(new Variable('account'), 'id')
                      ]))
                      ->addStmt(new Return_(new Variable('this')))
                )
                ->addStmt($factory->method('setOwnerId')
                      ->makePublic()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('uid')))
                      ->addStmt($factory->methodCall(new Variable('this'), 'set', [
                        'user_id',
                        new Variable('uid')
                      ]))
                      ->addStmt(new Return_(new Variable('this')))
                )
                ->addStmt($baseFieldStms)
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass(), $classNode, 'Entity');
    // Listbuilder
    $controllerDir = $interfaceDir . '/Controller';
    $controllerResult = $this->_fileSystem->prepareDirectory($controllerDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$controllerResult) {
      throw new \Exception('Controller Directory can not be created');
    }
    $listBuilderNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Controller')
          ->addStmt($factory->use('Drupal\Core\Entity\EntityInterface'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityListBuilder'))
          ->addStmt($factory->use('Drupal\Core\Url'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'List')
                ->extend('EntityListBuilder')
                ->setDocComment($this->_getDocComment($this->_getEntityNameClass() . ' List Builder'))
                ->addStmt($factory->method('buildHeader')
                      ->makePublic()
                      ->setDocComment($this->_getDocComment('{@inheritDoc}'))
                      ->addStmt(new Assign(new Variable('header'), new Array_([
                        new ArrayItem($factory->funcCall('t', ['Title'])),
                      ])))
                      ->addStmt(new Return_(new Plus(new Variable('header'), $factory->staticCall('parent', 'buildHeader'))))
                )
                ->addStmt($factory->method('buildRow')
                      ->makePublic()
                      ->setDocComment($this->_getDocComment('{@inheritDoc}'))
                      ->addParam(new Param(new Variable('entity'), null, 'EntityInterface'))
                      ->addStmt(new Assign(new Variable('row'), new Array_([
                        new ArrayItem($factory->methodCall($factory->methodCall(new Variable('entity'), 'toLink'), 'toString')),
                      ])))
                      ->addStmt(new Return_(new Plus(new Variable('row'), $factory->staticCall('parent', 'buildRow', [new Variable('entity')]))))
                )
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'List', $listBuilderNode, 'Controller');
  }
  
  private function _createForms(): void
  {
    $factory = new BuilderFactory();
    $formDir = $this->_modulePath . '/src/Form';
    $result = $this->_fileSystem->prepareDirectory($formDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$result) {
      throw new \Exception('Form Directory can not be created');
    }
    // Entity Form
    $entityFormNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Form')
          ->addStmt($factory->use('Drupal\Core\Entity\ContentEntityForm'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'Form')
                ->extend('ContentEntityForm')
                ->setDocComment($this->_getDocComment(t('Create or edit @type', [
                  '@type' => $this->_entityTypeLabel
                ])))
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'Form', $entityFormNode, 'Form');
    // Delete Form
    $deleteFormNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Form')
          ->addStmt($factory->use('Drupal\Core\Entity\ContentEntityDeleteForm'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'DeleteForm')
                ->extend('ContentEntityDeleteForm')
                ->setDocComment($this->_getDocComment(t('Delete @type', [
                  '@type' => $this->_entityTypeLabel
                ])))
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'DeleteForm', $deleteFormNode, 'Form');
    // SettingsForm
    $settingsFormNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\Form')
          ->addStmt($factory->use('Drupal\Core\Form\FormBase'))
          ->addStmt($factory->use('Drupal\Core\Form\FormStateInterface'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'SettingsForm')
                ->extend('FormBase')
                ->setDocComment($this->_getDocComment(t('@type Settings', [
                  '@type' => $this->_entityTypeLabel
                ])))
                ->addStmt($factory->method('getFormId')
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addStmt(new Return_(new String_($this->_entityTypeName . '_settings_form')))
                )
                ->addStmt($factory->method('buildForm')
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('form'), null, 'array'))
                      ->addParam(new Param(new Variable('form_state'), null, 'FormStateInterface'))
                      ->addStmt(new Assign(
                                  new ArrayDimFetch(new Variable('form'), new String_('markup')),
                                  new Array_([
                                    new ArrayItem($factory->funcCall('t', [
                                      '@type Settings', ['@type' => $this->_entityTypeLabel]
                                    ]), new String_('#markup'))
                                  ])
                                )
                      )
                      ->addStmt(new Return_(new Variable('form')))
                )
                ->addStmt($factory->method('submitForm')
                      ->setDocComment("/**\n * {@inheritDoc}\n * You can write your submit function here if needed \n */")
                      ->addParam(new Param(new Variable('form'), null, 'array', true))
                      ->addParam(new Param(new Variable('form_state'), null, 'FormStateInterface'))
                )
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'SettingsForm', $settingsFormNode, 'Form');
  }
  
  private function _createAccessControl(): void
  {
    $factory = new BuilderFactory();
    $dir = $this->_modulePath . '/src';
    $result = $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$result) {
      throw new \Exception('SRC Directory can not be created');
    }
    $node = $factory->namespace('Drupal\\' . $this->_moduleName)
          ->addStmt($factory->use('Drupal\Core\Access\AccessResult'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityAccessControlHandler'))
          ->addStmt($factory->use('Drupal\Core\Entity\EntityInterface'))
          ->addStmt($factory->use('Drupal\Core\Session\AccountInterface'))
          ->addStmt($factory->class($this->_getEntityNameClass() . 'Access')
                ->extend('EntityAccessControlHandler')
                ->setDocComment($this->_getDocComment('Access Control Handler'))
                ->addStmt($factory->method('checkAccess')
                      ->makeProtected()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('entity'), null, 'EntityInterface'))
                      ->addParam(new Param(new Variable('operation')))
                      ->addParam(new Param(new Variable('account'), null, 'AccountInterface'))
                      ->addStmt(new Switch_(new Variable('operation'), [
                        new Case_(new String_('view'), [
                              new Return_($factory->staticCall('AccessResult', 'allowedIfHasPermission', [
                                new Variable('account'),
                                new String_('view ' . $this->_entityTypeName)
                              ])),
                            ]),
                        new Case_(new String_('edit')),
                        new Case_(new String_('update'), [
                              new Return_($factory->staticCall('AccessResult', 'allowedIfHasPermission', [
                                new Variable('account'),
                                new String_('edit ' . $this->_entityTypeName)
                              ])),
                            ]),
                        new Case_(new String_('delete'), [
                              new Return_($factory->staticCall('AccessResult', 'allowedIfHasPermission', [
                                new Variable('account'),
                                new String_('delete ' . $this->_entityTypeName)
                              ])),
                            ]),
                        new Case_(null, [
                          new Throw_($factory->new('\Exception', [
                            $factory->funcCall('t', [new String_('Unknown Operation: @op'), new Array_([
                              new ArrayItem(new Variable('operation'), new String_('@op'))
                            ])]),
                          ])),
                        ])
                      ]))
                )
                ->addStmt($factory->method('checkCreateAccess')
                      ->makeProtected()
                      ->setDocComment("/**\n * {@inheritDoc}\n */")
                      ->addParam(new Param(new Variable('account'), null, 'AccountInterface'))
                      ->addParam(new Param(new Variable('context'), null, 'array'))
                      ->addParam(new Param(new Variable('entity_bundle'), new ConstFetch(new Name('null'))))
                      ->addStmt(new Return_($factory->staticCall('AccessResult', 'allowedIfHasPermission', [
                        new Variable('account'),
                        new String_('create ' . $this->_entityTypeName)
                      ])))
                )
          )
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'Access', $node);
  }
  
  /**
   * Entity template is created
   */
  private function _createTemplate(): void
  {
    $dir = $this->_modulePath . '/templates';
    $result = $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    if (!$result) {
      throw new \Exception('Template Directory can not be created');
    }
    $content = '{# ' . $this->_entityTypeLabel. ' #}';
    $content .= "\n\n";
    $content .= "{{ content }}\n\n";
    $content .= "{#\nYou can change this template according to your goals\n#}";
    file_put_contents($dir . '/' . str_replace('_', '-', $this->_entityTypeName) . '.html.twig', $content);
  }
  
  public function _createThemeHook(): void
  {
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $factory = new BuilderFactory();
    $moduleFile = $this->_modulePath . '/' . $this->_moduleName . '.module'; 
    if (file_exists($moduleFile)) {
      $content = file_get_contents($moduleFile);
      $ast = $parser->parse($content);
    }
    else {
      $ast = $parser->parse('<?php' . $this->_getDocComment($this->_entityTypeLabel . ' Module'));
    }
    $hookName = $this->_moduleName . '_theme';
    $preprocessName = 'template_preprocess_' . $this->_entityTypeName;
    $hasHook = false;
    $hasPreprocess = false;
    foreach($ast as $element) {
      if (property_exists($element, 'name') && (string)$element->name === $hookName) {
        $hasHook = true;
      }
      elseif (property_exists($element, 'name') && (string)$element->name === $preprocessName) {
        $hasPreprocess = true;
      }
    }
    if (!$hasHook) {
      $hook = new Function_($hookName);
      $hook->setDocComment(new Doc($this->_getDocComment('Implements hook_theme()')));
      $ast[] = $hook;
    }
    if (!$hasPreprocess) {
      $preprocess = new Function_($preprocessName);
      $preprocess->params = [new Param(new Variable('variables'), null, new Name('array'), true)];
      $preprocess->stmts = [
        new Expression(
          new Assign(new ArrayDimFetch(new Variable('variables'), new String_('content')), 
                new ArrayDimFetch(new Variable('variables'), new String_('elements')))
        ),
        new Return_(new Variable('hooks'))
      ];
      $ast[] = $preprocess;
    }
    $nodeTraverser = new NodeTraverser;
    $nodeTraverser->addVisitor(new DevUtilThemeHookVisitor($this->_moduleName, $this->_entityTypeName));
    $traversedNodes = $nodeTraverser->traverse($ast);
    $code = $this->_prettyPrinter->prettyPrintFile($traversedNodes);
    file_put_contents($moduleFile, $code);
    echo '- File ' . $moduleFile . " was created\n";
  }
  
  /**
   * Create Entity Bundle Interface and the folder for Bundle Classes
   */
  private function _createEntityBundleClass(): void
  {
    $factory = new BuilderFactory();
    $comment = $this->_getDocComment($this->_entityTypeLabel . ' Entity Type Bundle Base Interface');
    $interfaceNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\\Entity')
          ->setDocComment($comment)
          ->addStmt($factory->use('Drupal\\' . $this->_moduleName . '\\' . 
                $this->_getEntityNameClass() . 'Interface'))
          ->addStmt($factory->interface($this->_getEntityNameClass() . 'BundleBaseInterface')
                ->extend($this->_getEntityNameClass() . 'Interface'))
          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'BundleBaseInterface', $interfaceNode, 'Entity');
    $bundlePath = $this->_modulePath . '/src/Entity/Bundles';
    $this->_fileSystem->prepareDirectory($bundlePath, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
  }
  
}

class DevUtilThemeHookVisitor extends NodeVisitorAbstract {
  
  private     $_moduleName;
  private     $_entityTypeName;
  
  private     $_hookName;
  
  public function __construct(string $moduleName, string $entityTypeName) {
    $this->_moduleName = $moduleName;
    $this->_entityTypeName = $entityTypeName;
    $this->_hookName = $moduleName . '_theme';
  }
  
  public function leaveNode(Node $node) {
    $factory = new BuilderFactory();
    if ($node instanceof Function_) {
      if ($node->name->toString() === $this->_hookName) {
        // It's the Hook implementation
        $expr = null;
        foreach ($node->getStmts() as $stmt) {
          if ($stmt instanceof Expression) {
            /** @phpstan-ignore-next-line */
            if ($stmt->expr->var->name === 'hooks') {
              $expr = $stmt;
            }
          }
        }
        if (is_null($expr)) {
          $expr = new Expression(
                new Assign(new Variable('hooks'), new Array_([]))
          );
          $node->stmts[] = $expr;
        }
        /** @phpstan-ignore-next-line */
        $hooksArray = $expr->expr->expr;
        $hasThemeHook = false;
        foreach($hooksArray->items as $key => $item) {
          if ($item->key->value === $this->_entityTypeName) {
            $hasThemeHook = true;
          }
        }
        if (!$hasThemeHook) {
          $hooksArray->items[] = new ArrayItem(new Array_([
            new ArrayItem(new String_('elements'), new String_('render element')),
            new ArrayItem(new String_( str_replace('_', '-', $this->_entityTypeName)), new String_('template')),
            new ArrayItem($factory->methodCall($factory->staticCall('\Drupal', 'service', [new String_('extension.list.module')]), 'getPath', [new String_($this->_moduleName)]), new String_('path')),
          ]), new String_($this->_entityTypeName));
        }
      }
    }
    return null;
  }
  
}