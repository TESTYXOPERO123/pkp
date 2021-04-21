<?php

/**
 * @file classes/citation/CitationDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationDAO
 * @ingroup citation
 *
 * @see Citation
 *
 * @brief Operations for retrieving and modifying Citation objects
 */

namespace PKP\citation;

use PKP\db\DAOResultFactory;
use PKP\plugins\HookRegistry;

class CitationDAO extends \PKP\db\DAO
{
    /**
     * Insert a new citation.
     *
     * @param $citation Citation
     *
     * @return integer the new citation id
     */
    public function insertObject($citation)
    {
        $seq = $citation->getSequence();
        if (!(is_numeric($seq) && $seq > 0)) {
            // Find the latest sequence number
            $result = $this->retrieve(
                'SELECT MAX(seq) AS lastseq FROM citations
				 WHERE publication_id = ?',
                [(int)$citation->getData('publicationId')]
            );
            $citation->setSequence($row ? $row->lastseq + 1 : 1);
        }

        $this->update(
            sprintf('INSERT INTO citations
				(publication_id, raw_citation, seq)
				VALUES
				(?, ?, ?)'),
            [
                (int) $citation->getData('publicationId'),
                $citation->getRawCitation(),
                (int) $seq
            ]
        );
        $citation->setId($this->getInsertId());
        $this->_updateObjectMetadata($citation, false);
        return $citation->getId();
    }

    /**
     * Retrieve a citation by id.
     *
     * @param $citationId integer
     *
     * @return Citation
     */
    public function getById($citationId)
    {
        $result = $this->retrieve(
            'SELECT * FROM citations WHERE citation_id = ?',
            [$citationId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Import citations from a raw citation list of the particular publication.
     *
     * @param $publicationId int
     * @param $rawCitationList string
     */
    public function importCitations($publicationId, $rawCitationList)
    {
        assert(is_numeric($publicationId));
        $publicationId = (int) $publicationId;

        $existingCitations = $this->getByPublicationId($publicationId)->toAssociativeArray();

        // Remove existing citations.
        $this->deleteByPublicationId($publicationId);

        // Tokenize raw citations
        $citationTokenizer = new CitationListTokenizerFilter();
        $citationStrings = $citationTokenizer->execute($rawCitationList);

        // Instantiate and persist citations
        $importedCitations = [];
        if (is_array($citationStrings)) {
            foreach ($citationStrings as $seq => $citationString) {
                if (!empty(trim($citationString))) {
                    $citation = new Citation($citationString);
                    // Set the publication
                    $citation->setData('publicationId', $publicationId);
                    // Set the counter
                    $citation->setSequence($seq + 1);

                    $this->insertObject($citation);

                    $importedCitations[] = $citation;
                }
            }
        }

        HookRegistry::call('CitationDAO::afterImportCitations', [$publicationId, $existingCitations, $importedCitations]);
    }

    /**
     * Retrieve an array of citations matching a particular publication id.
     *
     * @param $publicationId int
     * @param null|mixed $rangeInfo
     *
     * @return DAOResultFactory containing matching Citations
     */
    public function getByPublicationId($publicationId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT *
			FROM citations
			WHERE publication_id = ?
			ORDER BY seq, citation_id',
            [(int)$publicationId],
            $rangeInfo
        );
        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Update an existing citation.
     *
     * @param $citation Citation
     */
    public function updateObject($citation)
    {
        $returner = $this->update(
            'UPDATE	citations
			SET	publication_id = ?,
				raw_citation = ?,
				seq = ?
			WHERE	citation_id = ?',
            [
                (int) $citation->getData('publicationId'),
                $citation->getRawCitation(),
                (int) $citation->getSequence(),
                (int) $citation->getId()
            ]
        );
        $this->_updateObjectMetadata($citation);
    }

    /**
     * Delete a citation.
     *
     * @param $citation Citation
     *
     * @return boolean
     */
    public function deleteObject($citation)
    {
        return $this->deleteById($citation->getId());
    }

    /**
     * Delete a citation by id.
     *
     * @param $citationId int
     *
     * @return boolean
     */
    public function deleteById($citationId)
    {
        $this->update('DELETE FROM citation_settings WHERE citation_id = ?', [(int) $citationId]);
        return $this->update('DELETE FROM citations WHERE citation_id = ?', [(int) $citationId]);
    }

    /**
     * Delete all citations matching a particular publication id.
     *
     * @param $publicationId int
     *
     * @return boolean
     */
    public function deleteByPublicationId($publicationId)
    {
        $citations = $this->getByPublicationId($publicationId);
        while ($citation = $citations->next()) {
            $this->deleteById($citation->getId());
        }
        return true;
    }

    //
    // Protected helper methods
    //
    /**
     * Get the id of the last inserted citation.
     *
     * @return int
     */
    public function getInsertId()
    {
        return parent::_getInsertId('citations', 'citation_id');
    }


    //
    // Private helper methods
    //
    /**
     * Construct a new citation object.
     *
     * @return Citation
     */
    public function _newDataObject()
    {
        return new Citation();
    }

    /**
     * Internal function to return a citation object from a
     * row.
     *
     * @param $row array
     *
     * @return Citation
     */
    public function _fromRow($row)
    {
        $citation = $this->_newDataObject();
        $citation->setId((int)$row['citation_id']);
        $citation->setData('publicationId', (int)$row['publication_id']);
        $citation->setRawCitation($row['raw_citation']);
        $citation->setSequence((int)$row['seq']);

        $this->getDataObjectSettings('citation_settings', 'citation_id', $row['citation_id'], $citation);

        return $citation;
    }

    /**
     * Update the citation meta-data
     *
     * @param $citation Citation
     */
    public function _updateObjectMetadata($citation)
    {
        $this->updateDataObjectSettings('citation_settings', $citation, ['citation_id' => $citation->getId()]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\citation\CitationDAO', '\CitationDAO');
}
