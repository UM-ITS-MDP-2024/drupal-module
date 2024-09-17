<?php

namespace Drupal\auto_alter\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a reusable Auto Alter Describe Image plugin annotation object.
 *
 * @Annotation
 */
class AutoAlterDescribeImage extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the form plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

}
