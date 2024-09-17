<?php

namespace Drupal\auto_alter\Form;

use Drupal\auto_alter\AutoAlterCredentials;
use Drupal\auto_alter\Plugin\AutoAlterDescribeImagePluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The Module Handler.
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

    $options = [];
    $plugins = $this->describeImage->getDefinitions();
    foreach ($plugins as $plugin) {
      $options[$plugin['id']] = $plugin['title'];
    }

    $form['engine'] = [
      '#type' => 'select',
      '#title' => $this->t('Image description engine'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $config->get('engine'),
      '#ajax' => [
        'callback' => '::updateEngineConfiguration',
        'wrapper' => 'engine-configuration-wrapper',
      ],
    ];

    $form['engine_configuration'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'engine-configuration-wrapper'],
    ];

    // Load the configuration form for the selected engine.
    $engine_id = $form_state->getValue('engine', $config->get('engine'));
    if (isset($plugins[$engine_id])) {
      $plugin = $this->describeImage->createInstance($engine_id);
      $form['engine_configuration'] += $plugin->buildConfigurationForm([], $form_state);
    }


    $form['status'] = [
      '#type' => 'checkbox',
      '#required' => FALSE,
      '#title' => $this->t('Show status message to user'),
      '#default_value' => $config->get('status'),
      '#description' => $this->t('If checked, a status message is generated after saving: "Alternate text has been changed to: "%text" by a confidence of %confidence"'),
    ];

    $form['suggestion'] = [
      '#type' => 'checkbox',
      '#required' => FALSE,
      '#title' => $this->t('Make suggestion for alternative text'),
      '#default_value' => $config->get('suggestion'),
      '#description' => $this->t('If checked and alternative text is enabled for the field, a suggestion for the alternative text is created, when image is uploaded to the system.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function updateEngineConfiguration(array $form, FormStateInterface $form_state) {
    return $form['engine_configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] !== 'edit-submit') {
      return;
    }

    $engine = $form_state->getValue('engine');
    $plugin = $this->describeImage->createInstance($engine);
    $plugin->validateConfigurationForm($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = &$form_state->getValues();
    $engine = $form_state->getValue('engine');
    $plugin = $this->describeImage->createInstance($engine);
    $config = $this->config('auto_alter.settings');
    $plugin->submitConfigurationForm($form_state, $config);
    $config
      ->set('status', $values['status'])
      ->set('engine', $values['engine'])
      ->set('suggestion', $values['suggestion'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
