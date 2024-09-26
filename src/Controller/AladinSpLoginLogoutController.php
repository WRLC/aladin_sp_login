<?php

namespace Drupal\aladin_sp_login\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AladinSpLoginLogoutController extends ControllerBase {
  public function __invoke(): TrustedRedirectResponse|RedirectResponse {
    // Check if user is logged in
    $logged_in = Drupal::currentUser()->isAuthenticated();

    // Redirect to front page if user is not logged in
    if (!$logged_in) {
      return $this->redirect('<front>');
    }

    // If user is logged in, log them out
    else {
      $request = Drupal::request();  # Get the request object
      $session = $request->getSession();  # Get the session object

      // If user is session contains 'authenticated_by_memcached' variable (set during login)
      if ($session->has('authenticated_by_memcached')) {
        user_logout();  # Log the user out
        $aladin_sp_url = \Drupal::config('aladin_sp_login.settings')->get('aladin_sp_url');  # Get the Aladin-SP URL
        $slug = Drupal::config('aladin_sp_login.settings')->get('service_slug');  # Get the service slug

        // Redirect to the Aladin-SP logout page
        return new TrustedRedirectResponse($aladin_sp_url . '/logout?service=' . urlencode($slug));
      }

      // If the user is not authenticated by memcached
      else {
        user_logout();  # Log the user out
        return $this->redirect('<front>');  # Redirect to the front page
      }
    }

  }
}
