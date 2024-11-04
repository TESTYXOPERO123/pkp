<?php
/**
 * @file classes/citation/Citation.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Citation
 *
 * @ingroup citation
 *
 * @defgroup citation Citation
 *
 * @brief Class representing a citation (bibliographic reference)
 */

namespace PKP\citation;

use PKP\core\DataObject;

class Citation extends DataObject
{
    /**
     * Constructor.
     *
     * @param string|null $rawCitation an unparsed citation string
     */
    public function __construct(string $rawCitation = null)
    {
        parent::__construct();
        $this->setRawCitation($rawCitation);
    }

    /**
     * Get publication id
     */
    public function getPublicationId()
    {
        return $this->getData('publicationId');
    }

    /**
     * Get the rawCitation
     *
     * @return string
     */
    public function getRawCitation(): string
    {
        return $this->getData('rawCitation');
    }

    /**
     * Set the rawCitation
     *
     * @param string|null $rawCitation
     */
    public function setRawCitation(string $rawCitation = null): void
    {
        $rawCitation = $this->cleanCitationString($rawCitation);
        $this->setData('rawCitation', $rawCitation);
    }

    /**
     * Get the sequence number
     *
     * @return int
     */
    public function getSequence(): int
    {
        return $this->getData('seq');
    }

    /**
     * Set the sequence number
     *
     * @param int $seq
     */
    public function setSequence(int $seq): void
    {
        $this->setData('seq', $seq);
    }

    /**
     * Replace URLs through HTML links, if the citation does not already contain HTML links
     *
     * @return string
     */
    public function getCitationWithLinks(): string
    {
        $citation = $this->getRawCitation();
        if (stripos($citation, '<a href=') === false) {
            $citation = preg_replace_callback(
                '#(http|https|ftp)://[\d\w\.-]+\.[\w\.]{2,6}[^\s\]\[\<\>]*/?#',
                function ($matches) {
                    $trailingDot = in_array($char = substr($matches[0], -1), ['.', ',']);
                    $url = rtrim($matches[0], '.,');
                    return "<a href=\"{$url}\">{$url}</a>" . ($trailingDot ? $char : '');
                },
                $citation
            );
        }
        return $citation;
    }

    /**
     * Take a citation string and clean/normalize it
     *
     * @param string $citationString
     *
     * @return string
     */
    public function cleanCitationString(string $citationString = null): string
    {
        // 1) Strip slashes and whitespace
        $citationString = trim(stripslashes($citationString));

        // 2) Normalize whitespace
        $citationString = preg_replace('/[\s]+/u', ' ', $citationString);

        return $citationString;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\citation\Citation', '\Citation');
}
