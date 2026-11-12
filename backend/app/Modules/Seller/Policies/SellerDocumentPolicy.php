<?php

namespace App\Modules\Seller\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\SellerDocument;

class SellerDocumentPolicy
{
    public function view(User $user, SellerDocument $document): bool
    {
        return $document->seller?->user_id === $user->getKey()
            || $user->can('manage_sellers');
    }

    public function download(User $user, SellerDocument $document): bool
    {
        return $this->view($user, $document);
    }
}
