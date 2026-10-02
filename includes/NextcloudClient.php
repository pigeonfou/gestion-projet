<?php
/**
 * Client WebDAV minimal pour Nextcloud
 */
class NextcloudClient {
    private string $baseUrl;
    private string $user;
    private string $password;

    public function __construct(?string $webdav = null, ?string $user = null, ?string $password = null) {
        require_once __DIR__ . '/settings_helper.php';
        seedSettingsIfEmpty();
        $this->baseUrl = rtrim($webdav ?? getSetting('nextcloud_webdav', ''), '/');
        $this->user = $user ?? getSetting('nextcloud_user', '');
        $this->password = $password ?? getSetting('nextcloud_password', '');
    }

    public function isConfigured(): bool {
        return $this->baseUrl !== '' && $this->user !== '' && $this->password !== '';
    }

    private function request(string $method, string $path, $body = null, array $headers = []): array {
        if (!function_exists('curl_init')) {
            return ['code'=>0, 'body'=>'', 'error'=>'Extension PHP cURL absente du serveur web. Activez php-curl pour la version PHP utilisée par Apache, puis rechargez Apache.'];
        }
        try {
        $url = $this->baseUrl . '/' . ltrim(implode('/', array_map('rawurlencode', explode('/', $path))), '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->user . ':' . $this->password,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $respBody = is_string($response) ? substr($response, $headerSize) : '';
        return ['code' => $code, 'body' => $respBody, 'error' => $err];
        } catch (Throwable $e) {
            // Ne jamais exposer URL, authentifiants ou contenu documentaire.
            error_log('ProjectFlow WebDAV: '.get_class($e));
            return ['code'=>0, 'body'=>'', 'error'=>'Erreur interne du client Nextcloud ('.get_class($e).'). Consultez le journal PHP du serveur.'];
        }
    }

    /** Test connexion (PROPFIND sur racine) */
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Nextcloud non configuré.'];
        }
        $r = $this->request('PROPFIND', '', null, ['Depth: 0', 'Content-Type: application/xml']);
        if ($r['error']) {
            return ['ok' => false, 'message' => 'Erreur cURL : ' . $r['error']];
        }
        if (in_array($r['code'], [200, 207])) {
            return ['ok' => true, 'message' => 'Connexion Nextcloud réussie (HTTP ' . $r['code'] . ').'];
        }
        if ($r['code'] === 401) {
            return ['ok' => false, 'message' => 'Authentification échouée (401). Vérifiez utilisateur / mot de passe.'];
        }
        return ['ok' => false, 'message' => 'Réponse HTTP ' . $r['code']];
    }

    /** Crée un dossier (et parents si besoin) */
    public function ensureFolder(string $remotePath): bool {
        $parts = array_filter(explode('/', trim($remotePath, '/')));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            $r = $this->request('MKCOL', $current);
            if (!in_array($r['code'], [201, 405], true)) return false;
        }
        return true;
    }

    /** Upload fichier */
    public function upload(string $remotePath, string $localFile): array {
        if (!is_readable($localFile)) {
            return ['ok' => false, 'message' => 'Fichier local illisible.'];
        }
        $stream = fopen($localFile, 'rb');
        if ($stream === false) return ['ok'=>false, 'message'=>'Fichier local illisible.'];
        try { return $this->uploadStream($remotePath, $stream, (int)filesize($localFile)); }
        finally { fclose($stream); }
    }


    /** Transfert binaire en flux, sans plafond ni chargement complet en mémoire. */
    public function uploadStream(string $remotePath, $stream, int $size): array {
        if (!$this->isConfigured()) return ['ok'=>false, 'message'=>'Nextcloud non configuré.'];
        if (!function_exists('curl_init')) return ['ok'=>false, 'message'=>'Extension PHP cURL absente.'];
        if (!is_resource($stream) || $size < 0) return ['ok'=>false, 'message'=>'Flux de fichier invalide.'];
        if (!$this->ensureFolder(dirname($remotePath))) return ['ok'=>false, 'message'=>'Création du dossier Nextcloud impossible.'];
        $url = $this->baseUrl . '/' . ltrim(implode('/', array_map('rawurlencode', explode('/', $remotePath))), '/');
        $ch = curl_init($url);
        $sent = 0;
        curl_setopt_array($ch, [
            CURLOPT_UPLOAD=>true, CURLOPT_INFILESIZE_LARGE=>$size,
            CURLOPT_USERPWD=>$this->user.':'.$this->password,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>0,
            CURLOPT_HTTPHEADER=>['Content-Type: application/octet-stream', 'If-None-Match: *'],
            CURLOPT_READFUNCTION=>static function ($curl, $unused, $length) use ($stream, &$sent) {
                $data = fread($stream, $length);
                if ($data === false) return CURL_READFUNC_ABORT;
                $sent += strlen($data);
                return $data;
            },
            CURLOPT_WRITEFUNCTION=>static function ($curl, $data) { return strlen($data); },
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok'=>$ok !== false && in_array($code,[200,201,204],true) && $sent === $size,
            'message'=>$code === 412 ? 'Un document porte déjà ce nom dans ce dossier. Renommez votre fichier.' : 'Envoi Nextcloud HTTP '.$code,
            'path'=>$remotePath];
    }

    /** Téléchargement en flux : le contenu distant ne s'exécute jamais sur ProjectFlow. */
    public function download(string $remotePath, string $filename): void {
        if (!function_exists('curl_init')) { http_response_code(502); exit('Extension PHP cURL absente.'); }
        $url = $this->baseUrl . '/' . ltrim(implode('/', array_map('rawurlencode', explode('/', $remotePath))), '/');
        $ch = curl_init($url);
        $status = 0;
        curl_setopt_array($ch, [
            CURLOPT_USERPWD=>$this->user.':'.$this->password,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>0,
            CURLOPT_HEADERFUNCTION=>static function ($curl, $line) use (&$status) {
                if (preg_match('#^HTTP/\\S+ (\\d{3})#', $line, $m)) $status = (int)$m[1];
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION=>static function ($curl, $data) use (&$status, $filename) {
                if ($status === 200) {
                    if (!headers_sent()) {
                        header('Content-Type: application/octet-stream');
                        header('X-Content-Type-Options: nosniff');
                        header("Content-Disposition: attachment; filename=\"document\"; filename*=UTF-8''".rawurlencode($filename));
                    }
                    echo $data;
                }
                return strlen($data);
            },
        ]);
        $ok = curl_exec($ch);
        curl_close($ch);
        if (!headers_sent()) {
            if ($ok === false || $status !== 200) { http_response_code(502); echo 'Téléchargement Nextcloud impossible.'; }
            else {
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=\"document\"; filename*=UTF-8''".rawurlencode($filename));
            }
        }
    }

    /** Envoi en mémoire : aucun fichier temporaire documentaire sur le serveur. */
    public function uploadContent(string $remotePath, string $content): array {
        if (!$this->isConfigured()) return ['ok'=>false, 'message'=>'Nextcloud non configuré.'];
        if (!$this->ensureFolder(dirname($remotePath))) return ['ok'=>false, 'message'=>'Création du dossier Nextcloud impossible.'];
        $r = $this->request('PUT', $remotePath, $content, ['Content-Type: text/plain; charset=utf-8']);
        return ['ok'=>in_array($r['code'], [200,201,204], true), 'message'=>'Envoi HTTP '.$r['code'].($r['error'] ? ' — '.$r['error'] : ''), 'path'=>$remotePath];
    }

    public function readContent(string $remotePath): array {
        $r = $this->request('GET', $remotePath);
        return ['ok'=>$r['code']===200, 'content'=>$r['body'], 'message'=>'Lecture HTTP '.$r['code'].($r['error'] ? ' — '.$r['error'] : '')];
    }

    /** Liste fichiers d'un dossier (PROPFIND basique) */
    public function listFolder(string $remotePath): array {
        $r = $this->request('PROPFIND', $remotePath, null, [
            'Depth: 1',
            'Content-Type: application/xml',
        ]);
        if (!in_array($r['code'], [207, 200])) {
            return [];
        }
        $files = [];
        if (preg_match_all('#<d:href>([^<]+)</d:href>#i', $r['body'], $m)) {
            foreach ($m[1] as $href) {
                $href = urldecode($href);
                $name = basename(rtrim($href, '/'));
                if ($name === '' || $name === basename(rtrim($remotePath, '/'))) continue;
                $files[] = $name;
            }
        }
        return $files;
    }
}
