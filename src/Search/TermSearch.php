<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Search;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class TermSearch extends Search
{
    /**
     * @param string $key
     * @param mixed $value
     * @param int $from
     * @param int $size
     */
    public function __construct(string $key, $value, int $from = 0, int $size = 10000)
    {
        parent::__construct(
            [
                'term' => [$key => $value]
            ],
            [],
            $from,
            $size
        );
    }
}
