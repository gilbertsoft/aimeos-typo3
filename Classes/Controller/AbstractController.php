<?php

/**
 * @license GPLv3, http://www.gnu.org/copyleft/gpl.html
 * @copyright Metaways Infosystems GmbH, 2013
 * @copyright Aimeos (aimeos.org), 2014-2016
 * @package TYPO3
 */

namespace Aimeos\Aimeos\Controller;


use Aimeos\Aimeos\Base;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;


/**
 * Abstract class with common functionality for all controllers.
 *
 * @package TYPO3
 */
abstract class AbstractController
	extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
	private static $context;
	private $contextBE;

	public $settingsOrg;


	/**
	 * Creates a new configuration object.
	 *
	 * @return \Aimeos\MW\Config\Iface Configuration object
	 * @deprecated Use \Aimeos\Aimeos\Base::getConfig() directly
	 */
	protected function getConfig()
	{
		return Base::getConfig( (array) $this->settings );
	}


	/**
	 * Returns the context item for the frontend
	 *
	 * @return \Aimeos\MShop\Context\Item\Iface Context item
	 */
	protected function getContext()
	{
		$config = Base::getConfig( (array) $this->settings );

		if( !isset( self::$context ) )
		{
			$context = Base::getContext( $config );
			$locale = Base::getLocale( $context, $this->request );
			$context->setI18n( Base::getI18n( array( $locale->getLanguageId() ), $config->get( 'i18n', array() ) ) );
			$context->setLocale( $locale );

			self::$context = $context;
		}

		// Use plugin specific configuration
		self::$context->setConfig( $config );

		$langid = self::$context->getLocale()->getLanguageId();
		$templatePaths = Base::getAimeos()->getCustomPaths( 'client/html/templates' );
		self::$context->setView( Base::getView( $config, $this->uriBuilder, $templatePaths, $this->request, $langid ) );

		return self::$context;
	}


	/**
	 * Returns the context item for backend operations
	 *
	 * @param array $templatePaths List of paths to the view templates
	 * @return \Aimeos\MShop\Context\Item\Iface Context item
	 */
	protected function getContextBackend( array $templatePaths = array(), $withView = true )
	{
		if( !isset( $this->contextBE ) )
		{
			$lang = 'en';
			$site = 'default';

			if( isset( $GLOBALS['BE_USER']->uc['lang'] ) && $GLOBALS['BE_USER']->uc['lang'] != '' ) {
				$lang = $GLOBALS['BE_USER']->uc['lang'];
			}

			if( $this->request->hasArgument( 'lang' ) ) {
				$lang = $this->request->getArgument( 'lang' );
			}

			if( $this->request->hasArgument( 'site' ) ) {
				$site = $this->request->getArgument( 'site' );
			}

			$config = Base::getConfig( (array) $this->settings );
			$context = Base::getContext( $config );

			$locale = Base::getLocaleBackend( $context, $site );
			$context->setLocale( $locale );

			$i18n = Base::getI18n( array( $lang, 'en' ), $config->get( 'i18n', array() ) );
			$context->setI18n( $i18n );

			if( $withView )
			{
				$view = Base::getView( $config, $this->uriBuilder, $templatePaths, $this->request, $lang, false );
				$context->setView( $view );
			}

			$this->contextBE = $context;
		}

		return $this->contextBE;
	}


	/**
	 * Returns the locale object for the context
	 *
	 * @param \Aimeos\MShop\Context\Item\Iface $context Context object
	 * @return \Aimeos\MShop\Locale\Item\Iface Locale item object
	 * @deprecated Use \Aimeos\Aimeos\Base::getLocale() directly
	 */
	protected function getLocale( \Aimeos\MShop\Context\Item\Iface $context )
	{
		return Base::getLocale( $context, $this->request );
	}


	/**
	 * Returns the output of the client and adds the header.
	 *
	 * @param Client_Html_Interface $client Html client object
	 * @return string HTML code for inserting into the HTML body
	 */
	protected function getClientOutput( \Aimeos\Client\Html\Iface $client )
	{
		$client->setView( $this->getContext()->getView() );
		$client->process();

		$this->response->addAdditionalHeaderData( (string) $client->getHeader() );

		return $client->getBody();
	}


	/**
	 * Initializes the object before the real action is called.
	 */
	protected function initializeAction()
	{
		$this->uriBuilder->setArgumentPrefix( 'ai' );
	}


	/**
	 * Disables Fluid views for performance reasons
	 *
	 * return null
	 */
	protected function resolveView()
	{
		return null;
	}


	/**
	 * Returns the sanitized configuration values.
	 *
	 * @param array $config Mulit-dimensional, associative list of key/value pairs
	 * @return array Sanitized Mulit-dimensional, associative list of key/value pairs
	 */
	protected static function getConfiguration( array $config )
	{
		// ignore deprecated values
   		unset( $config['client']['html']['catalog']['count']['url']['target'] );
   		unset( $config['client']['html']['catalog']['stock']['url']['target'] );
   		unset( $config['client']['html']['catalog']['suggest']['url']['target'] );
   		unset( $config['client']['html']['checkout']['update']['url']['target'] );

		reset( $config );
  		return $config;
	}


	/**
	 * Injects the Configuration Manager and is initializing the framework settings
	 *
	 * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager Instance of the Configuration Manager
	 */
	public function injectConfigurationManager( ConfigurationManagerInterface $configurationManager )
	{
		// set ConfigurationManager and get settings like the FrontendConfigurationManager
		$this->configurationManager = $configurationManager;
		$this->settingsOrg = $this->configurationManager->getConfiguration( ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS );

		$this->settings = $this->settingsOrg;

		// load settings from template setup
		$setup = $this->configurationManager->getConfiguration( ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT );
		$extensionName = 'aimeos';

		// get plugin settings and override with sanitized typo3 default settings
		if( is_array( $setup['plugin.']['tx_' . strtolower( $extensionName ) . '.'] ) )
		{
			$configuration = Base::convertTypoScriptArrayToPlainArray( $setup['plugin.']['tx_' . strtolower( $extensionName ) . '.'] );

			if( is_array( $configuration['settings'] ) )
	  		{
  				$this->settings = \TYPO3\CMS\Extbase\Utility\ArrayUtility::arrayMergeRecursiveOverrule( $configuration['settings'], $this->getConfiguration( $this->settingsOrg ), false, false );
	  		}
		}
	}

}
