<?php

namespace Drupal\aladin_sp_login\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Profile' block.
 *
 * @Block(
 *   id = "profile_block",
 *   admin_label = @Translation("Profile block"),
 *   category = @Translation("Custom")
 * )
 */
class ProfileBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => $this->t('<p><em>Account, Email Address, and University are set by your institution\'s Single Sign-On provider and cannot be changed here.</em></p><p>&nbsp;</p>'),
    ];
  }

}
