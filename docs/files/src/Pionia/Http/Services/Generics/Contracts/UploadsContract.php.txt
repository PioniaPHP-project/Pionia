<?php

namespace Pionia\Http\Services\Generics\Contracts;

use Exception;
use Pionia\Utils\Support;
use Symfony\Component\Filesystem\Filesystem;
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
     * @return string The value to be saved in the db, null or false to skip the file, or an exception to stop the entire request
     * @throws Exception
     */
    protected function defaultUpload(UploadedFile $file, string $fileName): string
    {
        $baseDir = alias(\DIRECTORIES::STORAGE_DIR->name);
        $fileSystem = new FileSystem();
        $settings = env('uploads', ['max_size' => 1024 * 1024 * 2, 'media_dir' => 'media', 'media_url' => 'media']);
        $size = $file->getSize();
        if (isset($settings['max_size']) && $size > $settings['max_size']) {
            throw new Exception("File size exceeds the maximum allowed size for $fileName, $size > {$settings['max_size']}");
        }
        if (!isset($settings['media_dir'])) {
            throw new Exception('`media_dir` not set in the upload settings.');
        }
        $mediaDir= $settings['media_dir'];
        $mediaUrl= $settings['media_url']?? '/media';
        $extension = $file->guessClientExtension();
        $fileName = Support::slugify(str_replace($extension, '', trim($file->getClientOriginalName()))). '.' . $extension;
        if (str_starts_with($mediaDir, '/')){
            $mediaDir = substr($mediaDir, 1);
        }
        if (!str_ends_with($baseDir, '/')){
            $baseDir = $baseDir . '/';
        }
        $fullPath = $baseDir . $mediaDir;
        $fileNameToSave = $fullPath.DIRECTORY_SEPARATOR. $fileName;
        if ($fileSystem->exists($fileNameToSave)) {
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
