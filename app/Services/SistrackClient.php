<?php

namespace App\Services;

use App\Models\ShippingCarrier;
use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente HTTP para el panel Nova de Sistrack / Express El Salvador.
 * Usa login por sesión (email/password) porque no hay API key.
 * Las credenciales vienen de la empresa de envío (shipping carrier).
 */
class SistrackClient
{
    private ?string $xsrfToken = null;

    private bool $authenticated = false;

    private CookieJar $cookies;

    private ?string $baseUrl = null;

    private ?string $email = null;

    private ?string $password = null;

    private int $senderId = 67306;

    public function __construct()
    {
        $this->cookies = new CookieJar;
    }

    public function useCarrier(ShippingCarrier $carrier): self
    {
        if (! $carrier->sistrack_enabled) {
            throw new RuntimeException('Esta empresa de envío no tiene Sistrack activado.');
        }

        if (! filled($carrier->sistrack_email) || ! filled($carrier->sistrack_password)) {
            throw new RuntimeException('Faltan credenciales Sistrack en la empresa de envío.');
        }

        return $this->useCredentials(
            $carrier->sistrackBaseUrl(),
            (string) $carrier->sistrack_email,
            (string) $carrier->sistrack_password,
            $carrier->sistrackSenderId(),
        );
    }

    public function useCredentials(string $baseUrl, string $email, string $password, int $senderId = 67306): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->email = $email;
        $this->password = $password;
        $this->senderId = $senderId > 0 ? $senderId : 67306;
        $this->authenticated = false;
        $this->xsrfToken = null;
        $this->cookies = new CookieJar;

