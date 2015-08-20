<?php
/**
 * MBC_DigestEmail_DigestMessage
 *
 */
namespace DoSomething\MBC_DigestEmail;


abstract class MBC_DigestEmail_BaseMessage {

  /**
   *
   */
  private $contentAreas = [];

  /**
   *
   */
  private $templateFile = '';

  /**
   *
   */
  private $fromName = '';

  /**
   *
   */
  private $fromEmail = '';

  /**
   *
   */
  private $subject = '';

  /**
   *
   */
  private $dispatchWindowStart;

  /**
   *
   */
  private $dispatchWindowEnd;

  /**
   *
   */
  protected function getMessageTemplate($templateFile) {

    $targetFile = __DIR__ . '/../templates/' . $templateFile;
    try {
      $messageMarkup = file_get_contents($targetFile);
    }
    catch(Exception $e) {
      die('MBC_DigestEmail_BaseMessage->getMessageTemplate(): Failed to load template: ' . $templateFile);
    }

    return $messageMarkup;
  }

}