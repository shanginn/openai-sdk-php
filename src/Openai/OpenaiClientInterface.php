<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

interface OpenaiClientInterface
{
    public function sendRequest(string $method, string $json): string;
}
