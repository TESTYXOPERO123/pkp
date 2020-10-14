<?php

/**
 * @file classes/security/UserGroupDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupDAO
 * @ingroup security
 * @see UserGroup
 *
 * @brief Operations for retrieving and modifying User Groups and user group assignments
 */


import('lib.pkp.classes.security.UserGroup');
import('lib.pkp.classes.workflow.WorkflowStageDAO');

class UserGroupDAO extends DAO {
	/** @var a shortcut to get the UserDAO **/
	var $userDao;

	/** @var a shortcut to get the UserGroupAssignmentDAO **/
	var $userGroupAssignmentDao;

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->userDao = DAORegistry::getDAO('UserDAO');
		$this->userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
	}

	/**
	 * create new data object
	 * (allows DAO to be subclassed)
	 */
	function newDataObject() {
		return new UserGroup();
	}

	/**
	 * Internal function to return a UserGroup object from a row.
	 * @param $row array
	 * @return UserGroup
	 */
	function _returnFromRow($row) {
		$userGroup = $this->newDataObject();
		$userGroup->setId($row['user_group_id']);
		$userGroup->setRoleId($row['role_id']);
		$userGroup->setContextId($row['context_id']);
		$userGroup->setDefault($row['is_default']);
		$userGroup->setShowTitle($row['show_title']);
		$userGroup->setPermitSelfRegistration($row['permit_self_registration']);
		$userGroup->setPermitMetadataEdit($row['permit_metadata_edit']);

		$this->getDataObjectSettings('user_group_settings', 'user_group_id', $row['user_group_id'], $userGroup);

		HookRegistry::call('UserGroupDAO::_returnFromRow', array(&$userGroup, &$row));

		return $userGroup;
	}

	/**
	 * Insert a user group.
	 * @param $userGroup UserGroup
	 * @return int Inserted user group ID
	 */
	function insertObject($userGroup) {
		$this->update(
			'INSERT INTO user_groups
				(role_id, context_id, is_default, show_title, permit_self_registration, permit_metadata_edit)
				VALUES
				(?, ?, ?, ?, ?, ?)',
			array(
				(int) $userGroup->getRoleId(),
				(int) $userGroup->getContextId(),
				$userGroup->getDefault()?1:0,
				$userGroup->getShowTitle()?1:0,
				$userGroup->getPermitSelfRegistration()?1:0,
				$userGroup->getPermitMetadataEdit()?1:0,
			)
		);

		$userGroup->setId($this->getInsertId());
		$this->updateLocaleFields($userGroup);
		return $userGroup->getId();
	}

	/**
	 * Update a user group.
	 * @param $userGroup UserGroup
	 */
	function updateObject($userGroup) {
		$this->update(
			'UPDATE user_groups SET
				role_id = ?,
				context_id = ?,
				is_default = ?,
				show_title = ?,
				permit_self_registration = ?,
				permit_metadata_edit = ?
			WHERE	user_group_id = ?',
			array(
				(int) $userGroup->getRoleId(),
				(int) $userGroup->getContextId(),
				$userGroup->getDefault()?1:0,
				$userGroup->getShowTitle()?1:0,
				$userGroup->getPermitSelfRegistration()?1:0,
				$userGroup->getPermitMetadataEdit()?1:0,
				(int) $userGroup->getId(),
			)
		);

		$this->updateLocaleFields($userGroup);
	}

	/**
	 * Delete a user group by its id
	 * will also delete related settings and all the assignments to this group
	 * @param $contextId int
	 * @param $userGroupId int
	 */
	function deleteById($contextId, $userGroupId) {
		$this->userGroupAssignmentDao->deleteAssignmentsByUserGroupId($userGroupId);
		$this->update('DELETE FROM user_group_settings WHERE user_group_id = ?', (int) $userGroupId);
		$this->update('DELETE FROM user_groups WHERE user_group_id = ?', (int) $userGroupId);
		$this->removeAllStagesFromGroup($contextId, $userGroupId);
	}

	/**
	 * Delete a user group.
	 * will also delete related settings and all the assignments to this group
	 * @param $userGroup UserGroup
	 */
	function deleteObject($userGroup) {
		$this->deleteById($userGroup->getContextId(), $userGroup->getId());
	}


	/**
	 * Delete a user group by its context id
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$result = $this->retrieve('SELECT user_group_id FROM user_groups WHERE context_id = ?', (int) $contextId);

		for ($i=1; $row = (array) $result->current(); $i++) {
			$this->update('DELETE FROM user_group_stage WHERE user_group_id = ?', [(int) $row['user_group_id']]);
			$this->update('DELETE FROM user_group_settings WHERE user_group_id = ?', [(int) $row['user_group_id']]);
			$this->update('DELETE FROM user_groups WHERE user_group_id = ?', [(int) $row['user_group_id']]);
			$result->next();
		}
	}

	/**
	 * Get the ID of the last inserted user group.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('user_groups', 'user_group_id');
	}

	/**
	 * Get field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('name', 'abbrev');
	}

	/**
	 * @copydoc DAO::getAdditionalFieldNames()
	 */
	function getAdditionalFieldNames() {
		return array_merge(parent::getAdditionalFieldNames(), array(
			'recommendOnly',

		));
	}

	/**
	 * Update the localized data for this object
	 * @param $author object
	 */
	function updateLocaleFields($userGroup) {
		$this->updateDataObjectSettings('user_group_settings', $userGroup, array(
			'user_group_id' => (int) $userGroup->getId()
		));
	}

	/**
	 * Get an individual user group
	 * @param $userGroupId int User group ID
	 * @param $contextId int Optional context ID to use for validation
	 */
	function getById($userGroupId, $contextId = null) {
		$params = array((int) $userGroupId);
		if ($contextId !== null) $params[] = (int) $contextId;
		$result = $this->retrieve(
			'SELECT	*
			FROM	user_groups
			WHERE	user_group_id = ?' . ($contextId !== null?' AND context_id = ?':''),
			$params
		);
		$row = (array) $result->current();
		return $row?$this->_returnFromRow($row):null;
	}

	/**
	 * Get a single default user group with a particular roleId
	 * @param $contextId int Context ID
	 * @param $roleId int ROLE_ID_...
	 */
	function getDefaultByRoleId($contextId, $roleId) {
		$allDefaults = $this->getByRoleId($contextId, $roleId, true);
		if ($allDefaults->eof()) return false;
		return $allDefaults->next();
	}

	/**
	 * Check whether the passed user group id is default or not.
	 * @param $userGroupId Integer
	 * @return boolean
	 */
	function isDefault($userGroupId) {
		$result = $this->retrieve(
			'SELECT is_default FROM user_groups
			WHERE user_group_id = ?',
			(int) $userGroupId
		);

		$result = $result->GetArray();
		if (isset($result[0]['is_default'])) {
			return $result[0]['is_default'];
		} else {
			return false;
		}
	}

	/**
	 * Get all user groups belonging to a role
	 * @param Integer $contextId
	 * @param Integer $roleId
	 * @param boolean $default (optional)
	 * @param DBResultRange $dbResultRange (optional)
	 * @return DAOResultFactory
	 */
	function getByRoleId($contextId, $roleId, $default = false, $dbResultRange = null) {
		$params = array((int) $contextId, (int) $roleId);
		if ($default) $params[] = 1; // true
		$result = $this->retrieveRange(
			$sql = 'SELECT	*
			FROM	user_groups
			WHERE	context_id = ? AND
				role_id = ?
				' . ($default?' AND is_default = ?':'')
			. ' ORDER BY user_group_id',
			$params,
			$dbResultRange
		);

		return new DAOResultFactory($result, $this, '_returnFromRow', [], $sql, $params);
	}

	/**
	 * Get an array of user group ids belonging to a given role
	 * @param $roleId int ROLE_ID_...
	 * @param $contextId int Context ID
	 */
	function getUserGroupIdsByRoleId($roleId, $contextId = null) {
		$params = array((int) $roleId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	user_group_id
			FROM	user_groups
				WHERE role_id = ?
				' . ($contextId?' AND context_id = ?':''),
			$params
		);

		$userGroupIds = [];
		foreach ($result as $row) {
			$row = (array) $row;
			$userGroupIds[] = (int) $row['user_group_id'];
		}
		return $userGroupIds;
	}

	/**
	 * Check if a user is in a particular user group
	 * @param $contextId int
	 * @param $userId int
	 * @param $userGroupId int
	 * @return boolean
	 */
	function userInGroup($userId, $userGroupId) {
		$result = $this->retrieve(
			'SELECT	count(*)
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE
				uug.user_id = ? AND
				ug.user_group_id = ?',
			array((int) $userId, (int) $userGroupId)
		);

		// > 0 because user could belong to more than one user group with this role
		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Check if a user is in any user group
	 * @param $userId int
	 * @param $contextId int optional
	 * @return boolean
	 */
	function userInAnyGroup($userId, $contextId = null) {
		$params = array((int) $userId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	count(*)
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE	uug.user_id = ?
				' . ($contextId?' AND ug.context_id = ?':''),
			$params
		);

		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve user groups to which a user is assigned.
	 * @param $userId int
	 * @param $contextId int
	 * @return DAOResultFactory
	 */
	function getByUserId($userId, $contextId = null) {
		$params = [(int) $userId];
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	ug.*
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
				WHERE uug.user_id = ?
				' . ($contextId?' AND ug.context_id = ?':''),
			$params
		);

		return new DAOResultFactory($result, $this, '_returnFromRow');
	}

	/**
	 * Validation check to see if user group exists for a given context
	 * @param $contextId
	 * @param $userGroupId
	 * @return bool
	 */
	function contextHasGroup($contextId, $userGroupId) {
		$result = $this->retrieve(
			'SELECT count(*) AS row_count
				FROM user_groups ug
				WHERE ug.user_group_id = ?
				AND ug.context_id = ?',
			[(int) $userGroupId, (int) $contextId]
		);
		$row = (array) $result->current();
		return $row && $row['row_count'] != 0;
	}

	/**
	 * Retrieve user groups for a given context (all contexts if null)
	 * @param Integer $contextId (optional)
	 * @param DBResultRange $dbResultRange (optional)
	 * @return DAOResultFactory
	 */
	function getByContextId($contextId = null, $dbResultRange = null) {
		$params = array();
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieveRange(
			$sql = 'SELECT ug.*
			FROM	user_groups ug' .
				($contextId?' WHERE ug.context_id = ?':''),
			$params,
			$dbResultRange
		);

		return new DAOResultFactory($result, $this, '_returnFromRow', [], $sql, $params);
	}

	/**
	 * Retrieve the number of users associated with the specified context.
	 * @param $contextId int
	 * @return int
	 */
	function getContextUsersCount($contextId, $userGroupId = null, $roleId = null) {
		$params = array((int) $contextId);
		if ($userGroupId) $params[] = (int) $userGroupId;
		if ($roleId) $params[] = (int) $roleId;
		$result = $this->retrieve(
			'SELECT	COUNT(DISTINCT(uug.user_id)) AS row_count
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE	context_id = ?' .
				($userGroupId?' AND ug.user_group_id = ?':'') .
				($roleId?' AND ug.role_id = ?':''),
			$params
		);
		$row = $result->current();
		return $row?$row['row_count']:0;
	}

	/**
	 * return an Iterator of User objects given the search parameters
	 * @param int $contextId
	 * @param string $searchType
	 * @param string $search
	 * @param string $searchMatch
	 * @param DBResultRange $dbResultRange
	 * @return DAOResultFactory
	 */
	function getUsersByContextId($contextId, $searchType = null, $search = null, $searchMatch = null, $dbResultRange = null) {
		return $this->getUsersById(null, $contextId, $searchType, $search, $searchMatch, $dbResultRange);
	}

	/**
	 * Find users that don't have a given role
	 * @param $roleId ROLE_ID_... int (const)
	 * @param $contextId int Optional context ID
	 * @param $search string Optional search string
	 * @param $rangeInfo RangeInfo Optional range info
	 * @return DAOResultFactory
	 */
	function getUsersNotInRole($roleId, $contextId = null, $search = null, $rangeInfo = null) {
		$params = isset($search) ? array(IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME) : array();
		$params[] = (int) $roleId;
		if ($contextId) $params[] = (int) $contextId;
		if(isset($search)) $params = array_merge($params, array_pad(array(), 4, '%' . $search . '%'));

		$result = $this->retrieveRange(
			'SELECT	DISTINCT u.*
			FROM	users u
			' .(isset($search) ? '
					LEFT JOIN user_settings usgs ON (usgs.user_id = u.user_id AND usgs.setting_name = ?)
					LEFT JOIN user_settings usfs ON (usfs.user_id = u.user_id AND usfs.setting_name = ?)
				':'') .'
			WHERE	u.user_id NOT IN (
				SELECT	DISTINCT u.user_id
				FROM	users u, user_user_groups uug, user_groups ug
				WHERE	u.user_id = uug.user_id
					AND ug.user_group_id = uug.user_group_id
					AND ug.role_id = ?' .
				($contextId ? ' AND ug.context_id = ?' : '') .
				')' .
			(isset($search) ? ' AND (usgs.setting_value LIKE ? OR usfs.setting_value LIKE ? OR u.email LIKE ? OR u.username LIKE ?)' : ''),
			$params,
			$rangeInfo
		);
		return new DAOResultFactory($result, $this->userDao, '_returnUserFromRowWithData');
	}

	/**
	 * return an Iterator of User objects given the search parameters
	 * @param int $userGroupId optional
	 * @param int $contextId optional
	 * @param string $searchType
	 * @param string $search
	 * @param string $searchMatch
	 * @param DBResultRange $dbResultRange
	 * @return DAOResultFactory
	 */
	function getUsersById($userGroupId = null, $contextId = null, $searchType = null, $search = null, $searchMatch = null, $dbResultRange = null) {
		$params = array_merge(
			$this->userDao->getFetchParameters(),
			[IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME]
		);
		if ($contextId) $params[] = (int) $contextId;
		if ($userGroupId) $params[] = (int) $userGroupId;


		// Get the result set
		$result = $this->retrieveRange(
			$sql = 'SELECT DISTINCT u.*,
				' . $this->userDao->getFetchColumns() .'
			FROM	users AS u
				LEFT JOIN user_settings us ON (us.user_id = u.user_id AND us.setting_name = \'affiliation\')
				LEFT JOIN user_interests ui ON (u.user_id = ui.user_id)
				LEFT JOIN controlled_vocab_entry_settings cves ON (ui.controlled_vocab_entry_id = cves.controlled_vocab_entry_id)
				LEFT JOIN user_user_groups uug ON (uug.user_id = u.user_id)
				LEFT JOIN user_groups ug ON (ug.user_group_id = uug.user_group_id)
				' . $this->userDao->getFetchJoins() .'
				LEFT JOIN user_settings usgs ON (usgs.user_id = u.user_id AND usgs.setting_name = ?)
				LEFT JOIN user_settings usfs ON (usfs.user_id = u.user_id AND usfs.setting_name = ?)

			WHERE	1=1 ' .
				($contextId?'AND ug.context_id = ? ':'') .
				($userGroupId?'AND ug.user_group_id = ? ':'') .
				$this->_getSearchSql($searchType, $search, $searchMatch, $params),
			$params,
			$dbResultRange
		);

		return new DAOResultFactory($result, $this->userDao, '_returnUserFromRowWithData', [], $sql, $params);
	}

	//
	// UserGroupAssignment related
	//
	/**
	 * Delete all user group assignments for a given userId
	 * @param int $userId
	 */
	function deleteAssignmentsByUserId($userId, $userGroupId = null) {
		$this->userGroupAssignmentDao->deleteByUserId($userId, $userGroupId);
	}

	/**
	 * Delete all assignments to a given user group
	 * @param unknown_type $userGroupId
	 */
	function deleteAssignmentsByUserGroupId($userGroupId) {
		$this->userGroupAssignmentDao->deleteAssignmentsByUserGroupId($userGroupId);
	}

	/**
	 * Remove all user group assignments for a given user in a context
	 * @param int $contextId
	 * @param int $userId
	 */
	function deleteAssignmentsByContextId($contextId, $userId = null) {
		$this->userGroupAssignmentDao->deleteAssignmentsByContextId($contextId, $userId);
	}

	/**
	 * Assign a given user to a given user group
	 * @param int $userId
	 * @param int $groupId
	 */
	function assignUserToGroup($userId, $groupId) {
		$assignment = $this->userGroupAssignmentDao->newDataObject();
		$assignment->setUserId($userId);
		$assignment->setUserGroupId($groupId);
		$this->userGroupAssignmentDao->insertObject($assignment);
	}

	/**
	 * remove a given user from a given user group
	 * @param $userId int
	 * @param $groupId int
	 * @param $contextId int
	 */
	function removeUserFromGroup($userId, $groupId, $contextId) {
		$assignments = $this->userGroupAssignmentDao->getByUserId($userId, $contextId);
		while ($assignment = $assignments->next()) {
			if ($assignment->getUserGroupId() == $groupId) {
				$this->userGroupAssignmentDao->deleteAssignment($assignment);
			}
		}
	}

	/**
	 * Delete all stage assignments in a user group.
	 * @param $contextId int
	 * @param $userGroupId int
	 */
	function removeAllStagesFromGroup($contextId, $userGroupId) {
		$assignedStages = $this->getAssignedStagesByUserGroupId($contextId, $userGroupId);
		foreach($assignedStages as $stageId => $stageLocaleKey) {
			$this->removeGroupFromStage($contextId, $userGroupId, $stageId);
		}
	}

	/**
	 * Assign a user group to a stage
	 * @param $contextId int
	 * @param $userGroupId int
	 * @param $stageId int
	 */
	function assignGroupToStage($contextId, $userGroupId, $stageId) {
		$this->update(
			'INSERT INTO user_group_stage (context_id, user_group_id, stage_id) VALUES (?, ?, ?)',
			[(int) $contextId, (int) $userGroupId, (int) $stageId]
		);
	}

	/**
	 * Remove a user group from a stage
	 * @param $contextId int
	 * @param $userGroupId int
	 * @param $stageId int
	 */
	function removeGroupFromStage($contextId, $userGroupId, $stageId) {
		$this->update(
			'DELETE FROM user_group_stage WHERE context_id = ? AND user_group_id = ? AND stage_id = ?',
			[(int) $contextId, (int) $userGroupId, (int) $stageId]
		);
	}

	//
	// Extra settings (not handled by rest of Dao)
	//
	/**
	 * Method for updatea userGroup setting
	 * @param $userGroupId int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 * @param $isLocalized boolean
	 */
	function updateSetting($userGroupId, $name, $value, $type = null, $isLocalized = false) {
		$keyFields = ['setting_name', 'locale', 'user_group_id'];

		if (!$isLocalized) {
			$value = $this->convertToDB($value, $type);
			$this->replace('user_group_settings',
				array(
					'user_group_id' => (int) $userGroupId,
					'setting_name' => $name,
					'setting_value' => $value,
					'setting_type' => $type,
					'locale' => ''
				),
				$keyFields
			);
		} else {
			if (is_array($value)) foreach ($value as $locale => $localeValue) {
				$this->update('DELETE FROM user_group_settings WHERE user_group_id = ? AND setting_name = ? AND locale = ?', array((int) $userGroupId, $name, $locale));
				if (empty($localeValue)) continue;
				$type = null;
				$this->update('INSERT INTO user_group_settings
					(user_group_id, setting_name, setting_value, setting_type, locale)
					VALUES (?, ?, ?, ?, ?)',
					[$userGroupId, $name, $this->convertToDB($localeValue, $type), $type, $locale]
				);
			}
		}
	}


	/**
	 * Retrieve a context setting value.
	 * @param $userGroupId int
	 * @param $name string
	 * @param $locale string optional
	 * @return mixed
	 */
	function getSetting($userGroupId, $name, $locale = null) {
		$params = array((int) $userGroupId, $name);
		if ($locale) $params[] = $locale;
		$result = $this->retrieve(
			'SELECT	setting_name, setting_value, setting_type, locale
			FROM	user_group_settings
			WHERE	user_group_id = ? AND
				setting_name = ?' .
				($locale?' AND locale = ?':''),
			$params
		);

		$returner = false;
		if ($row = (array) $result->current()) {
			return $this->convertFromDB($row['setting_value'], $row['setting_type']);
		}
		$returner = [];
		foreach ($result as $row) {
			$row = (array) $row;
			$returner[$row['locale']] = $this->convertFromDB($row['setting_value'], $row['setting_type']);
		}
		return count($returner) ? $returner : false;
	}

	//
	// Install/Defaults with settings
	//

	/**
	 * Load the XML file and move the settings to the DB
	 * @param $contextId
	 * @param $filename
	 * @return boolean true === success
	 */
	function installSettings($contextId, $filename) {
		$xmlParser = new XMLParser();
		$tree = $xmlParser->parse($filename);

		$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site = $siteDao->getSite();
		$installedLocales = $site->getInstalledLocales();

		if (!$tree) return false;

		foreach ($tree->getChildren() as $setting) {
			$roleId = hexdec($setting->getAttribute('roleId'));
			$nameKey = $setting->getAttribute('name');
			$abbrevKey = $setting->getAttribute('abbrev');
			$permitSelfRegistration = $setting->getAttribute('permitSelfRegistration');
			$permitMetadataEdit = $setting->getAttribute('permitMetadataEdit');

			// If has manager role then permitMetadataEdit can't be overriden
			if (in_array($roleId, array(ROLE_ID_MANAGER))) {
				$permitMetadataEdit = $setting->getAttribute('permitMetadataEdit');
			}

			$defaultStages = explode(',', $setting->getAttribute('stages'));

			// create a role associated with this user group
			$userGroup = $this->newDataObject();
			$userGroup->setRoleId($roleId);
			$userGroup->setContextId($contextId);
			$userGroup->setPermitSelfRegistration($permitSelfRegistration);
			$userGroup->setPermitMetadataEdit($permitMetadataEdit);
			$userGroup->setDefault(true);

			// insert the group into the DB
			$userGroupId = $this->insertObject($userGroup);

			// Install default groups for each stage
			if (is_array($defaultStages)) { // test for groups with no stage assignments
				foreach ($defaultStages as $stageId) {
					if (!empty($stageId) && $stageId <= WORKFLOW_STAGE_ID_PRODUCTION && $stageId >= WORKFLOW_STAGE_ID_SUBMISSION) {
						$this->assignGroupToStage($contextId, $userGroupId, $stageId);
					}
				}
			}

			// add the i18n keys to the settings table so that they
			// can be used when a new locale is added/reloaded
			$this->updateSetting($userGroup->getId(), 'nameLocaleKey', $nameKey);
			$this->updateSetting($userGroup->getId(), 'abbrevLocaleKey', $abbrevKey);

			// install the settings in the current locale for this context
			foreach ($installedLocales as $locale) {
				$this->installLocale($locale, $contextId);
			}
		}

		return true;
	}

	/**
	 * use the locale keys stored in the settings table to install the locale settings
	 * @param $locale
	 * @param $contextId
	 */
	function installLocale($locale, $contextId = null) {
		$userGroups = $this->getByContextId($contextId);
		while ($userGroup = $userGroups->next()) {
			$nameKey = $this->getSetting($userGroup->getId(), 'nameLocaleKey');
			$this->updateSetting($userGroup->getId(),
				'name',
				array($locale => __($nameKey, null, $locale)),
				'string',
				$locale,
				true
			);

			$abbrevKey = $this->getSetting($userGroup->getId(), 'abbrevLocaleKey');
			$this->updateSetting($userGroup->getId(),
				'abbrev',
				array($locale => __($abbrevKey, null, $locale)),
				'string',
				$locale,
				true
			);
		}
	}

	/**
	 * Remove all settings associated with a locale
	 * @param $locale
	 */
	function deleteSettingsByLocale($locale) {
		return $this->update('DELETE FROM user_group_settings WHERE locale = ?', $locale);
	}

	/**
	 * private function to assemble the SQL for searching users.
	 * @param string $searchType the field to search on.
	 * @param string $search the keywords to search for.
	 * @param string $searchMatch where to match (is, contains, startsWith).
	 * @param array $params SQL parameter array reference
	 * @return string SQL search snippet
	 */
	function _getSearchSql($searchType, $search, $searchMatch, &$params) {
		$searchTypeMap = array(
			IDENTITY_SETTING_GIVENNAME => 'usgs.setting_value',
			IDENTITY_SETTING_FAMILYNAME => 'usfs.setting_value',
			USER_FIELD_USERNAME => 'u.username',
			USER_FIELD_EMAIL => 'u.email',
			USER_FIELD_AFFILIATION => 'us.setting_value',
		);

		$searchSql = '';

		if (!empty($search)) {

			if (!isset($searchTypeMap[$searchType])) {
				$str = $this->concat('COALESCE(usgs.setting_value,\'\')', 'COALESCE(usfs.setting_value,\'\')', 'u.email', 'COALESCE(us.setting_value,\'\')');
				$concatFields = ' ( LOWER(' . $str . ') LIKE ? OR LOWER(cves.setting_value) LIKE ? ) ';

				$search = strtolower($search);

				$words = preg_split('{\s+}', $search);
				$searchFieldMap = array();

				foreach ($words as $word) {
					$searchFieldMap[] = $concatFields;
					$term = '%' . $word . '%';
					array_push($params, $term, $term);
				}

				$searchSql .= ' AND (  ' . join(' AND ', $searchFieldMap) . '  ) ';
			} else {
				$fieldName = $searchTypeMap[$searchType];
				switch ($searchMatch) {
					case 'is':
						$searchSql = "AND LOWER($fieldName) = LOWER(?)";
						$params[] = $search;
						break;
					case 'contains':
						$searchSql = "AND LOWER($fieldName) LIKE LOWER(?)";
						$params[] = '%' . $search . '%';
						break;
					case 'startsWith':
						$searchSql = "AND LOWER($fieldName) LIKE LOWER(?)";
						$params[] = $search . '%';
						break;
				}
			}
		} else {
			switch ($searchType) {
				case USER_FIELD_USERID:
					$searchSql = 'AND u.user_id = ?';
					break;
			}
		}

		$searchSql .= $this->userDao->getOrderBy(); // FIXME Add "sort field" parameter?

		return $searchSql;
	}

	//
	// Public helper methods
	//

	/**
	 * Get the user groups assigned to each stage.
	 * @param int $contextId Context ID
	 * @param int $stageId WORKFLOW_STAGE_ID_...
	 * @param int $roleId Optional ROLE_ID_... to filter results by
	 * @param DBResultRange (optional) $dbResultRange
	 * @return DAOResultFactory
	 */
	function getUserGroupsByStage($contextId, $stageId, $roleId = null, $dbResultRange = null) {
		$params = array((int) $contextId, (int) $stageId);
		if ($roleId) $params[] = (int) $roleId;
		return new DAOResultFactory(
			$this->retrieveRange(
				$sql = 'SELECT	ug.*
				FROM	user_groups ug
					JOIN user_group_stage ugs ON (ug.user_group_id = ugs.user_group_id AND ug.context_id = ugs.context_id)
				WHERE	ugs.context_id = ? AND
					ugs.stage_id = ?
					' . ($roleId?'AND ug.role_id = ?':'') . '
				ORDER BY ug.role_id ASC',
				$params,
				$dbResultRange
			),
			$this,
			'_returnFromRow',
			[],
			$sql, $params
		);
	}

	/**
	 * Get all stages assigned to one user group in one context.
	 * @param $contextId int The context ID.
	 * @param $userGroupId int The user group ID
	 * @return array
	 */
	function getAssignedStagesByUserGroupId($contextId, $userGroupId) {
		$result = $this->retrieve(
			'SELECT	stage_id
			FROM	user_group_stage
			WHERE	context_id = ? AND
				user_group_id = ?',
			[(int) $contextId, (int) $userGroupId]
		);

		$returner = [];
		foreach ($result as $row) {
			$row = (array) $row;
			$stageId = $row['stage_id'];
			$returner[$stageId] = WorkflowStageDAO::getTranslationKeyFromId($stageId);
		}
		return $returner;
	}

	/**
	 * Check if a user group is assigned to a stage
	 * @param int $userGroupId
	 * @param int $stageId
	 * @return bool
	 */
	function userGroupAssignedToStage($userGroupId, $stageId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM	user_group_stage
			WHERE	user_group_id = ? AND
			stage_id = ?',
			array((int) $userGroupId, (int) $stageId)
		);

		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Check to see whether a user is assigned to a stage ID via a user group.
	 * @param $contextId int
	 * @param $userId int
	 * @param $staeId int
	 * @return boolean
	 */
	function userAssignmentExists($contextId, $userId, $stageId) {
		$result = $this->retrieve(
			'SELECT	COUNT(*)
			FROM	user_group_stage ugs,
			user_user_groups uug
			WHERE	ugs.user_group_id = uug.user_group_id AND
			ugs.context_id = ? AND
			uug.user_id = ? AND
			ugs.stage_id = ?',
			array((int) $contextId, (int) $userId, (int) $stageId)
		);

		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Get all user group IDs with recommendOnly option enabled.
	 * @param $contextId integer
	 * @param $roleId integer (optional)
	 * @return array
	 */
	function getRecommendOnlyGroupIds($contextId, $roleId = null) {
		$params = array((int) $contextId);
		if ($roleId) $params[] = (int) $roleId;

		$result = $this->retrieve(
			'SELECT	ug.user_group_id AS user_group_id
			FROM user_groups ug
			JOIN user_group_settings ugs ON (ugs.user_group_id = ug.user_group_id AND ugs.setting_name = \'recommendOnly\' AND ugs.setting_value = \'1\')
			WHERE ug.context_id = ?
			' . ($roleId?' AND ug.role_id = ?':''),
			$params
		);

		$userGroupIds = [];
		foreach ($result as $row) {
			$row = (array) $row;
			$userGroupIds[] = (int) $row['user_group_id'];
		}
		return $userGroupIds;
	}

	/**
	 * Get all user group IDs with permit_metadata_edit option enabled.
	 * @param $contextId integer
	 * @param $roleId integer (optional)
	 * @return array
	 */
	function getPermitMetadataEditGroupIds($contextId, $roleId = null) {
		$params = array((int) $contextId);
		if ($roleId) $params[] = (int) $roleId;

		$result = $this->retrieve(
			'SELECT	ug.user_group_id AS user_group_id
			FROM user_groups ug
			WHERE permit_metadata_edit = 1 AND
			ug.context_id = ?
			' . ($roleId?' AND ug.role_id = ?':''),
			$params
		);

		$userGroupIds = [];
		foreach ($result as $row) {
			$row = (array) $row;
			$userGroupIds[] = (int) $row['user_group_id'];
		}
		return $userGroupIds;
	}

	/**
	 * Get a list of roles not able to change submissionMetadataEdit permission option.
	 * @return array
	 */
	static function getNotChangeMetadataEditPermissionRoles() {
		return array(ROLE_ID_MANAGER);
	}
}


