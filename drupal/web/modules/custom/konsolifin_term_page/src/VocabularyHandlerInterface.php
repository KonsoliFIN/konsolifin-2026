<?php

namespace Drupal\konsolifin_term_page;

use Drupal\taxonomy\TermInterface;

interface VocabularyHandlerInterface {

  /**
   * Returns the vocabulary ID this handler supports.
   */
  public function getVocabularyId(): string;

  /**
   * Preprocesses template variables for a taxonomy term page.
   *
   * @param array &$variables
   *   The preprocess variables array (by reference).
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term being rendered.
   */
  public function preprocess(array &$variables, TermInterface $term): void;

}
