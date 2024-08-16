<?php
/**
 * @file classes/publication/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write publications to the database.
 */

namespace PKP\publication;

use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\citation\CitationDAO;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;
use PKP\submission\SubmissionAgencyVocab;
use PKP\submission\SubmissionDisciplineDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionSubjectDAO;

/**
 * @template T of Publication
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_PUBLICATION;

    /** @copydoc EntityDAO::$table */
    public $table = 'publications';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'publication_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'publication_id';

    /** @var SubmissionKeywordDAO */
    public $submissionKeywordDao;

    /** @var SubmissionSubjectDAO */
    public $submissionSubjectDao;

    /** @var SubmissionDisciplineDAO */
    public $submissionDisciplineDao;

    /** @var CitationDAO */
    public $citationDao;

    /**
     * Constructor
     */
    public function __construct(
        SubmissionKeywordDAO $submissionKeywordDao,
        SubmissionSubjectDAO $submissionSubjectDao,
        SubmissionDisciplineDAO $submissionDisciplineDao,
        CitationDAO $citationDao,
        PKPSchemaService $schemaService
    ) {
        parent::__construct($schemaService);

        $this->submissionKeywordDao = $submissionKeywordDao;
        $this->submissionSubjectDao = $submissionSubjectDao;
        $this->submissionDisciplineDao = $submissionDisciplineDao;
        $this->citationDao = $citationDao;
    }

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'submission_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Publication
    {
        return app(Publication::class);
    }

    /**
     * Get the total count of rows matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
    }

    /**
     * Get a list of ids matching the configured query
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('p.' . $this->primaryKeyColumn)
            ->pluck('p.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of publications matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->publication_id => $this->fromRow($row);
            }
        });
    }

    /**
     * Get the publication dates of the first and last publications
     * matching the passed query
     *
     * @return object self::$min_date_published, self::$max_date_published
     */
    public function getDateBoundaries(Collector $query): object
    {
        return $query
            ->getQueryBuilder()
            ->reorder()
            ->select([
                DB::raw('MIN(p.date_published) AS min_date_published, MAX(p.date_published) AS max_date_published')
            ])
            ->first();
    }

    /**
     * Is the urlPath a duplicate?
     *
     * Checks if the urlPath is used in any other submission than the one
     * passed
     *
     * A urlPath may be duplicated across more than one publication of the
     * same submission. But two publications in two different submissions
     * can not share the same urlPath.
     *
     * This is only applied within a single context.
     */
    public function isDuplicateUrlPath(string $urlPath, int $submissionId, int $contextId): bool
    {
        if (!strlen($urlPath)) {
            return false;
        }
        return (bool) DB::table('publications as p')
            ->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
            ->where('url_path', '=', $urlPath)
            ->where('p.submission_id', '!=', $submissionId)
            ->where('s.context_id', '=', $contextId)
            ->count();
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Publication
    {
        $publication = parent::fromRow($row);

        $this->setDoiObject($publication);

        // Set the primary locale from the submission
        $locale = DB::table('submissions as s')
            ->where('s.submission_id', '=', $publication->getData('submissionId'))
            ->value('locale');
        $publication->setData('locale', $locale);

        $this->setAuthors($publication);
        $this->setCategories($publication);
        $this->setControlledVocab($publication);

        return $publication;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Publication $publication): int
    {
        $vocabs = $this->extractControlledVocab($publication);

        $id = parent::_insert($publication);

        $this->saveControlledVocab($vocabs, $id);
        $this->saveCategories($publication);

        // Parse the citations
        if ($publication->getData('citationsRaw')) {
            $this->saveCitations($publication);
        }

        return $id;
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Publication $publication, ?Publication $oldPublication = null)
    {
        $vocabs = $this->extractControlledVocab($publication);

        parent::_update($publication);

        $this->saveControlledVocab($vocabs, $publication->getId());
        $this->saveCategories($publication);

        if ($oldPublication && $oldPublication->getData('citationsRaw') != $publication->getData('citationsRaw')) {
            $this->saveCitations($publication);
        }
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Publication $publication)
    {
        parent::_delete($publication);
    }

    /**
     * @copydoc EntityDAO::deleteById()
     */
    public function deleteById(int $publicationId): int
    {
        $affectedRows = parent::deleteById($publicationId);

        $this->deleteAuthors($publicationId);
        $this->deleteCategories($publicationId);
        $this->deleteControlledVocab($publicationId);
        $this->deleteCitations($publicationId);

        return $affectedRows;
    }

    /**
     * Get publication ids that have a matching setting
     */
    public function getIdsBySetting(string $settingName, $settingValue, int $contextId): Enumerable
    {
        $q = DB::table($this->table . ' as p')
            ->join($this->settingsTable . ' as ps', 'p.publication_id', '=', 'ps.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->where('ps.setting_name', '=', $settingName)
            ->where('ps.setting_value', '=', $settingValue)
            ->where('s.context_id', '=', (int) $contextId);

        return $q->select('p.publication_id')
            ->pluck('p.publication_id');
    }

    /**
     * @copydoc PKPPubIdPluginDAO::pubIdExists()
     */
    public function pubIdExists(string $pubIdType, string $pubId, int $excludePubObjectId, int $contextId): bool
    {
        return DB::table('publication_settings AS ps')
            ->join('publications AS p', 'p.publication_id', '=', 'ps.publication_id')
            ->join('submissions AS s', 'p.submission_id', '=', 's.submission_id')
            ->where('ps.setting_name', '=', "pub-id::{$pubIdType}")
            ->where('ps.setting_value', '=', $pubId)
            ->where('s.submission_id', '<>', $excludePubObjectId)
            ->where('s.context_id', '=', $contextId)
            ->count() > 0;
    }

    /**
     * @copydoc PKPPubIdPluginDAO::changePubId()
     */
    public function changePubId($pubObjectId, $pubIdType, $pubId)
    {
        DB::table($this->settingsTable)
            ->update([
                'publication_id' => (int) $pubObjectId,
                'locale' => '',
                'setting_name' => 'pub-id::' . $pubIdType,
                'setting_value' => (string) $pubId
            ]);
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deletePubId()
     */
    public function deletePubId(int $pubObjectId, string $pubIdType): int
    {
        return DB::table($this->settingsTable)
            ->where('publication_id', (int) $pubObjectId)
            ->where('setting_name', '=', 'pub-id::' . $pubIdType)
            ->delete();
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
     */
    public function deleteAllPubIds(int $contextId, string $pubIdType): int
    {
        return DB::table('publication_settings AS ps')
            ->join('publications AS p', 'p.publication_id', '=', 'ps.publication_id')
            ->join('submissions AS s', 's.submission_id', '=', 'p.submission_id')
            ->where('ps.setting_name', '=', "pub-id::{$pubIdType}")
            ->where('s.context_id', '=', $contextId)
            ->delete();
    }

    /**
     * Set a publication's author properties
     */
    protected function setAuthors(Publication $publication)
    {
        $publication->setData(
            'authors',
            Repo::author()
                ->getCollector()
                ->filterByPublicationIds([$publication->getId()])
                ->orderBy(\PKP\author\Collector::ORDERBY_SEQUENCE)
                ->getMany()
                ->remember()
        );
    }

    /**
     * Delete a publication's authors
     */
    protected function deleteAuthors(int $publicationId)
    {
        $authors = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->getMany();

        foreach ($authors as $author) {
            Repo::author()->delete($author);
        }
    }

    /**
     * Set a publication's controlled vocabulary properties
     */
    protected function setControlledVocab(Publication $publication)
    {
        $publication->setData('keywords', $this->submissionKeywordDao->getKeywords($publication->getId()));
        $publication->setData('subjects', $this->submissionSubjectDao->getSubjects($publication->getId()));
        $publication->setData('disciplines', $this->submissionDisciplineDao->getDisciplines($publication->getId()));
        $publication->setData('supportingAgencies', SubmissionAgencyVocab::getAgencies($publication->getId()));
    }

    /**
     * Remove controlled vocabulary from a publication's data
     *
     * Controlled vocabulary includes keywords, subjects, and similar
     * metadata that shouldn't be saved in the publications table.
     *
     * @see self::saveControlledVocab()
     *
     * @return array Key/value of controlled vocabulary properties
     */
    protected function extractControlledVocab(Publication $publication): array
    {
        $controlledVocabKeyedArray = array_flip([
            'disciplines',
            'keywords',
            'subjects',
            'supportingAgencies',
        ]);

        $values = array_intersect_key($publication->_data, $controlledVocabKeyedArray);
        $publication->setAllData(array_diff_key($publication->_data, $controlledVocabKeyedArray));

        return $values;
    }

    /**
     * Save controlled vocabulary properties
     *
     * @see self::extractControlledVocab()
     */
    protected function saveControlledVocab(array $values, int $publicationId)
    {
        // Update controlled vocabularly for which we have props
        foreach ($values as $prop => $value) {
            switch ($prop) {
                case 'keywords':
                    $this->submissionKeywordDao->insertKeywords($value, $publicationId);
                    break;
                case 'subjects':
                    $this->submissionSubjectDao->insertSubjects($value, $publicationId);
                    break;
                case 'disciplines':
                    $this->submissionDisciplineDao->insertDisciplines($value, $publicationId);
                    break;
                case 'supportingAgencies':
                    SubmissionAgencyVocab::insertAgencies($value, $publicationId);
                    break;
            }
        }
    }

    /**
     * Delete controlled vocab entries for a publication
     */
    protected function deleteControlledVocab(int $publicationId)
    {
        $this->submissionKeywordDao->insertKeywords([], $publicationId);
        $this->submissionSubjectDao->insertSubjects([], $publicationId);
        $this->submissionDisciplineDao->insertDisciplines([], $publicationId);
        SubmissionAgencyVocab::insertAgencies([], $publicationId);
    }

    /**
     * Set a publication's category property
     */
    protected function setCategories(Publication $publication)
    {
        $publication->setData(
            'categoryIds',
            Repo::category()->getCollector()
                ->filterByPublicationIds([$publication->getId()])
                ->getIds()
                ->toArray()
        );
    }

    /**
     * Save the assigned categories
     */
    protected function saveCategories(Publication $publication)
    {
        Repo::category()->dao->deletePublicationAssignments($publication->getId());
        if (!empty($publication->getData('categoryIds'))) {
            foreach ($publication->getData('categoryIds') as $categoryId) {
                Repo::category()->dao->insertPublicationAssignment($categoryId, $publication->getId());
            }
        }
    }

    /**
     * Delete the category assignments
     */
    protected function deleteCategories(int $publicationId)
    {
        Repo::category()->dao->deletePublicationAssignments($publicationId);
    }

    /**
     * Save the citations
     */
    protected function saveCitations(Publication $publication)
    {
        $this->citationDao->importCitations($publication->getId(), $publication->getData('citationsRaw'));
    }

    /**
     * Delete the citations
     */
    protected function deleteCitations(int $publicationId)
    {
        $this->citationDao->deleteByPublicationId($publicationId);
    }

    /**
     * Set the DOI object
     *
     */
    protected function setDoiObject(Publication $publication)
    {
        if (!empty($publication->getData('doiId'))) {
            $publication->setData('doiObject', Repo::doi()->get($publication->getData('doiId')));
        }
    }
}
