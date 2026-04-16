<?php

namespace Drupal\konsolifin_some_links\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted link is a valid supported social media profile.
 *
 * @Constraint(
 *   id = "SoMeLink",
 *   label = @Translation("SoMe Link", context = "Validation"),
 * )
 */
class SoMeLinkConstraint extends Constraint {
  public $message = 'Vain BlueSky, Instagram, LinkedIn ja Threads ovat tuettuja palveluita. Kopioi oikea linkki profiiliisi tähän.';
}
