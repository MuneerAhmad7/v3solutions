<?php
/**
 * V3 Solutions — form handler (contact, consultation, careers, newsletter)
 * Works on standard cPanel / shared PHP hosting using PHP mail().
 * EDIT THE SETTINGS BELOW after uploading.
 */
declare(strict_types=1);

// ---------------- SETTINGS ----------------
const TO_EMAIL      = 'contact@v3solutions.dev';   // where inquiries are delivered
const CAREERS_EMAIL = 'careers@v3solutions.dev';   // where job applications are delivered
const FROM_EMAIL    = 'no-reply@v3solutions.dev';  // must be an address on YOUR domain (helps deliverability)
const MAX_CV_BYTES  = 5 * 1024 * 1024;             // 5 MB
const RATE_SECONDS  = 30;                          // min seconds between submissions per visitor
// ------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out(bool $ok, string $error = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}
function clean(string $key, int $max = 2000): string {
    $v = isset($_POST[$key]) ? (string)$_POST[$key] : '';
    $v = trim(str_replace(["\r", "\0"], '', $v));
    return mb_substr(strip_tags($v), 0, $max);
}
function oneLine(string $v): string { return trim(preg_replace('/[\r\n]+/', ' ', $v)); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(false, 'Method not allowed.', 405);

// Honeypot: bots fill hidden "website" field
if (!empty($_POST['website'])) out(true);

// Simple per-session rate limit
session_start();
$now = time();
if (isset($_SESSION['v3_last']) && ($now - (int)$_SESSION['v3_last']) < RATE_SECONDS) {
    out(false, 'Please wait a few seconds before submitting again.', 429);
}

$form  = clean('form', 30);
$email = oneLine(clean('email', 200));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'Please provide a valid email address.', 422);

$host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'website');
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($form === 'newsletter') {
    $subject = 'Newsletter signup — ' . $email;
    $body    = "New newsletter subscriber: {$email}\nIP: {$ip}\nSite: {$host}\n";
    $to = TO_EMAIL; $attachment = null;
} else {
    $name = oneLine(clean('fullName', 150));
    if ($name === '') out(false, 'Please provide your full name.', 422);
    if (clean('consent', 5) !== 'yes') out(false, 'Consent is required to submit this form.', 422);

    $labels = [
        'fullName' => 'Full name', 'email' => 'Email', 'phone' => 'Phone / WhatsApp', 'organization' => 'Organization',
        'country' => 'Country', 'organizationType' => 'Organization type', 'serviceRequired' => 'Service required',
        'projectDescription' => 'Project description', 'currentSystems' => 'Current systems', 'timeline' => 'Timeline',
        'budgetRange' => 'Budget range', 'preferredContactMethod' => 'Preferred contact', 'position' => 'Position',
        'workMode' => 'Work preference', 'portfolio' => 'Portfolio / LinkedIn', 'coverNote' => 'Cover note',
    ];
    $lines = [];
    foreach ($labels as $k => $label) {
        $v = clean($k, $k === 'projectDescription' || $k === 'coverNote' ? 5000 : 300);
        if ($v !== '') $lines[] = str_pad($label . ':', 22) . $v;
    }
    $body = "New {$form} submission from {$host}\n" . str_repeat('-', 50) . "\n" . implode("\n", $lines)
          . "\n" . str_repeat('-', 50) . "\nIP: {$ip}\nTime (UTC): " . gmdate('Y-m-d H:i:s') . "\n";

    $attachment = null;
    if ($form === 'careers') {
        $to = CAREERS_EMAIL;
        $subject = 'Job application — ' . oneLine(clean('position', 150)) . ' — ' . $name;
        if (empty($_FILES['cv']) || ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) out(false, 'Please attach your CV (PDF or DOCX).', 422);
        $f = $_FILES['cv'];
        if ($f['size'] > MAX_CV_BYTES) out(false, 'CV file exceeds 5MB.', 422);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) out(false, 'CV must be a PDF, DOC or DOCX file.', 422);
        $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
        $okMagic = ($ext === 'pdf' && str_starts_with($head, '%PDF')) || ($ext === 'docx' && str_starts_with($head, "PK")) || ($ext === 'doc' && str_starts_with($head, "\xD0\xCF\x11\xE0"));
        if (!$okMagic) out(false, 'The uploaded file does not look like a valid document.', 422);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $f['name']);
        $attachment = ['name' => $safeName, 'data' => file_get_contents($f['tmp_name']), 'type' => $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream'];
    } else {
        $to = TO_EMAIL;
        $subject = ($form === 'consultation' ? 'Consultation request' : 'Project inquiry') . ' — ' . oneLine(clean('serviceRequired', 150)) . ' — ' . $name;
    }
}

$subject = '=?UTF-8?B?' . base64_encode(oneLine($subject)) . '?=';
$headers = [
    'From: V3 Solutions Website <' . FROM_EMAIL . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'X-Mailer: V3-Website',
];

if ($attachment) {
    $boundary = 'b' . bin2hex(random_bytes(12));
    $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
    $msg  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$body}\r\n";
    $msg .= "--{$boundary}\r\nContent-Type: {$attachment['type']}; name=\"{$attachment['name']}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n"
          . chunk_split(base64_encode($attachment['data'])) . "\r\n--{$boundary}--";
} else {
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $msg = $body;
}

$sent = @mail($to, $subject, $msg, implode("\r\n", $headers), '-f' . FROM_EMAIL);
if (!$sent) out(false, 'Our mail server could not send your message right now.', 500);

$_SESSION['v3_last'] = $now;
out(true);
