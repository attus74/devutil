<?php

namespace Drupal\devutil;

use PhpParser\BuilderFactory;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;
use Drupal\devutil\EntityManagerBase;

/**
 * Entity Bundle Manager
 *
 * @author Attila Németh
 * @date 15.02.2022
 */
class EntityBundleManager extends EntityManagerBase {
  
  // Bundle Arguments from CLI
  private           $_bundleId;
  private           $_bundleLabel;
  
  // Own Intermediate data
  private           $_bundleInfo;
  private           $_entityTypeDefinition;
  private           $_className;
  private           $_interfaceName;
  private           $_moduleFilePath;
  
  // PHP Builder Factory
  private           $_factory;

  public function __construct(string $entityTypeId) {
    $this->_entityTypeName = $entityTypeId;
    $this->_factory = new BuilderFactory();
    parent::__construct();
  }
  
  /**
   * Create a new, class based entity bundle
   * @param string $bundleId
   *  Bundle ID (e.g. 'article')
   * @param string $bundleLabel
   *  Bundle Label (e.g. 'Article')
   * @param array $options
   *  Code Options: 
   *          - name                    @name-Attribute in Code
   */
  public function createBundle(string $bundleId, string $bundleLabel, array $options): void
  {
    $this->_bundleId = $bundleId;
    $this->_bundleLabel = $bundleLabel;
    if (!empty($options['name']) && !is_null($options['name'])) {
      $this->_author = $options['name'];
    }
    $this->_checkArguments();
    $this->_createInterface();
    $this->_createClass();
    $this->_createModuleFile();
    $this->_addBundleInfo();
    $this->_createConfig();
  }
  
  /**
   * Check the arguments and stopp the process, if needed
   * @throws \Exception
   */
  private function _checkArguments(): void
  {
    $this->_bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo($this->_entityTypeName);
    if (count($this->_bundleInfo) == 1 && current(array_keys($this->_bundleInfo)) == $this->_entityTypeName) {
      throw new \Exception('This entity type has no bundles');
    }
    if (array_key_exists($this->_bundleId, $this->_bundleInfo)) {
      throw new \Exception(t('The Bundle @id (@label) exists', [
        '@id' => $this->_bundleId,
        '@label' => $this->_bundleInfo[$this->_bundleId]['label'],
      ]));
    }
    foreach($this->_bundleInfo as $info) {
      if (!array_key_exists('class', $info)) {
        throw new \Exception('This Content Entity Type has no Bundle Classes');
      }
    }
    $matches = [];
    $this->_entityTypeDefinition = \Drupal::entityTypeManager()->getDefinition($this->_entityTypeName);
    if (preg_match('/^Drupal\\\(.*?)\\\/', $this->_entityTypeDefinition->getClass(), $matches)) {
      $this->_moduleName = $matches[1];
    }
    else {
      throw new \Exception('Definition of this Content Entity Type has an error: wrong namespace');
    }
    $this->_entityTypeLabel = (string)$this->_entityTypeDefinition->getLabel();
    $this->_modulePath = \Drupal::service('extension.list.module')->getPath($this->_moduleName);
  }
  
  /**
   * Create Bundle Interface
   */
  private function _createInterface(): void
  {
    $commentString = t('@entity_type @type_key @bundle_label Interface', [
      '@entity_type' => $this->_entityTypeLabel,
      '@type_key' => ucfirst($this->_entityTypeDefinition->getKey('bundle')),
      '@bundle_label' => $this->_bundleLabel,
    ]);
    $comment = $this->_getDocComment($commentString);
    $this->_interfaceName = $this->_getEntityNameClass() . ucfirst($this->_bundleId) . 'Interface';
    $interfaceNode = $this->_factory->namespace('Drupal\\' . $this->_moduleName . '\Entity\Bundles')
                    ->setDocComment($comment)
                    ->addStmt($this->_factory->use('Drupal\\' . $this->_moduleName . 
                                                '\Entity\\' . $this->_getEntityNameClass() . 'BundleBaseInterface'))
                    ->addStmt($this->_factory->interface($this->_interfaceName)
                                                ->extend($this->_getEntityNameClass() . 'BundleBaseInterface'))
                    ->getNode();
    $this->_savePhp($this->_interfaceName, $interfaceNode, 'Entity/Bundles');
  }
  
  /**
   * Create Bundle Class
   */
  private function _createClass(): void
  {
    $reflection = new \ReflectionClass($this->_entityTypeDefinition->getClass());
    $commentString = t('@entity_type @type_key @bundle_label', [
      '@entity_type' => $this->_entityTypeLabel,
      '@type_key' => ucfirst($this->_entityTypeDefinition->getKey('bundle')),
      '@bundle_label' => $this->_bundleLabel,
    ]);
    $comment = $this->_getDocComment($commentString);
    $this->_className = $this->_getEntityNameClass() . ucfirst($this->_bundleId);
    $classNode = $this->_factory->namespace('Drupal\\' . $this->_moduleName . '\Entity\Bundles')
          ->setDocComment($comment)
          ->addStmt($this->_factory->use($this->_entityTypeDefinition->getClass()))
          ->addStmt($this->_factory->use('Drupal\\' . $this->_moduleName . '\Entity\Bundles\\' . $this->_interfaceName))
          ->addStmt($this->_factory->class($this->_className)
                ->extend($reflection->getShortName())
                ->implement($this->_interfaceName))
          ->getNode();
    $this->_savePhp($this->_className, $classNode, 'Entity/Bundles');
  }
  
