<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Search;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface SearchInterface
{
    /**
     * @return int
     */
    public function getFrom(): int;

    /**
     * @return int
     */
    public function getSize(): int;

    /**
     * @return array
     */
    public function getQuery(): array;

    /**
     * @return array
     */
    public function getSorting(): array;
}
