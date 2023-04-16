<?php

namespace Drupal\nhc_notifier\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use Drupal\nhc_notifier\Event\NodeExtraEvents;

/**
 * Event subscriber.
 */
class NodeExtraEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The object renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Construct.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactory $loggerFactory, MailManagerInterface $mail_manager, RendererInterface $renderer) {
    $this->etm = $entity_type_manager;
    $this->loggerFactory = $loggerFactory->get('nhc_notifier');
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      NodeExtraEvents::NODE_EXTRA_ENTITY_INSERT => ['onNodeExtraEntityInsert'],
    ];
  }

  /**
   * Node Extra insert listener.
   */
  public function onNodeExtraEntityInsert(NodeExtraEvents $event) {
    $node = $event->getEntity();

    $field_first_choice = $node->get('field_first_choice')->entity ?? NULL;
    if (!$field_first_choice instanceof TermInterface) {
      return;
    }

    if (!$field_first_choice->get('status')->value) {
      return;
    }

    $tid = $field_first_choice->id();
    $term_name = $field_first_choice->getName();
    $search_for = ',';

    if (strpos($term_name, $search_for) !== FALSE) {
      $parts = explode($search_for, $term_name);
      $term_name = $parts[0];
    }
    $params['title'] = $node->getTitle();
    $params['location'] = $term_name;

    $query = $this->etm->getStorage('user')->getQuery();
    $uids = $query->condition('field_first_choice_emails', $tid)
      ->condition('status', '1')
      ->execute();
    $users = $this->etm->getStorage('user')->loadMultiple($uids);

    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }

      $user_mail = $user->get('mail')->value;
      $langcode = $user->get('langcode')->value;

      if (!$user_mail || !$langcode) {
        continue;
      }

      $this->sendMail($user_mail, $langcode, $params);
    }
  }

  /**
   * Send mail handler.
   */
  public function sendMail(string $user_mail, string $langcode, array $info): void {
    $url = Url::fromUri('https://leadershipapplication.nationalhealthcorps.org');
    $link = Link::fromTextAndUrl(t('leadershipapplication.nationalhealthcorps.org'), $url);

    $body_data = [
      '#theme' => 'application_review',
      '#title' => $info['title'],
      '#location' => $info['location'],
      '#link' => $link->toRenderable(),
    ];
    $params['info'] = $this->t("%title a candidate for %location has submitted an application in the portal.", [
      '%title' => $info['title'],
      '%location' => $info['location'],
    ]);
    $params['message'] = $this->renderer->render($body_data);

    $module = 'nhc_notifier';
    $key = 'application_node_insert';
    $to = $user_mail;
    $reply = 'noreply@nationalhealthcorps.org';
    $send = TRUE;

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, $reply, $send);

    if ($result['result'] !== TRUE) {
      $this->loggerFactory->notice('Something went wrong. Mail sending failed.');
    }
    else {
      $this->loggerFactory->notice('Mail has been sent.');
    }
  }

}
