<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\konsolifin_misc\Service\DataLayerService;
use Drupal\node\NodeInterface;

/**
 * Page attachments hook implementation for konsolifin_misc.
 */
class KonsolifinMiscPageAttachmentsHook {

  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DataLayerService $dataLayerService,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->routeMatch->getRouteName() !== 'entity.node.canonical') {
      return;
    }

    $node = $this->routeMatch->getParameter('node');
    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface) {
      $this->dataLayerService->attachDataLayer($attachments, $node);
    }
  }

}
