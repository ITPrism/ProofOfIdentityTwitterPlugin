<?php
/**
 * @package         IdentityProof
 * @subpackage      Plugins
 * @author          Todor Iliev
 * @copyright       Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/licenses/gpl-3.0.en.html GNU/GPLv3
 */

// no direct access
defined('_JEXEC') or die;

jimport('Prism.init');
jimport('Identityproof.init');

/**
 * Proof of Identity - Twitter Plugin
 *
 * @package        IdentityProof
 * @subpackage     Plugins
 */
class plgIdentityproofTwitter extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * @var JApplicationSite
     */
    protected $app;

    /**
     * This method prepares a code that will be included to step "Extras" on project wizard.
     *
     * @param string                   $context This string gives information about that where it has been executed the trigger.
     * @param stdClass                 $item    User data.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @return null|string
     */
    public function onDisplayVerification($context, &$item, &$params)
    {
        if (strcmp('com_identityproof.proof', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        if (!isset($item->id) or !$item->id) {
            return null;
        }

        $profile = new Identityproof\Profile\Twitter(JFactory::getDbo());
        $profile->load(array('user_id' => $item->id));

        $filter = JFilterInput::getInstance();

        // Get URI
        $uri         = JUri::getInstance();
        $callbackUrl = $filter->clean($uri->getScheme() . '://' . $uri->getHost()) . '/index.php?option=com_identityproof&task=service.verify&service=twitter&' . JSession::getFormToken() . '=1';

        $loginUrl = '#';
        if (!$profile->getId()) {
            $connection = new Abraham\TwitterOAuth\TwitterOAuth($this->params->get('consumer_key'), $this->params->get('consumer_secret'));
            $request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => $callbackUrl));

            $loginUrl = $connection->url('oauth/authorize', array('oauth_token' => $request_token['oauth_token']));
        }

        // Get the path for the layout file
        $path = JPath::clean(JPluginHelper::getLayoutPath('identityproof', 'twitter'));

        // Render the login form.
        ob_start();
        include $path;
        $html = ob_get_clean();

        return $html;
    }

    /**
     * This method prepares a code that will be included to step "Extras" on project wizard.
     *
     * @param string                   $context This string gives information about that where it has been executed the trigger.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @return null|string
     */
    public function onVerify($context, &$params)
    {
        if (strcmp('com_identityproof.service.twitter', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        $output = array(
            'redirect_url' => JRoute::_(IdentityproofHelperRoute::getProofRoute()),
            'message' => ''
        );

        $userId  = JFactory::getUser()->get('id');
        if (!$userId) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_TWITTER_INVALID_USER');
            return $output;
        }

        $oauthVerifier = $this->app->input->get('oauth_verifier');
        $oauthToken    = $this->app->input->get('oauth_token');

        try {
            $connection  = new Abraham\TwitterOAuth\TwitterOAuth($this->params->get('consumer_key'), $this->params->get('consumer_secret'));
            $accessToken = $connection->oauth('oauth/access_token', array('oauth_token' => $oauthToken, 'oauth_verifier' => $oauthVerifier));

            $connection  = new Abraham\TwitterOAuth\TwitterOAuth($this->params->get('consumer_key'), $this->params->get('consumer_secret'), $accessToken['oauth_token'], $accessToken['oauth_token_secret']);
            $userNode    = $connection->get('account/verify_credentials');

        } catch (Exception $e) {
            $output['message'] = $e->getMessage();
            return $output;
        }

        $profile = new Identityproof\Profile\Twitter(JFactory::getDbo());
        $profile->load(array('user_id' => $userId));

        $website = '';
        if (isset($userNode->entities->url->expanded_url)) {
            $website = $userNode->entities->url->expanded_url;
        }

        if (!$website and $userNode->url) {
            $website = $userNode->url;
        }

        $data = array(
            'twitter_id' => $userNode->id,
            'name'       => $userNode->name,
            'location'   => $userNode->location,
            'link'       => 'http://twitter.com/'.$userNode->screen_name,
            'website'    => $website,
            'verified'   => $userNode->verified,
            'picture'    => $userNode->profile_image_url
        );

        if (!$profile->getId()) {
            $data['user_id'] = $userId;
        }

        $profile->bind($data);
        $profile->store();

        return $output;
    }

    /**
     * This method prepares a code that will be included to step "Extras" on project wizard.
     *
     * @param string                   $context This string gives information about that where it has been executed the trigger.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @return null|string
     */
    public function onRemove($context, &$params)
    {
        if (strcmp('com_identityproof.service.twitter', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        $output = array(
            'redirect_url' => JRoute::_(IdentityproofHelperRoute::getProofRoute()),
            'message' => ''
        );

        $userId  = JFactory::getUser()->get('id');
        if (!$userId) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_TWITTER_INVALID_USER');
            return $output;
        }

        $profile = new Identityproof\Profile\Twitter(JFactory::getDbo());
        $profile->load(array('user_id' => $userId));

        if (!$profile->getId()) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_TWITTER_INVALID_PROFILE');
            return $output;
        }

        $profile->remove();
        $output['message'] = JText::_('PLG_IDENTITYPROOF_TWITTER_RECORD_REMOVED_SUCCESSFULLY');

        return $output;
    }
}
