<?php

namespace App\Contracts;

interface Extension
{
    public function register(ExtensionRegistrar $registrar): void;
}
