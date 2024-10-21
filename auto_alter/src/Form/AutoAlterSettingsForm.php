<?php

namespace Drupal\auto_alter\Form;

use Drupal\auto_alter\AutoAlterCredentials;
use Drupal\auto_alter\Plugin\AutoAlterDescribeImagePluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


const DEFAULT_PROMPT = "Create alt text for an image, following WCAG guidelines, at most 125 characters long.  
Make reasonable inferences only when identifying well-known characters, locations, objects, or text that are clearly visible in the image. 
Do not infer emotions, intentions, or any contextual meaning not directly observable in the image.
1. Begin by describing the main subject, followed by key details, and conclude with visible contextual elements.
2. Include relevant image text verbatim if it's integral to understanding the image.
3. Be clear and include necessary details without over-describing.
4. Avoid repetition and redundancy.
5. Do not make inferences or suggestions (e.g., don't say 'this shows/means/suggests...').
6. Do not begin with 'Alt text:'.
7. Incorporate keywords directly relevant to the image's primary content; avoid keyword stuffing (1-2 keywords max).";

/**
 * Class AutoAlterSettingsForm.
 *
 * @package Drupal\auto_alter\Form
 */
class AutoAlterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_alter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'auto_alter.settings',
    ];
  }

  /**
   * The Module Handler.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $modulehandler;

  /**
   * The Describe Image Plugin Manager.
   *
   * @var AutoAlterDescribeImagePluginManager
   */
  protected $describeImage;

  /**
   * Class constructor.
   */
  public function __construct(ModuleHandlerInterface $module_handler, AutoAlterDescribeImagePluginManager $describe_image) {
    $this->modulehandler = $module_handler;
    $this->describeImage = $describe_image;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.auto_alter_describe_image')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('auto_alter.settings');

    $form['service_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose AI Service'),
      '#options' => [
        'openai' => $this->t('OpenAI'),
        'azure_openai' => $this->t('Azure OpenAI'),
      ],
      '#default_value' => $config->get('service_selection') ?: 'openai',
      '#ajax' => [
        'callback' => '::updateServiceFields',
        'wrapper' => 'service-fields-wrapper',
      ],
    ];

    $form['service_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'service-fields-wrapper'],
    ];

    $service = $form_state->getValue('service_selection', $config->get('service_selection') ?: 'openai');

    if ($service === 'openai') {
      $form['service_fields']['openai_api_key'] = [
        '#type' => 'password',
        '#title' => $this->t('OpenAI API Key'),
        '#default_value' => $config->get('openai_api_key'),
      ];

      $form['service_fields']['openai_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Predefined OpenAI Prompt'),
        '#default_value' => $config->get('openai_prompt'),
        '#description' => $this->t('Define the prompt for OpenAI requests.'),
      ];

      $form['service_fields']['openai_restore'] = [
        '#type' => 'submit',
        '#value' => $this->t('Restore Default Prompt'),
        '#submit' => ['::restoreOpenAIPrompt'],
      ];
    }
    elseif ($service === 'azure_openai') {
      $form['service_fields']['azure_api_base'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Azure API Base URL'),
        '#default_value' => $config->get('azure_api_base'),
        '#description' => $this->t('Example: https://api.example.com/azure-openai-api'),
      ];

      $form['service_fields']['azure_api_key'] = [
        '#type' => 'password',
        '#title' => $this->t('Azure API Key'),
        '#default_value' => $config->get('azure_api_key'),
      ];

      $form['service_fields']['azure_organization'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Azure OpenAI Organization'),
        '#default_value' => $config->get('azure_organization'),
        '#description' => $this->t('Define your Azure OpenAI organization.'),
      ];

      $form['service_fields']['azure_api_version'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API Version'),
        '#default_value' => $config->get('azure_api_version'),
        '#description' => $this->t('Example: 2024-06-01'),
      ];

      $form['service_fields']['azure_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Predefined Azure OpenAI Prompt'),
        '#default_value' => $config->get('azure_prompt'),
        '#description' => $this->t('Define the prompt for Azure OpenAI requests.'),
      ];

      $form['service_fields']['azure_restore'] = [
        '#type' => 'submit',
        '#value' => $this->t('Restore Default Prompt'),
        '#submit' => ['::restoreAzurePrompt'],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function updateServiceFields(array $form, FormStateInterface $form_state) {
    return $form['service_fields'];
  }

  public function restoreOpenAIPrompt(array &$form, FormStateInterface $form_state) {
    $this->config('auto_alter.settings')
      ->set('openai_prompt', DEFAULT_PROMPT)
      ->save();
  }

  public function restoreAzurePrompt(array &$form, FormStateInterface $form_state) {
    $this->config('auto_alter.settings')
      ->set('azure_prompt', DEFAULT_PROMPT)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation logic here.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('auto_alter.settings');

    $service = $form_state->getValue('service_selection');
    $config->set('service_selection', $service);

    if ($service === 'openai') {
      $config->set('openai_api_key', $form_state->getValue('openai_api_key'))
             ->set('openai_prompt', $form_state->getValue('openai_prompt'));
    }
    elseif ($service === 'azure_openai') {
      $config->set('azure_api_base', $form_state->getValue('azure_api_base'))
             ->set('azure_api_key', $form_state->getValue('azure_api_key'))
             ->set('azure_organization', $form_state->getValue('azure_organization'))
             ->set('azure_api_version', $form_state->getValue('azure_api_version'))
             ->set('azure_prompt', $form_state->getValue('azure_prompt'));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
