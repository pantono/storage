<?php

namespace Pantono\Storage\Helper;

use Symfony\Component\Mime\MimeTypes;

class MimeTypeHelper
{
    public static function guessMimeType(string $filename, ?string $fileData = null): ?string
    {
        if ($fileData !== null) {
            $fInfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $fInfo->buffer($fileData);
            if ($mime) {
                return $mime;
            }
        }

        $types = new MimeTypes();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (!$extension) {
            return null;
        }
        $result = $types->getMimeTypes($extension);
        if (sizeof($result) == 0) {
            return null;
        }
        return $result[0];
    }
}
