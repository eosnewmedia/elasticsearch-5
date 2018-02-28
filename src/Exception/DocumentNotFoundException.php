<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Exception;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class DocumentNotFoundException extends ElasticsearchException
{
    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Document could not be found.',
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
