<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminClientPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }
}