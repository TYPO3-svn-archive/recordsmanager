<?php
/**
 * Copyright notice
 *
 *    (c) 2011  <>
 *    All rights reserved
 *
 *    This script is part of the TYPO3 project. The TYPO3 project is
 *    free software; you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation; either version 2 of the License, or
 *    (at your option) any later version.
 *
 *    The GNU General Public License can be found at
 *    http://www.gnu.org/copyleft/gpl.html.
 *
 *    This script is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    This copyright notice MUST APPEAR in all copies of the script!
 */

$LANG->includeLLFile('EXT:recordsmanager/mod1/locallang.xml');
require_once(PATH_t3lib . 'class.t3lib_scbase.php');
require_once(PATH_typo3 . 'class.db_list.inc');
require_once(PATH_typo3 . 'class.db_list_extra.inc');
require_once(PATH_typo3 . 'sysext/cms/layout/class.tx_cms_layout.php');
$BE_USER->modAccess($MCONF, 1); // This checks permissions and exits if the users has no permission for entry.
// DEFAULT initialization of a module [END]
/**
 * Module 'Données' for the 'recordsmanager' extension.
 *
 * @author <>
 * @package TYPO3
 * @subpackage tx_recordsmanager
 */
class tx_recordsmanager_module1 extends t3lib_SCbase
{
	public $pageinfo;
	protected $items = array();
	protected $currentItem = array();
	protected $nbElementsPerPage = 10;

	/**
	 * Initializes the Module
	 *
	 * @return void
	 */

	function init() {
		global $BE_USER, $LANG, $BACK_PATH, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS;
		$items = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'tx_recordsmanager_config', 'type=0 AND deleted=0 AND hidden=0', '', 'sorting');
		$usergroups = explode(',', $BE_USER->user['usergroup']);
		foreach ($items as $key => $row) {
			$configgroups = explode(',', $row['permsgroup']);
			$checkRights = array_intersect($usergroups, $configgroups);
			if (($BE_USER->isAdmin()) || (count($checkRights) > 0)) {
				$this->items [] = $row;
			}
		}
		// Check nb per page
		$nbPerPage = t3lib_div::_GP('nbPerPage');
		if ($nbPerPage !== null) {
			$this->nbElementsPerPage = $nbPerPage;
		}
		parent::init();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return void
	 */

	function menuConfig() {
		$this->MOD_MENU = array();
		$this->MOD_MENU['function'] = array();
		foreach ($this->items as $key => $row) {
			$this->MOD_MENU['function'] [] = $row['title'];
		}
		parent::menuConfig();
	}

