<?php

namespace Drupal\auto_alter\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages describe image plugins.
 *
 * @ingroup auto_alter
 */
class AutoAlterDescribeImagePluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
   public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
      parent::__construct('Plugin/AutoAlterDescribeImage', $namespaces, $module_handler, 'Drupal\auto_alter\DescribeImageServiceInterface', 'Drupal\auto_alter\Annotation\AutoAlterDescribeImage');
     $this->alterInfo('auto_alter_describe_image_info');
      $this->setCacheBackend($cache_backend, 'auto_alter_describe_image');
    }

}
