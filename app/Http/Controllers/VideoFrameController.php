<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoFrameController extends Controller
{
    public function __invoke(Image $image): StreamedResponse
    {
        return Storage::disk($image->disk)->response($image->path, headers: [
            'Content-Type' => $image->mime_type,
        ]);
    }
}
