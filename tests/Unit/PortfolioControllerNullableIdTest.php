<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PortfolioController;
use Tests\TestCase;

class PortfolioControllerNullableIdTest extends TestCase
{
    private function callNullableId(mixed $value): ?int
    {
        $controller = new PortfolioController();
        $method = new \ReflectionMethod(PortfolioController::class, 'nullableId');
        $method->setAccessible(true);

        return $method->invoke($controller, $value);
    }

    public function test_empty_string_is_treated_as_null(): void
    {
        // Laravel's `nullable` + `integer` validation rule lets an empty
        // string pass through validated() unchanged instead of coercing it
        // to null, which previously caused a saved image's project_file_id
        // to be misread as "0" on the next save and fail with a
        // "Selected project file does not belong to this project" error.
        $this->assertNull($this->callNullableId(''));
    }

    public function test_null_is_treated_as_null(): void
    {
        $this->assertNull($this->callNullableId(null));
    }

    public function test_numeric_string_is_cast_to_int(): void
    {
        $this->assertSame(175, $this->callNullableId('175'));
    }

    public function test_int_is_returned_as_is(): void
    {
        $this->assertSame(42, $this->callNullableId(42));
    }
}
