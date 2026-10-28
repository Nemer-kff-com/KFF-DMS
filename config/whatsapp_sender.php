<?php
/**
 * إرسال واتساب عبر المزود النشط في الإعدادات
 */
require_once __DIR__ . '/whatsapp_settings.php';

function dms_whatsapp_normalize_msisdn(string $mobile, ?string $defaultCc = null): string
{
    $settings = $defaultCc === null ? dms_get_whatsapp_settings() : null;
    $cc = $defaultCc ?? (string) ($settings['default_cc'] ?? '966');
    $cc = preg_replace('/\D+/', '', $cc) ?: '966';
    $digits = preg_replace('/\D+/', '', $mobile) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 10 && $digits[0] === '0') {
        $digits = $cc . substr($digits, 1);
    } elseif (strlen($digits) === 9 && $cc === '966' && ($digits[0] === '5' || $digits[0] === '4')) {
        $digits = $cc . $digits;
    }
    return $digits;
}

function dms_whatsapp_template_id(array $settings, string $lang = 'ar'): string
{
    $lang = strtolower($lang) === 'en' ? 'en' : 'ar';
    $templates = $settings['provider_config']['templates'] ?? [];
    return trim((string) ($templates['letter_' . $lang] ?? ''));
}

function dms_whatsapp_fail(string $provider, string $error, int $http = 0, string $response = ''): array
{
    return ['ok' => false, 'http' => $http, 'response' => $response, 'error' => $error, 'provider' => $provider];
}

function dms_whatsapp_ok(string $provider, int $http, string $response): array
{
    return ['ok' => true, 'http' => $http, 'response' => $response, 'error' => '', 'provider' => $provider];
}

function dms_whatsapp_http_json(string $url, array $headers, string $body, ?string $userPwd = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ];
    if ($headers) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    if ($userPwd !== null) {
        $opts[CURLOPT_USERPWD] = $userPwd;
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'response' => is_string($response) ? $response : '',
        'error' => $err,
        'http' => $http,
    ];
}

function dms_whatsapp_send_text(string $toMobile, string $body, array $options = []): array
{
    $payload = array_merge($options, [
        'mode' => 'text',
        'to' => $toMobile,
        'body' => $body,
    ]);
    if (!empty($options['attachment']) && is_array($options['attachment'])) {
        $payload['attachment'] = $options['attachment'];
    }
    return dms_whatsapp_dispatch($payload);
}

function dms_whatsapp_send_template(string $toMobile, string $lang, array $variables, array $options = []): array
{
    return dms_whatsapp_dispatch(array_merge($options, [
        'mode' => 'template',
        'to' => $toMobile,
        'lang' => $lang,
        'variables' => $variables,
    ]));
}

function dms_whatsapp_send_document(string $toMobile, array $attachment, string $caption = ''): array
{
    return dms_whatsapp_dispatch([
        'mode' => 'document',
        'to' => $toMobile,
        'body' => $caption,
        'attachment' => $attachment,
    ]);
}

function dms_whatsapp_attachment_media_url(array $attachment): string
{
    $docId = (int) ($attachment['id'] ?? 0);
    if ($docId > 0 && function_exists('dms_document_public_media_url')) {
        $url = dms_document_public_media_url($docId);
        if ($url !== '') {
            return $url;
        }
    }
    return trim((string) ($attachment['media_url'] ?? ''));
}

