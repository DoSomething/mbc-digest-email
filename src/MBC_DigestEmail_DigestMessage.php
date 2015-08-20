<?php
/**
 * MBC_DigestEmail_DigestMessage
 *
 */
namespace DoSomething\MBC_DigestEmail;


class MBC_DigestEmail_DigestMessage extends MBC_DigestEmail_BaseMessage {

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
  public function __construct($targetMessage) {

    // Build message with merge_vars
    if ($targetMessage == 'digest-2015-08-20') {

      $preheaderContent = [
        new MBC_DigestEmail_PreHeaderContent(),
      ];
      $headerContent = [
        new MBC_DigestEmail_HeaderContent(),
      ];
      $bodyContent = [
        new MBC_DigestEmail_CopyContent(),
        new MBC_DigestEmail_CampaignContent(),
      ];
      $footerContent = [
        new MBC_DigestEmail_BylineContent(),
        new MBC_DigestEmail_UnsubscribeContent(),
      ];

      $this->contentAreas = [
        'PREHEADER' => new MBC_DigestEmail_ContentArea($preheaderContent),
        'HEADER' => new MBC_DigestEmail_ContentArea($headerContent),
        'BODY' => new MBC_DigestEmail_ContentArea($bodyContent),
        'FOOTER' => new MBC_DigestEmail_ContentArea($footerContent),
      ];
      $this->templateFile = 'digest-2015-08-20.inc';
      $this->fromName = '';
      $this->fromEmail = '';
      $this->subject = '';
      $this->dispatchWindowStart = '';
      $this->dispatchWindowEnd = '';
      
      $this->setTemplateMarkup();

    }
    else {
      echo 'MBC_DigestEmail_DigestMessage->__construct(): Invalid targetMessage: ' . $targetMessage, PHP_EOL;
    }

  }
  
  /**
   *
   */
  public function setTemplateMarkup() {
    
    // Load page template
    $markup = parent::getMessageTemplate($this->templateFile);
    
    // Merge content area markup into page markup
    foreach($this->contentAreas as $area => $areaContent) {
      $markup = str_replace("*|$area|*", $areaContent->getMarkup(), $this->templateMarkup);
    }
    
    $this->templateMarkup = $markup;
  }

}