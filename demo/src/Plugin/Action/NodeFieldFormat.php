<?php

namespace Drupal\demo\Plugin\Action;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Change node's fields formats.
 *
 * @Action(
 *   id = "node_field_format_action",
 *   label = @Translation("Change field's format"),
 *   type = "node"
 * )
 */
class NodeFieldFormat extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new AssignOwnerNode action.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    // Get all fields.
    $fields = $entity->getFieldDefinitions();

    foreach ($fields as $field_name => $field_definition) {

      // Check if the field is a text field with CKEditor.
      if ($field_definition->getType() === 'text_with_summary' || $field_definition->getType() === 'text_long') {

        // Current text format.
        $old_text_format = $entity->get($field_name)->format;

        // Change format.
        if ($entity->get($field_name)->format !== $this->configuration['format']) {
          $entity->get($field_name)->format = $this->configuration['format'];
        } else {
          $this->messenger()->addMessage("Nothing happened");
          return;
        }

        // Save
        $entity->save();

        // Log the changes.
        $logger = \Drupal::logger('custom_text_format_action');
        $logger->info('Node ID: @nid, Old text format: @old_format, New text format: @new_format', [
          '@nid' => $entity->id(),
          '@old_format' => $old_text_format,
          '@new_format' => $this->configuration['format'],
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['format' => 'basic_html'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $formats = \Drupal::database()->query("select name from {config} where name like 'filter.format%'")->fetchAll();
    $values = [];

    foreach ($formats as $item) {
      $values[] = mb_substr($item->name, 14);
    }
    $form['format'] = [
      '#title' => $this->t('Choose your primary format'),
      '#options' => array_combine($values, $values),
      '#type' => 'select',
      '#required' => TRUE,
      '#default_value' => $this->configuration['format'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['format'] = $form_state->getValue('format');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}
