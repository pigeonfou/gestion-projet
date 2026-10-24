<?php
/** CDC approval requests and manual Outlook reply import. Email bytes stay in OddWorks/Nextcloud. */
require_once __DIR__ . '/NextcloudClient.php';
require_once __DIR__ . '/settings_helper.php';

function cdcValidationEnsureSchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS cdc_validations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL,
        reference TEXT NOT NULL UNIQUE,
        cdc_hash TEXT NOT NULL,
        pdf_hash TEXT NOT NULL,
        pdf_path TEXT NOT NULL,
        pdf_remote_path TEXT NOT NULL,
        recipient TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        decision TEXT,
        comment TEXT,
        validator_name TEXT,
        validator_email TEXT,
        validated_at TEXT,
        response_format TEXT,
        response_remote_path TEXT,
        archive_remote_path TEXT,
        created_by INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
    )");
}

function cdcValidationNextcloudPath(int $projectId, string $filename): string {
    $root = trim((string)getSetting('nextcloud_root', 'OddWorks'), '/');
    $root = $root === '' ? 'OddWorks' : $root;
    return $root . '/Projet_' . $projectId . '/08_Validation_CDC/' . $filename;
}

function cdcValidationRegisterDocument(PDO $db, int $projectId, string $phase, string $name, string $remotePath, string $mime, int $size, int $userId): void {
    $q = $db->prepare('SELECT id FROM documents WHERE projet_id=? AND chemin_nextcloud=?');
    $q->execute([$projectId, $remotePath]);
    if ($q->fetchColumn()) return;
    $db->prepare('INSERT INTO documents(projet_id,phase,nom_fichier,chemin_nextcloud,taille,mime,uploader_id) VALUES(?,?,?,?,?,?,?)')
        ->execute([$projectId, $phase, $name, $remotePath, $size, $mime, $userId]);
}

function cdcValidationSubject(string $projectName, string $reference): string {
    return 'Validation du cahier des charges — ' . $projectName . ' — ' . $reference;
}

function cdcValidationBody(string $projectName, string $reference): string {
    return "Bonjour,\r\n\r\nMerci de valider le cahier des charges du projet « {$projectName} ».\r\n\r\nRépondez en indiquant exactement l’une des décisions suivantes :\r\nCDC Accepté\r\nCDC Refusé\r\n\r\nCommentaire :\r\n[Votre commentaire]\r\n\r\nRéférence de validation : {$reference}\r\nMerci de conserver cette référence dans l’objet ou le corps de votre réponse et de joindre, si nécessaire, le CDC annoté.\r\n\r\nCordialement";
}

function cdcValidationOutlookUrl(string $recipient, string $subject, string $body): string {
    return 'https://outlook.office.com/mail/deeplink/compose?' . http_build_query(
        ['to' => $recipient, 'subject' => $subject, 'body' => $body],
        '', '&', PHP_QUERY_RFC3986
    );
}

function cdcValidationDecodeHeader(string $value): string {
    if (function_exists('iconv_mime_decode')) {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if (is_string($decoded)) return $decoded;
    }
    return $value;
}

function cdcValidationParseHeaders(string $raw): array {
    $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw) ?? $raw;
    $headers = [];
    foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
        if (!str_contains($line, ':')) continue;
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))][] = trim($value);
    }
    foreach ($headers as $key => $values) $headers[$key] = cdcValidationDecodeHeader(implode(', ', $values));
    return $headers;
}

