<?php

namespace App\Http\Requests;

class UpdatePlayerStartingInventoryRequest extends StartingInventoryRequest
{
    protected function allowsUnlimited(): bool
    {
        return false;
    }

    protected function itemFlag(): ?string
    {
        return null;
    }
}
