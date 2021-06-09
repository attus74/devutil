<?php

namespace Drupal\devutil\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\devutil\EntityManager;

/**
 * Create Code for a new Entity Type
 * 
 * @author Attila Németh
 * 19.02.2019
 */
class EntityForm extends FormBase {
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devutil_entity_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Machine readable name'),
      '#required' => TRUE,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => t('Human readable label'),
      '#required' => TRUE,
    ];
    $form['bundle'] = [
      '#type' => 'checkbox',
      '#title' => t('Type'),
      '#description' => t('Whether this entity type has bundles (i.e. types)'),
    ];
    $handler = \Drupal::service('module_handler');
    $allModules = $handler->getModuleList();
    $options = [
      '0' => t('new module'),
    ];
    foreach(array_keys($allModules) as $moduleName) {
      $options[$moduleName] = $handler->getName($moduleName) . ' (' . $moduleName . ')';
    }
    $form['module'] = [
      '#type' => 'select',
      '#title' => t('Module'),
      '#required' => TRUE,
      '#default_value' => 0,
      '#options' => $options,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Create'),
    ];
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('module') === '0') {
      $module = $form_state->getValue('name');
    }
    else {
      $module = $form_state->getValue('module');
    }
    $manager = new EntityManager();
    $manager->create($form_state->getValue('name'), $form_state->getValue('label'), $form_state->getValue('bundle'), $module);
    \Drupal::messenger()->addStatus(t('Code is created'));
  }
  
}
