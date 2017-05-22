<?php

/**
 * @file controllers/grid/announcements/AnnouncementGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementGridCellProvider
 * @ingroup controllers_grid_announcements
 *
 * @brief Cell provider for title column of a announcement grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class AnnouncementGridCellProvider extends GridCellProvider {

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'title':
				$announcement = $row->getData();
				$router = $this->_request->getRouter();
				$actionArgs = array('announcementId' => $row->getId());

				import('lib.pkp.classes.linkAction.request.AjaxModal');
				return array(new LinkAction(
					'moreInformation',
					new AjaxModal(
						$router->url($this->_request, null, null, 'moreInformation', null, $actionArgs),
						$announcement->getLocalizedTitle(),
						null,
						true
					),
					$announcement->getLocalizedTitle(),
					'moreInformation'
				));
		}
		return parent::getCellActions($row, $column, $position);
	}

	/**
	 * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$announcement = $row->getData();
		$columnId = $column->getId();
		assert(is_a($announcement, 'Announcement') && !empty($columnId));

		switch ($columnId) {
			case 'title':
				return array('label' => '');
				break;
			case 'type':
				$typeId = $announcement->getTypeId();
				if ($typeId) {
					$announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO');
					$announcementType = $announcementTypeDao->getById($typeId);
					return array('label' => $announcementType->getLocalizedTypeName());
				} else {
					return array('label' => __('common.none'));
				}
				break;
			default:
				break;
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}

?>
