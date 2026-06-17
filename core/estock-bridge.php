<?php
/**
 * ESTOCK Bridge - Connect to SQL Server 2008 via ODBC
 * Reads employees and products from ESTOCK database
 */

// ESTOCK Database Configuration via ODBC
// ======================================
// Using Windows Authentication (Trusted Connection)
function getESTOCK() {
    static $pdo = null;
    if ($pdo === null) {
        try {
$pdo = new PDO("odbc:Driver={SQL Server Native Client 11.0};Server=localhost;Database=stock;Trusted_Connection=yes;");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ESTOCK ODBC connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// Add this function here - after getESTOCK()
function fixArabic($text) {
    if (empty($text)) return $text;
    return $text;
}

// Check if ESTOCK is available
function isESTOCKAvailable() {
    return getESTOCK() !== null;
}
// =====================================================
// EMPLOYEES (الموظفين)
// =====================================================

function getESTOCKEmployees() {
    $db = getESTOCK();
    if (!$db) return [];

    try {
        $stmt = $db->query("
            SELECT 
                emp_id,
                emp_code,
                emp_name_ar,
                emp_name_en,
                username,
                pass as password,
                mobile,
                job_id,
                active,
                deleted
            FROM dbo.Employee
            WHERE deleted IS NULL OR deleted != '1'
            ORDER BY emp_name_ar
        ");
        $results = $stmt->fetchAll();
        // Fix Arabic encoding
        foreach ($results as &$row) {
            $row['emp_name_ar'] = fixArabic($row['emp_name_ar']);
            $row['emp_name_en'] = fixArabic($row['emp_name_en']);
        }
        return $results;
    } catch (PDOException $e) {
        error_log("ESTOCK employees error: " . $e->getMessage());
        return [];
    }
}

function getESTOCKEmployeeByUsername($username) {
    $db = getESTOCK();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT 
                emp_id,
                emp_code,
                emp_name_ar,
                emp_name_en,
                username,
                pass as password,
                mobile,
                job_id,
                active,
                deleted
            FROM dbo.Employee
            WHERE username = ? 
            AND (deleted IS NULL OR deleted != '1')
        ");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        if ($result) {
            $result['emp_name_ar'] = fixArabic($result['emp_name_ar']);
            $result['emp_name_en'] = fixArabic($result['emp_name_en']);
        }
        return $result;
    } catch (PDOException $e) {
        error_log("ESTOCK employee lookup error: " . $e->getMessage());
        return null;
    }
}

function getESTOCKEmployeeById($emp_id) {
    $db = getESTOCK();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT 
                emp_id,
                emp_code,
                emp_name_ar,
                emp_name_en,
                username,
                pass as password,
                mobile,
                job_id,
                active,
                deleted
            FROM dbo.Employee
            WHERE emp_id = ?
        ");
        $stmt->execute([$emp_id]);
        $result = $stmt->fetch();
        if ($result) {
            $result['emp_name_ar'] = fixArabic($result['emp_name_ar']);
            $result['emp_name_en'] = fixArabic($result['emp_name_en']);
        }
        return $result;
    } catch (PDOException $e) {
        error_log("ESTOCK employee by ID error: " . $e->getMessage());
        return null;
    }
}

// =====================================================
// PRODUCTS (الأصناف) - مع Pagination و Search
// =====================================================

function getESTOCKProducts($page = 1, $per_page = 100) {
    $db = getESTOCK();
    if (!$db) return [];

    try {
        $sql = "
            SELECT TOP " . (int)$per_page . "
                product_id,
                product_code,
                product_fast_code,
                product_name_ar,
                product_name_en,
                product_scientific_name,
                sell_price,
                buy_price,
                tax_price,
                product_unit1,
                product_unit2,
                product_unit3,
                product_sale_unit,
                company_id,
                active,
                deleted
            FROM dbo.Branches_products_edit
            WHERE (deleted IS NULL OR deleted != '1')
            AND (active IS NULL OR active = '1')
            ORDER BY product_name_ar
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("ESTOCK products error: " . $e->getMessage());
        return [];
    }
}


function getESTOCKProductsCount() {
    $db = getESTOCK();
    if (!$db) return 0;

    try {
        $stmt = $db->query("
            SELECT COUNT(*) as count
            FROM dbo.Branches_products_edit
            WHERE (deleted IS NULL OR deleted != '1')
            AND (active IS NULL OR active = '1')
        ");
        $result = $stmt->fetch();
        return $result['count'];
    } catch (PDOException $e) {
        error_log("ESTOCK products count error: " . $e->getMessage());
        return 0;
    }
}

function getESTOCKProductById($product_id) {
    $db = getESTOCK();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT 
                product_id,
                product_code,
                product_fast_code,
                product_name_ar,
                product_name_en,
                product_scientific_name,
                sell_price,
                buy_price,
                tax_price,
                product_unit1,
                product_unit2,
                product_unit3,
                product_sale_unit,
                company_id,
                active,
                deleted
            FROM dbo.Branches_products_edit
            WHERE product_id = ?
        ");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch();
        if ($result) {
            $result['product_name_ar'] = fixArabic($result['product_name_ar']);
            $result['product_name_en'] = fixArabic($result['product_name_en']);
            $result['product_scientific_name'] = fixArabic($result['product_scientific_name']);
        }
        return $result;
    } catch (PDOException $e) {
        error_log("ESTOCK product by ID error: " . $e->getMessage());
        return null;
    }
}

function getESTOCKProductByCode($product_code) {
    $db = getESTOCK();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT 
                product_id,
                product_code,
                product_fast_code,
                product_name_ar,
                product_name_en,
                product_scientific_name,
                sell_price,
                buy_price,
                tax_price,
                product_unit1,
                product_unit2,
                product_unit3,
                product_sale_unit,
                company_id,
                active,
                deleted
            FROM dbo.Branches_products_edit
            WHERE product_code = ?
        ");
        $stmt->execute([$product_code]);
        $result = $stmt->fetch();
        if ($result) {
            $result['product_name_ar'] = fixArabic($result['product_name_ar']);
            $result['product_name_en'] = fixArabic($result['product_name_en']);
            $result['product_scientific_name'] = fixArabic($result['product_scientific_name']);
        }
        return $result;
    } catch (PDOException $e) {
        error_log("ESTOCK product by code error: " . $e->getMessage());
        return null;
    }
}
function searchESTOCKProducts($query, $limit = 50) {
    $db = getESTOCK();
    if (!$db) return [];

    try {
        $search = '%' . $query . '%';
        $sql = "
            SELECT TOP " . (int)$limit . "
                product_id,
                product_code,
                product_fast_code,
                product_name_ar,
                product_name_en,
                product_scientific_name,
                sell_price,
                buy_price,
                tax_price,
                product_unit1,
                product_unit2,
                product_unit3,
                product_sale_unit,
                company_id,
                active,
                deleted
            FROM dbo.Branches_products_edit
            WHERE (deleted IS NULL OR deleted != '1')
            AND (active IS NULL OR active = '1')
            AND (
                product_name_ar LIKE '" . str_replace("'", "''", $search) . "'
                OR product_code LIKE '" . str_replace("'", "''", $search) . "'
                OR product_fast_code LIKE '" . str_replace("'", "''", $search) . "'
                OR product_scientific_name LIKE '" . str_replace("'", "''", $search) . "'
            )
            ORDER BY product_name_ar
        ";
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll();
        return $results;
    } catch (PDOException $e) {
        error_log("ESTOCK product search error: " . $e->getMessage());
        return [];
    }
}
function searchESTOCKProductsByBarcode($barcode) {
    $db = getESTOCK();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT TOP 1
                product_id,
                product_code,
                product_fast_code,
                product_name_ar,
                product_name_en,
                product_scientific_name,
                sell_price,
                buy_price,
                tax_price,
                product_unit1,
                product_unit2,
                product_unit3,
                product_sale_unit,
                company_id,
                active,
                deleted
            FROM dbo.Branches_products_edit
            WHERE (deleted IS NULL OR deleted != '1')
            AND (active IS NULL OR active = '1')
            AND (
                product_code = ? 
                OR product_fast_code = ?
                OR product_int_code = ?
            )
        ");
        $stmt->execute([$barcode, $barcode, $barcode]);
        $result = $stmt->fetch();
        if ($result) {
            $result['product_name_ar'] = fixArabic($result['product_name_ar']);
            $result['product_name_en'] = fixArabic($result['product_name_en']);
            $result['product_scientific_name'] = fixArabic($result['product_scientific_name']);
        }
        return $result;
    } catch (PDOException $e) {
        error_log("ESTOCK product barcode search error: " . $e->getMessage());
        return null;
    }
}

