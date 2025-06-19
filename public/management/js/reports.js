/**
 * Reports Module JavaScript
 * Handles report generation, filtering, and export functionality
 */

$(document).ready(function() {
    // Initialize date range picker
    const dateRangePicker = $('#date_range').daterangepicker({
        startDate: moment($('#start_date').val()),
        endDate: moment($('#end_date').val()),
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        alwaysShowCalendars: true,
        autoUpdateInput: true,
        locale: {
            format: 'YYYY-MM-DD',
            cancelLabel: 'Clear'
        }
    });

    // Update hidden date fields when date range changes
    dateRangePicker.on('apply.daterangepicker', function(ev, picker) {
        $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
        $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
    });

    // Toggle filters section
    $('#toggleFilters').on('click', function() {
        $('#filtersCard').slideToggle();
        const icon = $('#toggleFilters').find('i');
        if (icon.hasClass('fa-filter')) {
            icon.removeClass('fa-filter').addClass('fa-eye-slash');
        } else {
            icon.removeClass('fa-eye-slash').addClass('fa-filter');
        }
    });

    // Toggle filters collapse
    $('#collapseFilters').on('click', function() {
        $('#filtersBody').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-up fa-chevron-down');
    });

    // Reset all filters
    $('#resetFilters').on('click', function() {
        // Reset form
        $('#reportForm')[0].reset();
        
        // Reset date range
        const defaultStart = moment().subtract(30, 'days').format('YYYY-MM-DD');
        const defaultEnd = moment().format('YYYY-MM-DD');
        
        $('#date_range').data('daterangepicker').setStartDate(defaultStart);
        $('#date_range').data('daterangepicker').setEndDate(defaultEnd);
        $('#start_date').val(defaultStart);
        $('#end_date').val(defaultEnd);
        
        // Reset select2 dropdowns
        $('.select2').val(null).trigger('change');
        
        // Clear report results
        $('#reportResults').html(`
            <div class="text-center text-muted py-5">
                <i class="fas fa-chart-bar fa-4x mb-3"></i>
                <p class="mb-0">Select report parameters and click "Generate Report" to view results</p>
            </div>
        `);
    });

    // Handle form submission for report generation
    $('#reportForm').on('submit', function(e) {
        e.preventDefault();
        generateReport('html');
    });

    // Export buttons
    $('#exportPdf, #exportPdf2').on('click', function(e) {
        e.preventDefault();
        generateReport('pdf');
    });

    $('#exportExcel, #exportExcel2').on('click', function(e) {
        e.preventDefault();
        generateReport('excel');
    });

    $('#exportCsv').on('click', function(e) {
        e.preventDefault();
        generateReport('csv');
    });

    // Print report
    $('#printReport').on('click', function(e) {
        e.preventDefault();
        generateReport('print');
    });

    /**
     * Show alert message
     */
    function showAlert(message, type = 'info') {
        const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
        
        // Add to the top of the main content
        $('.main-content').prepend(alert);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.alert('close');
        }, 5000);
    }

    /**
     * Generate report based on selected filters
     * @param {string} format - Output format (html, pdf, excel, csv, print)
     */
    function generateReport(format) {
        const formData = $('#reportForm').serializeArray();
        const reportType = $('#report_type').val();
        
        if (!reportType) {
            showAlert('Please select a report type', 'danger');
            return;
        }
        
        // Add format to form data
        formData.push({ name: 'format', value: format });
        
        // Show loading state
        const loadingHtml = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Generating report, please wait...</p>
            </div>`;
        
        $('#reportResults').html(loadingHtml);
        
        // For non-HTML formats, submit a form to handle the download
        if (format !== 'html' && format !== 'print') {
            const $form = $('<form>')
                .attr('method', 'POST')
                .attr('action', 'ajax/generate_report.php')
                .attr('target', '_blank')
                .css('display', 'none');
            
            // Add CSRF token if available
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            if (csrfToken) {
                $form.append($('<input>').attr({
                    type: 'hidden',
                    name: 'csrf_token',
                    value: csrfToken
                }));
            }
            
            // Add form fields
            formData.forEach(field => {
                $form.append($('<input>')
                    .attr('type', 'hidden')
                    .attr('name', field.name)
                    .val(field.value));
            });
            
            // Submit form
            $('body').append($form);
            $form.submit();
            $form.remove();
            
            // Reset loading state after a short delay
            setTimeout(() => {
                $('#reportResults').html(`
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your ${format.toUpperCase()} export should start shortly. If it doesn't, please check your pop-up blocker.
                    </div>
                    ${$('#reportResults').html()}`);
            }, 1000);
            
            return;
        }
        
        // For HTML/print format, use AJAX to load the report
        $.ajax({
            url: 'ajax/generate_report.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (format === 'print') {
                        handlePrintReport(response);
                    } else {
                        displayHtmlReport(response);
                    }
                } else {
                    showAlert(response.message || 'An error occurred while generating the report.', 'danger');
                    $('#reportResults').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${response.message || 'An error occurred while generating the report.'}
                        </div>`);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error generating report:', error);
                showAlert('An error occurred while generating the report. Please try again.', 'danger');
                $('#reportResults').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        An error occurred while generating the report. Please try again.
                    </div>`);
            }
        });
    }

    /**
     * Handle printing a report in a new window
     */
    function handlePrintReport(response) {
        const printWindow = window.open('', '_blank');
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>${response.report_title || 'Report'}</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    @media print {
                        @page { margin: 0.5cm; }
                        body { padding: 20px; }
                        .no-print { display: none !important; }
                        .table { font-size: 11px !important; }
                        h1, h2, h3, h4, h5, h6 { page-break-after: avoid; }
                        table, figure { page-break-inside: avoid; }
                        thead { display: table-header-group; }
                        tr { page-break-inside: avoid; }
                    }
                    .report-header { margin-bottom: 20px; }
                    .report-title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                    .report-subtitle { font-size: 14px; color: #6c757d; margin-bottom: 15px; }
                    .summary-card { margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="container-fluid">
                    <div class="report-header">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h2 class="report-title">${response.report_title || 'Report'}</h2>
                                <div class="report-subtitle">
                                    Generated on ${new Date().toLocaleString()}
                                </div>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-sm btn-primary no-print" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                                <button class="btn btn-sm btn-secondary no-print" onclick="window.close()" style="margin-left: 5px;">
                                    <i class="fas fa-times me-1"></i> Close
                                </button>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3"><strong>Date Range:</strong> ${$('#date_range').val()}</div>
                            <div class="col-md-3"><strong>Generated By:</strong> ${$('meta[name="user-fullname"]').attr('content') || 'System'}</div>
                        </div>
                    </div>
                    ${response.html || '<p>No data available for the selected criteria.</p>'}
                </div>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"><\/script>
                <script>
                    // Auto-print when the window loads
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            // Close the window after printing (with a delay to ensure printing starts)
                            setTimeout(function() {
                                window.close();
                            }, 500);
                        }, 500);
                    };
                <\/script>
            </body>
            </html>`;

        printWindow.document.write(printContent);
        printWindow.document.close();
    }

    /**
     * Display HTML report in the results area
     */
    function displayHtmlReport(response) {
        // Display HTML report in the results area
        $('#reportResults').html(response.html || '<div class="alert alert-info">No data available for the selected criteria.</div>');
        
        // Initialize DataTables if the report contains a table
        if ($.fn.DataTable && $('#reportTable').length) {
            if ($.fn.DataTable.isDataTable('#reportTable')) {
                $('#reportTable').DataTable().destroy();
            }
            
            $('#reportTable').DataTable({
                responsive: true,
                pageLength: 25,
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });
        }
    }
});