function cdcValidationDecodeMimePart(string $raw, int $depth = 0): array {
    if ($depth > 12) return [];
    $split = preg_split("/\r?\n\r?\n/", $raw, 2);
    if (count($split) !== 2) return [];
    [$headerText, $body] = $split;
    $headers = cdcValidationParseHeaders($headerText);
    $type = strtolower($headers['content-type'] ?? 'text/plain');
    if (str_starts_with($type, 'multipart/')) {
        if (!preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $type, $m)) return [];
        $boundary = $m[1] !== '' ? $m[1] : $m[2];
        $parts = preg_split('/(?:\r?\n)?--' . preg_quote($boundary, '/') . '(?:--)?\s*(?:\r?\n|$)/', $body) ?: [];
        $result = [];
        foreach ($parts as $part) {
            if (trim($part) === '') continue;
            $result = array_merge($result, cdcValidationDecodeMimePart($part, $depth + 1));
        }
        return $result;
    }
    if (!str_starts_with($type, 'text/plain') && !str_starts_with($type, 'text/html')) return [];
    $encoding = strtolower($headers['content-transfer-encoding'] ?? '');
    if ($encoding === 'base64') $body = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true) ?: '';
    elseif ($encoding === 'quoted-printable') $body = quoted_printable_decode($body);
    $charset = 'UTF-8';
    if (preg_match('/charset\s*=\s*["\']?([^;"\'\s]+)/i', $type, $m)) $charset = $m[1];
    if (strtoupper($charset) !== 'UTF-8' && function_exists('iconv')) {
        $converted = @iconv($charset, 'UTF-8//IGNORE', $body);
        if (is_string($converted)) $body = $converted;
    }
    return [['type' => str_starts_with($type, 'text/html') ? 'html' : 'text', 'body' => $body]];
}

function cdcValidationBodyText(string $body, string $format): string {
    if ($format === 'html') {
        $body = preg_replace('/<(br|\/p|\/div|\/tr|\/li|\/h[1-6])\b[^>]*>/i', "\n", $body) ?? $body;
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    return trim($body);
}

function cdcValidationParseEml(string $raw): array {
    $split = preg_split("/\r?\n\r?\n/", $raw, 2);
    if (count($split) !== 2) throw new RuntimeException('Le fichier EML ne contient pas d’en-têtes de courriel valides.');
    $headers = cdcValidationParseHeaders($split[0]);
    $parts = cdcValidationDecodeMimePart($raw);
    usort($parts, static fn($a, $b) => ($a['type'] === 'text' ? 0 : 1) <=> ($b['type'] === 'text' ? 0 : 1));
    $body = $parts[0]['body'] ?? $split[1];
    $from = $headers['from'] ?? '';
    $email = '';
    $name = $from;
    if (preg_match('/^(.*?)\s*<([^<>]+)>\s*$/', $from, $m)) { $name = trim($m[1], " \t\"'"); $email = trim($m[2]); }
    elseif (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $from, $m)) $email = $m[0];
    $date = false;
    if (!empty($headers['date'])) {
        try { $date = (new DateTimeImmutable($headers['date']))->format('Y-m-d H:i:sP'); } catch (Throwable $e) { $date = false; }
    }
    return [
        'subject' => $headers['subject'] ?? '', 'body' => cdcValidationBodyText($body, $parts[0]['type'] ?? 'text'),
        'sender_name' => trim($name), 'sender_email' => $email,
        'date' => $date ?: date('Y-m-d H:i:sP'), 'format' => 'eml',
    ];
}

