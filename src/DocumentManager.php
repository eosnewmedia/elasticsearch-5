<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Enm\Elasticsearch\Document\DocumentInterface;
use Enm\Elasticsearch\Exception\DocumentNotFoundException;
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
     * @var DocumentInterface[]
     */
    private $documents = [];

    /**
     * @var string[][]
     */
    private $collections = [];

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
     * @return DocumentInterface
     */
    protected function register(DocumentInterface $document): DocumentInterface
    {
        if (!\array_key_exists(\get_class($document), $this->documents)) {
            $this->documents[\get_class($document)] = [];
        }
        if (!\array_key_exists($document->getId(), (array)$this->documents[\get_class($document)])) {
            $this->documents[\get_class($document)][$document->getId()] = $document;
        }

        return $this->documents[\get_class($document)][$document->getId()];
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
        if (!\array_key_exists($id, (array)$this->documents[$className])) {
            throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
        }

        return $this->documents[$className][$id];
    }

    /**
     * @param string $type
     * @param array $mapping
     */
    public function registerMapping(string $type, array $mapping): void
    {
        $this->mappings[$type] = $mapping;
    }

    /**
     * @param string $type
     * @param array $settings
     */
    public function registerSettings(string $type, array $settings): void
    {
        $this->settings[$type] = $settings;
    }

    public function createIndex(): void
    {
        foreach ($this->mappings as $class => $mapping) {
            try {
                $type = $this->type($class);
                $this->elasticsearch()->indices()->create(
                    [
                        'index' => $this->indexName($type),
                        'body' => [
                            'settings' => array_key_exists($class, $this->settings) ? $this->settings[$class] : [],
                            'mappings' => [
                                $type => [$mapping]
                            ],
                        ]
                    ]
                );
            } catch (\Throwable $e) {

            }
        }
    }

    public function dropIndex(): void
    {
        foreach ($this->mappings as $class => $mapping) {
            $this->elasticsearch()->indices()->delete(['index' => $this->indexName($this->type($class))]);
        }
    }

    /**
     * @param DocumentInterface $document
     */
    public function save(DocumentInterface $document): void
    {
        $document = $this->register($document);
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

        foreach ($this->collections as $key => $collection) {
            if (\in_array($id, $collection, true)) {
                unset($this->collections[$key]);
            }
        }

        if (!\array_key_exists($className, $this->documents)) {
            return;
        }
        if (!\array_key_exists($id, (array)$this->documents[$className])) {
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
    public function document(string $className, string $id): DocumentInterface
    {
        try {
            return $this->retrieve($className, $id);
        } catch (DocumentNotFoundException $e) {
            try {
                $response = $this->fetchDocument($className, $id);

                if (!array_key_exists('_source', $response) || !$response['found']) {
                    throw new \InvalidArgumentException('Not found.');
                }

                return $this->createDocument($className, $id, $response);
            } catch (\Exception $e) {
                throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
            }
        }
    }

    /**
     * @param DocumentInterface $document
     * @throws DocumentNotFoundException
     */
    public function refreshDocument(DocumentInterface $document): void
    {
        try {
            $response = $this->fetchDocument(\get_class($document), $document->getId());

            if (!array_key_exists('_source', $response) || !$response['found']) {
                throw new \InvalidArgumentException('Not found.');
            }

            $document->buildFromSource($response['_source']);
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
        $collection = sha1($className . '::' . serialize($search));
        if (!array_key_exists($collection, $this->collections)) {
            $this->collections[$collection] = [];

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

            foreach ((array)$response['hits']['hits'] as $hit) {
                try {
                    $this->createDocument($className, $hit['_id'], $hit);
                    $this->collections[$collection][] = $hit['_id'];
                } catch (\Exception $e) {

                }
            }
        }

        $documents = [];
        foreach ($this->collections[$collection] as $id) {
            try {
                $documents[] = $this->retrieve($className, $id);
            } catch (DocumentNotFoundException $e) {

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
     * @throws \InvalidArgumentException
     */
    protected function createDocument(string $className, string $id, $data): DocumentInterface
    {
        /** @var DocumentInterface $document */
        $document = new $className($id);
        /** @var DocumentInterface $document */
        $document = $this->register($document);
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
