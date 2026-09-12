<?php

namespace App\Contracts;

interface AiProvider
{
    public function id(): string;

    public function supports(string $feature): bool;

    public function generate(AiRequest $request): AiResult;
}