// =====================================================
// UNITS (الوحدات)
// =====================================================

function getESTOCKUnits() {
    $db = getESTOCK();
    if (!$db) return [];

    try {
        $stmt = $db->query("
            SELECT 
                unit_id,
                unit_code,
                unit_name_ar,
                unit_name_en
            FROM dbo.Branches_unit_edit
            WHERE (deleted IS NULL OR deleted != '1')
            ORDER BY unit_name_ar
        ");
        $results = $stmt->fetchAll();
        // Fix Arabic encoding
        foreach ($results as &$row) {
            $row['unit_name_ar'] = fixArabic($row['unit_name_ar']);
            $row['unit_name_en'] = fixArabic($row['unit_name_en']);
        }
        return $results;
    } catch (PDOException $e) {
        error_log("ESTOCK units error: " . $e->getMessage());
        return [];
    }
}

function getESTOCKUnitById($unit_id) {
    $db = getESTOCK();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("
            SELECT 
                unit_id,
                unit_code,
                unit_name_ar,
                unit_name_en
            FROM dbo.Branches_unit_edit
            WHERE unit_id = ?
        ");
        $stmt->execute([$unit_id]);
        $result = $stmt->fetch();
        if ($result) {
            $result['unit_name_ar'] = fixArabic($result['unit_name_ar']);
            $result['unit_name_en'] = fixArabic($result['unit_name_en']);
        }
        return $result;
    } catch (PDOException $e) {
        error_log("ESTOCK unit by ID error: " . $e->getMessage());
        return null;
    }
}

// =====================================================
// SYNC FUNCTIONS (مزامنة مع MySQL)
// =====================================================

function syncESTOCKEmployeesToMySQL() {
    $db = getDB();
    $estock_employees = getESTOCKEmployees();

    $synced = 0;
    foreach ($estock_employees as $emp) {
        // Check if employee already exists in MySQL
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$emp['username']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing user
            $stmt = $db->prepare("
                UPDATE users SET 
                    full_name = ?,
                    password = ?,
                    phone = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $emp['emp_name_ar'],
                password_hash($emp['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]),
                $emp['mobile'],
                ($emp['active'] == '1' && $emp['deleted'] != '1') ? 1 : 0,
                $existing['id']
            ]);
        } else {
            // Insert new user
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, role, phone, is_active)
                VALUES (?, ?, ?, 'pharmacist', ?, ?)
            ");
            $stmt->execute([
                $emp['username'],
                password_hash($emp['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]),
                $emp['emp_name_ar'],
                $emp['mobile'],
                ($emp['active'] == '1' && $emp['deleted'] != '1') ? 1 : 0
            ]);
            $synced++;
        }
    }

    return $synced;
}

function syncESTOCKProductsToMySQL($page = 1, $per_page = 1000) {
    $db = getDB();
    $estock_products = getESTOCKProducts($page, $per_page);

    $synced = 0;
    foreach ($estock_products as $prod) {
        // Check if product already exists in MySQL
        $stmt = $db->prepare("SELECT id FROM products WHERE product_code = ?");
        $stmt->execute([$prod['product_code']]);
        $existing = $stmt->fetch();

        // Get unit name
        $unit_name = '';
        if ($prod['product_sale_unit']) {
            $unit = getESTOCKUnitById($prod['product_sale_unit']);
            if ($unit) $unit_name = $unit['unit_name_ar'];
        }

        if ($existing) {
            // Update existing product
            $stmt = $db->prepare("
                UPDATE products SET 
                    product_name = ?,
                    category = ?,
                    notes = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $prod['product_name_ar'],
                $unit_name,
                'Scientific: ' . ($prod['product_scientific_name'] ?? ''),
                ($prod['active'] == '1' && $prod['deleted'] != '1') ? 1 : 0,
                $existing['id']
            ]);
        } else {
            // Insert new product
            $stmt = $db->prepare("
                INSERT INTO products (product_code, product_name, category, notes, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $prod['product_code'],
                $prod['product_name_ar'],
                $unit_name,
                'Scientific: ' . ($prod['product_scientific_name'] ?? ''),
                ($prod['active'] == '1' && $prod['deleted'] != '1') ? 1 : 0
            ]);
            $synced++;
        }
    }

    return $synced;
}

// =====================================================
// LOGIN WITH ESTOCK
// =====================================================

function loginWithESTOCK($username, $password) {
    $emp = getESTOCKEmployeeByUsername($username);

    if (!$emp) {
        return false; // Employee not found in ESTOCK
    }

    // Check if passwords match (plain text comparison)
    if ($emp['password'] !== $password) {
        return false; // Wrong password
    }

    // Check if employee is active
    if ($emp['active'] != '1' || $emp['deleted'] == '1') {
        return false; // Employee not active
    }

    // Sync employee to MySQL
    $db = getDB();
    $stmt = $db->prepare("SELECT id, role, is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create user in MySQL
        $stmt = $db->prepare("
            INSERT INTO users (username, password, full_name, role, phone, is_active)
            VALUES (?, ?, ?, 'pharmacist', ?, 1)
        ");
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]),
            $emp['emp_name_ar'],
            $emp['mobile']
        ]);
        $user_id = $db->lastInsertId();
        $role = 'pharmacist';
    } else {
        if (!$user['is_active']) {
            return false; // User disabled in MySQL
        }
        $user_id = $user['id'];
        $role = $user['role'];

        // Update password in MySQL (in case it changed in ESTOCK)
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]), $user_id]);
    }

    // Set session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_name'] = $emp['emp_name_ar'];
    $_SESSION['user_role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['estock_emp_id'] = $emp['emp_id'];

    // Update last login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
       ->execute([$user_id]);

    logActivity('login', 'users', $user_id, null, ['source' => 'ESTOCK']);

    return true;
}
