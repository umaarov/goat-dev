<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base FormRequest for the API. Validation failures are rendered as JSON 422
 * by the global exception handler (see bootstrap/app.php).
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
