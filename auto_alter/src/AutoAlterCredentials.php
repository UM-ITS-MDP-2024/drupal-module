<?php

namespace Drupal\auto_alter;

use Drupal\Core\Config\Config;

/**
 * Handles configuration of credentials.
 *
 * @package Drupal\auto_alter
 */
class AutoAlterCredentials {

  /**
    * The apikey.
    *
    * @var string
    */
  protected $apikey = '';

  /**
    * AutoAlterCredentials constructor.
    *
    * @param \Drupal\Core\Config\Config|null $config
    *   The auto_alter configuration object.
    */
  public function __construct(Config $config = NULL) {
    if ($config) {
      $credential_provider = $config->get('credential_provider');
      $credentials = $config->get('credentials');
      if ($credentials) {
        $this->setCredentials($credential_provider, $credentials ?? []);
      }
    }
  }

  /**
    * Set the credentials from configuration array.
    *
    * @param string $credential_provider
    *   The credential provider.
    * @param array $providers
    *   Nested array of all the credential providers.
    */
  public function setCredentials(string $credential_provider, array $providers) {
    switch ($credential_provider) {
      case 'config':
        $this->apikey = $providers['config']['api_key'] ?? NULL;
        break;

      case 'key':
        if (\Drupal::moduleHandler()->moduleExists('key')
          && $key_name = $providers['key']['api_key_key'] ?? NULL
        ) {
          /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
          $storage = \Drupal::entityTypeManager()->getStorage('key');
          /** @var \Drupal\key\KeyInterface $apikey_key */
          $apikey_key = $storage->load($key_name);
          if ($apikey_key) {
            $this->apikey = $apikey_key->getKeyValue();
          }
        }
        break;
    }
  }

  /**
    * Return the API Key.
    *
    * @return string
    *   The API key.
    */
  public function getApikey() {
    return $this->apikey;
  }

}
