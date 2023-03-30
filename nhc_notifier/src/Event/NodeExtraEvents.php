<?php

namespace Drupal\nhc_notifier\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\nhc_notifier\NodeExtraInterface;

/**
 * Node insert event.
 */
class NodeExtraEvents extends Event {

  const NODE_EXTRA_ENTITY_INSERT = 'nhc_notifier.node_extra.insert';

  /**
   * NHC NodeExtra entity.
   *
   * @var \Drupal\nhc_notifier\NodeExtraInterface
   */
  protected $entity;

  /**
   * Constructs a NodeExtra entity insert event object.
   *
   * @param \Drupal\nhc_notifier\NodeExtraInterface $entity
   *   NodeExtra entity.
   */
  public function __construct(NodeExtraInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Get the NodeExtra.
   *
   * @return \Drupal\nhc_notifier\NodeExtraInterface
   *   NodeExtra entity.
   */
  public function getEntity(): NodeExtraInterface {
    return $this->entity;
  }

}
