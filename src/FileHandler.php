<?php namespace XdroidTeam\FileHandler;

use File,
    Storage,
    XdroidTeam\FileHandler\File as FileModel;

class FileHandler{

    public $group;
    public $type;
    public $id;
    public $baseName;
    public $multipleID;
    public $multiple;

    public function __construct($group, $type, $id){
        $this->group = $group;
        $this->type = $type;
        if(is_numeric($id)){
            $this->id = $id;
        } else {
            $fileModel = FileModel::where('slug', '=', $id)->first();
            if($fileModel){
                $this->id = explode('_', $fileModel->filename)[0];
            }
        }
    }


    public function setMultipleID($multipleID){
        $this->multipleID = $multipleID;
        return $this;
    }

    public function getPath(){
        return storage_path('uploads/' . $this->group . '/' . $this->id . '/');
    }

    public function getFileName($forModel = false){
        $fileName = $this->id . '_' . $this->type;

        if ($this->multipleID && !$forModel)
            $fileName .= '_' . $this->multipleID;

        return $fileName;
    }

    public function saveFile($inputFile, $slug = false){
        if (!File::isDirectory($path = $this->getPath()))
            static::makeDirectory($path);

        $extension = $inputFile->getClientOriginalExtension();
        $originalName = $inputFile->getClientOriginalName();

        $file = $this->getNewFileModel();

        $file->extension = $extension;
        $file->original_filename = $originalName;
        if($slug !== false){
            $file->slug = $slug;
        }
        $file->save();

        $inputFile->move($this->getPath(), $this->getFileName() . '.' . $extension);
    }

    public function saveFileByContent($content, $originalName, $originalExtension, $isMultiple){
        if($isMultiple){
            $this->multipleID = $this->getMaxMultipleID() + 1;
        }

        if (!File::isDirectory($path = $this->getPath()))
            static::makeDirectory($path);

        $file = $this->getNewFileModel();
        
        $file->extension = $originalExtension;
        $file->original_filename = $originalName;
        $file->save();

        File::put($this->getPath() . $this->getFileName() . '.' . $originalExtension, $content);
    }

    public function saveFileFromStream($inputStream, $originalName, $extension){
        if (!File::isDirectory($path = $this->getPath()))
            static::makeDirectory($path);

        $file = $this->getNewFileModel();

        $file->extension = $extension;
        $file->original_filename = $originalName;
        $file->save();

        Storage::disk('server_root')->getDriver()->putStream($this->getPath() . $this->getFileName() . '.' . $extension, $inputStream);
    }

    public function duplicate($oldFile){
        if (!File::isDirectory($path = $this->getPath()))
            static::makeDirectory($path);

        $extension = $oldFile->extension;
        $originalName = $oldFile->original_filename;
        $this->setMultipleID($oldFile->multiple_id);

        $file = $this->getNewFileModel();

        $file->extension = $extension;
        $file->original_filename = $originalName;
        $file->save();

        File::copy($oldFile->path . $oldFile->filename . ($oldFile->multiple_id ? '_' . $oldFile->multiple_id : '') . '.' . $oldFile->extension,
                    $this->getPath() . $this->getFileName() . '.' . $extension);
    }

    public function saveFiles($inputFiles){
        $this->multipleID = $this->getMaxMultipleID() + 1;

        foreach ($inputFiles as $inputFile) {
            if(!$inputFile)
                continue;

            $this->saveFile($inputFile);
            $this->multipleID++;
        }
    }

    public function delete(){
        $file = $this->getExistingFileModel();

        if (!$file || !File::exists($existingFile = $this->getPath() . $this->getFileName() . '.' . $file->extension))
            return;

        unlink($existingFile);
        $file->delete();
    }

    public function downloadFile(){
        $file = $this->getExistingFileModel();
        if (!$file)
            return response('File not found.', 404);

        $fullPath = $this->getFullPath();

        if (File::exists($fullPath))
            return response()->download($fullPath, $file->original_filename);
        else
            return response('File not found.', 404);
    }

    public function getFullPath(){
        $file = $this->getExistingFileModel();

        if (!$file)
            return response('File not found.', 404);

        return  $this->getPath() . $this->getFileName() . '.' . $file->extension;
    }

    //FileModel functions
    public function getNewFileModel(){
        $file = FileModel::firstOrNew([
            'path' => $this->getPath(),
            'filename' => $this->getFileName(true),
            'multiple_id' => $this->multipleID
        ]);

        if ($file->exists && File::exists($existingFile = $this->getPath() . $this->getFileName() . '.' . $file->extension))
            unlink($existingFile);

        return $file;
    }

    public function getExistingFileModel(){
        $file = FileModel::where('path', '=', $this->getPath())
                ->where('filename', '=', $this->getFileName(true));

        if ($this->multipleID)
            $file->where('multiple_id', '=', $this->multipleID);

        return $file->first();
    }

    public function getMaxMultipleID(){
        return FileModel::where('path', '=', $this->getPath())
                            ->where('filename', '=', $this->getFileName(true))
                            ->max('multiple_id');
    }

    public static function getFilesByType($group, $types, $id){
        $fileList = [];
        foreach ($types as $type)
            $fileList[$type] = [];

        foreach (static::getMultipleTypesFileModels($group, $types, $id) as $file) {
            $type = static::getTypeFromModel($file);
            if($file->slug){
                $url = $group . '/' . $type . '/' . $file->slug;
            } else {
                $url = $group . '/' . $type . '/' . $id . '/' . $file->multiple_id;
            }
            $fileList[$type][] =  [
                'url' => $url,
                'filename' => $file->original_filename,
            ];
        }

        return $fileList;
    }

    public static function makeDirectory($directory){
        if (File::isDirectory($directory))
            return 0;
        $oldmask = umask(0);
        mkdir( $directory , 0777, true );
        umask($oldmask);
    }

    public static function deleteAllFilesByType($group, $types, $id){
        $fileHandler = static::deleteFilesByType($group, $types, $id);

        if ($fileHandler)
            rmdir($fileHandler->getPath());
    }

    public static function deleteFilesByType($group, $types, $id){
        $fileHandler = false;
        foreach (static::getMultipleTypesFileModels($group, $types, $id) as $file) {
            $type = static::getTypeFromModel($file);

            $fileHandler = new FileHandler($group, $type, $id);
            $fileHandler->setMultipleID($file->multiple_id);
            $fileHandler->delete();
        }

        return $fileHandler;
    }


    public static function getMultipleTypesFileModels($group, $types, $id){
        $files = FileModel::select('*');
        foreach ($types as $type) {
            $fileHandler = new FileHandler($group, $type, $id);
            $files->orWhere(function($subQuery) use ($fileHandler){
                $subQuery->where('path', '=', $fileHandler->getPath())
                        ->where('filename', '=', $fileHandler->getFileName(true));
            });
        }
        return $files->get();
    }

    public static function getTypeFromModel($file){
        $typeSegments = explode('_', $file->filename);
        unset($typeSegments[0]);

        return implode('_', $typeSegments);
    }
}
