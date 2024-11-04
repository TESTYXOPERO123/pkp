<?php
/**
 * @file classes/citation/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage citations.
 */

namespace PKP\citation;

use APP\core\Request;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\facades\Repo;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request */
    protected $request;

    /** @var PKPSchemaService<Citation> */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Citation
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?Citation
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping citations to their schema.
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a citation
     *
     * Perform validation checks on data used to add or edit a citation.
     *
     * @param Citation|null $object Citation being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Citation::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Citation $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check required fields if we're adding a citation
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Citation::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Citation $row): int
    {
        $id = $this->dao->insert($row);
        Hook::call('Citation::add', [$row]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Citation $row, array $params): void
    {
        $newRow = clone $row;
        $newRow->setAllData(array_merge($newRow->_data, $params));
        Hook::call('Citation::edit', [$newRow, $row, $params]);
        $this->dao->update($newRow);
    }

    /** @copydoc DAO::delete() */
    public function delete(Citation $citation): void
    {
        Hook::call('Citation::delete::before', [$citation]);
        $this->dao->delete($citation);
        Hook::call('Citation::delete', [$citation]);
    }

    /**
     * Delete a collection of citations
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $citation) {
            $this->delete($citation);
        }
    }

    /**
     * Get all citations for a given publication.
     *
     * @param int $publicationId
     *
     * @return LazyCollection
     */
    public function getByPublicationId(int $publicationId): LazyCollection
    {
        return $this->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->getMany();
    }

    /**
     * Delete an publication's citations.
     *
     * @param int $publicationId
     *
     * @return void
     */
    public function deleteByPublicationId(int $publicationId): void
    {
        $this->dao->deleteByPublicationId($publicationId);
    }

    /**
     * Import citations from a raw citation list of the particular publication.
     *
     * @param int $publicationId
     * @param string $rawCitationList
     *
     * $return void
     *
     * @hook CitationDAO::afterImportCitations [[$publicationId, $existingCitations, $importedCitations]]
     */
    public function importCitations(int $publicationId, string $rawCitationList): void
    {
        assert(is_numeric($publicationId));
        $publicationId = (int) $publicationId;

        $existingCitations = $this->getByPublicationId($publicationId)->toArray();

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

                    $this->dao->insert($citation);

                    $importedCitations[] = $citation;
                }
            }
        }

        Hook::call('CitationDAO::afterImportCitations', [$publicationId, $existingCitations, $importedCitations]);
    }
}
