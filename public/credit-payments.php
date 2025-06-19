<?php
// Start the session
session_start();

// Define that we're including this file
if (!defined('INCLUDED')) {
    define('INCLUDED', true);
}

// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';

// Set page title
$page_title = 'Credit Payments';

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = get_db_connection();
        
        // Start transaction
        $db->beginTransaction();
        
        // Get form data
        $customer_id = $_POST['customer_id'] ?? 0;
        $invoice_id = !empty($_POST['invoice_id']) ? $_POST['invoice_id'] : null;
        $amount = (float)$_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $reference_number = $_POST['reference_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $payment_date = date('Y-m-d H:i:s');
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Please enter a valid amount.');
        }
        
        // Insert payment record
        $stmt = $db->prepare("INSERT INTO credit_payments 
                             (customer_id, invoice_id, amount, payment_date, payment_method, reference_number, notes, created_by) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $invoice_id, $amount, $payment_date, $payment_method, $reference_number, $notes, $user_id]);
        
        // Update customer's credit balance
        $stmt = $db->prepare("UPDATE customers SET credit_balance = credit_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $customer_id]);
        
        // If this payment is for a specific invoice, update the invoice balance
        if ($invoice_id) {
            $stmt = $db->prepare("UPDATE sales SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$amount, $invoice_id]);
        }
        
        // Commit transaction
        $db->commit();
        
        $success = 'Payment recorded successfully!';
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get credit payments data
$credit_payments = [];
$customers = [];
$invoices = [];

try {
    $db = get_db_connection();
    
    // Get credit payments with customer names
    $stmt = $db->query("SELECT cp.*, c.name as customer_name, c.phone as customer_phone 
                        FROM credit_payments cp 
                        JOIN customers c ON cp.customer_id = c.id 
                        ORDER BY cp.payment_date DESC 
                        LIMIT 100");
    $credit_payments = $stmt->fetchAll();
    
    // Get customers with credit balance
    $stmt = $db->query("SELECT id, name, credit_balance FROM customers WHERE credit_balance > 0 ORDER BY name");
    $customers = $stmt->fetchAll();
    
    // Get open invoices for the selected customer
    if (!empty($_GET['customer_id'])) {
        $stmt = $db->prepare("SELECT id, invoice_number, total, balance 
                             FROM sales 
                             WHERE customer_id = ? AND balance > 0 
                             ORDER BY date DESC");
        $stmt->execute([$_GET['customer_id']]);
        $invoices = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!-- Include header -->
<?php include 'includes/header.php'; ?>
    <!-- Page content starts here -->
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fas fa-credit-card mr-2"></i> Credit Payments</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle mr-2"></i>Record New Payment
                    </div>
                    <div class="card-body">
                        <form id="paymentForm" method="post" action="credit-payments.php">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="customer_id">Customer *</label>
                                        <select class="form-control" id="customer_id" name="customer_id" required>
                                            <option value="">Select Customer</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>" 
                                                    data-balance="<?php echo $customer['credit_balance']; ?>">
                                                    <?php echo htmlspecialchars($customer['name']); ?> 
                                                    (Balance: <?php echo number_format($customer['credit_balance'], 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount *</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">UGX</span>
                                            </div>
                                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" id="payFullAmount">Pay Full</button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Maximum: <span id="maxAmount">0.00</span> UGX</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_id">Apply to Invoice (Optional)</label>
                                        <select class="form-control" id="invoice_id" name="invoice_id">
                                            <option value="">Select Invoice</option>
                                            <?php foreach ($invoices as $invoice): ?>
                                                <option value="<?php echo $invoice['id']; ?>">
                                                    #<?php echo htmlspecialchars($invoice['invoice_number']); ?> 
                                                    (Balance: <?php echo number_format($invoice['balance'], 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method *</label>
                                        <select class="form-control" id="payment_method" name="payment_method" required>
                                            <option value="cash">Cash</option>
                                            <option value="mobile_money">Mobile Money</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="check">Check</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference_number">Reference Number</label>
                                        <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="e.g., Receipt #, Transaction ID">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional notes about this payment">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Record Payment
                                </button>
                            </div>
                        </form>
                        
                        <!-- Customer Info Panel (initially hidden) -->
                        <div id="customerInfo" class="customer-info mt-3" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 id="customerName"></h5>
                                    <p id="customerPhone"></p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <h5>Credit Balance: <span id="customerBalance" class="text-warning">0.00</span> UGX</h5>
                                    <p id="customerInvoices" class="mb-0"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-history mr-2"></i>Payment History
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="paymentsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Invoice</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($credit_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y h:i A', strtotime($payment['payment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                            <td>
                                                <?php if ($payment['invoice_id']): ?>
                                                    <a href="#" class="text-primary">#<?php echo $payment['invoice_id']; ?></a>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-success font-weight-bold"><?php echo number_format($payment['amount'], 2); ?> UGX</td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                            <td><?php echo $payment['reference_number'] ?: '<span class="text-muted">-</span>'; ?></td>
                                            <td><?php echo 'User #' . $payment['created_by']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        .customer-info {
            background-color: var(--light-bg);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .customer-info h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .customer-info p {
            margin: 5px 0 0 0;
            color: var(--text-muted);
        }
        
        /* Custom styles for the payment form */
        .payment-form .form-group {
            margin-bottom: 1rem;
        }
        
        .payment-form label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .payment-form .input-group-text {
            background-color: var(--dark-bg);
            border-color: var(--light-bg);
            color: var(--text-muted);
        }
        
        .payment-form .btn-pay-full {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .payment-history .table-responsive {
            border-radius: 5px;
            overflow: hidden;
        }
        
        .payment-history .table {
            margin-bottom: 0;
        }
        
        .payment-history .table th {
            background-color: var(--light-bg);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
    </style>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#paymentsTable').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 25,
                "responsive": true,
                "autoWidth": false
            });
            
            // When customer is selected
            $('#customer_id').on('change', function() {
                const customerId = $(this).val();
                const selectedOption = $(this).find('option:selected');
                const balance = parseFloat(selectedOption.data('balance')) || 0;
                
                if (customerId) {
                    // Show customer info
                    $('#customerInfo').show();
                    $('#customerName').text(selectedOption.text().split(' (Balance:')[0]);
                    $('#customerBalance').text(balance.toFixed(2));
                    
                    // Set max amount
                    $('#maxAmount').text(balance.toFixed(2));
                    $('#amount').attr('max', balance);
                    
                    // Load customer invoices
                    loadCustomerInvoices(customerId);
                } else {
                    $('#customerInfo').hide();
                    $('#maxAmount').text('0.00');
                    $('#amount').removeAttr('max');
                }
            });
            
            // Pay full amount button
            $('#payFullAmount').on('click', function() {
                const maxAmount = parseFloat($('#maxAmount').text()) || 0;
                if (maxAmount > 0) {
                    $('#amount').val(maxAmount.toFixed(2));
                }
            });
            
            // Form validation
            $('#paymentForm').on('submit', function(e) {
                const amount = parseFloat($('#amount').val()) || 0;
                const maxAmount = parseFloat($('#maxAmount').text()) || 0;
                
                if (amount <= 0) {
                    alert('Please enter a valid amount.');
                    e.preventDefault();
                    return false;
                }
                
                if (amount > maxAmount) {
                    alert('Payment amount cannot exceed the customer\'s credit balance.');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Load customer invoices
            function loadCustomerInvoices(customerId) {
                if (!customerId) return;
                
                $.get('get_customer_invoices.php', { customer_id: customerId }, function(data) {
                    const invoices = JSON.parse(data);
                    const $select = $('#invoice_id');
                    
                    // Clear existing options except the first one
                    $select.find('option').not(':first').remove();
                    
                    // Add new options
                    invoices.forEach(function(invoice) {
                        $select.append(new Option(
                            '#' + invoice.invoice_number + ' (Balance: ' + parseFloat(invoice.balance).toFixed(2) + ')',
                            invoice.id
                        ));
                    });
                    
                    // Update invoice count
                    const invoiceCount = invoices.length;
                    const totalBalance = invoices.reduce((sum, inv) => sum + parseFloat(inv.balance), 0);
                    
                    $('#customerInvoices').html(
                        invoiceCount + ' open invoice' + (invoiceCount !== 1 ? 's' : '') + 
                        ' • Total: ' + totalBalance.toFixed(2) + ' UGX'
                    );
                }).fail(function() {
                    console.error('Failed to load customer invoices');
                });
            }
        });
    </script>
<!-- Include footer -->
<?php include 'includes/footer.php'; ?>
