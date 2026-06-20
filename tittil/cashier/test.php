<?php
// ============================================================
// SIMPLE TEST SCRIPT - db.php inside same folder
// ============================================================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>TITTIL Test</title>
<style>
body { background: #111; color: #fff; font-family: monospace; padding: 40px; }
.ok { color: #4CAF50; } .err { color: #ef5350; }
.box { background: #1a1a1a; border: 1px solid #333; padding: 20px; margin: 10px 0; border-radius: 8px; }
h2 { color: #4CAF50; }
a { color: #4CAF50; font-size: 1.2em; }
</style>
</head>
<body>
<h1>⚡ TITTIL System Test</h1>

<?php
$allOk = true;

// Test 1: PHP Version
echo '<div class="box">';
echo '<h2>1. PHP Version</h2>';
echo '<p>Running: <span class="ok">' . phpversion() . '</span></p>';
echo '</div>';

// Test 2: db.php exists (INSIDE same folder now)
echo '<div class="box">';
echo '<h2>2. Database Connection (db.php)</h2>';
$dbFile = __DIR__ . '/db.php';
if (!file_exists($dbFile)) {
    echo '<p class="err">❌ db.php NOT FOUND in: ' . __DIR__ . '/</p>';
    echo '<p>Make sure db.php is in the SAME folder as these PHP files.</p>';
    $allOk = false;
} else {
    echo '<p class="ok">✅ db.php found</p>';
    try {
        require_once $dbFile;
        if (isset($pdo) && $pdo instanceof PDO) {
            echo '<p class="ok">✅ PDO connected successfully</p>';
            $pdo->query("SELECT 1");
            echo '<p class="ok">✅ DB query test passed</p>';
        } else {
            echo '<p class="err">❌ $pdo variable not found in db.php</p>';
            echo '<p>Your db.php must create: <code>$pdo = new PDO(...);</code></p>';
            $allOk = false;
        }
    } catch (Exception $e) {
        echo '<p class="err">❌ DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $allOk = false;
    }
}
echo '</div>';

// Test 3: Required files
echo '<div class="box">';
echo '<h2>3. Required Files</h2>';
$files = ['index.html', 'index.php', 'history.php', 'stock.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo '<p class="ok">✅ ' . $f . ' found</p>';
    } else {
        echo '<p class="err">❌ ' . $f . ' NOT FOUND</p>';
        $allOk = false;
    }
}
echo '</div>';

// Test 4: Folders
echo '<div class="box">';
echo '<h2>4. Static Folders</h2>';
$folders = ['static/css', 'static/js', 'asset'];
foreach ($folders as $f) {
    $path = __DIR__ . '/' . $f;
    if (is_dir($path)) {
        echo '<p class="ok">✅ ' . $f . '/ exists</p>';
    } else {
        echo '<p class="err">❌ ' . $f . '/ NOT FOUND</p>';
    }
}
echo '</div>';

// Test 5: Summary
echo '<div class="box">';
echo '<h2>5. Result</h2>';
if ($allOk) {
    echo '<p class="ok" style="font-size:1.3em;">🎉 ALL TESTS PASSED! Your app should work.</p>';
    echo '<p><a href="index.php">→ Go to Cashier App</a></p>';
    echo '<p><a href="history.php">→ Go to History</a></p>';
    echo '<p><a href="stock.php">→ Go to Stock</a></p>';
} else {
    echo '<p class="err" style="font-size:1.3em;">⚠️ Some tests failed. Fix the issues above.</p>';
}
echo '</div>';
?>

</body>
</html>
