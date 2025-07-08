<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

if (!function_exists('getEmbeddedImageSource')) {
    function getEmbeddedImageSource($imagePath) {
        if (!$imagePath) return '';

        try {
            if (Storage::disk('public')->exists($imagePath)) {
                $fileContents = Storage::disk('public')->get($imagePath);
                $mimeType = Storage::disk('public')->mimeType($imagePath);
                return 'data:' . $mimeType . ';base64,' . base64_encode($fileContents);
            }
        } catch (Exception $e) {
            Log::error('Email Image Embedding Failed: ' . $e->getMessage(), ['path' => $imagePath]);
        }

        return '';
    }
}