/** Read bounded streams from an Outlook MSG OLE compound file, including mini streams. */
function cdcMsgReadStream(string $raw, string $streamName): ?string {
    if (strlen($raw) < 512 || substr($raw, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") return null;
    $u16 = static fn(string $s, int $o): int => unpack('v', substr($s, $o, 2))[1] ?? 0;
    $u32 = static fn(string $s, int $o): int => unpack('V', substr($s, $o, 4))[1] ?? 0;
    $sectorSize = 1 << $u16($raw, 30); $miniSize = 1 << $u16($raw, 32);
    if (!in_array($sectorSize, [512, 4096], true) || $miniSize < 32) return null;
    $numFat = $u32($raw, 44); $dirStart = $u32($raw, 48); $miniCutoff = $u32($raw, 56);
    $miniFatStart = $u32($raw, 60); $miniFatCount = $u32($raw, 64);
    $difat = [];
    for ($i=0; $i<109; $i++) { $sid=$u32($raw,76+$i*4); if ($sid<0xFFFFFFF0) $difat[]=$sid; }
    $nextDifat = $u32($raw,68); $difatCount = $u32($raw,72); $seen=[];
    for ($i=0; $i<$difatCount && $nextDifat<0xFFFFFFF0 && $i<10000; $i++) {
        if (isset($seen[$nextDifat])) break; $seen[$nextDifat]=true;
        $off=($nextDifat+1)*$sectorSize; if ($off+$sectorSize>strlen($raw)) break;
        for ($j=0; $j<$sectorSize/4-1; $j++) { $sid=$u32($raw,$off+$j*4); if ($sid<0xFFFFFFF0) $difat[]=$sid; }
        $nextDifat=$u32($raw,$off+$sectorSize-4);
    }
    $fat=[];
    foreach (array_slice($difat,0,$numFat) as $sid) {
        $off=($sid+1)*$sectorSize; if ($off+$sectorSize>strlen($raw)) return null;
        for ($j=0; $j<$sectorSize/4; $j++) $fat[]=$u32($raw,$off+$j*4);
    }
    $chain = static function(int $start, array $table, int $limit=200000): array {
        $ids=[]; $seen=[]; $sid=$start;
        while ($sid<0xFFFFFFF0 && isset($table[$sid]) && count($ids)<$limit && !isset($seen[$sid])) { $ids[]=$sid; $seen[$sid]=true; $sid=$table[$sid]; }
        return $ids;
    };
    $readRegular = static function(int $start, int $size) use ($raw,$sectorSize,$fat,$chain): string {
        $out=''; foreach ($chain($start,$fat) as $sid) { $off=($sid+1)*$sectorSize; if ($off+$sectorSize>strlen($raw)) break; $out.=substr($raw,$off,$sectorSize); if (strlen($out)>$size) break; }
        return substr($out,0,$size);
    };
    $dirBytes=$readRegular($dirStart, min(strlen($raw)*2, 32*1024*1024));
    $entries=[];
    for ($off=0; $off+128<=strlen($dirBytes); $off+=128) {
        $entry=substr($dirBytes,$off,128); $len=$u16($entry,64); $type=ord($entry[66]);
        $name=$len>=2 ? iconv('UTF-16LE','UTF-8//IGNORE',substr($entry,0,$len-2)) : '';
        $entries[]=['name'=>$name,'type'=>$type,'left'=>$u32($entry,68),'right'=>$u32($entry,72),'child'=>$u32($entry,76),'start'=>$u32($entry,116),'size'=>$u32($entry,120)];
    }
    if (!$entries || ($entries[0]['type']??0)!==5) return null;
    $paths=[];
    $walk = function(int $id, string $parent, array &$seen) use (&$walk,&$paths,$entries): void {
        if ($id>=0xFFFFFFFE || !isset($entries[$id]) || isset($seen[$id])) return; $seen[$id]=true; $e=$entries[$id];
        $walk($e['left'],$parent,$seen);
        $path=$parent.($parent!==''?'/':'').$e['name']; $paths[$path]=$e;
        if ($e['type']===1) $walk($e['child'],$path,$seen);
        $walk($e['right'],$parent,$seen);
    };
    $seen=[]; $walk($entries[0]['child'],'',$seen);
    if (!isset($paths[$streamName])) return null;
    $target=$paths[$streamName]; $size=(int)$target['size'];
    if ($size>$miniCutoff) return $readRegular((int)$target['start'],$size);
    $root=$entries[0]; $miniStream=$readRegular((int)$root['start'],(int)$root['size']);
    $miniFatBytes=$miniFatCount ? $readRegular($miniFatStart,$miniFatCount*$sectorSize) : '';
    $miniFat=[]; for($i=0;$i+4<=strlen($miniFatBytes);$i+=4) $miniFat[]=$u32($miniFatBytes,$i);
    $out=''; foreach($chain((int)$target['start'],$miniFat) as $sid) { $out.=substr($miniStream,$sid*$miniSize,$miniSize); if(strlen($out)>=$size) break; }
    return substr($out,0,$size);
}

function cdcValidationParseMsg(string $raw): array {
    $props = [
        'subject' => cdcMsgReadStream($raw,'__substg1.0_0037001F') ?? cdcMsgReadStream($raw,'__substg1.0_0037001E') ?? '',
        'sender_name' => cdcMsgReadStream($raw,'__substg1.0_0C1A001F') ?? cdcMsgReadStream($raw,'__substg1.0_0C1A001E') ?? '',
        'sender_email' => cdcMsgReadStream($raw,'__substg1.0_0C1F001F') ?? cdcMsgReadStream($raw,'__substg1.0_0C1F001E') ?? '',
        'body' => cdcMsgReadStream($raw,'__substg1.0_1000001F') ?? cdcMsgReadStream($raw,'__substg1.0_1000001E') ?? '',
    ];
    foreach ($props as $k=>$v) {
        if (str_ends_with($k,'_email') || $k==='subject' || $k==='sender_name' || $k==='body') {
            if (str_contains($v,"\0")) $v=@iconv('UTF-16LE','UTF-8//IGNORE',$v) ?: '';
            elseif (!preg_match('//u',$v)) $v=@iconv('Windows-1252','UTF-8//IGNORE',$v) ?: '';
            $props[$k]=trim($v,"\0 \t\r\n");
        }
    }
    $dateRaw=cdcMsgReadStream($raw,'__substg1.0_00390040'); $date=date('Y-m-d H:i:sP');
    if (is_string($dateRaw) && strlen($dateRaw)>=8) {
        $parts=unpack('Vlow/Vhigh',substr($dateRaw,0,8)); $timestamp=(int)(($parts['high']*4294967296+$parts['low'])/10000000-11644473600);
        if ($timestamp>0) $date=date('Y-m-d H:i:sP',$timestamp);
    }
    if ($props['body']==='' || ($props['subject']==='' && $props['sender_email']==='')) throw new RuntimeException('Le fichier MSG Outlook n’a pas pu être lu. Réexportez le message au format EML ou utilisez un MSG Outlook standard.');
    return ['subject'=>$props['subject'],'body'=>cdcValidationBodyText($props['body'],'text'),'sender_name'=>$props['sender_name'],'sender_email'=>$props['sender_email'],'date'=>$date,'format'=>'msg'];
}

function cdcValidationParseDecision(string $text): array {
    $accepted=preg_match('/\bCDC\s+Accept[eé]\b/ui',$text)===1;
    $refused=preg_match('/\bCDC\s+Refus[eé]\b/ui',$text)===1;
    if ($accepted===$refused) throw new RuntimeException('La réponse doit contenir une seule décision explicite : « CDC Accepté » ou « CDC Refusé ».');
    if (!preg_match('/\bCommentaire\s*:\s*(.*?)(?=\n\s*(?:Référence|Reference)\s+(?:de\s+)?validation\s*:|$)/isu',$text,$m)) {
        throw new RuntimeException('Le champ « Commentaire : » est absent du courriel.');
    }
    $comment=trim($m[1]);
    if (preg_match('/^\[Votre commentaire\]$/i',$comment)) $comment='';
    return ['decision'=>$accepted?'accepté':'refusé','comment'=>$comment];
}

function cdcValidationSafeZip(string $zipPath, array $files): void {
    if (!class_exists('ZipArchive')) throw new RuntimeException('L’extension PHP ZipArchive doit être activée pour créer l’archive de validation.');
    $zip=new ZipArchive();
    if ($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('L’archive ZIP de validation n’a pas pu être créée.');
    foreach ($files as $name=>$content) if (!$zip->addFromString($name,$content)) { $zip->close(); throw new RuntimeException('Un fichier n’a pas pu être ajouté à l’archive de validation.'); }
    if (!$zip->close()) throw new RuntimeException('L’archive ZIP de validation n’a pas pu être finalisée.');
}