        return $this;
    }

    public function baseUrl(): string
    {
        return (string) ($this->baseUrl ?: config('sistrack.base_url'));
    }

    public function senderId(): int
    {
        return $this->senderId;
    }

    public function ensureConfigured(): void
    {
        if (! filled($this->email) || ! filled($this->password) || ! filled($this->baseUrl)) {
            throw new RuntimeException('Credenciales Sistrack no configuradas en la empresa de envío.');
        }
    }

    public function login(): void
    {
        $this->ensureConfigured();

        $loginPage = $this->http()
            ->get($this->baseUrl().'/admin/login');

        $token = $this->extractCsrf($loginPage->body());
        $this->captureXsrfFromJar();

        $response = $this->http()
            ->asForm()
            ->withHeaders(['Referer' => $this->baseUrl().'/admin/login'])
            ->post($this->baseUrl().'/admin/login', [
                'email' => $this->email,
                'password' => $this->password,
                '_token' => $token,
            ]);

        $this->captureXsrfFromJar();

        if (! in_array($response->status(), [200, 302], true)) {
            throw new RuntimeException('No se pudo iniciar sesión en Sistrack (HTTP '.$response->status().').');
        }

        // Validar sesión con un endpoint Nova.
        $probe = $this->http()
            ->acceptJson()
            ->withHeaders($this->novaHeaders())
            ->get($this->baseUrl().'/nova-api/orders', ['perPage' => 1]);
        $this->captureXsrfFromJar();

        if (! $probe->successful()) {
            throw new RuntimeException('Login Sistrack inválido: no se pudo leer órdenes (HTTP '.$probe->status().').');
        }

        $this->authenticated = true;
    }

    public function novaGet(string $path, array $query = []): Response
    {
        $this->ensureSession();

        $response = $this->http()
            ->acceptJson()
            ->withHeaders($this->novaHeaders())
            ->get($this->baseUrl().$path, $query);

        $this->captureXsrfFromJar();

        return $response;
    }

    public function novaPost(string $path, array $payload): Response
    {
        $this->ensureSession();

        $response = $this->http()
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->novaHeaders())
            ->post($this->baseUrl().$path, $payload);

        $this->captureXsrfFromJar();

        return $response;
    }

    public function novaPut(string $path, array $payload): Response
    {
        $this->ensureSession();

        $response = $this->http()
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->novaHeaders())
            ->put($this->baseUrl().$path, $payload);

        $this->captureXsrfFromJar();

        return $response;
    }

    /**
     * @return list<array{id: int|string, order_id: ?string}>
     */
    public function searchOrders(string $term, int $perPage = 25): array
    {
        $response = $this->novaGet('/nova-api/orders', [
            'search' => $term,
            'perPage' => $perPage,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Error buscando órdenes en Sistrack (HTTP '.$response->status().').');
        }

        $out = [];
        foreach ($response->json('resources') ?? [] as $resource) {
            $id = data_get($resource, 'id.value', data_get($resource, 'id'));
            $orderId = null;
            foreach ($resource['fields'] ?? [] as $field) {
                if (($field['attribute'] ?? null) === 'order_id') {
                    $orderId = $field['value'] ?? null;
                    break;
                }
            }
            $out[] = [
                'id' => $id,
                'order_id' => $orderId !== null ? (string) $orderId : null,
            ];
        }

        return $out;
    }

    public function findOrderIdByCommerceId(string $commerceId): ?string
    {
        $best = $this->findBestOrderByCommerceId($commerceId);

        return $best ? (string) $best['id'] : null;
    }

    /**
     * Busca órdenes con el Id Comercio exacto.
     * Nova search es fuzzy (confunde sufijos tipo 0001), así que filtramos por
     * fecha del número de venta y exigimos order_id exacto.
     *
     * @return list<array{id: string, order_id: string, shipping_status: ?string}>
     */
    public function findOrdersByCommerceId(string $commerceId): array
    {
        $commerceId = trim($commerceId);
        if ($commerceId === '') {
            return [];
        }

        $dates = $this->commerceIdSearchDates($commerceId);
        $matches = [];
        $seen = [];

        foreach ($dates as $date) {
            foreach ($this->ordersForCreationDate($date) as $order) {
                if (($order['order_id'] ?? null) !== $commerceId) {
                    continue;
                }
                $id = (string) $order['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $matches[] = [
                    'id' => $id,
                    'order_id' => $commerceId,
                    'shipping_status' => $order['shipping_status'] ?? null,
                ];
            }
        }

        // Fallback: search fuzzy + exact order_id (por si el filtro de fecha falla).
        if ($matches === []) {
            foreach ($this->searchOrders($commerceId, 50) as $order) {
                if (($order['order_id'] ?? null) !== $commerceId) {
                    continue;
                }
                $id = (string) $order['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $matches[] = [
                    'id' => $id,
                    'order_id' => $commerceId,
                    'shipping_status' => null,
                ];
            }
        }

        return $matches;
    }

    /**
     * Entre posibles duplicados, elige el de estado más avanzado (ENTREGADO > … > CREADO).
     *
     * @return array{id: string, order_id: string, shipping_status: ?string}|null
     */
    public function findBestOrderByCommerceId(string $commerceId): ?array
    {
        $matches = $this->findOrdersByCommerceId($commerceId);
        if ($matches === []) {
            return null;
        }

        usort($matches, function (array $a, array $b) {
            $rankA = $this->shippingStatusRank($a['shipping_status'] ?? null);
            $rankB = $this->shippingStatusRank($b['shipping_status'] ?? null);
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            // Empate: preferir la más antigua (id menor).
            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        return $matches[0];
    }

    /**
     * @return list<string> Y-m-d
     */
    private function commerceIdSearchDates(string $commerceId): array
    {
        if (! preg_match('/-(\d{8})-\d+$/', $commerceId, $m)) {
            return [];
        }

        try {
            $base = Carbon::createFromFormat('Ymd', $m[1])->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        // ±1 día por desfases de creación vs sold_at.
        return [
            $base->copy()->subDay()->toDateString(),
            $base->toDateString(),
            $base->copy()->addDay()->toDateString(),
        ];
    }

    /**
     * @return list<array{id: string, order_id: ?string, shipping_status: ?string}>
     */
    private function ordersForCreationDate(string $date): array
    {
        $filters = base64_encode(json_encode([
            ['class' => 'App\\Nova\\Filters\\DateFromFilter', 'value' => $date],
            ['class' => 'App\\Nova\\Filters\\DateToFilter', 'value' => $date],
        ]));

        $out = [];
        for ($page = 1; $page <= 20; $page++) {
            $response = $this->novaGet('/nova-api/orders', [
                'filters' => $filters,
                'perPage' => 100,
                'page' => $page,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Error listando órdenes Sistrack por fecha (HTTP '.$response->status().').');
            }

            $resources = $response->json('resources') ?? [];
            foreach ($resources as $resource) {
                $id = data_get($resource, 'id.value', data_get($resource, 'id'));
                $orderId = null;
                $shippingStatus = null;
                foreach ($resource['fields'] ?? [] as $field) {
                    $attr = $field['attribute'] ?? null;
                    if ($attr === 'order_id') {
                        $orderId = $field['value'] ?? null;
                    }
                    if ($attr === 'shipping_status') {
                        $shippingStatus = $field['value'] ?? $field['displayedAs'] ?? null;
                    }
                }
                $out[] = [
                    'id' => (string) $id,
                    'order_id' => $orderId !== null ? (string) $orderId : null,
                    'shipping_status' => $shippingStatus !== null ? (string) $shippingStatus : null,
                ];
            }

            if (count($resources) < 100) {
                break;
            }
        }

        return $out;
    }

    public function shippingStatusRank(mixed $status): int
    {
        if ($status === null || $status === '') {
            return 0;
        }

        if (is_numeric($status)) {
            return match ((int) $status) {
                8 => 80, // ENTREGADO
                9 => 70, // DEVOLUCION
                4 => 40, // EN RUTA
                3 => 30, // EN BODEGA
                2 => 20, // RECOLECTADO
                1 => 10, // CREADO
                default => 0,
            };
        }

        $normalized = Str::upper(trim((string) $status));
        $normalized = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $normalized);

        return match ($normalized) {
            'ENTREGADO' => 80,
            'DEVOLUCION' => 70,
            'EN RUTA' => 40,
            'EN BODEGA' => 30,
            'RECOLECTADO' => 20,
            'CREADO' => 10,
            default => 0,
        };
    }

    /**
     * @return array{id: int|string, fields: array<string, mixed>}
     */
    public function createRecipient(array $payload): array
    {
        $response = $this->novaPost('/nova-api/recipients', $payload);
        if (! $response->successful()) {
            throw new RuntimeException($this->formatNovaError('No se pudo crear destinatario', $response));
        }

        $id = data_get($response->json(), 'id')
            ?? data_get($response->json(), 'resource.id.value')
            ?? data_get($response->json(), 'resource.id');

        if (! $id) {
            // Algunas versiones Nova responden 201 sin cuerpo útil: buscar por teléfono/nombre.
            $search = (string) ($payload['phone'] ?? $payload['name'] ?? '');
            if ($search !== '') {
                $found = $this->searchRecipientId($search, $payload['name'] ?? null, $payload['phone'] ?? null);
                if ($found) {
                    return ['id' => $found, 'fields' => $payload];
                }
            }
            throw new RuntimeException('Sistrack creó el destinatario pero no devolvió ID.');
        }

        return ['id' => $id, 'fields' => $payload];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: int|string, fields: array<string, mixed>}
     */
    public function updateRecipient(string $id, array $payload): array
    {
        $response = $this->novaPut('/nova-api/recipients/'.$id, $payload);
        if (! $response->successful()) {
            // Algunas instalaciones Nova aceptan POST a update; reintentar no — fallar claro.
            throw new RuntimeException($this->formatNovaError('No se pudo actualizar destinatario #'.$id, $response));
        }

        return ['id' => $id, 'fields' => $payload];
    }

    public function searchRecipientId(?string $search, ?string $name = null, ?string $phone = null): ?string
    {
        $term = $search ?: ($phone ?: $name);
        if (! filled($term)) {
            return null;
        }

        $response = $this->novaGet('/nova-api/recipients', [
            'search' => $term,
            'perPage' => 25,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $normalizePhone = fn (?string $value) => preg_replace('/\D+/', '', (string) $value) ?: null;
        $wantPhone = $normalizePhone($phone);
        $wantName = $name ? Str::lower(trim($name)) : null;

        foreach ($response->json('resources') ?? [] as $resource) {
            $id = data_get($resource, 'id.value', data_get($resource, 'id'));
            $fields = [];
            foreach ($resource['fields'] ?? [] as $field) {
                $fields[$field['attribute'] ?? ''] = $field['value'] ?? null;
            }

            $resourcePhone = $normalizePhone($fields['phone'] ?? null);
            $resourceName = isset($fields['name']) ? Str::lower(trim((string) $fields['name'])) : null;

            if ($wantPhone && $resourcePhone && $wantPhone === $resourcePhone) {
                return (string) $id;
            }
            if ($wantName && $resourceName && $wantName === $resourceName) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * @return array{id: int|string, fields: array<string, mixed>}
     */
    public function getOrder(int|string $id): array
    {
        $response = $this->novaGet('/nova-api/orders/'.$id);
        if (! $response->successful()) {
            throw new RuntimeException('No se pudo leer la orden Sistrack #'.$id.' (HTTP '.$response->status().').');
        }

        $json = $response->json() ?? [];
        $resourceId = data_get($json, 'resource.id.value')
            ?? data_get($json, 'resource.id')
            ?? $id;

        $rawFields = data_get($json, 'resource.fields') ?? data_get($json, 'fields') ?? [];
        if (is_array($rawFields) && ! array_is_list($rawFields)) {
            $rawFields = array_values($rawFields);
        }

        $fields = [];
        foreach ($rawFields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $attribute = (string) ($field['attribute'] ?? '');
            if ($attribute === '') {
                continue;
            }
            $fields[$attribute] = $field['value'] ?? null;
            if (array_key_exists('displayedAs', $field) && $field['displayedAs'] !== null) {
                $fields[$attribute.'_label'] = $field['displayedAs'];
            }
        }

        return [
            'id' => $resourceId,
            'fields' => $fields,
        ];
    }

    /**
     * @return array{id: int|string}
     */
    public function createOrder(array $payload): array
    {
        $response = $this->novaPost('/nova-api/orders', $payload);
        if (! $response->successful()) {
            throw new RuntimeException($this->formatNovaError('No se pudo crear la orden en Sistrack', $response));
        }

        $id = data_get($response->json(), 'id')
            ?? data_get($response->json(), 'resource.id.value')
            ?? data_get($response->json(), 'resource.id');

        if (! $id && ! empty($payload['order_id'])) {
            $id = $this->findOrderIdByCommerceId((string) $payload['order_id']);
        }

        if (! $id) {
            throw new RuntimeException('Sistrack creó la orden pero no devolvió ID.');
        }

        return ['id' => $id];
    }

    /**
     * Updates an existing order resubmitting its current Nova update-fields with overrides.
     * Readonly fields (status, dates, payment type, observations) are ignored by Sistrack.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{resource: array<string, mixed>, fields: array<string, mixed>}
     */
    public function updateOrder(int|string $id, array $overrides): array
    {
        $current = $this->novaGet('/nova-api/orders/'.$id.'/update-fields', ['editing' => 'true', 'editMode' => 'update']);
        if (! $current->successful()) {
            throw new RuntimeException('No se pudo leer la orden Sistrack #'.$id.' para editar (HTTP '.$current->status().').');
        }

        $payload = [];
        foreach (data_get($current->json(), 'fields') ?? [] as $field) {
            $attribute = (string) ($field['attribute'] ?? '');
            $component = (string) ($field['component'] ?? '');
            if ($attribute === '' || str_starts_with($attribute, 'conditional_container') || $component === 'file-field') {
                continue;
            }
            if ($component === 'belongs-to-field') {
                $payload[$attribute] = $field['belongsToId'] ?? null;

                continue;
            }
            $value = $field['value'] ?? null;
            $payload[$attribute] = is_bool($value) ? (int) $value : $value;
        }
        unset($payload['received_by']);

        $before = $payload;
        $payload = array_merge($payload, $overrides, [
            '_method' => 'PUT',
            '_retrieved_at' => now()->timestamp,
        ]);

        $response = $this->novaPut(
            '/nova-api/orders/'.$id.'?viaResource=&viaResourceId=&viaRelationship=&editing=true&editMode=update',
            $payload,
        );
        if (! $response->successful()) {
            throw new RuntimeException($this->formatNovaError('No se pudo actualizar la orden Sistrack #'.$id, $response));
        }

        return [
            'resource' => (array) (data_get($response->json(), 'resource') ?? []),
            'fields' => $before,
        ];
    }

    /**
     * Tamaños que ofrece la acción Nova "Crear Etiqueta".
     */
    public const LABEL_SIZES = [
        'EES4X4' => '4 × 4"',
        'EESSLOGOOBNEW' => '4 × 6"',
        'EES3X4' => '3 × 4"',
        'EESL' => 'Carta (2 etiquetas por hoja)',
    ];

    public const LABEL_DEFAULT_SIZE = 'EES4X4';

    public const LABEL_MAX_PER_BATCH = 80;

    /**
     * Ejecuta "Crear Etiqueta" y devuelve el HTML imprimible (autocontenido: estilos,
     * imagen y código de barras embebidos). La página exige sesión Sistrack.
     *
     * @param  list<int|string>  $orderIds  IDs internos de Sistrack (sistrack_external_id)
     */
    public function labelsHtml(array $orderIds, string $size = self::LABEL_DEFAULT_SIZE): string
    {
        $orderIds = array_values(array_unique(array_filter(array_map('strval', $orderIds))));
        if ($orderIds === []) {
            throw new RuntimeException('No hay órdenes Sistrack para imprimir.');
        }
        if (count($orderIds) > self::LABEL_MAX_PER_BATCH) {
            throw new RuntimeException('Sistrack imprime máximo '.self::LABEL_MAX_PER_BATCH.' etiquetas por vez.');
        }
        if (! array_key_exists($size, self::LABEL_SIZES)) {
            $size = self::LABEL_DEFAULT_SIZE;
        }

        $query = http_build_query(['action' => 'crear-etiqueta', 'pivotAction' => 'false', 'search' => '', 'filters' => '', 'trashed' => '']);
        $response = $this->novaPost('/nova-api/orders/action?'.$query, [
            'resources' => implode(',', $orderIds),
            'size' => $size,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatNovaError('Sistrack no generó la etiqueta', $response));
        }

        $path = (string) ($response->json('openInNewTab') ?? $response->json('redirect') ?? '');
        if ($path === '') {
            throw new RuntimeException('Sistrack no devolvió la página de la etiqueta.');
        }
        if (str_starts_with($path, 'http')) {
            $path = (string) parse_url($path, PHP_URL_PATH).(parse_url($path, PHP_URL_QUERY) ? '?'.parse_url($path, PHP_URL_QUERY) : '');
        }

        $page = $this->novaGet($path);
        if ($page->status() !== 200 || ! str_contains($page->body(), '<')) {
            throw new RuntimeException('No se pudo abrir la etiqueta en Sistrack (HTTP '.$page->status().').');
        }

        return $page->body();
    }

    /**
     * @return array<string, string> departamento => lista exacta de municipios Sistrack
     */
    public function departmentCityMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $response = $this->novaGet('/nova-api/recipients/creation-fields');
        if (! $response->successful()) {
            throw new RuntimeException('No se pudieron leer departamentos/municipios de Sistrack.');
        }

        $map = [];
        $fields = $response->json('fields') ?? [];
        if (is_array($fields)) {
            $fields = array_values($fields);
        }

        foreach ($fields as $field) {
            if (($field['component'] ?? null) !== 'nova-dependency-container') {
                continue;
            }
            $deps = $field['dependencies'] ?? [];
            $state = null;
            foreach ($deps as $dep) {
                if (($dep['property'] ?? null) === 'state' || ($dep['field'] ?? null) === 'state') {
                    $state = $dep['value'] ?? null;
                }
            }
            if (! $state) {
                continue;
            }

            $cities = [];
            foreach ($field['fields'] ?? [] as $nested) {
                if (($nested['attribute'] ?? null) === 'city') {
                    foreach ($nested['options'] ?? [] as $option) {
                        $cities[] = (string) ($option['value'] ?? $option['label'] ?? '');
                    }
                }
            }
            $map[(string) $state] = array_values(array_filter($cities));
        }

        $cache = $map;

        return $cache;
    }

    private function ensureSession(): void
    {
        if (! $this->authenticated) {
            $this->login();
        }
    }

    private function http(): PendingRequest
    {
        return Http::withOptions([
            'allow_redirects' => false,
            'cookies' => $this->cookies,
        ])->timeout(60);
    }

    /**
     * @return array<string, string>
     */
    private function novaHeaders(): array
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => $this->baseUrl().'/admin/resources/orders',
            'Origin' => $this->baseUrl(),
        ];

        if ($this->xsrfToken) {
            $headers['X-XSRF-TOKEN'] = $this->xsrfToken;
        }

        return $headers;
    }

    private function extractCsrf(string $html): string
    {
        if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        throw new RuntimeException('No se encontró CSRF token en el login de Sistrack.');
    }

    private function captureXsrfFromJar(): void
    {
        foreach ($this->cookies as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $this->xsrfToken = urldecode($cookie->getValue());
            }
        }
    }

    private function formatNovaError(string $prefix, Response $response): string
    {
        $json = $response->json();
        $message = data_get($json, 'message')
            ?? data_get($json, 'error')
            ?? null;

        $errors = data_get($json, 'errors');
        if (is_array($errors) && $errors !== []) {
            $parts = [];
            foreach ($errors as $field => $msgs) {
                $parts[] = $field.': '.(is_array($msgs) ? implode(', ', $msgs) : $msgs);
            }
            $message = implode(' | ', $parts);
        }

        return $prefix.' (HTTP '.$response->status().')'.($message ? ': '.$message : '').'.';
    }
}
