<?php

namespace Drupal\auto_alter;

use Drupal\file\Entity\File;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for the DescribeImageService service.
 */
interface DescribeImageServiceInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Checks if setup is complete.
   *
   * @return bool
   *   TRUE if the setup is complete, FALSE otherwise.
   */
  public function checkSetup();

  /**
   * Get the correct URI of the image.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity.
   *
   * @return string
   *   The URI of the image.
   */
  public function getUri(File $file);

  /**
   * Get the description of the image.
   *
   * @param string $uri_or_realpath
   *   The URI or relative path of the image.
   *
   * @return \Psr\Http\Message\ResponseInterface|bool
   *   The response from the Azure Cognitive Services API.
   */
  public function getDescription(string $uri_or_realpath);

  /**
   * Build the configuration form.
   *
   * @return array
   */
  public function buildConfigurationForm();

  /**
   * Validate the configuration form.
   *
   * @param $form_state
   */
  public function validateConfigurationForm($form_state);

  /**
   * Submit the configuration form.
   *
   * @param $form_state
   * @param $config
   */
  public function submitConfigurationForm($form_state, $config);

}
