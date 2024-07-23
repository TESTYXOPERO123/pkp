<?php
/**
 * @file classes/components/form/context/PKPAnnouncementSettingsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementSettingsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring announcements.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\site\Site;

class PKPAnnouncementSettingsForm extends FormComponent
{
    public const FORM_ANNOUNCEMENT_SETTINGS = 'announcementSettings';
    public $id = self::FORM_ANNOUNCEMENT_SETTINGS;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct($action, $locales, Context|Site $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('enableAnnouncements', [
            'label' => __('manager.setup.announcements'),
            'description' => __('manager.setup.enableAnnouncements.description'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.enableAnnouncements.enable')]
            ],
            'value' => (bool) $context->getData('enableAnnouncements'),
        ]))
            ->addField(new FieldRichTextarea('announcementsIntroduction', [
                'label' => __('manager.setup.announcementsIntroduction'),
                'tooltip' => __('manager.setup.announcementsIntroduction.description'),
                'isMultilingual' => true,
                'value' => $context->getData('announcementsIntroduction'),
                'showWhen' => 'enableAnnouncements',
            ]))
            ->addField(new FieldText('numAnnouncementsHomepage', [
                'label' => __('manager.setup.numAnnouncementsHomepage'),
                'description' => __('manager.setup.numAnnouncementsHomepage.description'),
                'size' => 'small',
                'value' => $context->getData('numAnnouncementsHomepage'),
                'showWhen' => 'enableAnnouncements',
            ]));
    }
}
