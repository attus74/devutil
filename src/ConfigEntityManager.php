<?php

namespace Drupal\devutil;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\ParserFactory;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;
use Drupal\devutil\EntityManagerBase;

/**
 * Configuration Entity Manager
 * 
 * @author Attila Németh
 * @date 08.06.2021
 */
class ConfigEntityManager extends EntityManagerBase {
  
  public function createCode(string $name, string $label, array $options): void
  {
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
    $this->_createPermissions();
    $this->_createRouting();
    $this->_createMenu();
    $this->_createAction();
    $this->_createEntityClasses();
    $this->_createSchema();
    $this->_createForms();
    $this->_createList('Drupal\Core\Config\Entity\ConfigEntityListBuilder');
    echo "\nYour code is created in module " . $this->_moduleName . "\n";
  }
  
  protected function _createPermissions(): void
  {
    $perms = $this->_loadYml('permissions');
    $perms['administer ' . $this->_entityTypeName] = [
      'title' => (string)t('Administer @label', $this->_getTranslation()),
      'description' => (string)t('Configure @label', $this->_getTranslation()),
      'restrict access' => TRUE,
    ];
    $perms['edit ' . $this->_entityTypeName] = [
      'title' => (string)t('Edit @label', $this->_getTranslation()),
      'description' => (string)t('Create and update @label', $this->_getTranslation()),
      'restrict access' => TRUE,
    ];
    $this->_saveYml('permissions', $perms);
  }
  
