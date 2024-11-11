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

use APP\core\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\author\Author;
use PKP\facades\Repo;
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
     * @param Affiliation|null $affiliation Affiliation being edited. Pass `null` if creating a new affiliation
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Affiliation::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Affiliation $affiliation, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales)
        );

        // Check required fields if we're adding an affiliation
        ValidatorFactory::required(
            $validator,
            $affiliation,
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

        Hook::call('Affiliation::validate', [&$errors, $affiliation, $props, $allowedLocales, $primaryLocale]);

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
