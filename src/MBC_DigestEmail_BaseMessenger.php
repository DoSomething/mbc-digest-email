<?php
/**
 * MBC_DigestEmail_BaseMessenger
 * 
 */

namespace DoSomething\MBC_DigestEmail;


/**
 * MBC_DigestEmail_BaseMessenger class - 
 */
abstract class MBC_DigestEmail_BaseMessenger {
  
  /*
   *
   */
  protected $to = [];

  /*
   *
   */
  abstract function addUser($user);
  
  /*
   *
   */
  abstract function sendDigestBatch();

  /**
   *
   */
  private function getTemplate($templateFile) {

    $targetFile = __DIR__ . '/../templates/' . $templateFile;
    try {
      $markup = file_get_contents($targetFile);
    }
    catch(Exception $e) {
      die('MBC_DigestEmail_BaseMessenger->getTemplate(): Failed to load template: ' . $templateFile);
    }

    return $markup;
  }
}