<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Document;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface DocumentInterface
{
    /**
     * @param string $id
     */
    public function __construct(string $id);

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param array $source
     * @return void
     */
    public function buildFromSource(array $source): void;

    /**
     * @return array
     */
    public function getStorable(): array;
}
