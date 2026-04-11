<?php
// ================================================
// MediTrack - Password Fix Script
// 
// INSTRUCTIONS:
// 1. Place this file in your hospital-tms/ folder
// 2. Visit: http://localhost/hospital-tms/fix_passwords.php
// 3. It will reset all passwords to working values
// 4. DELETE this file immediately after running it!
// ================================================

require_once __DIR__ . '/includes/config.php';

$passwords = [
    'admin@hospital.com'         => 'Admin@123',
    'dr.rivera@hospital.com'     => 'Doctor1@123',
    'dr.chen@hospital.com'       => 'Doctor2@123',
    'maria.santos@hospital.com'  => 'Nurse@123',
    'john.delacruz@hospital.com' => 'Nurse@123',
    'anna.reyes@hospital.com'    => 'Nurse@123',
    'carlos.mendoza@hospital.com'=> 'Nurse@123',
];

$results = [];
$db = getDB();

foreach ($passwords as $email => $plainPassword) {
    $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    $affected = $stmt->rowCount();
    $results[] = [
        'email'    => $email,
        'password' => $plainPassword,
        'hash'     => $hash,
        'updated'  => $affected > 0,
    ];
}

// Verify each one works
foreach ($results as &$r) {
    $stmt = $db->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute([$r['email']]);
    $row = $stmt->fetch();
    $r['verify'] = $row ? password_verify($r['password'], $row['password']) : false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Password Fix — MediTrack</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: #0f1117; color: #f0f2f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.wrap { width: 100%; max-width: 680px; }
h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: 6px; }
p.sub { color: #8b92b0; font-size: .875rem; margin-bottom: 24px; }
.card { background: #1a1f2e; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; font-size: .85rem; }
th { text-align: left; padding: 12px 16px; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: #555c7a; border-bottom: 1px solid rgba(255,255,255,0.07); }
td { padding: 13px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #c0c8e0; }
tr:last-child td { border-bottom: none; }
.ok  { color: #22c55e; font-weight: 700; }
.fail{ color: #ef4444; font-weight: 700; }
.mono { font-family: monospace; font-size: .78rem; color: #6389ff; }
.warn { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.25); border-radius: 10px; padding: 14px 18px; font-size: .85rem; color: #f59e0b; margin-bottom: 20px; }
.btn { display: inline-block; background: #3d6fff; color: white; padding: 11px 24px; border-radius: 8px; font-weight: 600; font-size: .9rem; text-decoration: none; }
.success-banner { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.25); border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; }
.success-banner h2 { color: #22c55e; font-size: 1rem; margin-bottom: 4px; }
.success-banner p { color: #8b92b0; font-size: .85rem; }
</style>
</head>
<body>
<div class="wrap">
    <h1>🔧 Password Fix Tool</h1>
    <p class="sub">This script regenerates all password hashes directly on your server's PHP version.</p>

    <?php $allOk = array_reduce($results, fn($carry, $r) => $carry && $r['verify'], true); ?>

    <?php if ($allOk): ?>
    <div class="success-banner">
        <h2>✓ All passwords fixed successfully!</h2>
        <p>Every account has been updated with a verified working hash.</p>
    </div>
    <?php else: ?>
    <div class="warn">⚠ Some passwords may not have updated. Check the table below.</div>
    <?php endif; ?>

    <div class="card">
        <table>
            <thead>
                <tr><th>Email</th><th>Password</th><th>Updated</th><th>Verified</th></tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['email']) ?></td>
                    <td class="mono"><?= htmlspecialchars($r['password']) ?></td>
                    <td class="<?= $r['updated'] ? 'ok' : 'fail' ?>"><?= $r['updated'] ? '✓ Yes' : '✗ No' ?></td>
                    <td class="<?= $r['verify'] ? 'ok' : 'fail' ?>"><?= $r['verify'] ? '✓ Works' : '✗ Failed' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="warn">
        ⚠ <strong>Security:</strong> Delete <code>fix_passwords.php</code> from your server immediately after this!
    </div>

    <?php if ($allOk): ?>
    <a href="index.php" class="btn">→ Go to Login</a>
    <?php endif; ?>
</div>
</body>
</html>