  protected function _createRouting(): void 
  {
    $routing = $this->_loadYml('routing');
    $routing['entity.' . $this->_entityTypeName . '.collection'] = [
      'path' => 'admin/structure/' . $this->_getEntityNamePath(),
      'defaults' => [
        '_entity_list' => $this->_entityTypeName,
        '_title' => $this->_entityTypeLabel,
      ],
      'requirements' => [
        '_permission' => 'administer ' . $this->_entityTypeName,
      ],
    ];
    $routing['entity.' . $this->_entityTypeName . '.add_form'] = [
      'path' => 'admin/structure/' . $this->_getEntityNamePath() . '/add',
      'defaults' => [
        '_entity_form' => $this->_entityTypeName . '.add',
        '_title' => (string)t('Add @label', $this->_getTranslation()),
      ],
      'requirements' => [
        '_permission' => 'edit ' . $this->_entityTypeName,
      ],
    ];
    $routing['entity.' . $this->_entityTypeName . '.edit_form'] = [
      'path' => 'admin/structure/' . $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}',
      'defaults' => [
        '_entity_form' => $this->_entityTypeName . '.edit',
        '_title' => (string)t('Edit @label', $this->_getTranslation()),
      ],
      'requirements' => [
        '_permission' => 'edit ' . $this->_entityTypeName,
      ],
    ];
    $routing['entity.' . $this->_entityTypeName . '.delete_form'] = [
      'path' => 'admin/structure/' . $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}/delete',
      'defaults' => [
        '_entity_form' => $this->_entityTypeName . '.delete',
        '_title' => (string)t('Delete @label', $this->_getTranslation()),
      ],
      'requirements' => [
        '_permission' => 'edit ' . $this->_entityTypeName,
      ],
    ];
    $this->_saveYml('routing', $routing);
  }
  
  protected function _createMenu(): void
  {
    $menu = $this->_loadYml('links.menu');
    $menu['entity.' . $this->_entityTypeName . '.collection'] = [
      'title' => $this->_entityTypeLabel,
      'description' => (string)t('Configure @label', $this->_getTranslation()),
      'parent' => 'system.admin_structure',
      'route_name' => 'entity.' . $this->_entityTypeName . '.collection',
    ];
    $this->_saveYml('links.menu', $menu);
  }
  
  protected function _createEntityClasses(): void 
  {
    $factory = new BuilderFactory();
    $node = $factory->namespace('Drupal\\' . $this->_moduleName)
                    ->addStmt($factory->use('Drupal\Core\Config\Entity\ConfigEntityInterface'))
                    ->addStmt($factory->interface(ucFirst($this->_getEntityNameClass() . 'Interface'))
                                                ->extend('ConfigEntityInterface'))
                    ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'Interface', $node);
    $annotations = [
      'ConfigEntityType' => [
        'id' => $this->_entityTypeName,
        'label' => '@Translation("' . $this->_entityTypeLabel . '")',
        'handlers' => [
          'list_builder' => 'Drupal\\' . $this->_moduleName . '\Controller\\' . $this->_getEntityNameClass() . 'List',
          'form' => [
            'add' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'Form',
            'edit' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'Form',
            'delete' => 'Drupal\\' . $this->_moduleName . '\Form\\' . $this->_getEntityNameClass() . 'DeleteForm',
          ],
        ],
        'config_prefix' => $this->_entityTypeName,
        'admin_permission' => 'administer ' . $this->_entityTypeName,
        'entity_keys' => [
          'id' => 'id',
          'label' => 'label',
        ],
        'config_export' => [
          'id',
          'label',
        ],
        'links' => [
          'edit-form' => '/admin/structure/' . $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}',
          'delete-form' => '/admin/structure/' . $this->_getEntityNamePath() . '/{' . $this->_entityTypeName . '}/delete',
        ]
      ],
    ];
    $classNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\\Entity')
                          ->addStmt($factory->use('Drupal\Core\Config\Entity\ConfigEntityBase'))
                          ->addStmt($factory->use('Drupal\\' . $this->_moduleName . '\\' . $this->_getEntityNameClass() . 'Interface'))
                          ->addStmt($factory->class($this->_getEntityNameClass())
                                              ->extend('ConfigEntityBase ')
                                              ->implement($this->_getEntityNameClass() . 'Interface')
                                              ->setDocComment($this->_getDocComment('Configuration Entity Type ' . $this->_entityTypeLabel,  $annotations))
                                              ->addStmt($factory->property('id')
                                                                  ->makePublic())
                                              ->addStmt($factory->property('label')
                                                                  ->makePublic()))
                          ->getNode();
    $this->_savePhp($this->_getEntityNameClass(), $classNode, 'Entity');
  }
  
  
  protected function _createSchema(): void
  {
    $schemaDir = $this->_modulePath . '/config/schema';
    $this->_fileSystem->prepareDirectory($schemaDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $schema = [
      $this->_entityTypeName . '.' . $this->_entityTypeName => [
        'type' => 'config_entity',
        'label' => $this->_entityTypeLabel,
        'mapping' => [
          'id' => [
            'type' => 'string',
            'label' => 'ID',
          ],
          'label' => [
            'type' => 'label',
            'label' => 'Label',
          ],
        ],
      ],
    ];
    $yml = Yaml::encode($schema);
    file_put_contents($schemaDir . '/' . $this->_moduleName . '.schema.yml', $yml);
  }
  
  protected function _createForms(): void
  {
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $factory = new BuilderFactory();
    
    ///// Entity Form
    
    $createCode = <<<'CODE'
<?php

return new static(
  $container->get('entity_type.manager')
);
CODE;
    $createAst = $parser->parse($createCode);
    $formCode = '
$form = parent::form($form, $form_state);

    $' . $this->_entityTypeName . ' = $this->entity;

    $form["label"] = [
      "#type" => "textfield",
      "#title" => $this->t("Label"),
      "#maxlength" => 255,
      "#default_value" => $' . $this->_entityTypeName . '->label(),
      "#description" => $this->t("@type Label", ["@type" => \'' . $this->_entityTypeLabel . '\']),
      "#required" => TRUE,
    ];
    $form["id"] = [
      "#type" => "machine_name",
      "#default_value" => $' . $this->_entityTypeName . '->id(),
      "#machine_name" => [
        "exists" => [$this, "exist"],
      ],
      "#disabled" => !$' . $this->_entityTypeName . '->isNew(),
    ];
    // You will need additional form elements for your custom properties.
    return $form;';
    $formCode = <<<CODE
<?php

$formCode
            
CODE;
    
    $formAst = $parser->parse($formCode);
    $formStatement = $factory->method('form')
                              ->makePublic()
                              ->setDocComment('/** @{inheritdoc} */')
                              ->addParam($factory->param('form')->setType('array'))
                              ->addParam($factory->param('form_state')->setType('FormStateInterface'));
    foreach($formAst as $command) {
      $formStatement->addStmt($command);
    }
    
    $saveCode = '$' . $this->_entityTypeName . ' = $this->entity;
    $status = $' . $this->_entityTypeName . '->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t("The %label ' . $this->_entityTypeLabel . ' created.", [
        "%label" => $' . $this->_entityTypeName . '->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t("The %label ' . $this->_entityTypeLabel . ' updated.", [
        "%label" => $' . $this->_entityTypeName . '->label(),
      ]));
    }

    $form_state->setRedirect("entity.' . $this->_entityTypeName . '.collection");
    return $status;';
    $saveCode = <<<CODE
<?php
            
$saveCode
            
CODE;
    $saveAst = $parser->parse($saveCode);
    $saveStatement = $factory->method('save')
                              ->makePublic()
                              ->setDocComment('/** @{inheritdoc} */')
                              ->addParam($factory->param('form')->setType('array'))
                              ->addParam($factory->param('form_state')->setType('FormStateInterface'));
    foreach($saveAst as $command) {
      $saveStatement->addStmt($command);
    }
    
    $existCode = '$entity = $this->entityTypeManager->getStorage(\'' . $this->_entityTypeName . '\')->getQuery()
      ->accessCheck(false)
      ->condition("id", $id)
      ->execute();
    return (bool) $entity;';
    $existCode = <<<CODE
<?php
  
$existCode

CODE;
    
    $existAst = $parser->parse($existCode);
    $existStatement = $factory->method('exist')
                              ->makePublic()
                              ->addParam($factory->param('id'));
    foreach($existAst as $command) {
      $existStatement->addStmt($command);
    }
                              
                                                                
    $formNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\\Form')
                          ->addStmt($factory->use('Drupal\Core\Entity\EntityForm'))
                          ->addStmt($factory->use('Drupal\Core\Entity\EntityTypeManagerInterface'))
                          ->addStmt($factory->use('Drupal\Core\Form\FormStateInterface'))
                          ->addStmt($factory->use('Symfony\Component\DependencyInjection\ContainerInterface'))
                          ->addStmt($factory->class($this->_getEntityNameClass() . 'Form')
                                              ->extend('EntityForm')
                                              ->setDocComment($this->_getDocComment($this->_entityTypeLabel . ' Form'))
                                              ->addStmt($factory->method('__construct')
                                                                ->makePublic()
                                                                ->addParam($factory->param('entityTypeManager')
                                                                                      ->setType('EntityTypeManagerInterface '))
                                                                ->addStmt(new Assign(new Variable('this->entityTypeManager'), new Variable('entityTypeManager'))))
                                              ->addStmt($factory->method('create')
                                                                ->makePublic()
                                                                ->makeStatic()
                                                                ->addParam($factory->param('container')
                                                                                    ->setType('ContainerInterface'))
                                                                ->addStmt(current($createAst)))
                                              ->addStmt($formStatement)
                                              ->addStmt($saveStatement)
                                              ->addStmt($existStatement))
                          ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'Form', $formNode, 'Form');
    
    ///// Entity Delete Form
    
    $deleteCreateCode = <<<'CODE'
<?php

return new static(
  $container->get('messenger'),
);
CODE;
    $deleteCreateAst = $parser->parse($deleteCreateCode);
    
    $getQuestionCode = <<<'CODE'
<?php

return $this->t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));

CODE;
    $getQuestionAst = $parser->parse($getQuestionCode);
    $getQuestionStatement = $factory->method('getQuestion')
                                      ->setDocComment("/**\n * {@inheritdoc}\n */")
                                      ->makePublic();
    foreach($getQuestionAst as $command) {
      $getQuestionStatement->addStmt($command);
    }
    $cancelUrlRaw = 'return new Url(\'entity.' . $this->_entityTypeName . '.collection\');';
    $cancelUrlCode = <<<CODE
<?php
$cancelUrlRaw
CODE;
    $cancelUrlAst = $parser->parse($cancelUrlCode);
    $cancelUrlStatement = $factory->method('getCancelUrl')
                                      ->setDocComment("/**\n * {@inheritdoc}\n */")
                                      ->makePublic();
    foreach($cancelUrlAst as $command) {
      $cancelUrlStatement->addStmt($command);
    }
    $submitFormCode = <<<'CODE'
<?php
$this->entity->delete();
$this->_messenger->addMessage($this->t('%label has been deleted.', array('%label' => $this->entity->label())));
$form_state->setRedirectUrl($this->getCancelUrl());
CODE;
    $submitFormAst = $parser->parse($submitFormCode);
    $submitFormStatement = $factory->method('submitForm')
                                      ->setDocComment("/**\n * {@inheritdoc}\n */")
                                      ->addParam($factory->param('form')->setType('array')->makeByRef())
                                      ->addParam($factory->param('form_state')->setType('FormStateInterface'))
                                      ->makePublic();
    foreach($submitFormAst as $command) {
      $submitFormStatement->addStmt($command);
    }
    
    $deleteFormClass = $factory->class($this->_getEntityNameClass() . 'DeleteForm')
                                ->extend('EntityConfirmFormBase')
                                ->addStmt($factory->property('_messenger')
                                      ->makePrivate())
                                ->addStmt($factory->method('__construct')
                                      ->makePublic()
                                      ->addParam($factory->param('messenger')
                                                            ->setType('MessengerInterface '))
                                      ->addStmt(new Assign(new Variable('this->_messenger'), new Variable('messenger'))))
                                ->addStmt($factory->method('create')
                                      ->makePublic()
                                      ->makeStatic()
                                      ->addParam($factory->param('container')
                                                          ->setType('ContainerInterface'))
                                      ->addStmt(current($deleteCreateAst)))
                                ->addStmt($getQuestionStatement)
                                ->addStmt($cancelUrlStatement)
                                ->addStmt($submitFormStatement);
    $deleteFormNode = $factory->namespace('Drupal\\' . $this->_moduleName . '\\Form')
                              ->addStmt($factory->use('Symfony\Component\DependencyInjection\ContainerInterface'))
                              ->addStmt($factory->use('Drupal\Core\Entity\EntityConfirmFormBase'))
                              ->addStmt($factory->use('Drupal\Core\Url'))
                              ->addStmt($factory->use('Drupal\Core\Form\FormStateInterface'))
                              ->addStmt($factory->use('Drupal\Core\Messenger\MessengerInterface'))
                              ->setDocComment($this->_getDocComment($this->_entityTypeLabel . ' Delete Form'))
                              ->addStmt($deleteFormClass)
                              ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'DeleteForm', $deleteFormNode, 'Form');
    
  }
  
}
