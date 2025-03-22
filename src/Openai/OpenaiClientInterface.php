<?php

declare(strict_types=1);

namespace App\Openai\Openai;

interface OpenaiClientInterface
{
    public function sendRequest(string $method, string $json): string;
}
