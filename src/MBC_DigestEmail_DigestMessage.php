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

      $preheaderContent = new MBC_DigestEmail_PreHeaderContent(
        'digest-2015-08-20_preheader.inc',
        NULL
      );
      $headerContent = new MBC_DigestEmail_HeaderContent(
        'digest-2015-08-20_header.inc',
        NULL
      );
      $bodyContent = new MBC_DigestEmail_BodyContent(
          'digest-2015-08-20_body.inc',
          [
            'BODY_COPY' => new MBC_DigestEmail_BodyCopyContent('digest-2015-08-20_bodycopy.inc'),
            'CAMPAIGNS' => new MBC_DigestEmail_CampaignContent('digest-2015-08-20_bodycampaigns.inc'),
          ]
      );
      $footerContent = new MBC_DigestEmail_FooterContent(
        'digest-2015-08-20_footer.inc',
        [
          'FOOTER_BYLINE' => new MBC_DigestEmail_BylineContent('digest-2015-08-20_footerbyline.inc'),
          'FOOTER_COPY' => new MBC_DigestEmail_UnsubscribeContent('digest-2015-08-20_footercopy.inc'),
        ]
      );

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