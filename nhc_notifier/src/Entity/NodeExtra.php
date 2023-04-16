<?php

namespace Drupal\nhc_notifier\Entity;

use Drupal\node\Entity\Node;
use Drupal\nhc_notifier\Event\NodeExtraEvents;
use Drupal\nhc_notifier\NodeExtraInterface;

/**
 * Alter the Node entity class.
 */
class NodeExtra extends Node implements NodeExtraInterface {

  /**
   * {@inheritdoc}
   */
  public function save() {
    if ($this->isNew()) {
      \Drupal::service('event_dispatcher')->dispatch(
        NodeExtraEvents::NODE_EXTRA_ENTITY_INSERT,
        new NodeExtraEvents($this)
      );
    }

    return parent::save();
  }

}
