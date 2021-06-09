<?php

namespace Drupal\devutil;

use PhpParser\PrettyPrinter;
use PhpParser\Node as PhpParserNode;
use PhpParser\ParserFactory;
use PhpParser\BuilderFactory;
use PhpParser\Builder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Component\Serialization\Yaml;

/**
 * Entity Manager Base Class
 * 
 * @author Attila Németh, UBG
 * @date 08.06.2021
 */
class EntityManagerBase {
  
  // Drupal Module Handler
  protected       $_moduleHandler;

  // Machine readable name and human readeable label of the new entity type
  protected       $_entityTypeName;
  protected       $_entityTypeLabel;

  // Name and Path of the custom module
  protected       $_moduleName;
  protected       $_modulePath;
  
  // Name of code Author
  protected       $_author;

  // PHP Parser Pretty Printer
  protected       $_prettyPrinter;
  
  public function __construct() {
    $this->_moduleHandler = \Drupal::service('module_handler');
    $this->_prettyPrinter = new PrettyPrinter\Standard();
  }
    
  protected function _createModule(string $name): void
  {
    $this->_moduleName = $name;
    if ($this->_moduleHandler->moduleExists($this->_moduleName)) {
      // The module exists. The existing module will be used.
      $this->_modulePath = drupal_get_path('module', $this->_moduleName);
    }
    else {
      $path = $this->_getModulePath();
      \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
      $this->_modulePath = $path;
      $info = [
        'name' => $this->_entityTypeLabel,
        'description' => (string)t('Entity Type @name', [
          '@name' => $this->_entityTypeLabel,
        ]),
        'type' => 'module',
        'core_version_requirement' => '^8.9 || ^9.1'
      ];
      $this->_saveYml('info', $info);
    }
  }
  
  /**
   * Create Action Link
   */
  protected function _createAction(): void
  {
    $action = $this->_loadYml('links.action');
    $action['entity.' . $this->_entityTypeName . '.add_form'] = [
      'route_name' => 'entity.' . $this->_entityTypeName . '.add_form',
      'title' => (string)t('Add @label', $this->_getTranslation()),
      'appears_on' => [
        'entity.' . $this->_entityTypeName . '.collection'
      ],
    ];
    $this->_saveYml('links.action', $action);
  }
  
