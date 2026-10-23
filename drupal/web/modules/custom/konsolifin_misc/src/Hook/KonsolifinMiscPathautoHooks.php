<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pathauto\AliasCleanerInterface;

/**
 * Pathauto hook implementations for konsolifin_misc.
 */
class KonsolifinMiscPathautoHooks {

  public function __construct(
    protected readonly AliasCleanerInterface $aliasCleaner,
  ) {}

  /**
   * Implements hook_pathauto_alias_alter().
   */
  #[Hook('pathauto_alias_alter')]
  public function pathautoAliasAlter(string &$alias, array &$context): void {
    if ($context['module'] === 'node' && isset($context['data']['node'])) {
      $alias_parts = explode('/', $alias);
      $node = $context['data']['node'];

      if ($node->hasField('field_sarja') && !$node->get('field_sarja')->isEmpty()) {
        $term = $node->get('field_sarja')->entity;
        if ($term && isset($alias_parts[1], $alias_parts[2])) {
          $alias = '/' . $alias_parts[1] .
            '/' . $this->aliasCleaner->cleanString($term->getName()) .
            '-' . $alias_parts[2];
        }
      }

      if (
        $node->hasField('field_pelit') &&
        !$node->get('field_pelit')->isEmpty() &&
        count($node->get('field_pelit')) === 1 &&
        $node->get('field_pelit')->entity
      ) {
        $alias = $alias . '-' . $this->aliasCleaner->cleanString($node->get('field_pelit')->entity->getName());
      }

      if ($node->hasField('field_pelin_nimi') && !$node->get('field_pelin_nimi')->isEmpty()) {
        $alias = $alias . '-' . $this->aliasCleaner->cleanString((string) $node->get('field_pelin_nimi')->value);
      }
    }
  }

}
