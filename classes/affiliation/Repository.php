<?php
/**
 * @file classes/affiliation/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage affiliations.
 */

namespace PKP\affiliation;

use APP\author\Author;
use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request */
    protected $request;

    /** @var PKPSchemaService<Affiliation> */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Affiliation
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $authorId = null): bool
    {
        return $this->dao->exists($id, $authorId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $authorId = null): ?Affiliation
    {
        return $this->dao->get($id, $authorId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping affiliations to their schema.
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an affiliation
     *
     * Perform validation checks on data used to add or edit an affiliation.
     *
     * @param Author|null $author Author being edited. Pass `null` if creating a new author
     * @param array $props A key/value array with the new data to validate
     * @param Submission $submission The context's supported locales
     * @param Context $context The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Affiliation::validate [[&$errors, $object, $props, $submission, $context]]
     */
    public function validate(?Author $author, array $props, Submission $submission, Context $context): array
    {
        $schemaService = app()->get('schema');
        $primaryLocale = $submission->getData('locale');
        $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales(
            $validator,
            $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_AFFILIATION), $allowedLocales
        );

        // The authorId must exist and ror_id or one name must exist
        $validator->after(function ($validator) use ($props) {
            // do something useful
        });

        $errors = [];

        $affiliations = (!empty($author)) ? $author->getAffiliations() : $props['affiliations'];
        foreach($affiliations as $affiliation) {
            if ($validator->fails()) {
                $errors = $this->schemaService->formatValidationErrors($validator->errors());
                break;
            }
        }

        Hook::call('Affiliation::validate',  [&$errors, $author, $props, $submission, $context]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Affiliation $affiliation): int
    {
        $id = $this->dao->insert($affiliation);
        Hook::call('Affiliation::add', [$affiliation]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Affiliation $affiliation, array $params): void
    {
        $newRow = clone $affiliation;
        $newRow->setAllData(array_merge($newRow->_data, $params));
        Hook::call('Affiliation::edit', [$newRow, $affiliation, $params]);
        $this->dao->update($newRow);
    }

    /**
     * @copydoc DAO::delete()
     */
    public function delete(Affiliation $affiliation): void
    {
        Hook::call('Affiliation::delete::before', [$affiliation]);
        $this->dao->delete($affiliation);
        Hook::call('Affiliation::delete', [$affiliation]);
    }

    /**
     * Delete a collection of affiliations
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $row) {
            $this->delete($row);
        }
    }

    /**
     * Get all affiliations for a given author.
     */
    public function getByAuthorId(int $authorId): LazyCollection
    {
        return $this->getCollector()
            ->filterByAuthorIds([$authorId])
            ->getMany();
    }

    /**
     * Save affiliations.
     */
    public function saveAffiliations(Author $author): void
    {
        $affiliations = $author->getAffiliations();
        $authorId = $author->getId();

        // delete all affiliations if parameter $affiliations empty array
        if ($affiliations->isEmpty()) {
            $this->deleteByAuthorId($authorId);
            return;
        }

        // delete affiliations not in param $affiliations
        // do this before insert/update, otherwise inserted will be deleted
        $currentAffiliations = $this->getByAuthorId($authorId);
        foreach ($currentAffiliations as $currentAffiliation) {
            $rowFound = false;
            $currentAffiliationId = $currentAffiliation->getId();

            foreach ($affiliations as $affiliation) {
                if (is_a($affiliation, 'Affiliation')) {
                    $affiliationId = (int)$affiliation->getId();
                } else {
                    $affiliationId = (int)$affiliation['id'];
                }

                if ($currentAffiliationId === $affiliationId) {
                    $rowFound = true;
                    break;
                }
            }

            if (!$rowFound) {
                $this->dao->delete($currentAffiliation);
            }
        }

        // insert, update
        foreach ($affiliations as $affiliation) {

            if (!($affiliation instanceof Affiliation)) {

                if (empty($affiliation)) continue;

                $newAffiliation = $this->newDataObject();
                $newAffiliation->setAllData($affiliation);

                $affiliation = $newAffiliation;
            }

            if (empty($affiliation->getData('authorId'))) {
                $affiliation->setData('authorId', $authorId);
            }

            $this->dao->updateOrInsert($affiliation);
        }
    }

    /**
     * Delete author's affiliations.
     */
    public function deleteByAuthorId(int $authorId): void
    {
        $this->dao->deleteByAuthorId($authorId);
    }

    /**
     * Migrates affiliation.
     */
    public function migrateAffiliation(array $userAffiliation, array $allowedLocales): LazyCollection
    {
        $affiliation = $this->newDataObject();
        $params = [
            "id" => null,
            "authorId" => null,
            "ror" => null,
            "name" => array_intersect_key($userAffiliation, $allowedLocales)
        ];

        foreach ($userAffiliation as $affiliationName) {
            $ror = Repo::ror()->getCollector()->filterByName($affiliationName)->getMany()->first();
            if($ror){
                $params = [
                    "id" => null,
                    "authorId" => null,
                    "ror" => $ror->_data['ror'],
                    "name" => $ror->_data['name']
                ];
                break;
            }
        }

        $affiliation->setAllData($params);

        return new LazyCollection($affiliation);
    }
}
