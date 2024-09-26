<?php declare(strict_types = 1);

namespace Drupal\aladin_sp_login\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Aladin SP Login routes.
 */
final class AladinSpLoginController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): TrustedRedirectResponse|RedirectResponse {

    # Redirect to user page if user is already logged in.
    $logged_in = Drupal::currentUser()->isAuthenticated();
    if ($logged_in) {
      return $this->redirect('user.page');
    }

    # If we got this far, we have a valid auth_type
    $aladin_sp_url = Drupal::config('aladin_sp_login.settings')->get('aladin_sp_url');  # Get the Aladin-SP URL
    $slug = Drupal::config('aladin_sp_login.settings')->get('service_slug');  # Get the service slug

    # Redirect to the Aladin-SP login page
    return new TrustedRedirectResponse($aladin_sp_url . '/login?service=' . urlencode($slug));
  }
}
