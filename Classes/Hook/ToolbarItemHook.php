<?php
namespace Cabag\CabagLoginas\Hook;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ToolbarItemHook implements ToolbarItemInterface {

	/**
	 * Template file for the dropdown menu
	 */
	const TOOLBAR_MENU_TEMPLATE = 'LoginAs.html';

	/**
	 * @var integer
	 */
	protected $totalCount = 0;

	/**
	 * @var StandaloneView
	 */
	protected $standaloneView = NULL;

	/**
	 * @var array
	 */
	protected $extensionConfiguration = array();

	public function __construct() {
		$extPath = ExtensionManagementUtility::extPath('cabag_loginas');
		/* @var $view StandaloneView */
		$this->standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
		$this->standaloneView->setTemplatePathAndFilename($extPath . 'Resources/Private/Templates/ToolbarMenu/' . static::TOOLBAR_MENU_TEMPLATE);

		$this->extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cabag_loginas']);
	}

	/**
	 * @return boolean
	 */
	public function checkAccess() {
		$conf = $this->getBackendUserAuthentication()->getTSConfig('backendToolbarItem.tx_cabagloginas.disabled');

		return ($conf['value'] == 1 ? FALSE : TRUE);
	}

	/**
	 * Render user icon
	 *
	 * @return string
	 */
	public function getItem() {
		$title = $this->getLanguageService()->sL('LLL:EXT:cabag_loginas/Resources/Private/Language/locallang_db.xml:fe_users.tx_cabagloginas_loginas', TRUE);
		return IconUtility::getSpriteIcon('status-user-backend', array('title' => $title));
	}

	/**
	 * Returns an integer between 0 and 100 to determine
	 * the position of this item relative to others
	 * By default, extensions should return 50 to be sorted between main core
	 * items and other items that should be on the very right.
	 * @return integer 0 .. 100
	 */
	public function getIndex() {
		return 42;
	}


	/**
	 * TRUE if this toolbar item has a collapsible drop down
	 * @return bool
	 */
	public function hasDropDown() {
		return TRUE;
	}

	/**
	 * Render "drop down" part of this toolbar
	 * @return string Drop down HTML
	 */
	public function getDropDown() {
		if (!$this->checkAccess()) {
			return '';
		}

		$this->getStandaloneView('cabag_loginas')->assignMultiple(
			array(
				'users' => $this->getUsers(),
				'count' => $this->totalCount,
				'config' => $this->extensionConfiguration
			)
		);

		return $this->getStandaloneView()->render();
	}

	/**
	 * @return array|NULL
	 */
	public function getUsers() {
		$email = $this->getBackendUserAuthentication()->user['email'];

		$users = $this->getDatabaseConnection()->exec_SELECTgetRows(
			'*',
			'fe_users',
			'email = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($email, 'fe_users') . ' AND disable = 0 AND deleted = 0',
			'',
			'',
			'15'
		);
		$this->totalCount = sizeof($users);

		$userIcon = IconUtility::getSpriteIcon(
			'apps-pagetree-folder-contains-fe_users',
			array(
				'style' => 'background-position: 0 10px;'
			)
		);
		$linkedUsers = array();

		foreach ($users as $user) {
			$link = $this->getHref($user);
			$linkText = $this->formatLinkText($user, $this->extensionConfiguration['defLinkText']);
			$title = sprintf(
				LocalizationUtility::translate('title.linkprefix', 'cabag_loginas'),
				$user['username']
			);

			$linkedUsers[] = '<a href="' . $link . '" title="' . $title . '" target="_blank">' . $userIcon . $linkText . '</a>';
		}

		return $linkedUsers;
	}

