<?php
/**
 * @file classes/affiliation/Affiliation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Affiliation
 *
 * @ingroup affiliation
 *
 * @see DAO
 *
 * @brief Basic class describing an affiliation.
 */

namespace PKP\affiliation;

use PKP\core\DataObject;

class Affiliation extends DataObject
{
    /**
     * Get author id
     */
    public function getAuthorId()
    {
        return $this->getData('authorId');
    }

    /**
     * Get the ROR
     */
    public function getROR(): ?string
    {
        return $this->getData('ror');
    }

    /**
     * Get localized affiliation name.
     */
    public function getLocalizedName(): mixed
    {
        return $this->getLocalizedData('name');
    }
}
