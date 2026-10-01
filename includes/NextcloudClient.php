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
        return $this->uploadContent($remotePath, file_get_contents($localFile));
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
