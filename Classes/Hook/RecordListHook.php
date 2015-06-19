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

use TYPO3\CMS\Recordlist\RecordList\RecordListHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecordListHook implements RecordListHookInterface {

	/**
	 * @var $loginAsObj \Cabag\CabagLoginas\Hook\ToolbarItemHook
	 */
	public $loginAsObj = NULL;

	public function __construct() {
		$this->loginAsObj = GeneralUtility::makeInstance('Cabag\CabagLoginas\Hook\ToolbarItemHook');
	}

	/**
	 * @param string $table
	 * @param array $row
	 * @param array $cells
	 * @param object $parentObject
	 * @return array
	 */
	public function makeClip($table, $row, $cells, &$parentObject) {
		return $cells;
	}

	/**
	 * @param string $table
	 * @param array $row
	 * @param array $cells
	 * @param object $parentObject
	 * @return array
	 */
	public function makeControl($table, $row, $cells, &$parentObject) {
		if ($table === 'fe_users') {
			$loginAs = $this->loginAsObj->getLoginAsIconInTable($row);
			$cells['secondary']['loginAs'] = $loginAs;
		}

		return $cells;
	}

	/**
	 * @param string $table
	 * @param array $currentIdList
	 * @param array $headerColumns
	 * @param object $parentObject
	 * @return array
	 */
	public function renderListHeader($table, $currentIdList, $headerColumns, &$parentObject) {
		return $headerColumns;
	}

	/**
	 * @param string $table
	 * @param array $currentIdList
	 * @param array $cells
	 * @param object $parentObject
	 * @return array
	 */
	public function renderListHeaderActions($table, $currentIdList, $cells, &$parentObject) {
		return $cells;
	}
}

