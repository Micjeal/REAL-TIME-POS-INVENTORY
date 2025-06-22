<?php
// Start the session
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: /MTECH%20UGANDA/public/login.php");
    exit();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include configuration
require_once '../../includes/config.php';

// Set page title
$page_title = "Documents Management";

// Initialize variables
$documents = [];
$error_message = '';
$success_message = '';

// Sanitize and validate GET parameters
// Get and sanitize input parameters
$start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date'], ENT_QUOTES, 'UTF-8') : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date'], ENT_QUOTES, 'UTF-8') : date('Y-m-t');
$document_type = isset($_GET['document_type']) ? htmlspecialchars($_GET['document_type'], ENT_QUOTES, 'UTF-8') : '';
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status'], ENT_QUOTES, 'UTF-8') : '';

// Validate date formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-t');
}

try {
    // Build the base query
    $query = "SELECT d.*, c.name AS customer_name, u.username, cr.name AS cash_register_name 
              FROM documents d
              LEFT JOIN customers c ON d.customer_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              LEFT JOIN cash_registers cr ON d.cash_register_id = cr.id
              WHERE d.document_date BETWEEN ? AND ?";
    
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    $types = 'ss';
    
    // Add document type filter if specified
    if ($document_type !== '') {
        $query .= " AND d.document_type = ?";
        $params[] = $document_type;
        $types .= 's';
    }
    
    // Add status filter if specified
    if ($status !== '') {
        $query .= " AND d.paid_status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Add sorting
    $query .= " ORDER BY d.document_date DESC, d.id DESC";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get document types for filter dropdown (cache in session)
    if (!isset($_SESSION['document_types'])) {
        $doc_types_result = $conn->query("SELECT DISTINCT document_type FROM documents ORDER BY document_type");
        $_SESSION['document_types'] = [];
        while ($row = $doc_types_result->fetch_assoc()) {
            $_SESSION['document_types'][] = $row['document_type'];
        }
    }
    $document_types = $_SESSION['document_types'];
    
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching documents: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        /* Embedded styles */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #f8f9fa;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .sidebar-menu {
            padding: 10px 0;
        }

        .menu-section-title {
            padding: 10px 20px;
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
        }

        .menu-item:hover, .menu-item.active {
            background: #e9ecef;
            color: #007bff;
        }

        .menu-item i {
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background: #fff;
        }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-text {
            text-align: right;
            margin-right: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #007bff;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .spinner-overlay.hidden {
            display: none;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .btn-group .btn {
            transition: all 0.2s ease;
        }

        .btn-group .btn:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
                transform: translateX(-250px);
                position: fixed;
                height: 100%;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                background: none;
                border: none;
                font-size: 1.5rem;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>MTECH UGANDA</h2>
            <button class="sidebar-toggle d-md-none" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-section-title">Main Navigation</div>
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="documents.php" class="menu-item active">
                    <i class="fas fa-file-invoice"></i>
                    <span>Documents</span>
                </a>
                <a href="products.php" class="menu-item">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="price-lists.php" class="menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Price Lists</span>
                </a>
                <a href="customers-suppliers.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Customers & Suppliers</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="company.php" class="menu-item">
                    <i class="fas fa-building"></i>
                    <span>Company</span>
                </a>
                <a href="security.php" class="menu-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="top-nav">
                <h1 class="page-title">
                    <i class="fas fa-file-alt"></i> Documents Management
                </h1>
                <div class="user-info">
                    <div class="user-text">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()" aria-label="Print documents">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="exportToExcel()" aria-label="Export to Excel">
                        <i class="fas fa-file-excel me-1"></i> Export
                    </button>
                </div>
                <a href="document_edit.php?action=create" class="btn btn-primary" aria-label="Create new document">
                    <i class="fas fa-plus me-1"></i> New Document
                </a>
            </div>

            <!-- Messages -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-filter text-primary me-2"></i>
                            Filter Documents
                        </h5>
                        <button class="btn btn-sm btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse show" id="filterCollapse">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label small text-muted mb-1">From Date</label>
                                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label small text-muted mb-1">To Date</label>
                                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                            </div>
                            <div curled="true" id="col-md-2">
                                <label for="form-label" to="document_type" id="small text-muted mb-1">Document Type</label>
                                <select class="form-select" id="document_type" title="document_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($document_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $document_type === $type ? 'selected' : ''; ?>
                                            <?php echo htmlspecialchars(ucfirst($type)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label small text-muted mb-1">Status</label>
                                <select class="form-select form-select-sm" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="unpaid" <?php echo $status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                    <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>Partially Paid</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn btn-primary btn-sm me-2" type="submit" aria-label="Apply filters">
                                    <button><i class="fas fa-search me-1"></i> Search</button>
                                </div>
                                <a href="documents.php" class="btn btn-outline-secondary btn-sm" aria-label="Reset filters">
                                    <i class="fas fa-sync"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt text-primary me-2"></i>
                            Documents List
                        </h5>
                        <div class="text-muted small">
                            Showing <?php echo count($documents); ?> document(s)
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0" id="documentsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Document</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Customer</th>
                                    <th>Cash Register</th>
                                    <th>User</th>
                                    <th class="text-end">Total</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <p class="mb-0">No documents found</p>
                                                        <a href="document_edit.php?action=create" class="btn btn-sm btn-primary mt-3" aria-label="Create new document">
                                                            <i class="fas fa-plus me-1"></i> Create New Document
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($documents as $doc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($doc['id']); ?></td>
                                                    <td>
                                                        <a href="document_view.php?id=<?php echo htmlspecialchars($doc['id']); ?>" class="text-primary">
                                                            <?php echo htmlspecialchars($doc['document_number']); ?>
                                                        </a>
                                                        <?php if (!empty($doc['external_document'])): ?>
                                                            <br><small class="text-muted">Ref: <?php echo htmlspecialchars($doc['external_document']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($doc['document_date'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = [
                                                            'invoice' => 'bg-primary',
                                                            'receipt' => 'bg-success',
                                                            'quote' => 'bg-info',
                                                            'purchase_order' => 'bg-warning',
                                                            'credit_note' => 'bg-danger',
                                                            'delivery_note' => 'bg-secondary'
                                                        ][$doc['document_type']] ?? 'bg-secondary';
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo strtoupper(str_replace('_', ' ', $doc['document_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo !empty($doc['customer_name']) ? htmlspecialchars($doc['customer_name']) : 'Walk-in Customer'; ?></td>
                                                    <td><?php echo !empty($doc['cash_register_name']) ? htmlspecialchars($doc['cash_register_name']) : 'N/A'; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['username']); ?></td>
                                                    <td class="text-end">
                                                        <?php echo number_format($doc['total'], 2); ?>
                                                        <?php if ($doc['discount'] > 0): ?>
                                                            <br><small class="text-muted">Disc: <?php echo number_format($doc['discount'], 2); ?></small>
                                                        <?php endif ?>
                                                    ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = [
                                                            'paid' => 'bg-success',
                                                            'unpaid' => 'bg-danger',
                                                            'partial' => 'bg-warning',
                                                            'cancelled' => 'bg-secondary',
                                                            'refunded' => 'bg-info'
                                                        ][$doc['paid_status']] ?? 'bg-secondary';
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($doc['paid_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="document_view.php?id=<?php echo htmlspecialchars($doc['id']); ?>" class="btn btn-outline-primary" title="View document" aria-label="View document">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="document_edit.php?id=<?php echo htmlspecialchars($doc['id']); ?>" class="btn btn-outline-secondary" title="Edit document" aria-label="Edit document">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="document_print.php?id=<?php echo htmlspecialchars($doc['id']); ?>" target="_blank" class="btn btn-outline-info" title="Print document" aria-label="Print document">
                                                                <i class="fas fa-print"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger delete-document" data-id="<?php echo htmlspecialchars($doc['id']); ?>" data-number="<?php echo htmlspecialchars($doc['document_number']); ?>" title="Delete document" aria-label="Delete document">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <?php if (!empty($documents)): ?>
                                        <tfoot>
                                            <tr>
                                                <th colspan="7" class="text-end">Total:</th>
                                                <th class="text-end"><?php echo number_format(array_sum(array_column($documents, 'total')), 2); ?></th>
                                                <th colspan="2"></th>
                                            </tr>
                                        </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        <?php if (!empty($documents) && count($documents) > 10): ?>
                            <div class="card-footer text-muted">
                                Showing <?php echo count($documents); ?> documents
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel">Confirm Delete Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this document? This action cannot be undone.</p>
                            <p class="fw-bold">Document #<span id="documentNumber"></span></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cancel deletion">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDelete" aria-label="Confirm deletion">Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div class="spinner-overlay hidden" id="loadingSpinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <script>
                // Embedded JavaScript
                function exportToExcel() {
                    const table = document.getElementById('documentsTable');
                    const ws = XLSX.utils.table_to_sheet(table);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Documents');
                    XLSX.writeFile(wb, `documents_export_${new Date().toISOString().slice(0, 10)}.xlsx`);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    // Sidebar toggle for mobile
                    const sidebarToggle = document.querySelector('.sidebar-toggle');
                    const sidebar = document.getElementById('sidebar');
                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', () => {
                            sidebar.classList.toggle('show');
                        });
                    }

                    // Initialize delete functionality
                    const deleteButtons = document.querySelectorAll('.delete-document');
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                    const confirmDelete = document.getElementById('confirmDelete');
                    const loadingSpinner = document.getElementById('loadingSpinner');
                    let documentToDelete = null;

                    deleteButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            documentToDelete = {
                                id: button.getAttribute('data-id'),
                                number: button.getAttribute('data-number')
                            };
                            document.getElementById('documentNumber').textContent = documentToDelete.number;
                            deleteModal.show();
                        });
                    });

                    confirmDelete.addEventListener('click', () => {
                        if (documentToDelete) {
                            loadingSpinner.classList.remove('hidden');
                            fetch('/MTECH%20UGANDA/ajax/delete_document.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `id=${encodeURIComponent(documentToDelete.id)}&csrf_token=${encodeURIComponent('<?php echo $_SESSION['csrf_token']; ?>')}`
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        window.location.reload();
                                    } else {
                                        alert(`Error: ${data.message || 'Failed to delete document'}`);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('An error occurred while deleting the document');
                                })
                                .finally(() => {
                                    loadingSpinner.classList.add('hidden');
                                    deleteModal.hide();
                                });
                        }
                    });

                    // Initialize DataTable if documents exist
                    if (document.querySelectorAll('#documentsTable tbody tr').length > 0) {
                        $('#documentsTable').DataTable({
                            pageLength: 25,
                            order: [[2, 'desc']],
                            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                            language: { search: '_INPUT_', searchPlaceholder: 'Search documents...' },
                            columnDefs: [{ orderable: false, targets: [9] }]
                        });
                    }
                });
            </script>
        </body>
    </html>