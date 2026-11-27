<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

/**
 * The hand-off token from mozaiklabs.fr: who is signed in there, said in a way
 * this site can check without sharing a database or running OAuth.
 *
 *   token   = base64url(payload) "." base64url(HMAC-SHA256(base64url(payload), secret))
 *   payload = {"sub": "<account id>", "name": "<display name>", "state": "<nonce>", "exp": <unix time>}
 *
 * mozaiklabs.fr signs it for a signed-in member and sends the browser back to
 * /connexion/mozaiklabs/retour?token=...; this site checks the signature, the
 * expiry and that `state` is the nonce it put in its own session before
 * leaving, so a token cannot be replayed into someone else's session. No
 * e-mail address travels: none is needed to propose a change.
 */
final class MozaikToken
{
    /** Tokens live a few minutes: long enough for a redirect, no longer. */
    public const MAX_LIFETIME = 300;

    public static function sign(array $payload, string $secret): string
    {
        $body = self::b64(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $body.'.'.self::b64(hash_hmac('sha256', $body, $secret, true));
    }

    /**
     * @return array{sub: string, name: string}|null null when anything is off
     */
    public static function verify(string $token, string $secret, string $expectedState, ?int $now = null): ?array
    {
        $now ??= time();
        if ($secret === '' || $expectedState === '' || substr_count($token, '.') !== 1) {
            return null;
        }
        [$body, $signature] = explode('.', $token);
        if (! hash_equals(self::b64(hash_hmac('sha256', $body, $secret, true)), $signature)) {
            return null;
        }
        $payload = json_decode((string) self::unb64($body), true);
        if (! is_array($payload)) {
            return null;
        }
        $sub = $payload['sub'] ?? null;
        $name = $payload['name'] ?? null;
        $exp = $payload['exp'] ?? null;
        $state = $payload['state'] ?? null;
        if (! is_string($sub) && ! is_int($sub)) {
            return null;
        }
        if (! is_string($name) || ! is_int($exp) || ! is_string($state)) {
            return null;
        }
        if ($exp < $now || $exp > $now + self::MAX_LIFETIME || ! hash_equals($expectedState, $state)) {
            return null;
        }

        return ['sub' => (string) $sub, 'name' => mb_substr(trim($name), 0, 64)];
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $text): string|false
    {
        return base64_decode(strtr($text, '-_', '+/'), true);
    }
}
