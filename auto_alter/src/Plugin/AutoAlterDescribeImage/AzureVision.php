<?php

namespace Drupal\auto_alter\Plugin\AutoAlterDescribeImage;

use Drupal\auto_alter\AutoAlterCredentials;
use Drupal\auto_alter\DescribeImageServiceInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Azure Cognitive Service integration.
 *
 * @AutoAlterDescribeImage(
 *   id = "azure_cognitive_services",
 *   title = @Translation("Azure Cognitive Services"),
 * )
 */
class AzureVision implements DescribeImageServiceInterface {

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

  protected $pluginId = 'azure_cognitive_services';

  protected $pluginDefinition = [
    'id' => 'azure_cognitive_services',
    'title' => 'Azure Cognitive Services',
  ];

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Class constructor.
   */
  public function __construct(FileSystemInterface $file_system, ClientInterface $http_client, ConfigFactory $configFactory, LoggerChannelFactoryInterface $loggerFactory, ModuleHandlerInterface $module_handler) {
    $this->fileSystem = $file_system;
    $this->httpClient = $http_client;
    $this->config = $configFactory->getEditable('auto_alter.settings');
    $this->loggerFactory = $loggerFactory->get('auto_alter');
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function checkSetup() {
    $endpoint = Xss::filter($this->config->get('endpoint'));
    $credentials = new AutoAlterCredentials();
    $credentials->setCredentials($this->config->get('credential_provider'), $this->config->get('credentials') ?? []);
    $api_key = Xss::filter($credentials->getApikey());
    if (empty($api_key) || empty($endpoint)) {
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
    $endpoint = $this->config->get('endpoint');
    $credentials = new AutoAlterCredentials();
    $credentials->setCredentials($this->config->get('credential_provider'), $this->config->get('credentials') ?? []);
    return $this->sendDescriptionRequest($uri_or_realpath, $endpoint, $credentials);
  }

  /**
   * Send the request to retrieve the alt text from the given endpoint.
   */
  protected function sendDescriptionRequest(string $uri_or_realpath, string $endpoint, AutoAlterCredentials $credentials) {
    $path = $this->fileSystem->realpath($uri_or_realpath);
    $client = $this->httpClient;

    if(empty($path)) {
      $body = [
        'json' => [
          'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($uri_or_realpath)
        ]
      ];
    } else {
      $body = [
        'multipart' => [
          [
            'name' => 'file',
            'contents' => fopen($path, "r"),
          ],
        ],
      ];
    }

    try {
      $api_key = $credentials->getApikey();
      $body['headers'] = [
          'Ocp-Apim-Subscription-Key' => Xss::filter($api_key),
      ];
      $request = $client->post(Xss::filter($endpoint), $body);
    }
    catch (\Exception $e) {
      $this->loggerFactory->error(
        "Azure Cognitive Services error code @code: @message",
        [
          '@code' => $e->getCode(),
          '@message' => $e->getMessage(),
        ]
      );
      return '';
    }

    if ($request !== FALSE && $request->getStatusCode() == 200) {
      $response = json_decode($request->getBody());
      if (isset($response->description->captions[0]->text)) {
        $alternate_text = $response->description->captions[0]->text;
      }
      else {
        if (isset($response->description->tags)) {
          $alternate_text = rtrim(implode(',', $response->description->tags), ',');
        }
      }

      if ($this->moduleHandler->moduleExists('auto_alter_translate')) {
        $active = \Drupal::config('auto_alter_translate.settings')
          ->get('active');
        $engine = \Drupal::configFactory()->getEditable('auto_alter.settings')->get('engine');
        $region = \Drupal::config('auto_alter_translate.settings')
          ->get('region');
        if ($active && $engine == 'azure_cognitive_services') {
          $request = \Drupal::service('auto_alter_translate.get_translation')
            ->gettranslation($alternate_text, $region);
          if (!empty($request) && $request->getStatusCode() == 200) {
            $trans = (array) json_decode($request->getBody());
            $alternate_text = $trans[0]->translations[0]->text;
          }
        }
      }
    }

    return $alternate_text;
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritDoc}
   */
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

    $form['endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('URL of Endpoint'),
      '#default_value' => $this->config->get('endpoint'),
      '#description' => $this->t('Enter the URL of your Endpoint here. fe. https://westeurope.api.cognitive.microsoft.com/vision/v1.0/describe?maxCandidates=1 for West Europe'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm($form_state) {
    $values = $form_state->getValues();
    $endpoint = $values['endpoint'];
    $credentials = new AutoAlterCredentials();
    $credential_provider = $form_state->getValue(['credentials', 'credential_provider']);
    $credentials_values = $form_state->getValue(['credentials', 'providers']);
    $credentials->setCredentials($credential_provider, $credentials_values ?? []);
    $path = $this->moduleHandler->getModule('auto_alter')->getPath();

    $alt = $this->sendDescriptionRequest($path . '/image/test.jpg', $endpoint, $credentials);

    if (!empty($alt)) {
      \Drupal::messenger()->addStatus($this->t('Your settings have been successfully validated'));
    }
    else {
      $form_state->setErrorByName('credentials', $this->t('The API Key or the endpoint seem to be wrong. Please check in your Azure Console.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm($form_state, $config) {
    $values = $form_state->getValues();
    $credential_provider = $form_state->getValue(['credentials', 'credential_provider']);
    $credentials = $form_state->getValue([
      'credentials',
      'providers',
      $credential_provider,
    ]);
    $config
      ->set('endpoint', $values['endpoint'])
      ->set('credential_provider', $credential_provider)
      ->set('credentials', [])
      ->set("credentials.$credential_provider", $credentials)
      ->set('status', $values['status'])
      ->set('suggestion', $values['suggestion'])
      ->save();
  }

}