	function main() {
		global $BE_USER, $LANG, $BACK_PATH, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS;
		// Draw the header.
		$this->doc = t3lib_div::makeInstance('bigDoc');
		$this->doc->styleSheetFile2 = '../typo3conf/ext/recordsmanager/lib/module.css';
		$this->doc->backPath = $BACK_PATH;
		$this->doc->form = '<form action="" method="post" enctype="multipart/form-data">';
		// JavaScript
		$this->doc->JScode = '
			<script language="javascript" type="text/javascript">
			script_ended = 0;
			function jumpToUrl(URL)	{
			document.location = URL;
			}

			function deleteRecord(table,id,url)	{	//
				if (confirm(' . $LANG->JScharCode($LANG->getLL('areyousure')) . '))	{
					jumpToUrl("tce_db.php?cmd["+table+"]["+id+"][delete]=1&redirect="+escape(url)+"&vC=' . $BE_USER->veriCode() . '&prErr=1&uPT=1");
				}
				return false;
			}
			</script>
		';

		$this->doc->postCode = '
			<script language="javascript" type="text/javascript">
			script_ended = 1;
			if (top.fsMod) top.fsMod.recentIds["web"] = 0;
			</script>
		';

		$this->content .= $this->doc->startPage($LANG->getLL('title'));

		if (count($this->MOD_MENU['function']) > 0) {
			$this->content .= '<table><tr><td class="functitle">' . $LANG->getLL('choose') . '</td><td align="right">' . $this->doc->funcMenu('', t3lib_BEfunc::getFuncMenu(0, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function'])) . '</td></tr></table>';
			$this->content .= $this->doc->divider(5);
			$this->moduleContent();
		} else {
			$this->content .= $LANG->getLL('norecords');
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return void
	 */
	function printContent() {
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return void
	 */
	function moduleContent() {
		foreach ($this->items as $key => $row) {
			if ((string)$this->MOD_SETTINGS['function'] == $key) {
				$this->currentItem = $row;
				$query = array();
				// we need to have the uid
				if (!t3lib_div::inList($row['sqlfields'], 'uid')) {
					$query['SELECT'] = 'uid,' . $row['sqlfields'];
				} else {
					$query['SELECT'] = $row['sqlfields'];
				}
				$query['FROM'] = $row['sqltable'];
				$query['WHERE'] = '1=1 AND deleted=0';
				$query['WHERE'] .= ($row['extrawhere'] != '') ? ' ' . $row['extrawhere'] : '';
				$query['GROUPBY'] = '';
				$query['GROUPBY'] .= ($row['extragroupby'] != '') ? $row['extragroupby'] : '';
				$query['ORDERBY'] = '';
				$query['ORDERBY'] .= ($row['extraorderby'] != '') ? $row['extraorderby'] : '';
				$query['LIMIT'] = '';
				$query['LIMIT'] .= ($row['extralimit'] != '') ? $row['extralimit'] : '';
				$content = $this->drawTable($query, $row['title']);
				$this->content .= $content;
			}
		}
	}

	function drawTable($query, $title) {
		global $BE_USER;
		$content = '';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid', $query['FROM'], $query['WHERE'], $query['GROUPBY'], $query['ORDERBY'], $query['LIMIT']);
		$listOfUids = array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pageinfo = t3lib_BEfunc::readPageAccess($row['pid'], $BE_USER->getPagePermsClause(1));
			if ($pageinfo !== false) { // check the right of the page container
				$listOfUids [] = $row['uid'];
			}
		}

		if (count($listOfUids) > 0) {
			// Page browser
			$pointer = t3lib_div::_GP('pointer');
			$limit = ($pointer !== null) ? $pointer . ',' . $this->nbElementsPerPage : '0,' . $this->nbElementsPerPage;
			$current = ($pointer !== null) ? intval($pointer) : 0;
			$pageBrowser = tx_t3devapi_befunc::renderListNavigation($GLOBALS['TYPO3_DB']->sql_num_rows($res), $this->nbElementsPerPage, $current, true);
			$query['WHERE'] .= ' AND uid IN (' . implode(',', $listOfUids) . ')';
			$query['LIMIT'] = $limit;
			$content .= $pageBrowser;
			$GLOBALS['TYPO3_DB']->sql_free_result($res);

			// List view
			$GLOBALS['SOBE']->MOD_SETTINGS['search_result_labels'] = 1;
			$GLOBALS['SOBE']->MOD_SETTINGS['labels_noprefix'] = 1;
			$result = $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($query);
			$content .= $this->formatAllResults($result, $query['FROM'], $title);
			$GLOBALS['TYPO3_DB']->sql_free_result($result);
		}

		return $content;
	}

	function formatAllResults($res, $table, $title) {
		$api = t3lib_div::makeInstance('tx_t3devapi_database');
		$content = '';
		$content .= tx_t3devapi_befunc::drawDBListTitle($title);
		$first = 1;
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($first) {
				$first = 0;
				$headers = $api->getResultRowTitles($row, $table);
				$headers['actions'] = '';
				$content .= tx_t3devapi_befunc::drawDBListHeader($headers);
			}
			$records = $api->getResultRow($row, $table);
			$records['actions'] = '<a onclick="top.launchView(\'' . $table . '\',' . $row['uid'] . ',\'\');return false;" href="#"><img src="' . t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR') . 'sysext/t3skin/icons/gfx/zoom2.gif"/></a>';
			$editLink = 'alt_doc.php?returnUrl=%2Ftypo3%2Fmod.php%3FM%3DtxrecordsmanagerM1_edit&amp;edit[' . $table . '][' . $row['uid'] . ']=edit';
			if ($this->currentItem['sqlfieldsinsert'] !== '') {
				$editLink .= '&columnsOnly=' . $this->currentItem['sqlfieldsinsert'];
			}
			$records['actions'] .= '<a onclick="window.location.href=\'' . $editLink . '\'; return false;" href="#"><img src="' . t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR') . 'sysext/t3skin/icons/gfx/edit2.gif"/></a>';
			$records['actions'] .= '<a onclick="return deleteRecord(\'' . $table . '\',\'' . $row['uid'] . '\',unescape(\'%2Ftypo3%2Fmod.php%3FM%3DtxrecordsmanagerM1_edit\'));" href="#"><img src="' . t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR') . 'sysext/t3skin/icons/gfx/garbage.gif"/></a>';
			$content .= tx_t3devapi_befunc::drawDBListRows($records);
		}
		$content .= '</table>';
		return tx_t3devapi_befunc::drawDBListTable($content);
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/recordsmanager/mod1/index.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/recordsmanager/mod1/index.php']);
}
// Make instance:
$SOBE = t3lib_div::makeInstance('tx_recordsmanager_module1');
$SOBE->init();
// Include files?
foreach ($SOBE->include_once as $INC_FILE) include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>