function dms_whatsapp_meta_upload_media(array $settings, array $attachment): array
{
    $cfg = $settings['provider_config'] ?? [];
    $phoneNumberId = trim((string) ($cfg['phone_number_id'] ?? ''));
    $accessToken = trim((string) ($cfg['access_token'] ?? ''));
    $apiVersion = trim((string) ($cfg['api_version'] ?? 'v21.0')) ?: 'v21.0';
    $path = (string) ($attachment['path'] ?? '');
    $name = trim((string) ($attachment['name'] ?? basename($path)));
    $mime = trim((string) ($attachment['mime'] ?? ''));
    if ($phoneNumberId === '' || $accessToken === '') {
        return dms_whatsapp_fail('meta', __('wa.err_meta'));
    }
    if ($path === '' || !is_file($path)) {
        return dms_whatsapp_fail('meta', __('send.wa_attach_fail'));
    }
    if ($mime === '') {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($path) ?: 'application/octet-stream');
    }
    if ($name === '') {
        $name = basename($path);
    }
    $url = 'https://graph.facebook.com/' . rawurlencode($apiVersion) . '/'
        . rawurlencode($phoneNumberId) . '/media';
    $cfile = new CURLFile($path, $mime, $name);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'messaging_product' => 'whatsapp',
            'type' => $mime,
            'file' => $cfile,
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '') {
        return dms_whatsapp_fail('meta', 'Meta upload cURL: ' . $err, $http, is_string($response) ? $response : '');
    }
    $data = json_decode(is_string($response) ? $response : '', true);
    $mediaId = is_array($data) ? trim((string) ($data['id'] ?? '')) : '';
    if ($http < 200 || $http >= 300 || $mediaId === '') {
        return dms_whatsapp_fail('meta', 'HTTP ' . $http . ' upload', $http, is_string($response) ? $response : '');
    }
    return [
        'ok' => true,
        'http' => $http,
        'response' => is_string($response) ? $response : '',
        'error' => '',
        'provider' => 'meta',
        'media_id' => $mediaId,
    ];
}

function dms_whatsapp_resolve_attachment_ref(array $settings, array $attachment): array
{
    $provider = strtolower((string) ($settings['provider'] ?? 'twilio'));
    $mediaUrl = dms_whatsapp_attachment_media_url($attachment);
    if ($provider === 'meta') {
        $uploaded = dms_whatsapp_meta_upload_media($settings, $attachment);
        if (!empty($uploaded['ok']) && !empty($uploaded['media_id'])) {
            return ['media_id' => (string) $uploaded['media_id'], 'media_url' => '', 'error' => ''];
        }
        if ($mediaUrl !== '') {
            return ['media_id' => '', 'media_url' => $mediaUrl, 'error' => ''];
        }
        return [
            'media_id' => '',
            'media_url' => '',
            'error' => trim((string) ($uploaded['error'] ?? __('send.wa_attach_fail'))),
        ];
    }
    if ($mediaUrl === '') {
        return [
            'media_id' => '',
            'media_url' => '',
            'error' => __('send.wa_attach_need_public_url'),
        ];
    }
    return ['media_id' => '', 'media_url' => $mediaUrl, 'error' => ''];
}

function dms_whatsapp_dispatch(array $payload): array
{
    $settings = dms_get_whatsapp_settings();
    $provider = strtolower((string) ($settings['provider'] ?? 'twilio'));
    if (!(int) ($settings['is_active'] ?? 0)) {
        return dms_whatsapp_fail($provider, __('wa.err_disabled'));
    }
    return match ($provider) {
        'twilio' => dms_whatsapp_driver_twilio($settings, $payload),
        'meta' => dms_whatsapp_driver_meta($settings, $payload),
        'custom' => dms_whatsapp_driver_custom($settings, $payload),
        default => dms_whatsapp_fail($provider, __('wa.err_provider')),
    };
}

