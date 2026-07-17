<?php

namespace Hybridcore\ServerCast\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('servercast.send') ?? false;
    }

    public function rules(): array
    {
        return [
            // Capped short of the bridge's 500-char command limit to leave room
            // for the "broadcast:" prefix.
            'message' => ['required', 'string', 'min:1', 'max:400'],
            'target' => ['required', Rule::in(['all', 'selected'])],
            'server_ids' => ['array', 'required_if:target,selected'],
            'server_ids.*' => ['integer', 'exists:servers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'server_ids.required_if' => trans('servercast::messages.pick_a_server'),
        ];
    }
}
