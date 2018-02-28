<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

use Enm\Elasticsearch\Document\DocumentInterface;
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
     * @param string $type
     * @param array $mapping
     */
    public function registerMapping(string $type, array $mapping): void;

    /**
     * @param string $type
     * @param array $settings
     */
    public function registerSettings(string $type, array $settings): void;

    /**
     * Drops the elasticsearch index
     * @return void
     */
    public function dropIndex(): void;

    /**
     * @param DocumentInterface $document
     * @return void
     */
    public function save(DocumentInterface $document): void;

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
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    public function document(string $className, string $id): DocumentInterface;

    /**
     * @param DocumentInterface $document
     */
    public function refreshDocument(DocumentInterface $document): void;

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
