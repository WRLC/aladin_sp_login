<?php declare(strict_types = 1);

namespace Drupal\aladin_sp_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Aladin-SP Login settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aladin_sp_login_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aladin_sp_login.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    # service_slug field for telling Aladin-SP which service is authenticating
    $form['service_slug'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service Slug'),
      '#default_value' => $this->config('aladin_sp_login.settings')->get('service_slug'),
      '#required' => TRUE,
      '#description' => 'The service slug used to identify this service in Aladin-SP',
    ];

    # aladin_sp_url field for specifying Aladin's web address
    $form['aladin_sp_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Aladin-SP URL'),
      '#default_value' => $this->config('aladin_sp_login.settings')->get('aladin_sp_url'),
      '#required' => TRUE,
      '#description' => 'The base URL of the Aladin-SP instance to use for authentication (including protocol and—if non-standard for protocol—port number)',
    ];

    # memcached_server field for address of memcached server
    $form['memcached_server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Memcached server address'),
      '#default_value' => $this->config('aladin_sp_login.settings')->get('memcached_server'),
      '#required' => TRUE,
      '#description' => 'The FQDN or IP address of Aladin\'s memcached server (do NOT include a protocol or port number)',
    ];

    # memcached_port field for the port used by the memcached server
    $form['memcached_port'] = [
      '#type' => 'number',
      '#title' => $this->t('Memcached port'),
      '#default_value' => $this->config('aladin_sp_login.settings')->get('memcached_port'),
      '#required' => TRUE,
      '#description' => 'The TCP port used by Aladin\'s memcached server (usually 11211)',
    ];

    # memcached_cookie field for the cookie name containing the memcached key
    $form['memcached_cookie_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Memcached cookie prefix'),
      '#default_value' => $this->config('aladin_sp_login.settings')->get('memcached_cookie_prefix'),
      '#description' => 'The prefix of Aladin-SP\'s cookie name containing the memcached key',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aladin_sp_login.settings')
      ->set('service_slug', $form_state->getValue('service_slug'))
      ->set('aladin_sp_url', $form_state->getValue('aladin_sp_url'))
      ->set('memcached_server', $form_state->getValue('memcached_server'))
      ->set('memcached_port', $form_state->getValue('memcached_port'))
      ->set('memcached_cookie_prefix', $form_state->getValue('memcached_cookie_prefix'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
