<?php
/**
 * @file classes/components/form/FieldAffiliation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldAffiliation
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for author affiliations.
 */

namespace PKP\components\forms;

use PKP\core\PKPApplication;

class FieldAffiliations extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-affiliations';

    /** @var array A default for this field when no value is specified. */
    public $default = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $submissionContext = PKPApplication::get()->getRequest()->getContext();
        $currentLocale = $submissionContext->getPrimaryLocale();
        $supportedLocales = $submissionContext->getSupportedSubmissionLocales();

        // locale display names
        $localeDisplayNames = [];
        foreach ($supportedLocales as $locale) {
            $localeDisplayNames[$locale] = locale_get_display_name($locale, $currentLocale);
        }

        // sort supported locales, with current locale as first element
        $dict = array_flip([$currentLocale]);
        $positions = array_map(function ($elem) use ($dict) { return $dict[$elem] ?? INF; }, $supportedLocales);
        array_multisort($positions, $supportedLocales);

        $config['value'] = $this->value ?? $this->default ?? null;
        $config['currentLocale'] = $currentLocale;
        $config['supportedLocales'] = $supportedLocales;
        $config['localeDisplayNames'] = $localeDisplayNames;

        return $config;
    }
}

