<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = $_SESSION['role'] ?? 'cashier';
if (!in_array($user_role, ['admin', 'manager', 'cashier'])) {
    header('Location: ../welcome.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Payments - MTECH UGANDA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #23272b; color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .credit-payments-container { max-width: 950px; margin: 40px auto; background: #181a1b; border-radius: 10px; box-shadow: 0 0 20px #000a; padding: 0; overflow: hidden; }
        .cp-header { padding: 20px 30px; border-bottom: 1px solid #343a40; background: #23272b; }
        .cp-header h2 { margin: 0; font-size: 1.5rem; color: #fff; }
        .cp-body { display: flex; }
        .cp-form { flex: 0 0 320px; padding: 30px; background: #202225; border-right: 1px solid #343a40; }
        .cp-summary { flex: 1; padding: 30px; background: #23272b; min-height: 220px; }
        .cp-form label { color: #b0b3b8; font-weight: 500; margin-top: 1rem; }
        .cp-form select, .cp-form input[type=number] { width: 100%; margin-top: 0.3rem; background: #23272b; color: #fff; border: 1px solid #343a40; border-radius: 4px; padding: 8px; }
        .cp-form input[type=checkbox] { margin-right: 8px; }
        .cp-form .form-group { margin-bottom: 1.2rem; }
        .cp-form .btn { width: 100%; margin-top: 1.5rem; }
        .cp-form .btn:disabled, .cp-form .btn[disabled] { background: #343a40; color: #888; }
        .cp-summary .empty-summary { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #b0b3b8; }
        .cp-summary .empty-summary i { font-size: 2.5rem; margin-bottom: 1rem; }
        .cp-summary .empty-summary span { font-size: 1rem; }
        .cp-info { text-align: center; padding: 40px 0 30px 0; color: #00bfff; font-size: 1rem; }
        .cp-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 30px; border-top: 1px solid #343a40; background: #181a1b; }
        .cp-footer .btn { min-width: 90px; }
        .cp-footer .btn-ok { background: #007bff; color: #fff; }
        .cp-footer .btn-ok:disabled { background: #343a40; color: #888; }
        .cp-footer .btn-close { background: #dc3545; color: #fff; }
        .cp-footer .btn-close:hover { background: #b52a37; }
    </style>
</head>
<body>
    <div class="credit-payments-container">
        <div class="cp-header">
            <h2><i class="fas fa-credit-card"></i> Credit payments</h2>
        </div>
        <div class="cp-body">
            <form class="cp-form" id="creditPaymentForm" autocomplete="off">
                <div class="form-group">
                    <label for="customer">Customer</label>
                    <select id="customer" name="customer" class="form-control">
                        <option value="">Select customer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="paymentType">Payment type</label>
                    <select id="paymentType" name="paymentType" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="mobile">Mobile Money</option>
                        <option value="card">Card</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" min="0" value="0" class="form-control" />
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" id="autoDist" name="autoDist" class="form-check-input" checked />
                    <label for="autoDist" class="form-check-label">Automatic distribution</label>
                </div>
                <button type="button" id="loadDocs" class="btn btn-secondary" disabled>Load unpaid documents</button>
            </form>
            <div class="cp-summary" id="cpSummary">
                <div class="empty-summary">
                    <i class="fas fa-eye-slash"></i>
                    <span>Customer not selected.<br>Please select customer for reconciliation.</span>
                </div>
            </div>
        </div>
        <div class="cp-info">
            <i class="fas fa-info-circle"></i> Paid amount will be automatically distributed across all unpaid sales
        </div>
        <div class="cp-footer">
            <button class="btn btn-ok" id="okBtn" disabled>OK</button>
            <button class="btn btn-close" id="closeBtn">Close</button>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
    // Real AJAX logic for Credit Payments
    $(document).ready(function() {
        // Utility: show loading spinner in summary
        function showLoadingSummary(msg) {
            $('#cpSummary').html('<div class="empty-summary"><i class="fas fa-spinner fa-spin"></i><span>' + msg + '</span></div>');
        }
        // Utility: show error in summary
        function showErrorSummary(msg) {
            $('#cpSummary').html('<div class="empty-summary" style="color:#dc3545"><i class="fas fa-exclamation-triangle"></i><span>' + msg + '</span></div>');
        }
        // Utility: show success in summary
        function showSuccessSummary(msg) {
            $('#cpSummary').html('<div class="empty-summary" style="color:#28a745"><i class="fas fa-check-circle"></i><span>' + msg + '</span></div>');
        }
        // 1. Populate customer dropdown
        $('#customer').prop('disabled', true);
        showLoadingSummary('Loading customers...');
        $.get('ajax/get_customers_list.php', function(resp) {
            if (resp.success) {
                $('#customer').empty().append('<option value="">Select customer</option>');
                resp.customers.forEach(function(c) {
                    $('#customer').append('<option value="'+c.id+'">'+c.name+'</option>');
                });
                $('#customer').prop('disabled', false);
                showErrorSummary('Customer not selected.<br>Please select customer for reconciliation.');
            } else {
                showErrorSummary(resp.message || 'Failed to load customers');
            }
        }, 'json').fail(function() {
            showErrorSummary('Failed to load customers');
        });
        // 2. On customer selection, enable Load button
        $('#customer').on('change', function() {
            let cid = $(this).val();
            $('#loadDocs').prop('disabled', !cid);
            $('#okBtn').prop('disabled', true);
            if (!cid) {
                showErrorSummary('Customer not selected.<br>Please select customer for reconciliation.');
                return;
            }
            showLoadingSummary('Ready to load unpaid documents.');
        });
        // 3. Load unpaid documents when Load button clicked
        $('#loadDocs').on('click', function() {
            let cid = $('#customer').val();
            if (!cid) return;
            showLoadingSummary('Loading unpaid documents...');
            $.get('ajax/get_unpaid_documents.php', {customer_id: cid}, function(resp) {
                if (resp.success) {
                    if (resp.documents.length === 0) {
                        showSuccessSummary('No unpaid documents.');
                        $('#okBtn').prop('disabled', true);
                        return;
                    }
                    // Show docs as table
                    let t = '<table class="table table-sm table-dark"><thead><tr><th>#</th><th>Doc No</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead><tbody>';
                    resp.documents.forEach(function(doc, i) {
                        t += `<tr><td>${i+1}</td><td>${doc.document_no}</td><td>${doc.total_amount}</td><td>${doc.paid_amount}</td><td>${doc.balance}</td></tr>`;
                    });
                    t += '</tbody></table>';
                    $('#cpSummary').html(t);
                    // Enable OK if amount > 0
                    if (parseFloat($('#amount').val()) > 0) $('#okBtn').prop('disabled', false);
                } else {
                    showErrorSummary(resp.message || 'Failed to load documents');
                    $('#okBtn').prop('disabled', true);
                }
            }, 'json').fail(function() {
                showErrorSummary('Failed to load documents');
                $('#okBtn').prop('disabled', true);
            });
        });
        // 4. Enable OK if amount > 0 and customer selected
        $('#amount').on('input', function() {
            let amt = parseFloat($(this).val());
            let cid = $('#customer').val();
            if (amt > 0 && cid) $('#okBtn').prop('disabled', false);
            else $('#okBtn').prop('disabled', true);
        });
        // 5. Process payment on OK
        $('#okBtn').on('click', function() {
            let cid = $('#customer').val();
            let amt = parseFloat($('#amount').val());
            let ptype = $('#paymentType').val();
            let autoDist = $('#autoDist').is(':checked');
            if (!cid || amt <= 0 || !ptype) return;
            showLoadingSummary('Processing payment...');
            $('#okBtn').prop('disabled', true);
            $.ajax({
                url: 'ajax/process_credit_payment.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    customer_id: cid,
                    amount: amt,
                    payment_type: ptype,
                    auto_dist: autoDist
                }),
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        showSuccessSummary('Payment successful!');
                        $('#okBtn').prop('disabled', true);
                        setTimeout(function() { window.location.href = 'dashboard.php'; }, 1800);
                    } else {
                        showErrorSummary(resp.message || 'Payment failed');
                        $('#okBtn').prop('disabled', false);
                    }
                },
                error: function() {
                    showErrorSummary('Payment failed (server error)');
                    $('#okBtn').prop('disabled', false);
                }
            });
        });
        // 6. Close button
        $('#closeBtn').on('click', function() {
            window.location.href = 'dashboard.php';
        });
    });
    </script>
</body>
</html>