	public function getHref($user) {
		$parameterArray = array();
		$parameterArray['userid'] = (string) $user['uid'];
		$parameterArray['timeout'] = (string) $timeout = time() + 3600;
		// Check user settings for any redirect page
		if ($user['felogin_redirectPid']) {
			$parameterArray['redirecturl'] = $this->getRedirectUrl($user['felogin_redirectPid']);
		} else {
			// Check group settings for any redirect page
			$userGroup = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
				'fe_groups.felogin_redirectPid',
				'fe_users, fe_groups',
				'fe_groups.felogin_redirectPid != "" AND fe_groups.uid IN (fe_users.usergroup) AND fe_users.uid = ' . $user['uid']
			);
			if (is_array($userGroup) && !empty($userGroup['felogin_redirectPid'])) {
				$parameterArray['redirecturl'] = $this->getRedirectUrl($userGroup['felogin_redirectPid']);
			} elseif (rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/') !== ($domain = $this->getRedirectForCurrentDomain($user['pid']))) {
				// Any manual redirection defined in sys_domain record
				$parameterArray['redirecturl'] = rawurlencode($domain);
			}
		}
		$ses_id = $GLOBALS['BE_USER']->user['ses_id'];
		$parameterArray['verification'] = md5($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . $ses_id . serialize($parameterArray));
		$link = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . '?' . GeneralUtility::implodeArrayForUrl('tx_cabagloginas', $parameterArray);

		return $link;
	}

	/**
	 * @param array $user
	 * @param string $defLinkText
	 * @return mixed
	 */
	public function formatLinkText($user, $defLinkText) {
		foreach ($user as $key => $value) {
			$defLinkText = str_replace('#' . $key . '#', $value, $defLinkText);
		}

		return $defLinkText;
	}

	/**
	 * Finds the redirect link for the current domain.
	 *
	 * @param integer $pid Page id the user is stored in
	 *
	 * @return string '../' if nothing was found, the link in the form of http://www.domain.tld/link/page.html otherwise.
	 */
	public function getRedirectForCurrentDomain($pid) {
		$domain = BackendUtility::getViewDomain($pid);
		$domainArray = parse_url($domain);

		if (empty($this->extensionConfiguration['enableDomainBasedRedirect'])) {
			return $domain;
		}

		$row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
			'domainName, tx_cabagfileexplorer_redirect_to',
			'sys_domain',
			'hidden = 0 AND domainName = ' . $this->getDatabaseConnection()->fullQuoteStr($domainArray['host'], 'sys_domain'),
			'',
			''
		);

		if (!$row || (trim($row['tx_cabagfileexplorer_redirect_to'])) === '') {
			return $domain;
		}

		$domain = 'http' . (GeneralUtility::getIndpEnv('TYPO3_SSL') ? 's' : '') . '://' . $row['domainName'] . '/' .
			ltrim($row['tx_cabagfileexplorer_redirect_to'], '/');

		return $domain;
	}

	/**
	 * @param $user
	 * @param string $title
	 * @return string
	 */
	public function getLoginAsIconInTable($user, $title = '') {
		$switchUserIcon = IconUtility::getSpriteIcon(
			'actions-system-backend-user-emulate',
			array(
				'style' => 'background-position: 0 10px;'
			)
		);
		$link = $this->getHref($user);
		$title = sprintf(
			LocalizationUtility::translate('title.linkprefix', 'cabag_loginas'),
			$user['username']
		);
		$content = '<a class="btn btn-default" title="' . $title . '" href="' . $link . '" target="_blank">' . $switchUserIcon . '</a>';

		return $content;
	}

	/**
	 * @param integer $pageId
	 *
	 * @return string
	 */
	protected function getRedirectUrl($pageId) {
		return rawurlencode(BackendUtility::getViewDomain($pageId) . '/index.php?id=' . $pageId);
	}

	/**
	 * Returns the current BE user.
	 *
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * Returns DatabaseConnection
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Returns current PageRenderer
	 *
	 * @return \TYPO3\CMS\Core\Page\PageRenderer
	 */
	protected function getPageRenderer() {
		/** @var \TYPO3\CMS\Backend\Template\DocumentTemplate $documentTemplate */
		$documentTemplate = $GLOBALS['TBE_TEMPLATE'];
		return $documentTemplate->getPageRenderer();
	}

	/**
	 * Returns LanguageService
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return string
	 */
	public function getAdditionalAttributes() {
		if ($this->totalCount) {
			return ' id="tx-cabagloginas-menu"';
		} else {
			return '';
		}
	}

	/**
	 * @param string $extension Set the extension context (required for shorthand locallang.xlf references)
	 * @return StandaloneView
	 */
	protected function getStandaloneView($extension = NULL) {
		if (!empty($extension)) {
			$request = $this->standaloneView->getRequest();
			$request->setControllerExtensionName($extension);
		}
		return $this->standaloneView;
	}
}
