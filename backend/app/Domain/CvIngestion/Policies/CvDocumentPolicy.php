<?php

namespace App\Domain\CvIngestion\Policies;

use App\Models\CvDocument;
use App\Models\User;

class CvDocumentPolicy
{
    public function view(User $user, CvDocument $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, CvDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, CvDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function download(User $user, CvDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function retry(User $user, CvDocument $document): bool
    {
        return $this->view($user, $document);
    }
}
