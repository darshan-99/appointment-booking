<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date'           => ['required', 'date', 'after_or_equal:today'],
            'start_time'     => ['required', 'date_format:H:i'],
            'end_time'       => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration'  => ['required', 'integer', 'min:5', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $start    = $this->input('start_time');
                $end      = $this->input('end_time');
                $duration = (int) $this->input('slot_duration');

                if (!$start || !$end || !$duration) {
                    return;
                }

                $startMins = $this->timeToMinutes($start);
                $endMins   = $this->timeToMinutes($end);
                $window    = $endMins - $startMins;

                if ($window < $duration) {
                    $validator->errors()->add(
                        'slot_duration',
                        "The time window ({$window} mins) is too small for even one slot of {$duration} mins."
                    );
                    return;
                }

                $remainder = $window % $duration;

                if ($remainder > 0) {
                    $validator->errors()->add(
                        'slot_duration',
                        "The time window ({$window} mins) is not evenly divisible by slot duration ({$duration} mins). "
                        . "{$remainder} mins at the end would be unused. Adjust end_time or slot_duration."
                    );
                }
            },
        ];
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return ((int) $hours * 60) + (int) $minutes;
    }
}
