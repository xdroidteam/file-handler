<?php namespace XdroidTeam\FileHandler;

use XdroidTeam\FileHandler\FileHandler;

trait FileHandlerTrait{
    private $filesByType;

    public function getFilesByType(){
        if ($this->filesByType)
            return $this->filesByType;

        return $this->filesByType = FileHandler::getFilesByType(static::$fileGroup, static::$fileTypes, $this->id);
    }

    public function saveFilesByType($request, $enabledFiles = false){
        foreach ($enabledFiles ?: static::$fileTypes as $type) {
            if (!$request->hasFile($type))
                continue;

            $fileHandler = new FileHandler(static::$fileGroup, $type, $this->id);
            if (is_array($request->file($type)))
                $fileHandler->saveFiles($request->file($type));
            else
                $fileHandler->saveFile($request->file($type));
        }

    }

    public function deleteAllFilesByType(){
        FileHandler::deleteAllFilesByType(static::$fileGroup, static::$fileTypes, $this->id);
    }

    public function deleteFilesByType($types){
        FileHandler::deleteFilesByType(static::$fileGroup, $types, $this->id);
    }

    public function saveFile($file, $type, $slug = false){
        $fileHandler = new FileHandler(static::$fileGroup, $type, $this->id);
        $fileHandler->saveFile($file, $slug);
    }

    public function saveFileByContent($type, $content, $originalName, $originalExtension, $isMultiple = true){
        $fileHandler = new FileHandler(static::$fileGroup, $type, $this->id);

        $fileHandler->saveFileByContent($content, $originalName, $originalExtension, $isMultiple);
    }

    public function duplicateGivenFile($types, $newID, $newFileGroup = false){
        foreach (FileHandler::getMultipleTypesFileModels(static::$fileGroup, $types, $this->id) as $file) {
            $type = FileHandler::getTypeFromModel($file);

            $fileHandler = new FileHandler($newFileGroup ?: static::$fileGroup, $type, $newID);
            $fileHandler->duplicate($file);
        }
    }
}
