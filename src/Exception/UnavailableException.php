<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Exception;

use Throwable;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class UnavailableException extends ElasticsearchException
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message = 'Elasticsearch is currently unavailable.',
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
