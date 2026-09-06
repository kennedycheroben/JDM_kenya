<?php

if (!function_exists('download_url')) {
    function download_url(?string $filePath, ?string $fileName = 'download'): string {
        $filePath = (string)($filePath ?? '');
        $fileName = (string)($fileName ?? 'download');

        return BASE_PATH . '/modules/helpers/download_helper.php?file='
            . rawurlencode($filePath)
            . '&name='
            . rawurlencode($fileName);
    }
}
