<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

try {
    // Get all price lists
    $price_lists = $pdo->query("SELECT * FROM price_lists WHERE active = 1 ORDER BY name")->fetchAll();
    
    // Get product categories (using categories table instead of product_groups)
    $groups = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Lists Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
            --dark-bg: #181818;
            --darker-bg: #141414;
            --medium-bg: #232323;
            --accent-blue: #0078d4;
            --accent-hover: #0086ef;
            --text-light: #fff;
            --text-muted: #888;
            --border-color: #333;
            --success-color: #28a745;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--darker-bg);
            color: var(--text-light);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-bg);
            color: var(--text-light);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        }

        .sidebar-header {
            padding: 1rem;
            background-color: var(--medium-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 500;
        }

        .logo-container {
            padding: 1.5rem;
            text-align: center;
        }

        .logo-container img {
            width: 120px;
            height: auto;
        }

        .sidebar-menu {
            padding: 0.5rem 0;
        }

        .sidebar-menu-header {
            padding: 1rem 1.5rem 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar-menu-item {
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            color: var(--text-light);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .sidebar-menu-item:hover {
            background-color: var(--medium-bg);
            border-left-color: var(--accent-blue);
            color: var(--text-light);
            text-decoration: none;
        }

        .sidebar-menu-item.active {
            background-color: var(--medium-bg);
            border-left-color: var(--accent-blue);
            color: var(--text-light);
            font-weight: 500;
        }

        .sidebar-menu-item i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
            font-size: 1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background: var(--dark-bg);
        }

        /* Toolbar */
        .toolbar {
            background: var(--medium-bg);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .toolbar button {
            width: 36px;
            height: 36px;
            color: var(--text-light);
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .toolbar button:hover {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            transform: translateY(-1px);
        }

        .toolbar button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .toolbar button i {
            font-size: 1rem;
        }

        /* Panels */
        .split {
            display: flex;
            gap: 1.5rem;
        }

        .left-panel {
            width: 280px;
            flex-shrink: 0;
        }

        .right-panel {
            flex: 1;
        }

        .panel {
            background: var(--medium-bg);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .panel h6 {
            color: var(--text-light);
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0 0 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Lists and Tables */
        .list-group-item {
            background: transparent;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .list-group-item:hover {
            background: var(--dark-bg);
        }

        .list-group-item.active {
            background: var(--accent-blue);
        }

        .folder {
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        .folder:hover {
            background: var(--dark-bg);
        }

        .folder i {
            color: var(--accent-blue);
            margin-right: 0.75rem;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--dark-bg);
            border-bottom: 2px solid var(--border-color);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.875rem;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-hover tbody tr:hover {
            background: var(--dark-bg);
        }

        /* Form Controls */
        .form-control {
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 0.5rem 0.75rem;
        }

        .form-control:focus {
            background: var(--dark-bg);
            border-color: var(--accent-blue);
            color: var(--text-light);
            box-shadow: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* Loading Overlay */
        #loadingOverlay {
            background: rgba(0, 0, 0, 0.8) !important;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="logo-container">
            <img src="../assets/images/logo.png" alt="MTECH UGANDA" />
        </div>
        
        <div class="sidebar-menu">
            <div class="sidebar-menu-header">MAIN NAVIGATION</div>
            <a href="dashboard.php" class="sidebar-menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-file-invoice"></i>
                <span>Documents</span>
            </a>
            <a href="products.php" class="sidebar-menu-item">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="price-lists.php" class="sidebar-menu-item active">
                <i class="fas fa-tags"></i>
                <span>Price Lists</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-percent"></i>
                <span>Promotions</span>
            </a>
            
            <div class="sidebar-menu-header">INVENTORY</div>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-warehouse"></i>
                <span>Stock</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reporting</span>
            </a>
            
            <div class="sidebar-menu-header">CUSTOMERS</div>
            <a href="customers-suppliers.php" class="sidebar-menu-item">
                <i class="fas fa-users"></i>
                <span>Customers & Suppliers</span>
            </a>
            
            <div class="sidebar-menu-header">SETTINGS</div>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-print"></i>
                <span>Print Stations</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Payment Types</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-globe"></i>
                <span>Countries</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-percentage"></i>
                <span>Tax Rates</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <i class="fas fa-building"></i>
                <span>My Company</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="toolbar">
            <button title="Refresh"><i class="fas fa-sync"></i></button>
            <button title="New price list"><i class="fas fa-plus"></i></button>
            <button title="Edit" disabled><i class="fas fa-edit"></i></button>
            <button title="Delete" disabled><i class="fas fa-trash"></i></button>
            <button title="Print"><i class="fas fa-print"></i></button>
            <button title="Save as PDF"><i class="fas fa-file-pdf"></i></button>
            <button title="Excel"><i class="fas fa-file-excel"></i></button>
            <button title="Copy price list"><i class="fas fa-copy"></i></button>
            <button title="Edit prices"><i class="fas fa-percent"></i></button>
            <button title="Product prices"><i class="fas fa-tags"></i></button>
            <button title="Help"><i class="fas fa-question-circle"></i></button>
        </div>

        <div class="split">
            <div class="left-panel">
                <div class="panel">
                    <h6>Price Lists</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($price_lists as $list): ?>
                        <div class="list-group-item" data-id="<?= htmlspecialchars($list['id']) ?>">
                            <i class="fas fa-tags"></i>
                            <span><?= htmlspecialchars($list['name']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="panel">
                    <h6>Product Groups</h6>
                    <?php foreach ($groups as $group): ?>
                    <div class="folder" data-id="<?= htmlspecialchars($group['id']) ?>">
                        <i class="fas fa-folder"></i>
                        <span><?= htmlspecialchars($group['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="right-panel">
                <div class="panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="input-group" style="width: 300px;">
                            <span class="input-group-text bg-dark border-dark text-light">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control product-search" placeholder="Search products...">
                        </div>
                        <span class="text-muted products-count">Products count: 0</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Product</th>
                                    <th>Default Price</th>
                                    <th>List Price</th>
                                    <th>Markup %</th>
                                    <th>Tax Rate</th>
                                    <th>Tax Inclusive</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT p.*, t.rate as tax_rate FROM products p 
                                                   LEFT JOIN tax_rates t ON p.tax_rate_id = t.id 
                                                   WHERE p.active = 1 LIMIT 5");
                                while ($row = $stmt->fetch()) {
                                    echo "<tr data-id=\"" . htmlspecialchars($row['id']) . "\">";
                                    echo "<td>" . htmlspecialchars($row['code']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                    echo "<td>" . number_format($row['price'], 2) . "</td>";
                                    echo "<td><input type='number' class='form-control form-control-sm price' value='" . number_format($row['price'], 2) . "' step='0.01'></td>";
                                    echo "<td>0%</td>";
                                    echo "<td>" . ($row['tax_rate'] ?? 0) . "%</td>";
                                    echo "<td>" . ($row['tax_included'] ? '<i class="fas fa-check text-success"></i>' : '') . "</td>";
                                    echo "</tr>";
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='7' class='text-danger'>Error loading products: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="position-fixed w-100 h-100 d-none" style="top: 0; left: 0; z-index: 2000;">
        <div class="position-absolute top-50 start-50 translate-middle text-light text-center">
            <div class="spinner-border mb-2" role="status"></div>
            <div>Loading...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>        $(document).ready(function() {
            let currentPriceList = null;
            
            // Show/hide loading overlay
            function showLoading() {
                $('#loadingOverlay').removeClass('d-none');
            }
            
            function hideLoading() {
                $('#loadingOverlay').addClass('d-none');
            }

            // Load price lists
            function loadPriceLists() {
                showLoading();
                $.post('ajax/price_list_actions.php', {action: 'get_price_lists'}, function(response) {
                    if (response.success) {
                        const $list = $('.price-lists-panel .list-group');
                        $list.empty();
                        response.data.forEach(list => {
                            $list.append(`
                                <li class="list-group-item bg-transparent text-white" data-id="${list.id}">
                                    <i class="fa fa-tags me-2"></i>${list.name}
                                </li>
                            `);
                        });
                    }
                });
            }            // Load products for a price list
            function loadProducts(priceListId, search = '') {
                $.post('ajax/price_list_actions.php', {
                    action: 'get_products',
                    price_list_id: priceListId,
                    search: search
                }, function(response) {
                    if (response.success) {
                        const $tbody = $('.table tbody');
                        $tbody.empty();
                        response.data.forEach(product => {
                            $tbody.append(`
                                <tr data-id="${product.id}">
                                    <td>${product.code}</td>
                                    <td>${product.name}</td>
                                    <td>${parseFloat(product.default_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                    <td><input type="number" class="form-control form-control-sm price" value="${parseFloat(product.effective_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" step="0.01"></td>
                                    <td>${product.markup}%</td>
                                    <td>${product.tax_rate || 0}%</td>
                                    <td>${product.tax_included ? '<i class="fa fa-check text-success"></i>' : ''}</td>
                                </tr>
                            `);
                        });
                        
                        $('.products-count').text(`Products count: ${response.data.length}`);
                    }
                });
            }            // Handle price list selection
            $(document).on('click', '.price-lists-panel .list-group-item', function() {
                $('.price-lists-panel .list-group-item').removeClass('active');
                $(this).addClass('active');
                currentPriceList = $(this).data('id');
                loadProducts(currentPriceList);
                
                // Enable/disable toolbar buttons
                $('.toolbar button[title="Edit"],.toolbar button[title="Delete"]').prop('disabled', false);
            });

            // Handle search
            $('.product-search').on('input', function() {
                if (currentPriceList) {
                    loadProducts(currentPriceList, $(this).val());
                }
            });

            // Handle delete price list
            $('.toolbar button[title="Delete"]').click(function() {
                if (!currentPriceList) return;
                
                if (confirm('Are you sure you want to delete this price list?')) {
                    $.post('ajax/price_list_actions.php', {
                        action: 'delete_price_list',
                        id: currentPriceList
                    }, function(response) {
                        if (response.success) {
                            loadPriceLists();
                            $('.table tbody').empty();
                            currentPriceList = null;
                            $('.toolbar button[title="Edit"],.toolbar button[title="Delete"]').prop('disabled', true);
                        }
                    });
                }
            });

            // Handle new price list
            $('.toolbar button[title="New price list"]').click(function() {
                const name = prompt('Enter price list name:');
                if (name) {
                    $.post('ajax/price_list_actions.php', {
                        action: 'save_price_list',
                        name: name
                    }, function(response) {
                        if (response.success) {
                            loadPriceLists();
                        }
                    });
                }
            });            // Auto-save changes after brief delay
            let saveTimeout;
            $(document).on('change input', '.price', function() {
                clearTimeout(saveTimeout);
                const $row = $(this).closest('tr');
                const $input = $(this);
                const value = parseFloat($input.val());
                
                if (isNaN(value) || value < 0) {
                    $input.addClass('is-invalid');
                    return;
                }
                
                $input.removeClass('is-invalid');
                
                saveTimeout = setTimeout(() => {
                    if (currentPriceList) {
                        const loadingOverlay = $('<div class="position-absolute w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center" style="top: 0; left: 0; z-index: 1000;"><div class="spinner-border text-light" role="status"></div></div>');
                        $row.css('position', 'relative').append(loadingOverlay);
                        
                        $.post('ajax/price_list_actions.php', {
                            action: 'update_prices',
                            price_list_id: currentPriceList,
                            items: [{
                                product_id: $row.data('id'),
                                price: value
                            }]
                        })
                        .done(function(response) {
                            if (response.success) {
                                // Update the markup percentage
                                const defaultPrice = parseFloat($row.find('td:eq(2)').text().replace(/,/g, ''));
                                const markup = ((value / defaultPrice) - 1) * 100;
                                $row.find('td:eq(4)').text(markup.toFixed(2) + '%');
                            }
                        })
                        .fail(function() {
                            $input.addClass('is-invalid');
                        })
                        .always(function() {
                            loadingOverlay.remove();
                        });
                    }
                }, 500);
            });            // Disable edit/delete buttons initially
            $('.toolbar button[title="Edit"],.toolbar button[title="Delete"]').prop('disabled', true);

            // Initial load
            loadPriceLists();

            // Handle refresh button
            $('.toolbar button[title="Refresh"]').click(function() {
                loadPriceLists();
                if (currentPriceList) {
                    loadProducts(currentPriceList);
                }
            });

            // Add error handler for failed AJAX requests
            $(document).ajaxError(function(event, jqXHR, settings, error) {
                hideLoading();
                alert('An error occurred: ' + (jqXHR.responseJSON?.error || error));
            });

            // Add success handler for completed AJAX requests
            $(document).ajaxSuccess(function() {
                hideLoading();
            });
        });
    </script>
</body>
</html>
