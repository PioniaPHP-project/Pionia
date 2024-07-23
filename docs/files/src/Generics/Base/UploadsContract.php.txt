<?php

namespace Pionia\Generics\Base;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

trait UploadsContract
{

    /**
     * This hook will receive every file found in your request.
     * Whatever you return here is what we shall send to the db.
     *
     * If you to want to avoid the rest of the processing, just return false or null or throw and exception
     * @param FileBag|UploadedFile $file The file object to work with
     * @param string $fileName The name as defined in the request
     * @return mixed
     */
    public function handleUpload(FileBag | UploadedFile $file, string $fileName): mixed
    {
        return null;
    }
}
