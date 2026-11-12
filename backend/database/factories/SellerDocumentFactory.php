<?php

namespace Database\Factories;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Modules\Seller\Models\Seller;
use App\Modules\Seller\Models\SellerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SellerDocument>
 */
class SellerDocumentFactory extends Factory
{
    protected $model = SellerDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'type' => SellerDocumentType::Ktp,
            'file_path' => 'seller-documents/'.Str::uuid().'.pdf',
            'file_hash' => hash('sha256', Str::uuid()->toString()),
            'status' => SellerDocumentStatus::Pending,
            'reviewed_at' => null,
        ];
    }
}
