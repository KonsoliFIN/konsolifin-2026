<?php

namespace Drupal\migrate_konsolifin\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node;

/**
 * Extends the D7 Node source to include URL aliases.
 *
 * @MigrateSource(
 *   id = "d7_node_with_alias",
 *   source_module = "node"
 * )
 */
class D7NodeWithAlias extends Node {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if (!parent::prepareRow($row)) {
      return FALSE;
    }

    $nid = $row->getSourceProperty('nid');
    $source_path = 'node/' . $nid;

    $alias = $this->getDatabase()->select('url_alias', 'ua')
      ->fields('ua', ['alias'])
      ->condition('ua.source', $source_path)
      ->orderBy('ua.pid', 'DESC')
      ->execute()
      ->fetchField();

    if ($alias) {
      $row->setSourceProperty('url_alias', '/' . ltrim($alias, '/'));
    }

    return TRUE;
  }

}
