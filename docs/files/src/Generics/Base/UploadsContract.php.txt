<?php

namespace Pionia\Generics\Base;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Pionia\Core\Pionia;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait UploadsContract
{

    /**
     * This hook will receive every file found in your request and you can handle it as you wish.
     * Whatever you return here is what we shall send to the db.
     * Returning null or false will skip the file in the entire request from being saved in the db.
     *
     * @param UploadedFile $file The file object that was uploaded.
     * @param string $fileName The name as defined in the request.
     * @return mixed The value to be saved in the db, null or false to skip the file, or an exception to stop the entire request
     */
    abstract public function handleUpload(UploadedFile $file, string $fileName): mixed;

    /**
     * This hook will receive every file found in your request and you can handle it as you wish.
     * Whatever you return here is what we shall send to the db.
     * Returning null or false will skip the file in the entire request from being saved in the db.
     *
     * @param UploadedFile $file The file object that was uploaded.
     * @param string $fileName The name as defined in the request.
     * @return mixed The value to be saved in the db, null or false to skip the file, or an exception to stop the entire request
     * @throws Exception
     */
    protected function defaultUpload(UploadedFile $file, string $fileName): mixed
    {
        $fileSystem = new FileSystem();
        $settings = pionia::getUploadSettings();
        $size = $file->getSize();
        if (isset($settings['max_size']) && $size > $settings['max_size']) {
            throw new Exception("File size exceeds the maximum allowed size for $fileName, $size > {$settings['max_size']}");
        }
        if (!isset($settings['media_dir'])) {
            throw new Exception('`media_dir` not set in the upload settings.');
        }
        $mediaDir= $settings['media_dir'];
        $mediaUrl= $settings['media_url']?? '/media';
        $fileName = $file->getClientOriginalName();
        if (str_starts_with($mediaDir, '/')){
            $mediaDir = substr($mediaDir, 1);
        }
        $baseDir = BASEPATH;
        if (!str_ends_with($baseDir, '/')){
            $baseDir = $baseDir . '/';
        }
        $fullPath = $baseDir . $mediaDir;
        if ($fileSystem->exists($fullPath. '/'. $fileName)) {
            $fileName = time() . '_' . $fileName;
        }
        $file->move($fullPath, $fileName);
        if (!str_starts_with($mediaUrl, '/')){
            $mediaUrl = '/'. $mediaUrl;
        }
        if (!str_ends_with($mediaUrl, '/')){
            $mediaUrl = $mediaUrl . '/';
        }
         return $mediaUrl . $fileName;
    }
}
