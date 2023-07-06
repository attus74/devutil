<?php

namespace Drupal\devutil;

use Drupal\devutil\EntityManagerInterface;

/**
 * Bundle Manager Interface
 * @author Attila Németh
 */
interface EntityBundleManagerInterface extends EntityManagerInterface {
  
  /**
   * Set Entity Type
   * @param string $entityTypeId
   */
  public function setEntityType(string $entityTypeId): void;
  
}
