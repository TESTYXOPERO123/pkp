<?php
/**
 * @file classes/components/form/FieldAffiliation.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
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

        $config['value'] = $this->value ?? $this->default ?? null;
        $config['currentLocale'] = $submissionContext->getPrimaryLocale();
        $config['supportedLocales'] = $submissionContext->getSupportedSubmissionLocales();

        return $config;
    }
}

