<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Enm\Elasticsearch\Document\DocumentInterface;
use Enm\Elasticsearch\Exception\DocumentManagerException;
use Enm\Elasticsearch\Exception\DocumentNotFoundException;
use Enm\Elasticsearch\Exception\ElasticsearchException;
use Enm\Elasticsearch\Exception\UnavailableException;
use Enm\Elasticsearch\Search\SearchInterface;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class DocumentManager implements DocumentManagerInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $baseIndex;

    /**
     * @var array
     */
    private $settings = [];

    /**
     * @var array
     */
    private $mappings = [];

    /**
     * @var DocumentInterface[][]
     */
    private $documents = [];

    /**
     * @param string $index
     * @param string $host
     */
    public function __construct(string $index, string $host)
    {
        $this->baseIndex = $index;
        $this->client = ClientBuilder::create()->setHosts([$host])->build();
    }

    /**
     * @return Client
     */
    protected function elasticsearch(): Client
    {
        return $this->client;
    }

    /**
     * @param string $type
     * @return string
     */
    protected function indexName(string $type): string
    {
        return $this->baseIndex . '__' . strtolower($type);
    }

    /**
     * @param string $className
     * @return string
     */
    protected function type(string $className): string
    {
        $classParts = explode('\\', $className);
        return lcfirst((string)array_pop($classParts));
    }

    /**
     * @param DocumentInterface $document
     * @throws DocumentManagerException
     */
    public function register(DocumentInterface $document): void
    {
        if (!\array_key_exists(\get_class($document), $this->documents)) {
            $this->documents[\get_class($document)] = [];
        }

        $documentExists = \array_key_exists($document->getId(), $this->documents[\get_class($document)]);
        if ($documentExists && $this->documents[\get_class($document)][$document->getId()] !== $document) {
            throw new DocumentManagerException('Document with same id already registered.');
        }

        $this->documents[\get_class($document)][$document->getId()] = $document;
    }

    /**
     * @param string|null $className
     * @param string|null $id
     */
    public function detach(string $className = null, string $id = null): void
    {
        if (!$className) {
            $this->documents = [];

            return;
        }

        if (!$id) {
            if (\array_key_exists($className, $this->documents)) {
                unset($this->documents[$className]);
            }
            return;
        }

        if (!\array_key_exists($className, $this->documents)) {
            return;
        }

        if (!\array_key_exists($id, $this->documents[$className])) {
            return;
        }

        unset($this->documents[$className][$id]);
    }

    /**
     * @param string $className
     * @param string $id
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    protected function retrieve(string $className, string $id): DocumentInterface
    {
        if (!\array_key_exists($className, $this->documents)) {
            throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
        }
        if (!\array_key_exists($id, $this->documents[$className])) {
            throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
        }

        return $this->documents[$className][$id];
    }

    /**
     * @param string $className
     * @param array $mapping
     */
    public function registerMapping(string $className, array $mapping): void
    {
        $this->mappings[$this->type($className)] = $mapping;
    }

    /**
     * @param string $className
     * @param array $settings
     */
    public function registerSettings(string $className, array $settings): void
    {
        $this->settings[$this->type($className)] = $settings;
    }

    /**
     * Creates the elasticsearch index
     * @return void
     */
    public function createIndex(): void
    {
        foreach ($this->mappings as $type => $mapping) {
            $body = [
                'mappings' => [
                    $type => (array)$mapping
                ]
            ];

            if (array_key_exists($type, $this->settings)) {
                $body['settings'] = (array)$this->settings[$type];
            }

            $this->elasticsearch()->indices()->create(
                [
                    'index' => $this->indexName($type),
                    'body' => $body
                ]
            );
        }
    }

    /**
     * Drops the elasticsearch index
     * @return void
     */
    public function dropIndex(): void
    {
        foreach ($this->mappings as $class => $mapping) {
            $this->elasticsearch()->indices()->delete(['index' => $this->indexName($this->type($class))]);
        }
    }

    /**
     * @param DocumentInterface $document
     * @throws ElasticsearchException
     */
    public function save(DocumentInterface $document): void
    {
        $this->register($document);
        $type = $this->type(\get_class($document));
        $this->elasticsearch()->index(
            [
                'index' => $this->indexName($type),
                'type' => $type,
                'id' => $document->getId(),
                'body' => $document->getStorable()
            ]
        );
    }

    /**
     * Save all registered documents
     * @throws ElasticsearchException
     */
    public function saveAll(): void
    {
        foreach ($this->documents as $documents) {
            foreach ($documents as $document) {
                $this->save($document);
            }
        }
    }

    /**
     * @param string $className
     * @param string $id
     */
    public function delete(string $className, string $id): void
    {
        try {
            $type = $this->type($className);
            $this->elasticsearch()->delete(
                [
                    'index' => $this->indexName($type),
                    'type' => $type,
                    'id' => $id
                ]
            );
        } catch (\Exception $e) {

        }

        $this->detach($className, $id);
    }

    /**
     * @param string $className
     * @param string $id
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    public function document(string $className, string $id): DocumentInterface
    {
        try {
            return $this->retrieve($className, $id);
        } catch (DocumentNotFoundException $e) {
            try {
                $response = $this->fetchDocument($className, $id);

                return $this->buildDocument($className, $id, $response);
            } catch (\Exception $e) {
                throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
            }
        }
    }

    /**
     * @param DocumentInterface $document
     * @throws DocumentNotFoundException|DocumentManagerException
     */
    public function refreshDocument(DocumentInterface $document): void
    {
        try {
            $retrieved = $this->retrieve(\get_class($document), $document->getId());
            if ($retrieved !== $document) {
                throw new DocumentManagerException('Document is not managed by this document manager!');
            }

            $response = $this->fetchDocument(\get_class($document), $document->getId());

            $this->buildDocument(\get_class($document), $document->getId(), $response);
        } catch (\Exception $e) {
            throw new DocumentNotFoundException(\get_class($document) . ' ' . $document->getId() . ' not found.');
        }
    }

    /**
     * @param string $className
     * @param SearchInterface $search
     * @return array
     */
    public function documents(string $className, SearchInterface $search): array
    {
        $body = [];
        $body['from'] = $search->getFrom();
        $body['size'] = $search->getSize();
        if (\count($search->getQuery()) > 0) {
            $body['query'] = $search->getQuery();
        }
        if (\count($search->getSorting()) > 0) {
            $body['sort'] = $search->getSorting();
        }

        $type = $this->type($className);
        $response = $this->elasticsearch()->search([
                'index' => $this->indexName($type),
                'type' => $type,
                'body' => $body
            ]
        );

        if ($response['hits']['total'] === 0) {
            return [];
        }

        $documents = [];
        foreach ((array)$response['hits']['hits'] as $hit) {
            try {
                $documents[] = $this->buildDocument($className, $hit['_id'], $hit);
            } catch (\Exception $e) {

            }
        }

        return $documents;
    }

    /**
     * @param string $className
     * @param SearchInterface $search
     * @return int
     */
    public function count(string $className, SearchInterface $search): int
    {
        $body = [];
        if (\count($search->getQuery()) > 0) {
            $body['query'] = $search->getQuery();
        }

        $type = $this->type($className);
        $count = (int)$this->elasticsearch()->count(
            [
                'index' => $this->indexName($type),
                'type' => $type,
                'body' => $body
            ]
        )['count'];

        return $count;
    }

    /**
     * @param string $className
     * @param string $id
     * @param $data
     *
     * @return DocumentInterface
     * @throws DocumentManagerException|DocumentNotFoundException
     */
    protected function buildDocument(string $className, string $id, $data): DocumentInterface
    {
        try {
            $document = $this->retrieve($className, $id);
        } catch (\Exception $e) {
            /** @var DocumentInterface $document */
            $document = new $className($id);
            $this->register($document);
        }
        if (!array_key_exists('_source', $data)) {
            throw new DocumentNotFoundException('Invalid data: "_source" not available!');
        }
        $document->buildFromSource($data['_source']);

        return $document;
    }

    /**
     * @param string $className
     * @param string $id
     * @return array
     * @throws UnavailableException
     */
    protected function fetchDocument(string $className, string $id): array
    {
        // try 3 times, because elasticsearch may be down for short times...
        for ($i = 0; $i < 3; $i++) {
            try {
                $type = $this->type($className);
                $response = $this->elasticsearch()->get(
                    [
                        'index' => $this->indexName($type),
                        'type' => $type,
                        'id' => $id
                    ]
                );

                return $response;
            } catch (\Exception $e) {
                sleep(($i + 1) * $i);
            }
        }

        throw new UnavailableException();
    }
}
