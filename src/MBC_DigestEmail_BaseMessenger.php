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
  protected $markup;
  
  /**
   *
   */
  public function __construct($targetTemplate) {

    $this->markup = $this->getTemplate($targetTemplate);
  }
  
  /**
   *
   */
  private function getTemplate($templateFile) {

    $targetFile = __DIR__ . '/../templates/' . $templateFile;
    try {
      $messageMarkup = file_get_contents($targetFile);
    }
    catch(Exception $e) {
      die('MBC_DigestEmail_BaseContent->getTemplate(): Failed to load template: ' . $templateFile);
    }

    return $messageMarkup;
  }
  
  /**
   *
   */
  public function getMarkup() {
    return $this->markup;
  }
  
  /**
   *
   */
  protected function getMerge() {
    
  }

}