function dms_whatsapp_driver_twilio(array $settings, array $payload): array
{
    $cfg = $settings['provider_config'] ?? [];
    $sid = trim((string) ($cfg['account_sid'] ?? ''));
    $token = trim((string) ($cfg['auth_token'] ?? ''));
    $from = trim((string) ($settings['from_number'] ?? ''));
    if ($sid === '' || $token === '') {
        return dms_whatsapp_fail('twilio', __('wa.err_twilio'));
    }
    $digits = dms_whatsapp_normalize_msisdn((string) ($payload['to'] ?? ''), (string) $settings['default_cc']);
    if ($digits === '' || strlen($digits) < 8) {
        return dms_whatsapp_fail('twilio', __('wa.err_mobile'));
    }
    $to = 'whatsapp:+' . $digits;
    if ($from === '') {
        return dms_whatsapp_fail('twilio', __('wa.err_from'));
    }
    $mode = (string) ($payload['mode'] ?? 'text');
    $post = ['From' => $from, 'To' => $to];
    $attachment = is_array($payload['attachment'] ?? null) ? $payload['attachment'] : null;
    if ($mode === 'template') {
        $contentSid = dms_whatsapp_template_id($settings, (string) ($payload['lang'] ?? 'ar'));
        if ($contentSid === '') {
            return dms_whatsapp_fail('twilio', __('wa.err_template'));
        }
        $vars = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $post['ContentSid'] = $contentSid;
        $post['ContentVariables'] = json_encode($vars, JSON_UNESCAPED_UNICODE);
    } elseif ($mode === 'document' || $attachment) {
        $ref = dms_whatsapp_resolve_attachment_ref($settings, $attachment ?: []);
        if ($ref['error'] !== '') {
            return dms_whatsapp_fail('twilio', $ref['error']);
        }
        $mediaUrl = $ref['media_url'] !== '' ? $ref['media_url'] : trim((string) ($payload['media_url'] ?? ''));
        if ($mediaUrl === '') {
            return dms_whatsapp_fail('twilio', __('send.wa_attach_need_public_url'));
        }
        $post['MediaUrl'] = $mediaUrl;
        $body = (string) ($payload['body'] ?? '');
        if ($body !== '') {
            $post['Body'] = $body;
        } elseif ($mode === 'document') {
            $post['Body'] = (string) (($attachment['name'] ?? '') ?: 'document');
        }
    } else {
        $body = (string) ($payload['body'] ?? '');
        if ($body === '') {
            return dms_whatsapp_fail('twilio', __('wa.err_empty_body'));
        }
        $post['Body'] = $body;
        $mediaUrl = trim((string) ($payload['media_url'] ?? ''));
        if ($mediaUrl !== '') {
            $post['MediaUrl'] = $mediaUrl;
        }
    }
    $res = dms_whatsapp_http_json(
        'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json',
        [],
        http_build_query($post),
        $sid . ':' . $token
    );
    if ($res['error'] !== '') {
        return dms_whatsapp_fail('twilio', 'Twilio cURL: ' . $res['error'], $res['http'], $res['response']);
    }
    $ok = $res['http'] >= 200 && $res['http'] < 300;
    return $ok
        ? dms_whatsapp_ok('twilio', $res['http'], $res['response'])
        : dms_whatsapp_fail('twilio', 'HTTP ' . $res['http'], $res['http'], $res['response']);
}

function dms_whatsapp_meta_text_parameter(string $value): array
{
    return ['type' => 'text', 'text' => $value];
}

function dms_whatsapp_meta_template_components(array $variables): array
{
    $link = trim((string) ($variables['link'] ?? ''));
    $numericBody = [];
    foreach ($variables as $key => $value) {
        if (is_numeric($key)) {
            $numericBody[(int) $key] = trim((string) $value);
        }
    }
    if ($numericBody !== []) {
        ksort($numericBody);
        $bodyParams = array_map(
            static fn(string $text): array => dms_whatsapp_meta_text_parameter($text),
            array_values($numericBody)
        );
    } else {
        $name = trim((string) ($variables['name'] ?? $variables['guest_name'] ?? $variables['full_name'] ?? ''));
        $number = trim((string) ($variables['number'] ?? $variables['ref_number'] ?? ''));
        $bodyParams = [
            dms_whatsapp_meta_text_parameter($name !== '' ? $name : '—'),
            dms_whatsapp_meta_text_parameter($number !== '' ? $number : '—'),
        ];
    }
    $components = [['type' => 'body', 'parameters' => $bodyParams]];
    if ($link !== '') {
        $components[] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [dms_whatsapp_meta_text_parameter($link)],
        ];
    }
    return $components;
}

