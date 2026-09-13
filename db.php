<?php
// Centralized Database Connection & Schema Initialization
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/osint_api.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Old Tables (Ensure they exist)
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_text TEXT UNIQUE,
        service_type TEXT,
        daily_limit INTEGER DEFAULT 100,
        expiry_date TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        active INTEGER DEFAULT 1,
        client_name TEXT DEFAULT '',
        rpm_limit INTEGER DEFAULT 60,
        user_id INTEGER DEFAULT 0,
        used_today INTEGER DEFAULT 0,
        total_used INTEGER DEFAULT 0
    )");
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN client_name TEXT DEFAULT ''"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN rpm_limit INTEGER DEFAULT 60"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN user_id INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN used_today INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN total_used INTEGER DEFAULT 0"); } catch(Exception $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS usage_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        api_key_id INTEGER,
        query TEXT,
        service_type TEXT,
        used_at TEXT DEFAULT (datetime('now'))
    )");
    try { $pdo->exec("ALTER TABLE usage_logs ADD COLUMN service_type TEXT DEFAULT ''"); } catch(Exception $e) {}

    // New SaaS Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        mobile TEXT UNIQUE,
        email TEXT UNIQUE,
        password_hash TEXT,
        wallet_balance REAL DEFAULT 0.00,
        is_vip INTEGER DEFAULT 0,
        vip_expiry TEXT DEFAULT NULL,
        last_login_ip TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        active INTEGER DEFAULT 1
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS api_pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_name TEXT UNIQUE,
        cost_per_hit REAL DEFAULT 0.00
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        amount REAL,
        type TEXT,
        description TEXT,
        closing_balance REAL,
        created_at TEXT DEFAULT (datetime('now'))
    )");

    // Seed default pricing if not exists
    $pdo->exec("INSERT OR IGNORE INTO api_pricing (service_name, cost_per_hit) VALUES ('number', 0.50)");
    $pdo->exec("INSERT OR IGNORE INTO api_pricing (service_name, cost_per_hit) VALUES ('vehicle', 1.00)");
    $pdo->exec("INSERT OR IGNORE INTO api_pricing (service_name, cost_per_hit) VALUES ('lpg', 1.00)");

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
