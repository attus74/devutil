<?php

namespace Drupal\devutil;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\devutil\PluginManagerInterface;

/**
 * Plugin Manager
 *
 * @author Attila Németh
 * 05.03.2020
 */
class PluginManager implements PluginManagerInterface {
  
  // Plugin Underscore Name, e.g. plugin_name
  private     $_nameUnderscore;
  
  // Plugin Class Name, e.g. PluginName
  private     $_nameClass;
  
  // Plugin Label e.g. Plugin Name
  private     $_nameLabel;
  
  // Module Name
  private     $_moduleName;
  
  // Module Directory
  private     $_moduleDir;
  
  // Your Name, e.g. John Doe
  private     $_yourName;
  
  // Drupal Services
  private     $_extensionListModule;
  private     $_fileSystem;
  private     $_moduleHandler;

  public function __construct(ModuleExtensionList $extensionListModule,
        FileSystemInterface $fileSystem,
        ModuleHandlerInterface $moduleHandler) {
    $this->_extensionListModule = $extensionListModule;
    $this->_fileSystem = $fileSystem;
    $this->_moduleHandler = $moduleHandler;
  }
  
  public function create(string $name, string $module = NULL, $yourName = NULL): void
  {
    if (is_null($module)) {
      $module = $name;
    }
    $this->_moduleName = $module;
    $this->_nameUnderscore = strtolower(str_replace(' ', '_', $name));
    $words = explode('_', $this->_nameUnderscore);
    $upperWords = [];
    foreach($words as $word) {
      $upperWords[] = ucfirst($word);
    }
    $this->_nameClass = implode('', $upperWords);
    $this->_nameLabel = implode(' ', $upperWords);
    $this->_yourName = $yourName;
    $this->_createModule();
    $this->_createServices();
    $this->_createPluginBase();
    $this->_createPluginInterface();
    $this->_createPluginManager();
    $this->_createAnnotation();
  }
  
  private function _createAnnotation(): void
  {
    $dir = $this->_moduleDir . '/src/Annotation';
    $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $fileName = $dir . '/' . $this->_nameClass . '.php';
    if (file_exists($fileName)) {
      unlink($fileName);
    }
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . '\\Annotation;',
      '',
      'use Drupal\Component\Annotation\Plugin;',
      '',
      '/**',
      ' * ' . $this->_nameLabel . ' Annotation',
      ' *',
    ];
    if (!empty($this->_yourName)) {
      $lines[] = ' * @author ' . $this->_yourName;
    }
    $lines[] = ' * @date ' . date('d.m.Y');
    $lines[] = ' *';
    $lines[] = ' * @Annotation';
    $lines[] = ' */';
    $lines[] = 'class ' . $this->_nameClass . ' extends Plugin {';
    $lines[] = '';
    $lines[] = '  // Plugin ID';
    $lines[] = '  public $id;';
    $lines[] = '';
    $lines[] = '  // You can add here more Annotations';
    $lines[] = '';
    $lines[] = '}';
    file_put_contents($fileName, implode("\n", $lines));
  }
  
  /**
   * Plugin Manager Class
   */
  private function _createPluginManager(): void
  {
    $fileName = $this->_moduleDir . '/src/' . $this->_nameClass . 'Manager.php';
    if (file_exists($fileName)) {
      unlink($fileName);
    }
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . ';',
      '',
      'use Drupal\Core\Cache\CacheBackendInterface;',
      'use Drupal\Core\Extension\ModuleHandlerInterface;',
      'use Drupal\Core\Plugin\DefaultPluginManager;',
      '',
      '/**',
      ' * ' . $this->_nameLabel . ' Plugin Manager ',
      ' *',
    ];
    if (!empty($this->_yourName)) {
      $lines[] = ' * @author ' . $this->_yourName;
    }
    $lines[] = ' * @date ' . date('d.m.Y');
    $lines[] = ' */';
    $lines[] = 'class ' . $this->_nameClass . 'Manager extends DefaultPluginManager {';
    $lines[] = '';
    $lines[] = '  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {';
    $lines[] = '    parent::__construct(';
    $lines[] = '      \'Plugin/' . $this->_nameClass . '\',';
    $lines[] = '      $namespaces,';
    $lines[] = '      $module_handler,';
    $lines[] = '      \'Drupal\\' . $this->_moduleName . '\\' . $this->_nameClass . 'Interface\',';
    $lines[] = '      \'Drupal\\' . $this->_moduleName . '\Annotation\\' . $this->_nameClass . '\'';
    $lines[] = '    );';
    $lines[] = '    $this->alterInfo(\'' . $this->_nameUnderscore .  '_info\');';
    $lines[] = '    $this->setCacheBackend($cache_backend, \'' . $this->_nameUnderscore .  '_plugins\');';
    $lines[] = '  }';
    $lines[] = '';
    $lines[] = '}';
    file_put_contents($fileName, implode("\n", $lines));
  }

  /**
   * Plugin Interface
   */
  private function _createPluginInterface(): void
  {
    $fileName = $this->_moduleDir . '/src/' . $this->_nameClass . 'Interface.php';
    if (file_exists($fileName)) {
      unlink($fileName);
    }
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . ';',
      '',
      '/**',
      ' * ' . $this->_nameLabel . ' Plugin Interface',
      ' *',
    ];
    if (!empty($this->_yourName)) {
      $lines[] = ' * @author ' . $this->_yourName;
    }
    $lines[] = ' * @date ' . date('d.m.Y');
    $lines[] = ' */';
    $lines[] = 'interface ' . $this->_nameClass . 'Interface {';
    $lines[] = '';
    $lines[] = '}';
    file_put_contents($fileName, implode("\n", $lines));
  }
  
  /**
   * Plugin Base Class
   */
  private function _createPluginBase(): void
  {
    $dir = $this->_moduleDir . '/src';
    $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $fileName = $this->_moduleDir . '/src/' . $this->_nameClass . 'Base.php';
    if (file_exists($fileName)) {
      unlink($fileName);
    }
    $lines = [
      '<?php',
      '',
      'namespace Drupal\\' . $this->_moduleName . ';',
      '',
      'use Drupal\Component\Plugin\PluginBase;',
      'use Drupal\\' . $this->_moduleName . '\\' . $this->_nameClass . 'Interface;',
      '',
      '/**',
      ' * ' . $this->_nameLabel . ' Base Class',
      ' *',
    ];
    if (!empty($this->_yourName)) {
      $lines[] = ' * @author ' . $this->_yourName;
    }
    $lines[] = ' * @date ' . date('d.m.Y');
    $lines[] = ' */';
    $lines[] = 'abstract class ' . $this->_nameClass . 'Base extends PluginBase implements '. $this->_nameClass . 'Interface {';
    $lines[] = '';
    $lines[] = '}';
    file_put_contents($this->_moduleDir . '/src/' . $this->_nameClass . 'Base.php', implode("\n", $lines));
  }