function dms_whatsapp_driver_meta(array $settings, array $payload): array
{
    $cfg = $settings['provider_config'] ?? [];
    $phoneNumberId = trim((string) ($cfg['phone_number_id'] ?? ''));
    $accessToken = trim((string) ($cfg['access_token'] ?? ''));
    $apiVersion = trim((string) ($cfg['api_version'] ?? 'v21.0')) ?: 'v21.0';
    if ($phoneNumberId === '' || $accessToken === '') {
        return dms_whatsapp_fail('meta', __('wa.err_meta'));
    }
    $digits = dms_whatsapp_normalize_msisdn((string) ($payload['to'] ?? ''), (string) $settings['default_cc']);
    if ($digits === '' || strlen($digits) < 8) {
        return dms_whatsapp_fail('meta', __('wa.err_mobile'));
    }
    $mode = (string) ($payload['mode'] ?? 'text');
    $bodyJson = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $digits,
    ];
    $attachment = is_array($payload['attachment'] ?? null) ? $payload['attachment'] : null;
    if ($mode === 'template') {
        $lang = strtolower((string) ($payload['lang'] ?? 'ar')) === 'en' ? 'en' : 'ar';
        $templateName = dms_whatsapp_template_id($settings, $lang);
        if ($templateName === '') {
            return dms_whatsapp_fail('meta', __('wa.err_template'));
        }
        $vars = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $bodyJson['type'] = 'template';
        $bodyJson['template'] = [
            'name' => $templateName,
            'language' => ['code' => $lang === 'en' ? 'en' : 'ar'],
            'components' => dms_whatsapp_meta_template_components($vars),
        ];
    } elseif ($mode === 'document' || $attachment) {
        $ref = dms_whatsapp_resolve_attachment_ref($settings, $attachment ?: []);
        if ($ref['error'] !== '') {
            return dms_whatsapp_fail('meta', $ref['error']);
        }
        $text = (string) ($payload['body'] ?? '');
        $filename = trim((string) (($attachment['name'] ?? '') ?: 'document.pdf'));
        $doc = [];
        if ($ref['media_id'] !== '') {
            $doc['id'] = $ref['media_id'];
        } else {
            $mediaUrl = $ref['media_url'] !== '' ? $ref['media_url'] : trim((string) ($payload['media_url'] ?? ''));
            if ($mediaUrl === '') {
                return dms_whatsapp_fail('meta', __('send.wa_attach_fail'));
            }
            $doc['link'] = $mediaUrl;
        }
        $doc['filename'] = $filename;
        if ($text !== '') {
            $doc['caption'] = mb_substr($text, 0, 1024);
        }
        $bodyJson['type'] = 'document';
        $bodyJson['document'] = $doc;
    } else {
        $text = (string) ($payload['body'] ?? '');
        $mediaUrl = trim((string) ($payload['media_url'] ?? ''));
        if ($mediaUrl !== '') {
            $bodyJson['type'] = 'document';
            $bodyJson['document'] = ['link' => $mediaUrl];
            if ($text !== '') {
                $bodyJson['document']['caption'] = $text;
            }
        } else {
            if ($text === '') {
                return dms_whatsapp_fail('meta', __('wa.err_empty_body'));
            }
            $bodyJson['type'] = 'text';
            $bodyJson['text'] = ['preview_url' => true, 'body' => $text];
        }
    }
    $url = 'https://graph.facebook.com/' . rawurlencode($apiVersion) . '/'
        . rawurlencode($phoneNumberId) . '/messages';
    $res = dms_whatsapp_http_json($url, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ], json_encode($bodyJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($res['error'] !== '') {
        return dms_whatsapp_fail('meta', 'Meta cURL: ' . $res['error'], $res['http'], $res['response']);
    }
    $ok = $res['http'] >= 200 && $res['http'] < 300;
    return $ok
        ? dms_whatsapp_ok('meta', $res['http'], $res['response'])
        : dms_whatsapp_fail('meta', 'HTTP ' . $res['http'], $res['http'], $res['response']);
}