  /**
   * Module File (*.module) should already exist. But for being on the safe side, 
   * if it doesn't, it will be created.
   */
  private function _createModuleFile(): void
  {
    $this->_moduleFilePath = $this->_modulePath . '/' . $this->_moduleName . '.module';
    if (!file_exists($this->_moduleFilePath)) {
      $commentString = t('@entity_type Module', [
        '@entity_type' => $this->_entityTypeLabel,
      ]);
      $comment = $this->_getDocComment($commentString);
      $file = "<?php\n\n$comment\n\n";
      file_put_contents($this->_moduleFilePath, $file);
    }
  }
  
  public function _addBundleInfo(): void
  {
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    try {
      $hookName = $this->_moduleName . '_entity_bundle_info';
      $code = file_get_contents($this->_moduleFilePath);
      if (function_exists($hookName)) {
        $bundleInfo = $hookName();
      }
      else {
        $bundleInfo = [];
        $code .= "\n\n/**\n * Implements hook_entity_bundle_info()\n */\nfunction $hookName() {}";
      }
      $ast = $parser->parse($code);
      $bundleInfo[$this->_entityTypeName][$this->_bundleId] = [
        'label' => $this->_bundleLabel,
        'class' => 'Drupal\\' . $this->_moduleName . '\Entity\Bundles\\' . $this->_className,
      ];
      $traverser = new NodeTraverser();
      $traverser->addVisitor(new ModuleFileVisitor($hookName, $bundleInfo));
      $traversedAst = $traverser->traverse($ast);
      $prettyPrinter = new PrettyPrinter\Standard;
      $modifiedCode = $prettyPrinter->prettyPrintFile($traversedAst);
      $replacementLines = [
        '[',
      ];
      foreach($bundleInfo as $e => $b) {
        $replacementLines[] = '      \'' . $e . '\' => [';
        foreach($b as $bi => $bf) {
          $replacementLines[] = '        \'' . $bi . '\' => [';
          $replacementLines[] = '          \'label\' => \'' . $bf['label'] . '\',';
          $replacementLines[] = '          \'class\' => \'' . $bf['class'] . '\',';
          $replacementLines[] = '        ],';
        }
        $replacementLines[] = '      ],';
      }
      $replacementLines[] = '    ]';
      $modifiedCode = str_replace('\'%BUNDLES%\'', implode("\n", $replacementLines), $modifiedCode);
      file_put_contents($this->_moduleFilePath, $modifiedCode);
      echo '- File ' . $this->_moduleFilePath . " was updated\n";
    } catch (Error $error) {
      echo 'Module File may not be updated: ' . $error->getMessage() . "\n";
    }
  }
  
  public function _createConfig(): void 
  {
    $configDir = $this->_modulePath . '/config/install/';
    \Drupal::service('file_system')
          ->prepareDirectory($configDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $configPath = $configDir . '/' . $this->_moduleName . '.' . 
          $this->_entityTypeDefinition->getBundleEntityType() . '.' . $this->_bundleId . '.yml';
    if (is_file($configPath)) {
      $config = Yaml::decode(file_get_contents($configPath));
    }
    else {
      $config = [];
    }
    $config['status'] = true;
    $config['id'] = $this->_bundleId;
    $config['label'] = $this->_bundleLabel;
    file_put_contents($configPath, Yaml::encode($config));
    $schemaDir = $this->_modulePath . '/config/schema/';
    \Drupal::service('file_system')
          ->prepareDirectory($schemaDir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $schemaPath = $schemaDir . '/' . $this->_moduleName . '.schema.yml';
    if (is_file($schemaPath)) {
      $schema = Yaml::decode(file_get_contents($schemaPath));
    }
    else {
      $schema = [];
    }
    $schema[$this->_moduleName . '.' . $this->_entityTypeDefinition->getBundleEntityType() . '.*'] = [
      'type' => 'config_object',
      'label' => $this->_entityTypeDefinition->getLabel() . ' ' . ucfirst($this->_entityTypeDefinition->getKey('bundle')),
      'mapping' => [
        'uuid' => [
          'type' => 'string',
          'label' => 'UUID',
        ],
        'langcode' => [
          'type' => 'string',
          'label' => (string)t('Language'),
        ],
        'status' => [
          'type' => 'boolean',
          'label' => (string)t('Status'),
        ],
        'id' => [
          'type' => 'string',
          'label' => 'ID',
        ],
        'label' => [
          'type' => 'string',
          'label' => (string)t('Name'),
        ],
        'dependencies' => [
          'type' => 'mapping',
          'label' => (string)t('Dependencies'),
        ],
      ],
    ];
    file_put_contents($schemaPath, Yaml::encode($schema));
  }
  
}

class ModuleFileVisitor extends NodeVisitorAbstract {
  
  private     $_hookName;
  private     $_bundleInfo;
  
  public function __construct(string $hookName, array $bundleInfo) {
    $this->_hookName = $hookName;
    $this->_bundleInfo = $bundleInfo;
  }
  
  public function enterNode(Node $node)  {
    if ($node instanceof Function_ && $node->name->name === $this->_hookName) {
      $node->returnType = new Node\Identifier('array');
      $var = new Variable('bundles');
      $value = new String_('%BUNDLES%');
      $expression = new Assign($var, $value);
      $node->stmts[] = new Expression($expression);
      $node->stmts[] = new Return_($var);
    }
  }
  
}