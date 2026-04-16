<?php

namespace Drupal\konsolifin_some_links\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SoMeLink constraint.
 */
class SoMeLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$value) {
      return;
    }

    $uri = $value->uri;
    
    // Empty URIs are fine, but if it has a value, check it.
    if (empty($uri)) {
      return;
    }

    // The prepended validation might add https:// but the link module might store it.
    // If it's a valid social media URL, let it pass.
    $valid = FALSE;

    // Instagram: https://www.instagram.com/username
    if (preg_match('/^(?:https?:\/\/)?(?:www\.)?instagram\.com\/([a-zA-Z0-9_\.]+)\/?/i', $uri)) {
      $valid = TRUE;
    }
    // Threads: https://www.threads.net/@username
    elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?threads\.net\/@([a-zA-Z0-9_\.]+)\/?/i', $uri)) {
      $valid = TRUE;
    }
    // Threads format without @ just in case: threads.net/username
    elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?threads\.net\/([a-zA-Z0-9_\.]+)\/?/i', $uri)) {
        $valid = TRUE;
    }
    // BlueSky: https://bsky.app/profile/username.bsky.social
    elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?bsky\.app\/profile\/([a-zA-Z0-9_\.-]+)\/?/i', $uri)) {
      $valid = TRUE;
    }
    // LinkedIn: https://www.linkedin.com/in/username/
    elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/([a-zA-Z0-9_-]+)\/?/i', $uri)) {
      $valid = TRUE;
    }

    if (!$valid) {
      $this->context->addViolation($constraint->message);
    }
  }

}
