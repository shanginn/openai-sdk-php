<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Async\Coroutine;

interface OpenaiClientInterface
{
    public function sendRequest(string $method, string $json): string;

    /**
     * @return Coroutine<string>
     */
    public function sendRequestAsync(string $method, string $json): Coroutine;

    /**
     * @return iterable<string>
     */
    public function streamRequest(string $method, string $json): iterable;
}
