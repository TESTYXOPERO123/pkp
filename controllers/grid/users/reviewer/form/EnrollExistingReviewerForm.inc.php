<?php

/**
 * @file controllers/grid/users/reviewer/form/EnrollExistingReviewerForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnrollExistingReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for enrolling an existing reviewer and adding them to a submission.
 */

use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\user\Collector;

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerForm');

class EnrollExistingReviewerForm extends ReviewerForm
{
    /**
     * Constructor.
     */
    public function __construct($submission, $reviewRound)
    {
        parent::__construct($submission, $reviewRound);
        $this->setTemplate('controllers/grid/users/reviewer/form/enrollExistingReviewerForm.tpl');

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'user.profile.form.usergroupRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userId', 'required', 'manager.people.existingUserRequired'));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param \PKP\core\PKPRequest $request
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $advancedSearchAction = $this->getAdvancedSearchAction($request);

        $this->setReviewerFormAction($advancedSearchAction);
        $apiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $request->getContext()->getPath(), 'users', null, null, [
            'excludeRoleIds' => [Role::ROLE_ID_REVIEWER],
            'status' => Collector::STATUS_ALL,
            'orderDirection' => Collector::ORDER_DIR_ASC,
            'orderBy' => Collector::ORDERBY_GIVENNAME
        ]);
        TemplateManager::getManager($request)->assign(['autocompleteApiUrl' => $apiUrl]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars(['userId', 'userGroupId']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        // Assign a reviewer user group to an existing non-reviewer
        $userId = (int) $this->getData('userId');

        $userGroupId = (int) $this->getData('userGroupId');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroupDao->assignUserToGroup($userId, $userGroupId);

        // Set the reviewerId in the Form for the parent class to use
        $this->setData('reviewerId', $userId);

        return parent::execute(...$functionArgs);
    }
}
