<?php
/**
 * @file classes/components/form/FieldAffiliation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldAffiliation
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for author affiliations.
 */

namespace PKP\components\forms\affiliation;

use PKP\components\forms\Field;

class FieldAffiliation extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-affiliation';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        return $config;
    }
}
