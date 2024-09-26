<?php
/** @noinspection PhpUnused */
declare(strict_types = 1);

namespace Drupal\aladin_sp_login\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Exception;
use Memcached;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Aladin-SP Login routes.
 */
final class AladinSpLoginCallbackController extends ControllerBase {

  /**
   * @throws EntityStorageException
   */
  public function __invoke(): Response {

    # Check if user is logged in
    $logged_in = Drupal::currentUser()->isAuthenticated();

    if (!$logged_in) {  # If user is not logged in...

      $user_array = $this->aladin_sp_login_get_memcached();  # Get user from memcached

      # Make sure the user array status is success
      if ($user_array['status'] !== 'success') {
        Drupal::messenger()->addMessage($user_array['message']);  # Display an error message
        Drupal::logger('aladin_sp_login')->error($user_array['error']);  # Log the error
        return $this->redirect('system.403');  # Redirect the user to the 403 page
      }

      # If the user exists, update their account
      if ($user = user_load_by_mail($user_array['Email'])) {

        try {  # Try to update the user
          $user = $this->aladin_sp_login_populate($user, $user_array);  # Update the user's account

          # If the first name field isn't set and it exists...
          if (!isset($user->field_first_name->value) && array_key_exists('field_first_name', Drupal::service('entity_field.manager')
              ->getFieldDefinitions('user', 'user'))) {
            $user->set('field_first_name', $user_array['GivenName']);  # Set the user's first name
          }

          # If the last name field isn't set and it exists...
          if (!isset($user->field_last_name->value) && array_key_exists('field_last_name', Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user'))) {
            $user->set('field_last_name', $user_array['Name']);  # Set the user's last name
          }

          $user->save();  # Save the user
        }

        catch (Exception $e) {  # If there's an error, log it but keep going because we might still be able to log the user in
          Drupal::logger('aladin_sp_login')->error('Failed to update user: ' . $e->getMessage());  # Log the error
        }
      }

      else {  # If the user doesn't alread exist

        try {  # Try to create a new user

          $user = Drupal\user\Entity\User::create();  # Create a new user
          $user = $this->aladin_sp_login_populate($user, $user_array);  # Populate the user's account

          # If the first name field exists...
          if (array_key_exists('field_first_name', Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user'))) {
            $user->set('field_first_name', $user_array['GivenName']);  # Set the user's first name
          }

          # If the last name field exists...
          if (array_key_exists('field_last_name', Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user'))) {
            $user->set('field_last_name', $user_array['Name']);  # Set the user's last name
          }

          $user->activate();  # Activate the user
          $user->save();  # Save the user

        }

        catch (Exception $e) {  # If there's an error, log it and redirect the user
          Drupal::logger('aladin_sp_login')->error('Failed to create user: ' . $e->getMessage());  # Log the error
          Drupal::messenger()->addMessage("Failed to create user. Please contact the WRLC Service Desk for assistance.");  # Display an error message
          return $this->redirect('system.403');  # Redirect the user to the 403 page
        }
      }

      # Finally time to log in the user
      try {  # Try to log in the user
        user_login_finalize($user);  # If the user is found, log them in
      }

      catch (Exception $e) {  # If there's an error, log it
        Drupal::logger('aladin_sp_login')->error('Failed to log in user: ' . $e->getMessage());  # Log the error
        Drupal::messenger()->addMessage("Failed to login user. Please contact the WRLC Service Desk for assistance.");  # Display an error message
        return $this->redirect('system.403');  # Redirect the user to the 403 page
      }

      # If we got this far, the user is now logged in!

      # If the user has a first and last name, welcome them by name
      if (array_key_exists('field_first_name', Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user'))) {
        if (isset($user->field_first_name->value)) {
          Drupal::messenger()->addMessage('Welcome back, ' . $user->field_first_name->value);
        }
      }
      else {  # Otherwise, welcome them generically
        Drupal::messenger()->addMessage('Welcome back');
      }
      Drupal::messenger()->addWarning(t('<div style="margin-bottom: 3px">Accounts are matched using the email address provided by your university\'s single-sign on system. If the emails don\'t match, a new account is created without any posting privileges.</div><div>If you already have an account on this site but a new one was created, please contact Joel Shields (<a href="mailto:shields@wrlc.org">shields@wrlc.org</a>) to merge the accounts.</div>'));  # Display a success message
    }

    # Add session variable marking session as authenticated by memcached (used by logout)
    $request = Drupal::request();  # Get the request
    $session = $request->getSession();  # Get the session
    $session->set('authenticated_by_memcached', TRUE);  # Set the authenticated_by_memcached session variable to TRUE

    return $this->redirect('user.page');  # Redirect the user to the user page
  }

  # Get user from memcached
  private function aladin_sp_login_get_memcached (): array {
    $user_array = [];  # Initialize empty user array

    # Get memcached cookie name from config
    $cookie_name = '_wr_' . Drupal::config('aladin_sp_login.settings')->get('service_slug');

    if (isset($_COOKIE[$cookie_name])) {  # Check for memcached cookie
      $session_id = $_COOKIE[$cookie_name];  # Get session ID from cookie

      # Get memcached server and port from config
      $memcached_server = Drupal::config('aladin_sp_login.settings')->get('memcached_server');
      $memcached_port = (int) Drupal::config('aladin_sp_login.settings')->get('memcached_port');

      $m = new Memcached();  # create memcached object
      $m->addServer($memcached_server, $memcached_port);  # add server to memcached object

      try {  # Try to get session from memcached
        $session_string = $m->get($session_id);  # Get session from memcached
        $session_split = explode("\n", $session_string);  # Explode the session string by line

        foreach ($session_split as $line) {  # Loop through the session string's lines

          if ($line !== '') {  # If the line isn't empty...
            $line_split = explode('=', $line);  # Explode the line into key/value by equals sign
            $user_array[trim($line_split[0])] = trim($line_split[1]);  # Add the key/value to the user array
          }
        }
      }

      catch (Exception $e) {  # If there's an error, set the user array to an error and return it
        return [
          'status' => 'error',
          'message' => 'Failed to get user credentials from SSO. Please contact the WRLC Service Desk for assistance.',
          'error' => 'Unable to retrieve session from Memcached' . $e->getMessage()
        ];
      }
    }

    else {  # If there's no memcached cookie, set the user array to an error and return it
      return [
        'status' => 'error',
        'message' => 'Failed to get user credentials from SSO. Please contact the WRLC Service Desk for assistance.',
        'error' => 'No memcached cookie found.'
      ];
    }

    # If we got this far, we have a user array
    $user_array['status'] = 'success';  # Set status to success

    return $user_array;  # Return the user array
  }

# Populate the user's account
  private function aladin_sp_login_populate ($user, $session) {
    $user->setUsername($session['UserName'] . '@' . $session['University']);  # Set the user's username
    $user->setEmail($session['Email']);  # Set the user's email
    # If the university field exists...
    if (array_key_exists('field_university', Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user'))) {
      $university = $this->aladin_sp_login_encode_university($session['University']);  # Encode the user's university
      $user->set('field_university', $university);  # Set the user's institution
    }
    return $user;
  }

  # Encode the user's university
  private function aladin_sp_login_encode_university ($university): string {
    if ($university == 'au') {
      $encoded_university = 'AU';  # American University
    }
    elseif ($university == 'amulaw') {
      $encoded_university = 'AU Law';  # AU Washington College of Law
    }
    elseif ($university == 'cu') {
      $encoded_university = 'CU';  # Catholic University of America
    }
    elseif ($university == 'culaw') {
      $encoded_university = 'CU Law';  # CUA Columbus School of Law
    }
    elseif ($university == 'ga') {
      $encoded_university = 'GA';  # Gallaudet University
    }
    elseif ($university == 'gm') {
      $encoded_university = 'GM';  # George Mason University
    }
    elseif ($university == 'gmlaw') {
      $encoded_university = 'GM Law';  # GMU Antonin Scalia Law School
    }
    elseif ($university == 'gw') {
      $encoded_university = 'GW';  # George Washington University
    }
    elseif ($university == 'gwahlth') {
      $encoded_university = 'GW HS';  # GW Himmelfarb Health Sciences Library
    }
    elseif ($university == 'gwalaw') {
      $encoded_university = 'GW Law';  # GW Law
    }
    elseif ($university == 'gt') {
      $encoded_university = 'GT';  # Georgetown University
    }
    elseif ($university == 'gt-law') {
      $encoded_university = 'GT Law';  # Georgetown University Law Center
    }
    elseif ($university == 'hu') {
      $encoded_university = 'HU';  # Howard University
    }
    elseif ($university == 'hulaw') {
      $encoded_university = 'HU Law';  # HU School of Law
    }
    elseif ($university == 'huhs') {
      $encoded_university = 'HUHS';  # HU Health Sciences
    }
    elseif ($university == 'mu') {
      $encoded_university = 'MU';  # Marymount University
    }
    elseif ($university == 'tr') {
      $encoded_university = 'TR';  # Trinity University Washington
    }
    elseif ($university == 'dc') {
      $encoded_university = 'DC';  # University of the District of Columbia
    }
    elseif ($university == 'dclaw') {
      $encoded_university = 'DC Law';  # UDC David A. Clarke School of Law
    }
    elseif ($university == 'wr') {
      $encoded_university = 'WRLC';  # WRLC
    }
    else {
      $encoded_university = '';  # Other
    }
    return $encoded_university;
  }
}
