<?php

/**
 * @file pages/invitation/PKPInvitationHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\components\forms\createUser\UserDetailsForm;
use PKP\core\PKPApplication;
use PKP\invitation\invitations\BaseInvitation;

class PKPInvitationHandler extends Handler
{
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    //
    // Overridden methods from Handler
    //
    /**
     * @see PKPHandler::initialize()
     */
    public function initialize($request): void
    {
        parent::initialize($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pageComponent', 'SettingsPage');
    }
    /**
     * Accept invitation handler
     */
    public function accept($args, $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $steps = $this->getSteps($request,$invitation);
        $templateMgr->setState([
            'steps' => $steps,
            'acceptInvitationApiUrl'=>$this->getAcceptInvitationApiUrl($request,$invitation),
            'primaryLocale'=> $context->getData('primaryLocale'),
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'csrfToken' => $request->getSession()->getCSRFToken(),
            'invitationId' => $request->getUserVar('id') ?: null,
            'invitationKey' => $request->getUserVar('key') ?: null,
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
        ]);
        $templateMgr->display('invitation/acceptInvitation.tpl');
//        $invitation->acceptHandle();
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitation->declineHandle();
    }

    private function getInvitationByKey(Request $request): BaseInvitation
    {
        $key = $request->getUserVar('key') ?: null;
        $id = $request->getUserVar('id') ?: null;

        $invitation = Repo::invitation()
            ->getByIdAndKey($id, $key);

        if (is_null($invitation)) {
            $request->getDispatcher()->handle404();
        }

        return $invitation;
    }

    /**
     * get user account create steps
     * @param Request $request
     * @param $invitation
     * @return array
     */
    protected function getSteps(Request $request,$invitation): array
    {
        $apiUrl = $this->getAcceptInvitationApiUrl($request,$invitation);

        $steps = [];
        $steps[] = $this->verifyOrcid();
        if(!$invitation->userId){
            $steps[] = $this->userCreate();
            $steps[] = $this->getUserDetailsForm($request,$apiUrl,$invitation);
        }
        $steps[] = $this->userCreateReview();

        return $steps;
    }

    /**
     * Get the state for the user orcid verification
     */
    protected function verifyOrcid(): array
    {
        return [
            'id' => 'verifyOrcid',
            'name' => __('invitation.verifyOrcid'),
            'reviewName' => '',
            'stepName' => __('invitation.verifyOrcidStep'),
            'type' => 'popup',
            'description' => __('invitation.verifyOrcidDescription'),
            'sections' => [],
        ];
    }

    /**
     * create username and password for ojs account
     */
    protected function userCreate(): array
    {
        return [
            'id' => 'userCreate',
            'name' => __('invitation.userCreate'),
            'reviewName' => __('invitation.userCreateReviewName'),
            'stepName' => __('invitation.userCreateStep'),
            'type' => 'form',
            'description' => __('invitation.userCreateDescription'),
            'sections' => [],
            'reviewData'=>[]
        ];
    }

    protected function getUserDetailsForm(Request $request,string $apiUrl,$invitation): array
    {
        $localeNames = $request->getContext()->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $contactForm = new UserDetailsForm($apiUrl, $locales,$invitation);
        $sections = [
            [
                'id' => 'userCreateDetailsForm',
                'type'=> 'form',
                'description' => $request->getContext()->getLocalizedData('detailsHelp'),
                'form' => $contactForm->getConfig(),
            ]
        ];

        return [
            'id' => 'userDetails',
            'name' => __('invitation.userCreateDetails'),
            'reviewName' => __('invitation.userCreateDetailsReviewName'),
            'stepName' => __('invitation.userCreateDetailStep'),
            'type' => 'form',
            'description' => __('invitation.userCreateDetailsDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * create review all steps for create ojs account
     */
    protected function userCreateReview(): array
    {
        return [
            'id' => 'userCreateReview',
            'name' => __('invitation.userCreateReview'),
            'reviewName' => 'Roles',
            'stepName' => __('invitation.userCreateReviewStep'),
            'type' => 'review',
            'description' => __('invitation.userCreateReviewDescription'),
            'sections' => [
                [
                    'id' => 'userCreateRoles',
                    'type'=>'table',
                    'description' => '',
                    'rows' => [
                        [
                            'date_start'=>'2024-03-01',
                            'date_end'=>'2025-01-01',
                            'user_group_id'=>3,
                            'setting_value'=>'test',
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * Get the url to the create user API endpoint
     * or if user already in the system get accept invitation
     * API endpoint
     * @param Request $request
     * @param $invitation
     * @return string
     */
    protected function getAcceptInvitationApiUrl(Request $request, $invitation): string
    {
        return $invitation->userId ? $request
            ->getDispatcher()
            ->url(
                $request,
                PKPApplication::ROUTE_API,
                $request->getContext()->getPath(),
                'invitations/accept'
            ) : $request
            ->getDispatcher()
            ->url(
                $request,
                PKPApplication::ROUTE_API,
                $request->getContext()->getPath(),
                'users'
            );
    }
}