function dms_whatsapp_driver_custom(array $settings, array $payload): array
{
    $custom = $settings['provider_config']['custom'] ?? [];
    if (!is_array($custom)) {
        $custom = [];
    }
    $apiUrl = trim((string) ($custom['api_url'] ?? ''));
    $authMethod = strtolower(trim((string) ($custom['auth_method'] ?? 'token')));
    $senderId = trim((string) ($custom['sender_id'] ?? ($settings['from_number'] ?? '')));
    $mode = (string) ($payload['mode'] ?? 'text');
    if ($apiUrl === '') {
        return dms_whatsapp_fail('custom', __('wa.err_custom_url'));
    }
    $digits = dms_whatsapp_normalize_msisdn((string) ($payload['to'] ?? ''), (string) $settings['default_cc']);
    if ($digits === '' || strlen($digits) < 8) {
        return dms_whatsapp_fail('custom', __('wa.err_mobile'));
    }
    $templateName = '';
    $templateLang = 'ar';
    $vars = [];
    if ($mode === 'template') {
        $templateLang = strtolower((string) ($payload['lang'] ?? 'ar')) === 'en' ? 'en' : 'ar';
        $templateName = dms_whatsapp_template_id($settings, $templateLang);
        $requestBody = trim((string) ($custom['template_request_body'] ?? ''));
        $vars = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        if ($templateName === '') {
            return dms_whatsapp_fail('custom', __('wa.err_template'));
        }
    } else {
        $requestBody = trim((string) ($custom['text_request_body'] ?? ''));
    }
    if ($requestBody === '') {
        return dms_whatsapp_fail('custom', __('wa.err_custom_body'));
    }
    $replacements = [
        '{{to}}' => $digits,
        '{{to_e164}}' => '+' . $digits,
        '{{body}}' => (string) ($payload['body'] ?? ''),
        '{{sender_id}}' => $senderId,
        '{{from}}' => $senderId,
        '{{token}}' => (string) ($custom['auth_token'] ?? ''),
        '{{api_key}}' => (string) ($custom['api_key'] ?? ''),
        '{{username}}' => (string) ($custom['auth_username'] ?? ''),
        '{{password}}' => (string) ($custom['auth_password'] ?? ''),
        '{{template_name}}' => $templateName,
        '{{template_lang}}' => $templateLang,
        '{{media_url}}' => (string) ($payload['media_url'] ?? ''),
        '{{variables_json}}' => json_encode($vars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    foreach ($vars as $key => $value) {
        $replacements['{{var_' . $key . '}}'] = (string) $value;
        $replacements['{{' . $key . '}}'] = (string) $value;
    }
    $body = strtr($requestBody, $replacements);
    $url = strtr($apiUrl, $replacements);
    $headers = ['Content-Type: application/json'];
    $extraHeadersRaw = trim((string) ($custom['http_headers'] ?? ''));
    if ($extraHeadersRaw !== '') {
        $decodedHeaders = json_decode($extraHeadersRaw, true);
        if (is_array($decodedHeaders)) {
            foreach ($decodedHeaders as $hk => $hv) {
                $headers[] = $hk . ': ' . strtr((string) $hv, $replacements);
            }
        }
    }
    $userPwd = null;
    if ($authMethod === 'token') {
        $token = trim((string) ($custom['auth_token'] ?? ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
    } elseif ($authMethod === 'api_key') {
        $apiKey = trim((string) ($custom['api_key'] ?? ''));
        $headerName = trim((string) ($custom['api_key_header'] ?? 'X-API-Key')) ?: 'X-API-Key';
        if ($apiKey !== '') {
            $headers[] = $headerName . ': ' . $apiKey;
        }
    } elseif ($authMethod === 'basic') {
        $userPwd = trim((string) ($custom['auth_username'] ?? '')) . ':' . (string) ($custom['auth_password'] ?? '');
    }
    $res = dms_whatsapp_http_json($url, $headers, $body, $userPwd);
    if ($res['error'] !== '') {
        return dms_whatsapp_fail('custom', 'cURL: ' . $res['error'], $res['http'], $res['response']);
    }
    $ok = $res['http'] >= 200 && $res['http'] < 300;
    return $ok
        ? dms_whatsapp_ok('custom', $res['http'], $res['response'])
        : dms_whatsapp_fail('custom', 'HTTP ' . $res['http'], $res['http'], $res['response']);
}
