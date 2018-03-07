<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

use Enm\Elasticsearch\Document\DocumentInterface;
use Enm\Elasticsearch\Exception\DocumentManagerException;
use Enm\Elasticsearch\Exception\DocumentNotFoundException;
use Enm\Elasticsearch\Search\SearchInterface;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface DocumentManagerInterface
{
    /**
     * Creates the elasticsearch index
     * @return void
     */
    public function createIndex(): void;

    /**
     * @param string $className
     * @param array $mapping
     */
    public function registerMapping(string $className, array $mapping): void;

    /**
     * @param string $className
     * @param array $settings
     */
    public function registerSettings(string $className, array $settings): void;

    /**
     * Drops the elasticsearch index
     * @return void
     */
    public function dropIndex(): void;

    /**
     * @param DocumentInterface $document
     * @throws DocumentManagerException
     */
    public function register(DocumentInterface $document): void;

    /**
     * @param string|null $className
     * @param string|null $id
     */
    public function detach(string $className = null, string $id = null): void;

    /**
     * @param DocumentInterface $document
     * @return void
     */
    public function save(DocumentInterface $document): void;

    /**
     * Save all registered documents
     */
    public function saveAll(): void;

    /**
     * @param string $className
     * @param string $id
     *
     * @return void
     */
    public function delete(string $className, string $id): void;

    /**
     * @param string $className
     * @param string $id
     * @param int $retriesOnError
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    public function document(string $className, string $id, int $retriesOnError = 3): DocumentInterface;

    /**
     * @param DocumentInterface $document
     * @param int $retriesOnError
     * @throws DocumentNotFoundException
     */
    public function refreshDocument(DocumentInterface $document, int $retriesOnError = 3): void;

    /**
     * @param string $className
     * @param  SearchInterface $search
     *
     * @return DocumentInterface[]
     */
    public function documents(string $className, SearchInterface $search): array;

    /**
     * @param string $className
     * @param SearchInterface $search
     * @return int
     */
    public function count(string $className, SearchInterface $search): int;
}
