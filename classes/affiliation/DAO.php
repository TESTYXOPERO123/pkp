<?php
/**
 * @file classes/affiliation/DAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\affiliation\DAO
 *
 * @ingroup affiliation
 *
 * @see Affiliation
 *
 * @brief Read and write affiliation cache to the database.
 */

namespace PKP\affiliation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of Affiliation
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_AFFILIATION;

    /** @copydoc EntityDAO::$table */
    public $table = 'author_affiliations';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'author_affiliation_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'author_affiliation_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'author_affiliation_id',
        'authorId' => 'author_id',
        'ror' => 'ror'
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'author_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Affiliation
    {
        return App::make(Affiliation::class);
    }

    /**
     * Get the number of RORs matching the configured query
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
            ->select('r.' . $this->primaryKeyColumn)
            ->pluck('r.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of rors matching the configured query
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
                yield $row->author_affiliation_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Affiliation
    {
        return parent::fromRow($row);
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Affiliation $row): int
    {
        return parent::_insert($row);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Affiliation $row): void
    {
        parent::_update($row);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Affiliation $row): void
    {
        parent::_delete($row);
    }

    /**
     * Save affiliations.
     *
     * @param int $authorId
     * @param array $affiliations [Affiliation, Affiliation, ...]
     *
     * @return void
     */
    public function saveAffiliations(int $authorId, array $affiliations): void
    {
        error_log(json_encode($affiliations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
