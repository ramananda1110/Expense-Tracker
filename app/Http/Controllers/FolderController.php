<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Folder;
use Illuminate\Support\Facades\Log;

class FolderController extends Controller
{


    public function importFolders()
    {
       // $directoryPath = '/Users/sarkar/Documents/Panchagarh_XEN';

        $directoryPath = '/home/ramananda/Documents/LGD/Validated Certificates (Panchagarh)/Panchagarh_XEN';


        if (!file_exists($directoryPath)) {
            return response()->json(['error' => 'Directory does not exist'], 404);
        }

        if (!is_readable($directoryPath)) {
            return response()->json(['error' => 'Directory is not readable. Check folder permissions.'], 403);
        }

        // Get only folder names
        $folders = array_filter(scandir($directoryPath), function ($item) use ($directoryPath) {
            return is_dir($directoryPath . DIRECTORY_SEPARATOR . $item) && !in_array($item, ['.', '..']);
        });

        // Store folder names in the database
        foreach ($folders as $folderName) {
            try {
                // Ensure folder name is treated as a string
                $folderName = (string) $folderName;
                Folder::updateOrCreate(['name' => $folderName]);
            } catch (\Exception $e) {
                Log::error("Error while inserting folder: {$folderName} - {$e->getMessage()}");
                return response()->json(['error' => 'Failed to import folder: ' . $folderName], 500);
            }
        }

        return response()->json([
            'message' => 'Folders imported successfully',
            'folders' => array_values($folders)
        ]);
    }

    
    public function getFolders()
    {
        return response()->json(Folder::all());
    }
}
