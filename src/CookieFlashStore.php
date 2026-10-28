<?php

declare(strict_types=1);

namespace Solo\Inertia;

use InvalidArgumentException;
use JsonException;
use LengthException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;
use Solo\Inertia\Contracts\FlashStoreInterface;
use Solo\Inertia\Internal\Json;

/**
 * Keeps a Flash in a cookie signed with the application's secret: no session and no
 * storage on the server. The browser sends it with the request the redirect leads
 * to, and a cookie that was changed or signed with another secret reads as an empty
 * Flash. The data is signed, not encrypted: the user can read it, as they read the
 * page that shows it.
 *
 * The cookie is HttpOnly and SameSite=Lax, and Secure on https requests unless
 * $secure says otherwise. A browser keeps about 4 KB per cookie, so put() throws a
 * LengthException for a Flash that does not fit.
 */
final readonly class CookieFlashStore implements FlashStoreInterface
{
    // The cookie a browser keeps at most: name, value and attributes
    private const int MAX_COOKIE = 4096;

    private string $key;

    /**
     * @param string                $secret   At least 32 bytes, such as the application's APP_KEY
     * @param string                $name     Letters, digits, "_" and "-": PHP renames cookies with other characters
     * @param bool|null             $secure   Send the cookie over https only; null: when the request is https
     * @param 'Lax'|'Strict'|'None' $sameSite
     */
    public function __construct(
        #[SensitiveParameter] string $secret,
        private string $name = 'inertia_flash',
        private string $path = '/',
        private ?string $domain = null,
        private ?bool $secure = null,
        private string $sameSite = 'Lax',
    ) {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('The secret of a CookieFlashStore must be at least 32 bytes long');
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Cookie name $name may hold letters, digits, '_' and '-' only");
        }
        if ($sameSite === 'None' && $secure !== true) {
            throw new InvalidArgumentException('A SameSite=None cookie must be Secure: pass secure: true');
        }

        // A key of its own, so the application's secret signs nothing else this way
        $this->key = hash_hmac('sha256', 'solophp/inertia flash', $secret, true);
    }

    public function get(ServerRequestInterface $request): Flash
    {
        $value = $request->getCookieParams()[$this->name] ?? null;

        return (is_string($value) ? $this->decode($value) : null) ?? new Flash();
    }

    public function put(ServerRequestInterface $request, ResponseInterface $response, Flash $flash): ResponseInterface
    {
        if ($flash->isEmpty()) {
            return $this->forget($request, $response);
        }

        $cookie = $this->cookie($request, $this->encode($flash));
        if (strlen($cookie) > self::MAX_COOKIE) {
            throw new LengthException(sprintf(
                'The flash cookie takes %d bytes, more than the %d a browser keeps: '
                . 'flash less, or use a store that keeps it on the server',
                strlen($cookie),
                self::MAX_COOKIE,
            ));
        }

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    public function forget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!array_key_exists($this->name, $request->getCookieParams())) {
            return $response;
        }

        return $response->withAddedHeader('Set-Cookie', $this->cookie($request, '', expired: true));
    }

    private function cookie(ServerRequestInterface $request, string $value, bool $expired = false): string
    {
        $cookie = "$this->name=$value; Path=$this->path";
        if ($this->domain !== null) {
            $cookie .= "; Domain=$this->domain";
        }
        if ($expired) {
            $cookie .= '; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        }
        if ($this->secure ?? $request->getUri()->getScheme() === 'https') {
            $cookie .= '; Secure';
        }

        return "$cookie; HttpOnly; SameSite=$this->sameSite";
    }

    private function encode(Flash $flash): string
    {
        $json = json_encode(array_filter([
            'data' => $flash->data,
            'errors' => $flash->errors,
            'clearHistory' => $flash->clearHistory,
            'preserveFragment' => $flash->preserveFragment,
        ]), Json::FLAGS);

        $payload = self::base64Url($json);

        return $payload . '.' . self::base64Url($this->sign($payload));
    }

    private function decode(string $value): ?Flash
    {
        [$payload, $signature] = explode('.', $value, 2) + ['', ''];
        if (!hash_equals(self::base64Url($this->sign($payload)), $signature)) {
            return null;
        }

        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        try {
            $fields = $json === false ? null : json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($fields)) {
            return null;
        }

        $data = $fields['data'] ?? [];
        $errors = $fields['errors'] ?? [];
        if (!is_array($data) || !is_array($errors)) {
            return null;
        }

        /**
         * Signed by this store, so shaped as encode() wrote it
         * @var array<string, mixed> $data
         * @var array<string, mixed> $errors
         */
        return new Flash(
            $data,
            $errors,
            ($fields['clearHistory'] ?? false) === true,
            ($fields['preserveFragment'] ?? false) === true,
        );
    }

    private function sign(string $payload): string
    {
        // The cookie's name is signed too, so a value cannot move to another cookie
        return hash_hmac('sha256', "$this->name=$payload", $this->key, true);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
