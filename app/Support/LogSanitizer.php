<?php

namespace App\Support;

class LogSanitizer
{
    /**
        * Remove ou mascara dados sensíveis antes de registrar em logs.
        *
        * @param mixed $data
        * @return mixed
        */
    public static function sanitize($data)
    {
        $keysToMask = [
            'password',
            'password_confirmation',
            'pkcs12_cert',
            'pkcs12_cert_encrypted',
            'pkcs12_pass',
            'pkcs12_pass_encrypted',
            'token_api',
            'chave_criptografia',
            'access_token',
            'secret',
        ];

        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $keysToMask, true)) {
                    $clean[$key] = '[MASKED]';
                } else {
                    $clean[$key] = self::sanitize($value);
                }
            }
            return $clean;
        }

        return $data;
    }
}
