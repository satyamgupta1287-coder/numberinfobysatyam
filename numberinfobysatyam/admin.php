<?php
/**
 * Admin Panel & Management API for Number Info API Keys
 * Developer: Satyam Gupta
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/KeyManager.php';

// Helper to check admin authentication
function isAuthorized() {
    $providedKey = $_REQUEST['admin_key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? $_SESSION['admin_auth'] ?? '';
    return (!empty($providedKey) && hash_equals(ADMIN_SECRET_KEY, $providedKey));
}

// Handle REST API / JSON actions
$action = $_REQUEST['action'] ?? '';
$isJsonRequest = (isset($_REQUEST['format']) && $_REQUEST['format'] === 'json') 
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || !empty($action);

// Process Login via Web Form
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['admin_key'] ?? '';
    if (hash_equals(ADMIN_SECRET_KEY, $pass)) {
        $_SESSION['admin_auth'] = $pass;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = "Invalid Admin Secret Key!";
    }
}

// Process Logout
if ($action === 'logout') {
    unset($_SESSION['admin_auth']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

// REST API Handlers (JSON responses)
if (!empty($action) && $action !== 'login') {
    if (!isAuthorized()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Invalid Admin Key.']);
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'list':
            $keys = KeyManager::getAllKeys();
            echo json_encode(['success' => true, 'keys' => array_values($keys)], JSON_PRETTY_PRINT);
            exit;

        case 'create':
            $owner = $_POST['owner'] ?? $_GET['owner'] ?? '';
            $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : -1;
            $validityDays = !empty($_REQUEST['validity_days']) ? (int)$_REQUEST['validity_days'] : null;
            $customKey = $_REQUEST['custom_key'] ?? null;

            $result = KeyManager::createKey($owner, $limit, $validityDays, $customKey);
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;

        case 'toggle_status':
            $key = $_REQUEST['key'] ?? '';
            $result = KeyManager::toggleStatus($key);
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;

        case 'reset_usage':
            $key = $_REQUEST['key'] ?? '';
            $result = KeyManager::resetUsage($key);
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;

        case 'update':
            $key = $_REQUEST['key'] ?? '';
            $updates = [];
            if (isset($_REQUEST['owner'])) $updates['owner'] = $_REQUEST['owner'];
            if (isset($_REQUEST['status'])) $updates['status'] = $_REQUEST['status'];
            if (isset($_REQUEST['limit'])) $updates['request_limit'] = (int)$_REQUEST['limit'];
            if (isset($_REQUEST['expires_at'])) {
                $updates['expires_at'] = !empty($_REQUEST['expires_at']) ? $_REQUEST['expires_at'] : null;
            }
            $result = KeyManager::updateKey($key, $updates);
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;

        case 'delete':
            $key = $_REQUEST['key'] ?? '';
            $result = KeyManager::deleteKey($key);
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }
}

// Check Web UI Authorization
$loggedIn = isAuthorized();
$allKeys = $loggedIn ? KeyManager::getAllKeys() : [];

// Calculate Dashboard Stats
$totalKeys = count($allKeys);
$activeKeys = 0;
$totalRequests = 0;
$expiredKeys = 0;

foreach ($allKeys as $k) {
    if ($k['status'] === 'active') $activeKeys++;
    if ($k['status'] === 'expired' || (!empty($k['expires_at']) && time() > strtotime($k['expires_at']))) $expiredKeys++;
    $totalRequests += ($k['requests_used'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Key Manager - <?php echo htmlspecialchars(API_DEVELOPER); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#f0fdf4', 500: '#22c55e', 600: '#16a34a', 700: '#15803d' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased">

<?php if (!$loggedIn): ?>
<!-- Login Screen -->
<div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-400 mb-4 border border-emerald-500/20">
                <i class="fa-solid fa-shield-halved text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">API Key Manager</h1>
            <p class="text-slate-400 text-sm mt-1">Enter your Admin Secret Key to continue</p>
        </div>

        <?php if (!empty($loginError)): ?>
            <div class="mb-5 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($loginError); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin.php?action=login" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Admin Secret Key</label>
                <div class="relative">
                    <input type="password" name="admin_key" required placeholder="Enter secret key..."
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                </div>
            </div>
            <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold py-3 px-4 rounded-xl transition duration-200 shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                <span>Access Dashboard</span>
                <i class="fa-solid fa-arrow-right text-sm"></i>
            </button>
        </form>
        <p class="text-center text-xs text-slate-600 mt-6">Default Key: <code class="text-slate-400 font-mono">satyam_admin_2026</code> (Configurable in config.php)</p>
    </div>
</div>

<?php else: ?>
<!-- Admin Dashboard -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Navigation -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800 mb-8">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">API Key Control Panel</h1>
                    <p class="text-xs text-slate-400">Manage keys, request limits, and expiration periods</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openCreateModal()" class="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold px-4 py-2.5 rounded-xl transition duration-200 flex items-center gap-2 shadow-lg shadow-emerald-500/20 text-sm">
                <i class="fa-solid fa-plus"></i>
                <span>Generate API Key</span>
            </button>
            <a href="admin.php?action=logout" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl transition text-sm flex items-center gap-2">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Keys</p>
                    <h3 class="text-3xl font-bold text-white mt-1"><?php echo $totalKeys; ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center border border-blue-500/20">
                    <i class="fa-solid fa-database text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Keys</p>
                    <h3 class="text-3xl font-bold text-emerald-400 mt-1"><?php echo $activeKeys; ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total API Hits</p>
                    <h3 class="text-3xl font-bold text-purple-400 mt-1"><?php echo number_format($totalRequests); ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center border border-purple-500/20">
                    <i class="fa-solid fa-chart-line text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Expired Keys</p>
                    <h3 class="text-3xl font-bold text-amber-400 mt-1"><?php echo $expiredKeys; ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/20">
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- API Keys Table Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fa-solid fa-list text-slate-400 text-sm"></i>
                <span>Managed API Keys</span>
            </h2>
            <div class="text-xs text-slate-400">
                Endpoint: <code class="bg-slate-950 px-2 py-1 rounded text-emerald-400 border border-slate-800 font-mono">index.php?apikey={KEY}&number={NUMBER}</code>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/60 text-xs uppercase font-semibold text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-4">Owner / Key</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Usage / Limit</th>
                        <th class="px-6 py-4">Validity / Expiration</th>
                        <th class="px-6 py-4">Last Activity</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($allKeys)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No API keys found. Click "Generate API Key" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allKeys as $keyData): 
                            $isExpired = !empty($keyData['expires_at']) && (time() > strtotime($keyData['expires_at']));
                            $status = $isExpired ? 'expired' : ($keyData['status'] ?? 'active');
                            $limit = $keyData['request_limit'];
                            $used = $keyData['requests_used'] ?? 0;
                            $pct = ($limit > 0) ? min(100, round(($used / $limit) * 100)) : 0;
                        ?>
                        <tr class="hover:bg-slate-800/30 transition">
                            <!-- Owner & Key -->
                            <td class="px-6 py-4">
                                <div class="font-medium text-white"><?php echo htmlspecialchars($keyData['owner'] ?? 'Unnamed'); ?></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <code class="font-mono text-xs text-emerald-400 bg-slate-950 px-2 py-0.5 rounded border border-slate-800">
                                        <?php echo htmlspecialchars($keyData['key']); ?>
                                    </code>
                                    <button onclick="copyToClipboard('<?php echo htmlspecialchars($keyData['key']); ?>')" title="Copy Key" class="text-slate-500 hover:text-slate-300 text-xs">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-6 py-4">
                                <?php if ($status === 'active'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active
                                    </span>
                                <?php elseif ($status === 'suspended'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span> Suspended
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Expired
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Usage & Limit -->
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-semibold text-white"><?php echo number_format($used); ?></span>
                                    <span class="text-slate-400">/ <?php echo ($limit === -1) ? '∞ Unlimited' : number_format($limit); ?></span>
                                </div>
                                <?php if ($limit > 0): ?>
                                    <div class="w-32 bg-slate-950 rounded-full h-1.5 overflow-hidden border border-slate-800">
                                        <div class="h-full rounded-full <?php echo $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500'); ?>" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Expiry -->
                            <td class="px-6 py-4 text-xs">
                                <?php if (empty($keyData['expires_at'])): ?>
                                    <span class="text-slate-400 flex items-center gap-1">
                                        <i class="fa-solid fa-infinity text-[10px]"></i> Lifetime
                                    </span>
                                <?php else: ?>
                                    <div class="<?php echo $isExpired ? 'text-red-400 font-semibold' : 'text-slate-300'; ?>">
                                        <?php echo date('d M Y, h:i A', strtotime($keyData['expires_at'])); ?>
                                    </div>
                                    <?php if (!$isExpired): 
                                        $diffDays = ceil((strtotime($keyData['expires_at']) - time()) / 86400);
                                    ?>
                                        <span class="text-[11px] text-slate-500">(<?php echo $diffDays; ?> days left)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <!-- Last Activity -->
                            <td class="px-6 py-4 text-xs text-slate-400">
                                <?php echo !empty($keyData['last_used_at']) ? date('d M Y, h:i A', strtotime($keyData['last_used_at'])) : 'Never used'; ?>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button onclick="copyApiUrl('<?php echo htmlspecialchars($keyData['key']); ?>')" title="Copy Test API Request URL" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition text-xs">
                                        <i class="fa-solid fa-link"></i>
                                    </button>
                                    <button onclick="resetKeyUsage('<?php echo htmlspecialchars($keyData['key']); ?>')" title="Reset Usage Counter" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition text-xs">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <button onclick="toggleKeyStatus('<?php echo htmlspecialchars($keyData['key']); ?>')" title="Toggle Active / Suspended" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition text-xs">
                                        <i class="fa-solid <?php echo ($keyData['status'] === 'active') ? 'fa-pause text-yellow-400' : 'fa-play text-emerald-400'; ?>"></i>
                                    </button>
                                    <button onclick="deleteApiKey('<?php echo htmlspecialchars($keyData['key']); ?>')" title="Delete Key" class="p-2 rounded-lg bg-slate-800 hover:bg-red-500/20 text-slate-300 hover:text-red-400 transition text-xs">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create New API Key -->
<div id="createModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl relative">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-5">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-key text-emerald-400"></i>
                <span>Generate New API Key</span>
            </h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="createKeyForm" onsubmit="handleCreateKey(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Owner / Client Name</label>
                <input type="text" id="keyOwner" required placeholder="e.g. John Doe, VIP Bot, Client A"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Request Limit</label>
                    <select id="keyLimit" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-emerald-500">
                        <option value="-1">Unlimited (-1)</option>
                        <option value="50">50 Requests</option>
                        <option value="100">100 Requests</option>
                        <option value="500">500 Requests</option>
                        <option value="1000">1,000 Requests</option>
                        <option value="5000">5,000 Requests</option>
                        <option value="10000">10,000 Requests</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Validity Duration</label>
                    <select id="keyValidity" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-emerald-500">
                        <option value="">Lifetime (No Expiry)</option>
                        <option value="1">1 Day</option>
                        <option value="7">7 Days (1 Week)</option>
                        <option value="15">15 Days</option>
                        <option value="30">30 Days (1 Month)</option>
                        <option value="90">90 Days (3 Months)</option>
                        <option value="180">180 Days (6 Months)</option>
                        <option value="365">365 Days (1 Year)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Custom Key String (Optional)</label>
                <input type="text" id="customKey" placeholder="Leave empty for auto-generated secure token"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 font-mono text-xs">
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold text-sm transition shadow-lg shadow-emerald-500/20">
                    Create Key
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.getElementById('createModal').classList.add('flex');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createModal').classList.remove('flex');
}

async function handleCreateKey(e) {
    e.preventDefault();
    const owner = document.getElementById('keyOwner').value;
    const limit = document.getElementById('keyLimit').value;
    const validity = document.getElementById('keyValidity').value;
    const customKey = document.getElementById('customKey').value;

    const formData = new FormData();
    formData.append('owner', owner);
    formData.append('limit', limit);
    formData.append('validity_days', validity);
    if (customKey.trim()) formData.append('custom_key', customKey.trim());

    const res = await fetch('admin.php?action=create', {
        method: 'POST',
        body: formData
    });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Failed to create key');
    }
}

async function toggleKeyStatus(key) {
    if (!confirm(`Toggle status for API Key: ${key}?`)) return;
    const res = await fetch(`admin.php?action=toggle_status&key=${encodeURIComponent(key)}`);
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Action failed');
    }
}

async function resetKeyUsage(key) {
    if (!confirm(`Reset request counter to 0 for API Key: ${key}?`)) return;
    const res = await fetch(`admin.php?action=reset_usage&key=${encodeURIComponent(key)}`);
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Action failed');
    }
}

async function deleteApiKey(key) {
    if (!confirm(`Are you sure you want to permanently delete API Key: ${key}?`)) return;
    const res = await fetch(`admin.php?action=delete&key=${encodeURIComponent(key)}`);
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        alert(data.message || 'Action failed');
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    alert(`Copied to clipboard:\n${text}`);
}

function copyApiUrl(key) {
    const url = `${window.location.origin}${window.location.pathname.replace('admin.php', 'index.php')}?apikey=${encodeURIComponent(key)}&number=9570187989`;
    navigator.clipboard.writeText(url);
    alert(`Copied Test API URL to clipboard:\n${url}`);
}
</script>

<?php endif; ?>
</body>
</html>
