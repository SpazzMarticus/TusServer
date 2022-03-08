<?php

namespace SpazzMarticus\Tus\Services;

use Psr\Http\Message\RequestInterface;

class MetadataService
{
    /**
     * Extract metadata-arry from request
     * @see https://tus.io/protocols/resumable-upload.html#upload-metadata
     */
    public function getMetadata(RequestInterface $request): array
    {
        $metadata = [];

        if ($metadataHeader = $request->getHeaderLine('Upload-Metadata')) {
            foreach (explode(',', $metadataHeader) as $keyValuePair) {
                $keyValuePair = explode(' ', $keyValuePair);
                if (!isset($keyValuePair[0])) {
                    continue;
                }
                $metadata[$keyValuePair[0]] = isset($keyValuePair[1]) ? base64_decode($keyValuePair[1]) : null;
            }
        }

        return $metadata;
    }
}
