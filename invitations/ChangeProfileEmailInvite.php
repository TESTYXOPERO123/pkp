<?php

/**
 * @file invitations/ChangeProfileEmailInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInvite
 *
 * @ingroup invitations
 *
 * @brief Change Profile Email invitation
 */

namespace PKP\invitations;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Mail\Mailable;
use PKP\identity\Identity;
use PKP\invitation\invitations\contracts\IBackofficeHandleable;
use PKP\invitation\invitations\enums\InvitationAction;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\invitations\Invitation;
use PKP\invitation\invitations\PKPInvitationActionRedirectController;
use PKP\invitation\invitations\traits\HasMailable;
use PKP\invitation\invitations\traits\ShouldValidate;
use PKP\invitation\models\InvitationModel;
use PKP\invitations\handlers\ChangeProfileEmailInviteRedirectController;
use PKP\mail\mailables\ChangeProfileEmailInvitationNotify;

class ChangeProfileEmailInvite extends Invitation implements IBackofficeHandleable
{
    use HasMailable, ShouldValidate;

    const INVITATION_TYPE = 'changeProfileEmail';

    public $newEmail = null;

    public static function getType(): string
    {
        return self::INVITATION_TYPE;
    }

    public function getHiddenAfterDispatch(): array
    {
        $baseHiddenItems = parent::getHiddenAfterDispatch();
        
        $additionalHiddenItems = ['newEmail'];

        return array_merge($baseHiddenItems, $additionalHiddenItems);
    }

    public function getMailable(): Mailable
    {
        $user = Repo::user()->get($this->invitationModel->userId);
        $sendIdentity = new Identity();
        $sendIdentity->setFamilyName($user->getFamilyName(null), null);
        $sendIdentity->setGivenName($user->getGivenName(null), null);
        $sendIdentity->setEmail($this->newEmail);

        $mailable = new ChangeProfileEmailInvitationNotify();
        $mailable->recipients([$sendIdentity]);
        $mailable->sender($user);

        $request = Application::get()->getRequest();
        $site = $request->getSite();
        $sitePrimaryLocale = $site->getPrimaryLocale();

        $emailTemplate = Repo::emailTemplate()->getByKey(Application::CONTEXT_ID_NONE, $mailable::getEmailTemplateKey());
        $mailable->subject($emailTemplate->getLocalizedData('subject', $sitePrimaryLocale))
            ->body($emailTemplate->getLocalizedData('body', $sitePrimaryLocale));

        $mailable->setData($sitePrimaryLocale);

        $this->setMailable($mailable);
        
        $acceptUrl = $this->getActionURL(InvitationAction::ACCEPT);
        $declineUrl = $this->getActionURL(InvitationAction::DECLINE);

        $this->mailable->buildViewDataUsing(function () use ($acceptUrl, $declineUrl) {
            return [
                'acceptInvitationUrl' => $acceptUrl,
                'declineInvitationUrl' => $declineUrl,
                'newEmail' => $this->newEmail
            ];
        });

        return $this->mailable;
    }

    protected function preDispatchActions(): void
    {
        // Check if everything is in order regarding the properties
        if (!isset($this->newEmail)) {
            throw new Exception('The invitation can not be dispatched because the email property is missing');
        }

        // Invalidate any other related invitation
        $pendingInvitations = InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType(self::INVITATION_TYPE)
            ->byUserId($this->invitationModel->userId)
            ->get();

        foreach($pendingInvitations as $pendingInvitation) {
            $pendingInvitation->markAs(InvitationStatus::DECLINED);
        }
    }

    public function finalise(): void
    {
        $user = Repo::user()->get($this->invitationModel->userId);
        
        if (!$user) {
            throw new Exception();
        }

        $user->setEmail($this->newEmail);

        Repo::user()->edit($user);

        $this->invitationModel->markAs(InvitationStatus::ACCEPTED);
    }

    public function getInvitationActionRedirectController(): ?PKPInvitationActionRedirectController
    {
        return new ChangeProfileEmailInviteRedirectController($this);
    }

    public function validate(): bool 
    {
        if ($this->newEmail) {
            if (filter_var($this->newEmail, FILTER_VALIDATE_EMAIL) == false) {
                $this->addError('The provided email is not in the correct form');
            }
        }

        return $this->isValid();
    }
}