/**
 * DownnloadImageSourceBase
 *
    ];
  }
  
  /**
   * Create services.yml file
   */
  private function _createServices(): void
  {
    $services = $this->_getExistingYmlContent('services');
    if (!array_key_exists('services', $services)) {
      $services['services'] = [];
    }
    $services['services']['plugin.manager.' . $this->_nameUnderscore] = [
      'class' => 'Drupal\\' . $this->_moduleName . '\\' . $this->_nameClass . 'Manager',
      'parent' => 'default_plugin_manager',
    ];
    $this->_setYmlContent('services', $services);
  }
  
  /**
   * Create a module if it does not exist
   */
  private function _createModule()
  {
    if ($this->_moduleHandler->moduleExists($this->_moduleName)) {
      $this->_moduleDir  = $this->_extensionListModule->getPath($this->_moduleName);
    }
    else {
      $dir = dirname($this->_extensionListModule->getPath('devutil')) . '/' . $this->_moduleName;
      $this->_fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
      $info = [
        'name' => $this->_nameClass,
        'description' => (string)t('Plugin @name', [
          '@name' => $this->_nameClass,
        ]),
        'type' => 'module',
        'core_version_requirement' => '^10.1',
      ];
      file_put_contents($dir . '/' . $this->_moduleName . '.info.yml', Yaml::encode($info));
      $this->_moduleDir  = $dir;
    }
  }
  
  /**
   * Current file content or an empty array
   * @param string $fileName
   * @return array
   */
  private function _getExistingYmlContent(string $fileName): array
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
  
  /**
   * Create or Update an YML file
   * @param string $fileName
   *  File Name withouth module name and extension, e.g. 'permissions'
   * @param array $content
   *  File Content
   */
  private function _setYmlContent(string $fileName, array $content): void
  {
    $filePath = $this->_moduleDir . '/' . $this->_moduleName . '.' . $fileName . '.yml';
    file_put_contents($filePath, Yaml::encode($content));
  }
  
}
