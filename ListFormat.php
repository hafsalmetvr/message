<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use SplFileObject;

class ListFormat implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $file = new SplFileObject($value);
        $file->seek(0);
        $header = explode(",", $file->current());
        $phone = strtolower(
            str_replace(" ", "", preg_replace( "/\r|\n/", "", $header[0]))
        );
        /** @var STRING $phone */
        return $phone === "phone" ? true : false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'malformed csv header - missing phone on first column in header';
    }
}
