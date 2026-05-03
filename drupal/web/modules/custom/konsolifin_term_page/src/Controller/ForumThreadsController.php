<?php

declare (strict_types = 1);

namespace Drupal\konsolifin_term_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;

class ForumThreadsController extends ControllerBase {

  public function query(string $string = '', ?string $forum_id = null): JsonResponse {
    // Get the connection to the external 'xenforo' database.
    try {
      $connection = Database::getConnection('default', 'xenforo');

      // Use the dynamic query builder to prevent SQL injection.
      $query = $connection->select('xf_thread', 'xt')
        ->fields('xt', ['thread_id', 'title'])
        ->condition('title', '%' . $connection->escapeLike($string) . '%', 'LIKE');

      if ($forum_id) {
        $query->condition('node_id', $forum_id);
      }

      $res = $query->execute();

      $matches = [];
      foreach ($res as $row) {
        // Per the request, use the thread ID as the key and title as the value.
        $matches[$row->thread_id] = $row->title;
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