  /**
   * Build List Controller
   * @param string $builderClass
   *    ConfigEntityListBuilder
   */
  protected function _createList(string $builderClass = NULL): void 
  {
    $factory = new BuilderFactory();
    $headerRaw = '$header = [
      t(\'' . $this->_entityTypeLabel . '\'),
      t(\'Operations\'),
      // You can add additional header elements
    ];
    return $header;';
    $headerAst = $this->_getAst($headerRaw);
    $headerMethod = $factory->method('buildHeader')
                            ->setDocComment("/**\n * {@inheritdoc}\n */")
                            ->makePublic();
    $this->_addAst($headerMethod, $headerAst);
    $rowRaw = '$row = [
      $entity->toLink($entity->label(), \'edit-form\')->toString(),
      // You can add additional elements
    ];
    return $row + parent::buildRow($entity);';
    $rowAst = $this->_getAst($rowRaw);
    $rowMethod = $factory->method('buildRow')
                          ->setDocComment("/**\n * {@inheritdoc}\n */")
                          ->makePublic()
                          ->addParam($factory->param('entity')->setType('EntityInterface '));
    $this->_addAst($rowMethod, $rowAst);
    $builderUse = explode('\\', $builderClass);
    $node = $factory->namespace('Drupal\\' . $this->_moduleName . '\Controller')
                    ->addStmt($factory->use($builderClass))
                    ->addStmt($factory->use('Drupal\Core\Entity\EntityInterface'))
                    ->addStmt($factory->class($this->_getEntityNameClass() . 'List')
                                      ->extend(end($builderUse))
                                      ->addStmt($headerMethod)
                                      ->addStmt($rowMethod))
                    ->getNode();
    $this->_savePhp($this->_getEntityNameClass() . 'List', $node, 'Controller');
  }
  
  /**
   * Full Path of the module to be created
   */
  private function _getModulePath(): string
  {
    if ($this->_modulePath && !empty($this->_modulePath)) {
      if (!preg_match('#^/#', $this->_modulePath)) {
        $this->_modulePath = '/' . $this->_modulePath;
      }
      return DRUPAL_ROOT . $this->_modulePath . '/' . $this->_moduleName;
    }
    else {
      return DRUPAL_ROOT . '/modules/' . $this->_moduleName;
    }
  }
  
  /**
   * Entity Type name decoded for path
   * @return string
   */
  protected function _getEntityNamePath(): string
  {
    return str_replace('_', '/', $this->_entityTypeName);
  }
  
  /**
   * Entity Type name converted for class name
   * @return string
   */
  protected function _getEntityNameClass(): string
  {
    $parts = explode('_', $this->_entityTypeName);
    $class = '';
    foreach($parts as $part) {
      $class .= ucFirst(strtolower($part));
    }
    return $class;
  }


  /**
   * Create or Update YAML Configuration file
   * @param string $name
   *  File Name
   * @param array $data
   *  Configuration to be saved
   */
  protected function _saveYml(string $name, array $data): void
  {
    file_put_contents($this->_modulePath . '/' . $this->_moduleName . '.' . $name . '.yml', Yaml::encode($data));
  }
  
  /**
   * Load YAML Configuration from file
   * @param string $name
   *  File Name
   * @return array
   *  Configuration or an empty array if the configuration file does not exist. 
   */
  protected function _loadYml(string $name): array
  {
    $filePath = $this->_modulePath . '/' . $this->_moduleName . '.' . $name . '.yml';
    if (file_exists($filePath)) {
      $yml = file_get_contents($filePath);
      $data = Yaml::decode($yml);
      return $data;
    }
    else {
      return [];
    }
  }
  
  /**
   * Create or Update PHP file
   * @param string $name
   *  PHP file name
   * @param PhpParserNode $node
   *  PHP Code
   * @param string $path
   *  File Path relative to src. Optional. 
   */
  protected function _savePhp(string $name, PhpParserNode $node, string $path = NULL): void
  {
    $filePath = $this->_modulePath . '/src';
    if (!is_null($path)) {
      $filePath .= '/' . $path;
    }
    \Drupal::service('file_system')->prepareDirectory($filePath, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $stmts = array($node);
    $code = $this->_prettyPrinter->prettyPrintFile($stmts);
    file_put_contents($filePath . '/' . $name . '.php', $code);
  }
  
  /**
   * Labels for translation
   * @return array
   */
  protected function _getTranslation(): array
  {
    return [
      '@label'          => $this->_entityTypeLabel,
    ];
  }
  
  /**
   * Comments Formatted
   * @param array $annotations
   *  Annotations
   * @return string
   */
  protected function _getDocComment(string $title, array $annotations = NULL): string
  {
    $lines = [
      '',
      '/**',
      ' * ' . $title,
      ' *',
    ];
    if (!is_null($this->_author)) {
      $lines[] = ' * @author ' . $this->_author;
    }
    $lines[] = ' * @date ' . date('d.m.Y');
    if (is_array($annotations)) {
      foreach($annotations as $key => $args) {
        $lines[] = ' *';
        $lines[] = ' * @' . $key . '(';
        $this->_addAnnotationCode($lines, $args);
        $lines[] = ' * )';
      }
    }
    $lines[] = ' */';
    return implode("\n", $lines);
  }
  
  /**
   * {@internal}
   */
  private function _addAnnotationCode(array &$lines, array $args, $level = 0): void
  {
    $spaces = str_pad('', $level * 2, ' ', STR_PAD_LEFT);
    foreach($args as $key => $value) {
      $line = ' *   ' . $spaces;
      if (!is_numeric($key)) {
        if ($level > 0) {
          $key = '"' . $key . '"';
        }
        $line .=  $key . ' = ';
      }
      if (is_array($value)) {
        $line .= '{';
        $lines[] = $line;
        $this->_addAnnotationCode($lines, $value, $level + 1);
        $line = ' *   ' . $spaces . '}';
      }
      elseif (is_numeric($value)) {
        $line .= $value;
      }
      elseif (preg_match('/^@/', $value)) {
        $line .= $value;
      }
      else {
        $line .= '"' . $value . '"';
      }
      $line .= ',';
      $lines[] = $line;
    }
  }
  
  protected function _getAst(string $rawCode): array
  {
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $code = <<<CODE
<?php
$rawCode
CODE;
    try {
      $ast = $parser->parse($code);
      return $ast;
    }
    catch(\Exception $ex) {
      \Drupal::logger('Development Utilities')->error($ex->getMessage());
      echo $code;
      exit(1);
    }
  }
  
  /**
   * Add AST Elements to class or method
   * @param Builder $element
   *  The PHP Parser Class or Method
   * @param array $ast
   *  The AST elements
   */
  protected function _addAst(Builder $element, array $ast): void
  {
    foreach($ast as $command) {
      $element->addStmt($command);
    }
  }
  
}
