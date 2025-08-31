<?php

namespace App\Actions\Images;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\DB;

class ReprocessImageAction
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {}

    public function execute(Image $image): Image
    {
        return DB::transaction(function () use ($image) {
            return $this->imageUploadService->reprocessImage($image);
        });
    }
}