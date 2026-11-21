<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Http\Requests\ChatRequest;
use App\Modules\Chat\Services\AssistantService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class AssistantController
{
    public function __construct(
        private readonly AssistantService $assistant,
    ) {}

    public function chat(ChatRequest $request): JsonResponse
    {
        /** @var array<int, array{role: string, content: string}> $history */
        $history = $request->input('history', []);

        $data = $this->assistant->reply(
            (string) $request->validated('message'),
            $history,
        );

        return ApiResponse::success($data, 'Balasan asisten berhasil dibuat.');
    }
}
