<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
abstract class AbstractElasticsearchRepository
{
    /**
     * @var DocumentManagerInterface
     */
    private $documentManager;

    /**
     * @param DocumentManagerInterface $documentManager
     */
    public function __construct(DocumentManagerInterface $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    /**
     * @return DocumentManagerInterface
     */
    protected function documentManager(): DocumentManagerInterface
    {
        return $this->documentManager;
    }
}
