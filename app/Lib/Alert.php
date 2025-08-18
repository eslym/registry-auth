<?php

namespace App\Lib;

final class Alert
{
    /**
     * Create a success alert.
     *
     * usage: response()->with('alert', Alert::make(...))
     *
     * @param string $title
     * @param string $message
     * @param string $close
     * @return array
     */
    public static function make(string $title, string $message, string $close = 'OK'): array
    {
        return [
            'title' => $title,
            'message' => $message,
            'close' => $close
        ];
    }
}
