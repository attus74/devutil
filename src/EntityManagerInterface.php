<?php

namespace Drupal\devutil;

/**
 * Entity Manager Interface
 * @author Attila Németh
 * @date 03.07.2023
 */
interface EntityManagerInterface {
  
  /**
   * Create code
   * @param string $name
   *  Entity type machine readable name, e.g. my_example
   * @param string $label
   *  Entity type human readable label, e.g. "My Example"
   * @param array $options
   *  Drush options
   */
  public function createCode(string $name, string $label, array $options): void;
  
}
