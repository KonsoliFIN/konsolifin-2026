<?php

namespace Drupal\konsolifin_misc\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing Drupal routes.
 *
 * D7 equivalent: hook_menu_alter() in konsolifin.module
 *
 * The D7 module overrode user/password to redirect to the external forum's
 * lost-password page (HTTP 410 Gone).  In D11 this is done by replacing the
 * route's controller via a RouteSubscriber.
 *
 * Registration in konsolifin.services.yml:
 *
 *   konsolifin.route_subscriber:
 *     class: Drupal\konsolifin_misc\EventSubscriber\RouteSubscriber
 *     tags:
 *       - { name: event_subscriber }
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Replace the built-in password-reset page with our own controller method.
    // D7: header("HTTP/1.1 410 Gone"); header("Location: https://forum.konsolifin.net/lost-password/");
    if ($route = $collection->get('user.pass')) {
      $route->setDefault(
        '_controller',
        '\Drupal\konsolifin_misc\Controller\KonsolifinController::resetPasswordRedirect'
      );
      // Remove any access restrictions so anonymous users get the redirect too.
      $route->setRequirement('_access', 'TRUE');
    }
  }

}
