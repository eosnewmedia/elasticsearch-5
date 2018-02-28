<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Search;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class Search implements SearchInterface
{
    /**
     * @var int
     */
    private $from;

    /**
     * @var int
     */
    private $size;

    /**
     * @var array
     */
    private $query;

    /**
     * @var array
     */
    private $sorting;

    /**
     * @param array $query
     * @param array $sorting
     * @param int $from
     * @param int $size
     */
    public function __construct(array $query = [], array $sorting = [], int $from = 0, int $size = 10000)
    {
        $this->from = $from;
        $this->size = $size;
        $this->query = $query;
        $this->sorting = $sorting;
    }

    /**
     * @return int
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function getSorting(): array
    {
        return $this->sorting;
    }
}
