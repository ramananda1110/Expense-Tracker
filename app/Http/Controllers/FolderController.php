<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Folder;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;


class FolderController extends Controller
{


    // public function importFolders()
    // {
    //    // $directoryPath = '/Users/sarkar/Documents/Panchagarh_XEN';

    //     $directoryPath = '/home/ramananda/Documents/LGD/Validated Certificates (Panchagarh)/Panchagarh_XEN';


    //     if (!file_exists($directoryPath)) {
    //         return response()->json(['error' => 'Directory does not exist'], 404);
    //     }

    //     if (!is_readable($directoryPath)) {
    //         return response()->json(['error' => 'Directory is not readable. Check folder permissions.'], 403);
    //     }

    //     // Get only folder names
    //     $folders = array_filter(scandir($directoryPath), function ($item) use ($directoryPath) {
    //         return is_dir($directoryPath . DIRECTORY_SEPARATOR . $item) && !in_array($item, ['.', '..']);
    //     });

    //     // Store folder names in the database
    //     foreach ($folders as $folderName) {
    //         try {
    //             // Ensure folder name is treated as a string
    //             $folderName = (string) $folderName;
    //             Folder::updateOrCreate(['name' => $folderName]);
    //         } catch (\Exception $e) {
    //             Log::error("Error while inserting folder: {$folderName} - {$e->getMessage()}");
    //             return response()->json(['error' => 'Failed to import folder: ' . $folderName], 500);
    //         }
    //     }

    //     return response()->json([
    //         'message' => 'Folders imported successfully',
    //         'folders' => array_values($folders)
    //     ]);
    // }


    public function getFolders()
    {
        return response()->json(Folder::all());
    }


    public function importFolders()
    {
        $directoryPath = '/home/ramananda/Documents/LGD-2/Panchagarh_XEN';
        
        // Check if the directory exists
        if (!file_exists($directoryPath)) {
            return response()->json(['error' => 'Directory does not exist'], 404);
        }
        
        // Check if the directory is readable
        if (!is_readable($directoryPath)) {
            return response()->json(['error' => 'Directory is not readable. Check folder permissions.'], 403);
        }
        
        // Get all subfolders in the directory
        $folders = array_filter(scandir($directoryPath), function ($item) use ($directoryPath) {
            return is_dir($directoryPath . DIRECTORY_SEPARATOR . $item) && !in_array($item, ['.', '..']);
        });
        
        // Ensure there's at least one folder
        if (empty($folders)) {
            return response()->json(['error' => 'No subfolders found in the specified directory'], 404);
        }
        
        foreach ($folders as $folderName) {
            $folderPath = $directoryPath . DIRECTORY_SEPARATOR . $folderName;
            $pdfFiles = glob($folderPath . '/*.pdf');
            
            if (empty($pdfFiles)) {
                Log::warning("No PDF files found in folder: {$folderName}");
                continue; // Skip empty folders
            }
    
            $totalIpcCount = 0;
            $completionStatus = ''; // Default status
            $datesChecked = []; // To track dates already counted for the same tender ID
        
            foreach ($pdfFiles as $pdfFile) {
                try {
                    // Extract text from PDF
                    $text = Pdf::getText($pdfFile);
        
                    // Normalize the text to remove newlines and extra spaces
                    $normalizedText = preg_replace('/\s+/', ' ', $text);
        
                    // Debugging: Log the normalized text (you can adjust the length if needed)
                    Log::info("Normalized Text from PDF in folder {$folderName}: " . substr($normalizedText, 0, 1000));
        
                    // Check if "Completion Status" is present
                    if (preg_match('/Completion\s+Status\s*[:\-]?\s*([A-Za-z\s]+)/i', $normalizedText, $statusMatch)) {
                        $completionStatus = trim($statusMatch[1]);
                        // Clean up the status string to remove any unwanted text after the status
                        $completionStatus = preg_replace('/\s*Package\s*No.*/i', '', $completionStatus); // Remove anything after 'Package No'
                        Log::info("Completion Status Found in folder {$folderName}: " . $completionStatus);
                        // Skip counting IPCs if Completion Status is found
                        continue;
                    }
        
                    // Log the raw text to verify if IPC numbers exist
                    Log::info("Checking IPC Numbers in folder {$folderName}: " . $normalizedText);
        
                    // Extract IPC count using a more precise regex pattern for both formats
                    if (preg_match_all('/IPC\s*No\.\s*(\d+)[^\d]/', $normalizedText, $ipcMatches)) {
                        Log::info("Found IPC Matches in folder {$folderName}: " . implode(", ", $ipcMatches[0]));
        
                        $date = $this->extractDateFromText($normalizedText);
                        Log::info("Date extracted from folder {$folderName}: " . $date);
        
                        // Ensure the date is not counted twice for the same tender ID
                        if (!in_array($date, $datesChecked)) {
                            $totalIpcCount += count($ipcMatches[0]);
                            $datesChecked[] = $date;
                            Log::info("IPC Count Found in folder {$folderName}: " . count($ipcMatches[0]) . " for date " . $date);
                        } else {
                            Log::info("Skipping IPC for duplicate date {$date} in folder {$folderName}");
                        }
                    } else {
                        Log::info("No IPC-related information found in folder {$folderName}");
                    }
        
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Error reading PDF: ' . $e->getMessage()], 500);
                }
            }
        
            // Store or update the folder with the extracted IPC count and completion status
            Folder::updateOrCreate(
                ['name' => $folderName],
                ['ipc_count' => $totalIpcCount, 'completion_status' => $completionStatus]
            );
        }
        
        return response()->json([
            'message' => 'Folders and IPC counts imported successfully',
        ]);
    }
    
    // Method to extract date from the text with better handling of newline and space issues
    private function extractDateFromText($text)
    {
        // Normalize the text by replacing newlines with a single space
        $text = preg_replace('/\n/', ' ', $text); // Replace all newlines with a space
        
        // Remove extra spaces before and after the date for accurate matching
        $text = preg_replace('/\s+/', ' ', $text); // Replace multiple spaces with a single space
        
        // Regex to match dates like "17-Apr-2018" or "17- Apr - 2018" or "17-Apr\n2018"
        if (preg_match('/\d{1,2}[-]?\s*[A-Za-z]{3}[-]?\s*\d{4}/', $text, $dateMatch)) {
            return $dateMatch[0]; // Return the matched date
        }
        
        return ''; // Return empty if no date is found
    }
    
   

}