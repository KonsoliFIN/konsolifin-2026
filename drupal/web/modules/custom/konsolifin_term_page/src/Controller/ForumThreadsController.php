<?php

declare (strict_types = 1);

namespace Drupal\konsolifin_term_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

// ...

class ForumThreadsController extends ControllerBase {

  const BASE_URL = 'https://forum.konsolifin.net/';

  static public function get_thread_title_by_id(int $thread_id): ?string {
    try {
      $connection = Database::getConnection('default', 'xenforo');
      $query      = $connection->select('xf_thread', 'xt')
        ->fields('xt', ['title'])
        ->condition('thread_id', $thread_id);
      $result = $query->execute()->fetchField();
      return $result ?: null;
    } catch (\Exception $e) {
      \Drupal::logger('konsolifin_term_page')->error('Database connection failed: @message', ['@message' => $e->getMessage()]);
      return null;
    }
  }

  public function query(Request $request, ?string $forum_id = null): JsonResponse {
    // Extract the string typed by the user in the autocomplete text field.
    $string = $request->query->get('q', '');

    // Get the connection to the external 'xenforo' database.
    try {
      $connection = Database::getConnection('default', 'xenforo');

      // Use the dynamic query builder to prevent SQL injection.
      $query = $connection->select('xf_thread', 'xt')
        ->fields('xt', ['thread_id', 'title'])
        ->condition('title', '%' . $connection->escapeLike($string) . '%', 'LIKE')
        ->condition('node_id', 6); // Node 6 = "Pelit" forum

      if ($forum_id) {
        $query->condition('node_id', $forum_id);
      }

      $res = $query->execute();

      $matches = [];
      foreach ($res as $row) {
        // Drupal's core autocomplete functionality expects an array of objects,
        // each with a 'value' and a 'label' key.
        $matches[] = [
          'value' => (string) $row->thread_id,
          'label' => $row->title,
        ];
      }

      return new JsonResponse($matches);
    } catch (\Exception $e) {
      // Log the exception for debugging purposes.
      \Drupal::logger('konsolifin_term_page')->error('Database connection failed: @message', ['@message' => $e->getMessage()]);
    }

    // If the connection fails, return an empty result with a 500 status.
    return new JsonResponse([], 500);
  }

}
