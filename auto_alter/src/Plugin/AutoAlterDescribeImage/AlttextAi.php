<?php

namespace Drupal\auto_alter\Plugin\AutoAlterDescribeImage;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Utility\Xss;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\auto_alter\AutoAlterCredentials;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\auto_alter\DescribeImageServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alttext.ai integration.
 *
 * @AutoAlterDescribeImage(
 *   id = "alttext_ai",
 *   title = @Translation("Alttext.AI"),
 * )
 */
class AlttextAi implements DescribeImageServiceInterface {

  use StringTranslationTrait;

  /**
   * The file system service.
   *
   * @var Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The httpClient.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactory;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The messenger service.
   *
   * @var \Drupal\auto_alter\Plugin\AutoAlterDescribeImage\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Class constructor.
   */
  public function __construct(FileSystemInterface $file_system, ClientInterface $http_client, LanguageManagerInterface $language_manager, ConfigFactory $configFactory, LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger, ModuleHandlerInterface $module_handler) {
    $this->fileSystem = $file_system;
    $this->httpClient = $http_client;
    $this->languageManager = $language_manager;
    $this->config = $configFactory->get('auto_alter.settings');
    $this->loggerFactory = $loggerFactory->get('auto_alter');
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function checkSetup() {
    $credentials = new AutoAlterCredentials();
    $credentials->setCredentials($this->config->get('credential_provider'), $this->config->get('credentials') ?? []);
    $api_key = Xss::filter($credentials->getApikey());
    if (empty($api_key)) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getUri(File $file) {
    $filesize = $file->getSize();
    $uri = $file->get('uri')->value;
    if ($filesize > 1048576) {
      $style = ImageStyle::load('auto_alter_help');
      $original_uri = $uri;
      $uri = $style->buildUri($original_uri);
      $style->createDerivative($original_uri, $uri);
    }
    return $uri;
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription(string $uri_or_realpath) {
    $path = $this->fileSystem->realpath($uri_or_realpath);

    // We might want to force image upload from local environments.
    if(empty($path) || !empty(getenv('ALTTEXT_AI_FORCE_IMAGE_UPLOAD'))) {
      $json = [
        'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($uri_or_realpath),
      ];
    }
    else {
      $json = [
        'image' => [
          'raw' => base64_encode(file_get_contents($path)),
        ],
        'lang' => $this->languageManager->getCurrentLanguage()->getId(),
      ];
    }
    try {
      $credentials = new AutoAlterCredentials();
      $credentials->setCredentials($this->config->get('credential_provider'), $this->config->get('credentials') ?? []);
      $apiKey = $credentials->getApikey();
      $response = $this->httpClient->post('https://alttext.ai/api/v1/images', [
        'headers' => ['X-API-Key' => $apiKey],
        'json' => $json,
      ]);

      if (empty($response)) {
        $this->messenger->addWarning($this->t('The Alttext.AI service returned an empty response.'));
        return '';
      }

      if ($response->getStatusCode() == 200) {
        $data = json_decode($response->getBody(), TRUE);
        if (!empty($data['alt_text'])) {
          return $data['alt_text'];
        }
      }
      else {
        $this->messenger->addWarning($this->t('The Alttext.AI service returned an error: @error', [
          '@error' => $response->getReasonPhrase(),
        ]));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addWarning($this->t('The Alttext.AI service returned an error: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
    return '';
  }

  public function getPluginId() {
    return 'alttext_ai';
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginDefinition() {
    return [
      'id' => 'azure_cognitive_services',
      'title' => 'Azure Cognitive Services',
    ];
  }

  public function buildConfigurationForm() {
    $form = [];

    $form['credentials'] = [
      '#id' => 'credentials',
      '#type' => 'details',
      '#title' => $this->t('Credentials'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['credentials']['credential_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Credential provider'),
      '#options' => [
        'config' => $this->t('Local configuration'),
      ],
      '#default_value' => $this->config->get('credential_provider'),
    ];

    $form['credentials']['providers'] = [
      '#type' => 'item',
      '#id' => 'credentials_configuration',
    ];

    $provider_config_state = [':input[name="credentials[credential_provider]"]' => ['value' => 'config']];
    $form['credentials']['providers']['config']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key (config)'),
      '#description' => $this->t('The API key for @link', [
        '@link' => Link::fromTextAndUrl($this->t('Alttext.AI'), Url::fromUri('https://alttext.ai/'))->toString(),
      ]),
      '#default_value' => $this->config->get('credentials.config.api_key'),
      '#states' => [
        'visible' => $provider_config_state,
        'required' => $provider_config_state,
      ],
    ];

    if (\Drupal::moduleHandler()->moduleExists('key')) {
      $form['credentials']['credential_provider']['#options']['key'] = $this->t('Key Module');
      $provider_key_state = [':input[name="credentials[credential_provider]"]' => ['value' => 'key']];
      $form['credentials']['providers']['key']['api_key_key'] = [
        '#type' => 'key_select',
        '#title' => $this->t('API Key (Key)'),
        '#default_value' => $this->config->get('credentials.key.api_key_key'),
        '#empty_option' => $this->t('- Please select -'),
        '#key_filters' => ['type' => 'authentication'],
        '#description' => $this->t('Your API key stored as a secure key.'),
        '#states' => [
          'visible' => $provider_key_state,
          'required' => $provider_key_state,
        ],
      ];
    }
    else {
      $form['credentials']['credential_provider']['#value'] = 'config';
      $form['credentials']['credential_provider']['#disabled'] = TRUE;
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm($form_state) {
    $credentials = new AutoAlterCredentials();
    $credential_provider = $form_state->getValue([
      'credentials',
      'credential_provider',
    ]);
    $credentials_values = $form_state->getValue(['credentials', 'providers']);
    $credentials->setCredentials($credential_provider, $credentials_values ?? []);
    $api_key = $credentials->getApikey();

    // The API key is at least 32 characters long.
    if (strlen($api_key) < 32) {
      $form_state->setErrorByName('api_key', $this->t('The API key is invalid.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm($form_state, $config) {
    $values = $form_state->getValues();
    $credential_provider = $form_state->getValue([
      'credentials',
      'credential_provider',
    ]);
    $credentials = $form_state->getValue([
      'credentials',
      'providers',
      $credential_provider,
    ]);
    $config
      ->set('credential_provider', $credential_provider)
      ->set('credentials', [])
      ->set("credentials.$credential_provider", $credentials)
      ->set('status', $values['status'])
      ->set('suggestion', $values['suggestion'])
      ->save();
  }

}
