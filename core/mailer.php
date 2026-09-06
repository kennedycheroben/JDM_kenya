<?php

if (!function_exists('site_base_url')) {
    function site_base_url(): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return $scheme . '://' . $host . '/JDM_kenya';
        }

        if (defined('SITE_URL') && SITE_URL !== '') {
            $siteUrl = rtrim((string)SITE_URL, '/');
            if (preg_match('#^https?://#i', $siteUrl)) {
                return $siteUrl;
            }

            return $scheme . '://' . $host . '/' . ltrim($siteUrl, '/');
        }

        return $scheme . '://' . $host . '/JDM_kenya';
    }
}

if (!function_exists('send_app_email')) {
    function send_app_email(string $to, string $subject, string $htmlBody, ?string $plainBody = null): bool {
        $fromEmail = defined('MAIL_FROM_EMAIL') ? (string)MAIL_FROM_EMAIL : 'no-reply@jdmkenya.com';
        $fromName = defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'JDM Kenya';
        $plainBody = $plainBody ?? trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

        $boundary = 'jdm_' . bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion(),
        ];

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plainBody . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--{$boundary}--";

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = strpos($host, 'localhost') !== false 
            || strpos($host, '127.0.0.1') !== false
            || PHP_SAPI === 'cli';
            
        if ($isLocal) {
            $logDir = dirname(dirname(__FILE__)) . '/uploads/';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            $logFile = $logDir . 'mail_outbox.log';
            $logEntry = date('Y-m-d H:i:s') . " | TO: {$to} | SUBJECT: {$subject}\n"
                . "BODY:\n{$plainBody}\n"
                . "--------------------------------------------------\n\n";
            @file_put_contents($logFile, $logEntry, FILE_APPEND);
        }

        if (defined('SMTP_HOST') && SMTP_HOST !== '') {
            $smtpSent = send_smtp_email($to, $subject, $message, $headers, $fromEmail, $fromName);
            if ($smtpSent) {
                return true;
            }
            error_log("send_app_email: SMTP send failed, trying PHP mail() fallback.");
        }

        $mailSent = @mail($to, $encodedSubject, $message, implode("\r\n", $headers));
        if ($mailSent) {
            return true;
        }

        if ($isLocal) {
            return true;
        }

        return false;
    }
}

if (!function_exists('send_smtp_email')) {
    function send_smtp_email(string $to, string $subject, string $message, array $headers, string $fromEmail, string $fromName): bool {
        $host = (string)SMTP_HOST;
        $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $secure = defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : 'tls';
        $username = defined('SMTP_USERNAME') ? (string)SMTP_USERNAME : $fromEmail;
        $password = defined('SMTP_PASSWORD') ? (string)SMTP_PASSWORD : '';
        $timeout = defined('SMTP_TIMEOUT') ? (int)SMTP_TIMEOUT : 15;
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            error_log("SMTP connection failed: {$errno} {$errstr}");
            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            smtp_expect($socket, [220]);
            smtp_command($socket, 'EHLO ' . smtp_local_name(), [250]);

            if ($secure === 'tls') {
                smtp_command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                smtp_command($socket, 'EHLO ' . smtp_local_name(), [250]);
            }

            if ($username !== '' && $password !== '') {
                smtp_command($socket, 'AUTH LOGIN', [334]);
                smtp_command($socket, base64_encode($username), [334]);
                smtp_command($socket, base64_encode($password), [235]);
            }

            smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            smtp_command($socket, 'DATA', [354]);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $data = "Subject: {$encodedSubject}\r\n"
                . implode("\r\n", $headers)
                . "\r\nTo: <{$to}>"
                . "\r\n\r\n"
                . smtp_escape_data($message)
                . "\r\n.";

            smtp_command($socket, $data, [250]);
            smtp_command($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            error_log('SMTP send failed: ' . $e->getMessage());
            @fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return false;
        }
    }
}

if (!function_exists('smtp_command')) {
    function smtp_command($socket, string $command, array $expectedCodes): string {
        fwrite($socket, $command . "\r\n");
        return smtp_expect($socket, $expectedCodes);
    }
}

if (!function_exists('smtp_expect')) {
    function smtp_expect($socket, array $expectedCodes): string {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
        }

        return $response;
    }
}

if (!function_exists('smtp_local_name')) {
    function smtp_local_name(): string {
        $host = $_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost';
        return preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: 'localhost';
    }
}

if (!function_exists('smtp_escape_data')) {
    function smtp_escape_data(string $data): string {
        $data = str_replace(["\r\n", "\r"], "\n", $data);
        $lines = explode("\n", $data);

        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }
}
