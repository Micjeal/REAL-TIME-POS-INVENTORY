<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Set page title
$page_title = 'Customer Management';

// Include database configuration
require_once __DIR__ . '/../../config.php';

// Get user role for access control
$user_role = $_SESSION['role'] ?? 'cashier';

// Check if user has permission to access this page
if (!in_array(strtolower($user_role), ['admin', 'manager'])) {
    header('Location: ../../unauthorized.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | YourApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f7fa;
            font-family: 'Inter', sans-serif;
        }
        .sidebar {
            background-color: #1a2b4a;
            color: white;
            min-height: 100vh;
            padding: 1rem;
            position: relative;
            padding-bottom: 80px; /* Space for fixed button */
        }
        .sidebar .nav-link {
            color: #b0c4de;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #2c3e50;
            color: white;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .table {
            background-color: white;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #3858c5;
            border-color: #3858c5;
            transform: translateY(-1px);
        }
        .modal-content {
            border-radius: 1rem;
            border: none;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .alert {
            border-radius: 0.5rem;
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1050;
            min-width: 300px;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            width: calc(100% - 2rem);
        }
        .btn-back {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            width: 100%;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar col-md-3 col-lg-2 d-md-block">
            <h3 class="text-white mb-4">MTECH UGANDA</h3>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="customers.php"><i class="fas fa-users me-2"></i>Customers</a>
                </li>
            </ul>
            <!-- Back Button -->
            <div class="sidebar-footer">
                <a href="../../welcome.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Back to Sales
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h3 fw-bold">Customer Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                        <i class="fas fa-plus me-2"></i>Add New Customer
                    </button>
                </div>
            </div>

            <!-- Customer List Table -->
            <div class="card">
                <div class="card-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs mb-3" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button" role="tab" aria-controls="customers" aria-selected="true">Customers</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab" aria-controls="audit" aria-selected="false">Audit Logs</button>
                        </li>
                    </ul>
                    
                    <!-- Tab panes -->
                    <div class="tab-content">
                        <!-- Customers Tab -->
                        <div class="tab-pane fade show active" id="customers" role="tabpanel" aria-labelledby="customers-tab">
                            <div class="table-responsive">
                                <table id="customersTable" class="table table-striped table-hover" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Tax Number</th>
                                            <th>Discount %</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data will be loaded by DataTables -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Audit Logs Tab -->
                        <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Select a customer from the table to view their audit history.
                            </div>
                            <div id="customerAuditSection" style="display: none;">
                                <h5 class="mb-3">Audit Logs for: <span id="auditCustomerName"></span></h5>
                                <div class="table-responsive">
                                    <table id="auditLogsTable" class="table table-striped table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Changes</th>
                                                <th>IP Address</th>
                                                <th>User Agent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data will be loaded by DataTables -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="customerModalLabel">Add New Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="customerForm">
                    <div class="modal-body">
                        <input type="hidden" id="customerId" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="customer">Customer</option>
                                    <option value="supplier">Supplier</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tax_number" class="form-label">Tax Number</label>
                                <input type="text" class="form-control" id="tax_number" name="tax_number">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address">
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city">
                            </div>
                            <div class="col-md-4">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code">
                            </div>
                            <div class="col-md-4">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person">
                            </div>
                            <div class="col-md-6">
                                <label for="discount_percent" class="form-label">Discount Percentage</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discount_percent" name="discount_percent" min="0" max="100" step="0.01" value="0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" value="1" checked>
                                    <label class="form-check-label" for="active">Active</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this customer? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script>
        let customersTable;
        let auditLogsTable;
        let selectedCustomerId = null;
        let selectedCustomerName = '';
        
        $(document).ready(function() {
            // Initialize Customers DataTable
            customersTable = $('#customersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'ajax/setup_customers_table.php',
                    type: 'GET',
                    error: function(xhr, error, thrown) {
                        console.error('Error loading customers:', error);
                        alert('Failed to load customers. Please try again.');
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'name', name: 'name' },
                    { 
                        data: 'type',
                        name: 'type',
                        render: function(data) {
                            return data ? data.charAt(0).toUpperCase() + data.slice(1) : '';
                        }
                    },
                    { data: 'phone', name: 'phone' },
                    { data: 'email', name: 'email' },
                    { data: 'tax_number', name: 'tax_number' },
                    { 
                        data: 'discount_percent', 
                        name: 'discount_percent',
                        render: function(data) {
                            return data ? data + '%' : '0%';
                        }
                    },
                    { 
                        data: 'active',
                        name: 'active',
                        render: function(data) {
                            return data ? 
                                '<span class="badge bg-success">Active</span>' : 
                                '<span class="badge bg-secondary">Inactive</span>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `
                                <button class='btn btn-sm btn-primary edit-customer me-1' data-id='${row.id}'>
                                    <i class='fas fa-edit'></i>
                                </button>
                                <button class='btn btn-sm btn-danger delete-customer' data-id='${row.id}'>
                                    <i class='fas fa-trash'></i>
                                </button>
                            `;
                        }
                    }
                ],
                order: [[1, 'asc']], // Default sort by name
                responsive: true,
                language: {
                    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
                    emptyTable: 'No customers found',
                    zeroRecords: 'No matching customers found'
                },
                dom: '<"d-flex justify-content-between align-items-center mb-3"<l><f>>rt<"d-flex justify-content-between align-items-center mt-3"<i><p>>'
            });

            // Reset form and show modal for adding new customer
            $('[data-bs-target="#customerModal"]').click(function() {
                $('#customerForm')[0].reset();
                $('#customerId').val('');
                $('#customerModalLabel').text('Add New Customer');
                $('#customerModal').modal('show');
            });

            // Handle form submission
            $('#customerForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const submitBtn = $(this).find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Saving...');

                $.ajax({
                    url: 'ajax/save_customer.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message);
                            $('#customerModal').modal('hide');
                            customersTable.ajax.reload();
                        } else {
                            showAlert('danger', response.message || 'An error occurred');
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Failed to save customer. Please try again.');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });

            // Handle row click to select customer for audit logs
            $('#customersTable tbody').on('click', 'tr', function() {
                const data = customersTable.row(this).data();
                if (data) {
                    selectedCustomerId = data[0]; // Assuming ID is in the first column
                    selectedCustomerName = data[1]; // Assuming Name is in the second column
                    
                    // Update the audit customer name
                    $('#auditCustomerName').text(selectedCustomerName);
                    
                    // Show the audit section
                    $('#customerAuditSection').show();
                    
                    // If audit table is already initialized, reload it
                    if ($.fn.DataTable.isDataTable('#auditLogsTable')) {
                        auditLogsTable.ajax.reload();
                    } else {
                        // Initialize audit logs DataTable
                        initializeAuditLogsTable();
                    }
                    
                    // Switch to audit tab
                    const auditTab = new bootstrap.Tab(document.getElementById('audit-tab'));
                    auditTab.show();
                }
            });
            
            // Initialize audit logs DataTable
            function initializeAuditLogsTable() {
                auditLogsTable = $('#auditLogsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'ajax/get_customer_audit_logs.php',
                        type: 'GET',
                        data: function(d) {
                            d.customer_id = selectedCustomerId;
                        },
                        error: function(xhr, error, thrown) {
                            console.error('Error loading audit logs:', error);
                            alert('Failed to load audit logs. Please try again.');
                        }
                    },
                    order: [[0, 'desc']], // Default sort by date descending
                    columns: [
                        { data: 0, width: '150px' },
                        { data: 1, width: '120px' },
                        { data: 2, width: '80px' },
                        { data: 3, orderable: false },
                        { data: 4, width: '120px' },
                        { 
                            data: 5,
                            render: function(data, type, row) {
                                if (type === 'display') {
                                    return data ? '<span title="' + data + '">' + data.substring(0, 20) + '...</span>' : '';
                                }
                                return data;
                            },
                            width: '150px'
                        }
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel me-1"></i> Export to Excel',
                            className: 'btn btn-success btn-sm',
                            exportOptions: {
                                columns: [0, 1, 2, 4, 5]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print me-1"></i> Print',
                            className: 'btn btn-primary btn-sm',
                            exportOptions: {
                                columns: [0, 1, 2, 4, 5]
                            }
                        }
                    ],
                    language: {
                        processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
                        emptyTable: 'No audit records found for this customer.',
                        zeroRecords: 'No matching audit records found.'
                    },
                    drawCallback: function() {
                        // Add tooltips for user agent
                        $('[title]').tooltip({
                            placement: 'top',
                            trigger: 'hover'
                        });
                    }
                });
            }
            
            // Handle edit button click
            $('#customersTable').on('click', '.edit-customer', function(e) {
                e.stopPropagation(); // Prevent row click event from firing
                const customerId = $(this).data('id');
                const originalBtnHtml = $(this).html();
                $(this).html('<span class="spinner-border spinner-border-sm" role="status"></span>');

                $.ajax({
                    url: 'ajax/get_customer.php',
                    type: 'GET',
                    data: { id: customerId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            const customer = response.data;
                            $('#customerId').val(customer.id);
                            $('#name').val(customer.name || '');
                            $('#type').val(customer.type || 'customer');
                            $('#tax_number').val(customer.tax_number || '');
                            $('#address').val(customer.address || '');
                            $('#city').val(customer.city || '');
                            $('#postal_code').val(customer.postal_code || '');
                            $('#country').val(customer.country || '');
                            $('#phone').val(customer.phone || '');
                            $('#email').val(customer.email || '');
                            $('#contact_person').val(customer.contact_person || '');
                            $('#notes').val(customer.notes || '');
                            $('#discount_percent').val(customer.discount_percent || '0');
                            $('#active').prop('checked', customer.active == 1);
                            $('#customerModalLabel').text('Edit Customer');
                            $('#customerModal').modal('show');
                        } else {
                            showAlert('danger', response.message || 'Failed to load customer data');
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Failed to load customer data');
                    },
                    complete: function() {
                        $('.edit-customer[data-id="' + customerId + '"]').html(originalBtnHtml);
                    }
                });
            });

            // Handle delete customer
            let customerToDelete = null;
            $('#customersTable').on('click', '.delete-customer', function() {
                customerToDelete = $(this).data('id');
                $('#deleteConfirmModal').modal('show');
            });

            $('#confirmDelete').on('click', function() {
                if (!customerToDelete) return;
                const deleteBtn = $(this);
                const originalBtnText = deleteBtn.html();
                deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Deleting...');

                $.ajax({
                    url: 'ajax/delete_customer.php',
                    type: 'POST',
                    data: { id: customerToDelete },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message);
                            customersTable.ajax.reload();
                        } else {
                            showAlert('danger', response.message || 'Failed to delete customer');
                        }
                    },
                    error: function() {
                        showAlert('danger', 'Failed to delete customer. Please try again.');
                    },
                    complete: function() {
                        deleteBtn.prop('disabled', false).html(originalBtnText);
                        $('#deleteConfirmModal').modal('hide');
                    }
                });
            });

            // Show alerts
            function showAlert(type, message) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('.alert').remove();
                $('body').append(alertHtml);
                setTimeout(() => $('.alert').alert('close'), 5000);
            }
        });
    </script>
</body>
</html>